<?php

namespace App\Http\Controllers;

use App\Actions\CategorizeDocumentAction;
use App\Actions\ConvertDocumentToPreviewAction;
use App\Actions\ImportDocumentAction;
use App\DataTransferObjects\CategorizeDocumentData;
use App\DataTransferObjects\ConvertDocumentToPreviewData;
use App\DataTransferObjects\ImportDocumentData;
use App\Enums\DocumentSource;
use App\Http\Requests\CategorizeDocumentRequest;
use App\Http\Requests\ImportDocumentRequest;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const PREVIEW_DIRECTORY = 'previews';

    /**
     * type[]-filter value => matching `mime_type`, colocated with the
     * type-filter parsing/application below (Design Notes, spec-1-7).
     * `created` is deliberately absent — it filters on `source`, not a
     * mime type, and is handled separately in applyFilters().
     */
    private const TYPE_MIME_MAP = [
        'pdf' => 'application/pdf',
        'word' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * User-uploaded content is streamed inline into an iframe — nosniff
     * closes off content-sniffing if a stored `mime_type` ever mismatches
     * the actual file content.
     */
    private const PREVIEW_RESPONSE_HEADERS = ['X-Content-Type-Options' => 'nosniff'];

    /**
     * Library entry point: renders one card per document (type badge,
     * title, category, date), sorted most recent first, and hosts the
     * Import modal. Sole point of entry for the documents query — search
     * (`?search=`, Story 1.6) and category/type filters (`?category_id[]=`,
     * `?type[]=`, Story 1.7) both converge here (AD-8). Pagination remains
     * out of scope.
     *
     * An empty/absent `search` leaves the previous, unfiltered behavior
     * untouched: `latest()` over `Document::query()`. A non-empty term
     * instead runs through Scout (driver `database`, indexed on
     * `extracted_text` only), constrained to the same eager-load via its
     * query callback — both branches converge on the same `get([...])`.
     * Filters apply identically to both branches through applyFilters(),
     * never a second/divergent query path (Boundaries & Constraints,
     * spec-1-7).
     */
    public function index(Request $request): Response
    {
        $rawSearch = $request->query('search', '');
        $search = trim(is_scalar($rawSearch) ? (string) $rawSearch : '');

        $categoryIds = $this->categoryIdsFromQuery($request);
        $types = $this->typesFromQuery($request);

        $columns = ['id', 'title', 'source', 'mime_type', 'category_id', 'created_at'];

        $documents = $search === ''
            ? $this->applyFilters(Document::query(), $categoryIds, $types)
                ->with('category:id,name')->latest()->get($columns)
            // Scout's `database` driver interpolates the term unescaped into a
            // `LIKE '%...%'` clause (Laravel\Scout\Engines\DatabaseEngine) — `%`/`_`
            // are LIKE wildcards, so they're escaped here to keep the match literal.
            : Document::search(addcslashes($search, '%_'))
                ->query(fn ($query) => $this->applyFilters($query, $categoryIds, $types)
                    ->select($columns)->with('category:id,name')->latest())
                ->get();

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'search' => $search,
            'categoryFilters' => $categoryIds,
            'typeFilters' => $types,
        ]);
    }

    /**
     * Sole filter-application point, called identically by both `index()`
     * branches (plain `Document::query()` and the Scout query callback) —
     * no divergence between the search and non-search paths (AD-8,
     * Design Notes spec-1-7). ET between the category and type groups, OU
     * within each group: `whereIn('category_id', ...)` narrows by category
     * when any is selected, and a single `where()` closure ORs together
     * the recognized-mime types plus `source = created` when selected.
     */
    private function applyFilters(Builder $query, array $categoryIds, array $types): Builder
    {
        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        if ($types !== []) {
            $mimeTypes = array_values(array_intersect_key(self::TYPE_MIME_MAP, array_flip($types)));
            $includesCreated = in_array('created', $types, true);

            $query->where(function (Builder $typeQuery) use ($mimeTypes, $includesCreated) {
                if ($mimeTypes !== []) {
                    $typeQuery->orWhereIn('mime_type', $mimeTypes);
                }

                if ($includesCreated) {
                    $typeQuery->orWhere('source', DocumentSource::Created);
                }
            });
        }

        return $query;
    }

    /**
     * `category_id[]` as a deduplicated list of positive ints — anything
     * non-numeric or malformed is dropped rather than surfacing an error,
     * mirroring how `search` tolerates a non-scalar/missing value.
     *
     * `FILTER_VALIDATE_INT` (rather than `is_numeric()`) rejects a value
     * like `"2.5"` outright instead of silently truncating it to `2` via
     * `(int) "2.5"` — that truncation could otherwise match a real
     * category the client never actually selected.
     */
    private function categoryIdsFromQuery(Request $request): array
    {
        $raw = $request->query('category_id', []);

        if (! is_array($raw)) {
            return [];
        }

        $ids = array_map(
            static fn ($value) => is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT) : false,
            $raw,
        );

        return array_values(array_unique(array_filter($ids, static fn ($value) => $value !== false)));
    }

    /**
     * `type[]` filtered down to the four recognized values (Boundaries &
     * Constraints, spec-1-7: no fifth type) — anything else is silently
     * dropped, same tolerance as categoryIdsFromQuery().
     */
    private function typesFromQuery(Request $request): array
    {
        $raw = $request->query('type', []);

        if (! is_array($raw)) {
            return [];
        }

        $allowedTypes = [...array_keys(self::TYPE_MIME_MAP), 'created'];
        $stringValues = array_filter($raw, 'is_string');

        return array_values(array_intersect(array_unique($stringValues), $allowedTypes));
    }

    /**
     * ImportDocumentAction never touches `category_id` (Boundaries &
     * Constraints, spec-1-5) — an optional category chosen in the Import
     * modal is assigned afterwards, in a second step, through
     * CategorizeDocumentAction, the sole write point for it (AD-16).
     *
     * Both calls run inside one transaction: without it, a
     * CategorizeDocumentAction failure after a successful import would
     * leave an orphaned Document row committed with no way to roll it
     * back.
     */
    public function store(ImportDocumentRequest $request, ImportDocumentAction $import, CategorizeDocumentAction $categorize): RedirectResponse
    {
        $document = DB::transaction(function () use ($request, $import, $categorize) {
            $document = $import(new ImportDocumentData(
                file: $request->file('file'),
            ));

            $categoryId = $request->validated('category_id');

            if ($categoryId !== null) {
                $categorize(new CategorizeDocumentData(
                    document: $document,
                    categoryId: $categoryId,
                ));
            }

            return $document;
        });

        return to_route('documents.show', $document);
    }

    public function show(Document $document): Response
    {
        $document->loadMissing('category:id,name');

        return Inertia::render('Documents/Show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'source' => $document->source,
                'mime_type' => $document->mime_type,
                'category_id' => $document->category_id,
                'category' => $document->category,
                'created_at' => $document->created_at,
            ],
            'sourceMissing' => $this->sourceMissing($document),
        ]);
    }

    /**
     * Sole route through which a document's category is reassigned or
     * cleared back to "Uncategorized" after creation — always delegates
     * to CategorizeDocumentAction (AD-16), never writes `category_id`
     * itself.
     */
    public function updateCategory(CategorizeDocumentRequest $request, Document $document, CategorizeDocumentAction $action): RedirectResponse
    {
        $action(new CategorizeDocumentData(
            document: $document,
            categoryId: $request->validated('category_id'),
        ));

        return back();
    }

    /**
     * Streams the document inline for the browser's built-in viewer: native
     * PDFs stream directly (no conversion, no processing indicator); Word/
     * Excel are converted to PDF on demand — and cached — via
     * ConvertDocumentToPreviewAction. Mime routing mirrors
     * ExtractDocumentTextJob::formatFromMimeType().
     *
     * Never an uncaught exception: a missing/unreadable source or a failed
     * conversion both resolve to an explicit HTTP error the client already
     * distinguishes from success (Boundaries & Constraints, spec-1-3).
     */
    public function preview(Document $document, ConvertDocumentToPreviewAction $convert): StreamedResponse
    {
        abort_if($this->sourceMissing($document), 404);

        return match ($this->previewFormat($document->mime_type)) {
            'pdf' => Storage::disk('local')->response($document->file_path, "{$document->id}.pdf", self::PREVIEW_RESPONSE_HEADERS),
            'docx', 'xlsx' => $this->previewOfficeDocument($document, $convert),
            default => abort(422, 'Aperçu indisponible pour ce type de document.'),
        };
    }

    /**
     * Downloads the original source file, independent of preview state —
     * only a missing/unreadable source disables it (Boundaries & Constraints,
     * spec-1-3).
     */
    public function download(Document $document): StreamedResponse
    {
        abort_if($this->sourceMissing($document), 404);

        return Storage::disk('local')->download($document->file_path, basename($document->file_path));
    }

    /**
     * The check-then-convert-then-cache sequence below is not atomic on its
     * own, so two concurrent preview requests for the same document could
     * otherwise both see the cache as empty and both convert. A per-document
     * lock serializes them: the first request converts and populates the
     * cache, the second blocks and then simply finds the cache already
     * populated. `ConvertDocumentToPreviewAction` separately makes the
     * *intermediate* LibreOffice output collision-proof across different
     * documents (a document-id-scoped temp directory) — this lock is what
     * keeps two requests for the *same* document from racing each other.
     */
    private function previewOfficeDocument(Document $document, ConvertDocumentToPreviewAction $convert): StreamedResponse
    {
        $disk = Storage::disk('local');
        $previewPath = self::PREVIEW_DIRECTORY."/{$document->id}.pdf";

        Cache::lock("preview-conversion-{$document->id}", 130)->block(125, function () use ($disk, $document, $convert, $previewPath) {
            if ($disk->exists($previewPath)) {
                return;
            }

            $converted = $convert(new ConvertDocumentToPreviewData(
                documentId: $document->id,
                sourcePath: $document->file_path,
            ));

            if (! $converted) {
                abort(422, 'Aperçu indisponible pour ce fichier.');
            }
        });

        return $disk->response($previewPath, "{$document->id}.pdf", self::PREVIEW_RESPONSE_HEADERS);
    }

    /**
     * Explicit existence/readability check backing `sourceMissing` — drives
     * both the Show page's message/Download-disabling and the preview/
     * download routes' own guard, never a silent failure.
     */
    private function sourceMissing(Document $document): bool
    {
        return blank($document->file_path) || ! Storage::disk('local')->exists($document->file_path);
    }

    /**
     * Mirrors ExtractDocumentTextJob::formatFromMimeType() — the same mime
     * routing used for text extraction drives which preview strategy
     * applies.
     */
    private function previewFormat(?string $mimeType): ?string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => null,
        };
    }
}
