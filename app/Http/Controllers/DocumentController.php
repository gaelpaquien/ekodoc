<?php

namespace App\Http\Controllers;

use App\Actions\ConvertDocumentToPreviewAction;
use App\Actions\ImportDocumentAction;
use App\DataTransferObjects\ConvertDocumentToPreviewData;
use App\DataTransferObjects\ImportDocumentData;
use App\Http\Requests\ImportDocumentRequest;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const PREVIEW_DIRECTORY = 'previews';

    /**
     * User-uploaded content is streamed inline into an iframe — nosniff
     * closes off content-sniffing if a stored `mime_type` ever mismatches
     * the actual file content.
     */
    private const PREVIEW_RESPONSE_HEADERS = ['X-Content-Type-Options' => 'nosniff'];

    /**
     * Library entry point: renders one card per document (type badge,
     * title, category placeholder, date), sorted most recent first, and
     * hosts the Import modal. Search, filters, pagination and category
     * assignment remain out of scope — see Stories 1.5/1.6/1.7.
     */
    public function index(): Response
    {
        return Inertia::render('Documents/Index', [
            'documents' => Document::query()
                ->latest()
                ->get(['id', 'title', 'source', 'mime_type', 'created_at']),
        ]);
    }

    public function store(ImportDocumentRequest $request, ImportDocumentAction $action): RedirectResponse
    {
        $document = $action(new ImportDocumentData(
            file: $request->file('file'),
        ));

        return to_route('documents.show', $document);
    }

    public function show(Document $document): Response
    {
        return Inertia::render('Documents/Show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'source' => $document->source,
                'mime_type' => $document->mime_type,
                'created_at' => $document->created_at,
            ],
            'sourceMissing' => $this->sourceMissing($document),
        ]);
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
