<?php

namespace App\Http\Controllers;

use App\Actions\CategorizeDocumentAction;
use App\Actions\ConvertDocumentToPreviewAction;
use App\Actions\CreateDocumentAction;
use App\Actions\DeleteDocumentAction;
use App\Actions\ExportDocumentToPdfAction;
use App\Actions\ExportDocumentToWordAction;
use App\Actions\ImportDocumentAction;
use App\Actions\UpdateDocumentAction;
use App\Actions\UploadEditorImageAction;
use App\DataTransferObjects\CategorizeDocumentData;
use App\DataTransferObjects\ConvertDocumentToPreviewData;
use App\DataTransferObjects\CreateDocumentData;
use App\DataTransferObjects\DeleteDocumentData;
use App\DataTransferObjects\ExportDocumentToPdfData;
use App\DataTransferObjects\ExportDocumentToWordData;
use App\DataTransferObjects\ImportDocumentData;
use App\DataTransferObjects\UpdateDocumentData;
use App\DataTransferObjects\UploadEditorImageData;
use App\Enums\DocumentSource;
use App\Http\Requests\CategorizeDocumentRequest;
use App\Http\Requests\CreateDocumentRequest;
use App\Http\Requests\ImportDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Requests\UploadEditorImageRequest;
use App\Models\Document;
use App\Support\DocumentMimeTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const PREVIEW_DIRECTORY = 'previews';

    /**
     * User-uploaded content is streamed inline — into a preview iframe, or
     * an `<img>` (spec-2-2) — nosniff closes off content-sniffing if a
     * stored file's actual content ever mismatches what its extension/
     * validated mime type implies.
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
            $mimeTypes = array_values(array_intersect_key(DocumentMimeTypes::TYPE_TO_MIME, array_flip($types)));
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

        $allowedTypes = [...array_keys(DocumentMimeTypes::TYPE_TO_MIME), 'created'];
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

    /**
     * Renders the empty WYSIWYG editor for drafting a brand-new document
     * (FR8) — registered at `/documents/create`, ahead of the
     * `/documents/{document}` show route, so `create` is never captured by
     * that route's model binding.
     */
    public function create(): Response
    {
        return Inertia::render('Documents/Editor');
    }

    /**
     * Sole entry point for saving a document authored in the editor —
     * mirrors store()'s transaction shape exactly: CreateDocumentAction
     * never touches `category_id` itself (Boundaries & Constraints,
     * spec-2-1), an optional category chosen alongside the content is
     * assigned afterwards, in the same transaction, through
     * CategorizeDocumentAction, the sole write point for it (AD-16).
     */
    public function storeCreated(CreateDocumentRequest $request, CreateDocumentAction $create, CategorizeDocumentAction $categorize): RedirectResponse
    {
        $document = DB::transaction(function () use ($request, $create, $categorize) {
            $document = $create(new CreateDocumentData(
                title: $request->validated('title'),
                contentHtml: $request->validated('content_html'),
                draftToken: $request->validated('draft_token'),
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

    /**
     * Sole entry point for an image inserted into the editor before its
     * document exists (FR9, spec-2-2) — always delegates to
     * UploadEditorImageAction, which stores it under a temporary,
     * draft-token-keyed area (AD-14 without a `document_id` yet
     * available). Communicates back to the editor exclusively through a
     * standard Inertia redirect plus the shared `flash.uploadedImage` prop
     * (AD-13) — never `response()->json()` — so the client reads it via
     * `preserveState`, keeping the in-progress draft untouched.
     */
    public function storeEditorImage(UploadEditorImageRequest $request, UploadEditorImageAction $upload): RedirectResponse
    {
        $uploadedImage = $upload(new UploadEditorImageData(
            draftToken: $request->validated('draft_token'),
            image: $request->file('image'),
            alt: $request->validated('alt'),
        ));

        return back()->with('uploadedImage', $uploadedImage);
    }

    /**
     * Streams a temporarily stored draft image back to the editor — the
     * only route an image inserted into a not-yet-saved document is ever
     * served from, before CreateDocumentAction moves it into its final
     * `documents/{id}/images/` home at save time. `{token}`/`{filename}`
     * are constrained at the route level (routes/web.php) to the exact
     * UUID/uuid.ext shapes this story ever produces, so a missing file is
     * the only failure mode left to handle here.
     */
    public function serveDraftImage(string $token, string $filename): StreamedResponse
    {
        $path = "documents/tmp/{$token}/images/{$filename}";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $filename, self::PREVIEW_RESPONSE_HEADERS);
    }

    /**
     * Streams an image embedded in a saved document's `content_html` — the
     * sole route through which a document's inline images are ever served
     * (never base64, AD-14). Mirrors serveDraftImage()'s shape, just
     * rooted at the document's own final directory instead of a draft's
     * temporary one.
     */
    public function serveDocumentImage(Document $document, string $filename): StreamedResponse
    {
        $path = "documents/{$document->id}/images/{$filename}";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $filename, self::PREVIEW_RESPONSE_HEADERS);
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
                'content_html' => $document->content_html,
                'category_id' => $document->category_id,
                'category' => $document->category,
                'created_at' => $document->created_at,
            ],
            'sourceMissing' => $this->sourceMissing($document),
        ]);
    }

    /**
     * Reopens the WYSIWYG editor to correct a previously created document
     * (spec-2-3) — refused for an `imported` document (Boundaries &
     * Constraints, spec-2-3), which never had `content_html` of its own to
     * edit. Mirrors create()'s bare Inertia::render(), just with the
     * existing document's fields pre-loaded as a prop so Editor.vue can
     * pre-fill the form and TipTap before allowing any input.
     */
    public function edit(Document $document): Response
    {
        abort_unless($document->source === DocumentSource::Created, 403);

        return Inertia::render('Documents/Editor', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'content_html' => $document->content_html,
                'category_id' => $document->category_id,
            ],
        ]);
    }

    /**
     * Sole entry point for saving changes made to a document in the editor
     * — UpdateDocumentRequest::authorize() already closes off an
     * `imported` document before this ever runs. Unlike storeCreated(),
     * the transaction and the conditional CategorizeDocumentAction call
     * both live inside UpdateDocumentAction itself (Code Map, spec-2-3),
     * since re-categorization here is conditional on a comparison against
     * the document's *current* `category_id` that only the Action has
     * loaded.
     */
    public function update(UpdateDocumentRequest $request, Document $document, UpdateDocumentAction $update): RedirectResponse
    {
        $document = $update(new UpdateDocumentData(
            document: $document,
            title: $request->validated('title'),
            contentHtml: $request->validated('content_html'),
            categoryId: $request->validated('category_id'),
            draftToken: $request->validated('draft_token'),
        ));

        return to_route('documents.show', $document);
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
     * Sole route through which a document is permanently deleted (AD-15) —
     * always delegates to DeleteDocumentAction, never removes files/index
     * entries/the row itself directly. Confirmation happens client-side
     * before this request is ever sent (UX-DR21); no undo, no SoftDeletes.
     */
    public function destroy(Document $document, DeleteDocumentAction $action): RedirectResponse
    {
        $action(new DeleteDocumentData(
            document: $document,
        ));

        return to_route('documents.index');
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
     * Sole entry point for FR11: exports a `source=created` document to PDF
     * via ExportDocumentToPdfAction (spec-2-4, AD-11) — refused with a
     * `403` for an `imported` document, same guard shape as edit()
     * (Boundaries & Constraints, spec-2-4: it already has a native PDF or
     * goes through the Office preview above instead).
     *
     * Never a degraded file nor an uncaught exception: a Browsershot
     * failure resolves to an explicit `422`, mirroring
     * previewOfficeDocument(). Unlike that cached Office preview, the PDF
     * here is regenerated on every call — no cache directory, no lock.
     */
    public function exportPdf(Document $document, ExportDocumentToPdfAction $export): HttpResponse
    {
        abort_unless($document->source === DocumentSource::Created, 403);

        $pdf = $export(new ExportDocumentToPdfData(document: $document));

        if ($pdf === null) {
            abort(422, "Export PDF impossible pour l'instant, merci de réessayer.");
        }

        $filename = Str::slug($document->title);
        $filename = $filename !== '' ? $filename : "document-{$document->id}";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
            ...self::PREVIEW_RESPONSE_HEADERS,
        ]);
    }

    /**
     * Sole entry point for FR12: exports a `source=created` document to
     * `.docx` via ExportDocumentToWordAction (spec-2-5, AD-12) — same `403`
     * guard for an `imported` document as exportPdf() (Boundaries &
     * Constraints, spec-2-5: it already keeps its own original file).
     *
     * Never a degraded file nor an uncaught exception: a PhpWord conversion
     * failure resolves to an explicit `422`, mirroring exportPdf(). The
     * `.docx` here is regenerated on every call too — no cache directory,
     * no lock.
     */
    public function exportWord(Document $document, ExportDocumentToWordAction $export): HttpResponse
    {
        abort_unless($document->source === DocumentSource::Created, 403);

        $docx = $export(new ExportDocumentToWordData(document: $document));

        if ($docx === null) {
            abort(422, "Export Word impossible pour l'instant, merci de réessayer.");
        }

        $filename = Str::slug($document->title);
        $filename = $filename !== '' ? $filename : "document-{$document->id}";

        return response($docx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => "attachment; filename=\"{$filename}.docx\"",
            ...self::PREVIEW_RESPONSE_HEADERS,
        ]);
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
     * Delegates to DocumentMimeTypes::formatFromMime() — the mime-type
     * mapping itself no longer lives in this controller (moved to
     * `App\Support\DocumentMimeTypes`, Epic 1/2 retrospectives action item
     * 3); the same mapping drives both this preview routing and text
     * extraction (ExtractDocumentTextJob). Method kept (rather than
     * inlining the call at its single caller) so that caller never changes.
     */
    private function previewFormat(?string $mimeType): ?string
    {
        return DocumentMimeTypes::formatFromMime($mimeType);
    }
}
