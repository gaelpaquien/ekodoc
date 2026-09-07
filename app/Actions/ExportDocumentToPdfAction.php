<?php

namespace App\Actions;

use App\DataTransferObjects\ExportDocumentToPdfData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * On-demand, never-cached PDF export of a `source=created` document (FR11,
 * spec-2-4, AD-11) — renders resources/views/documents/export-pdf.blade.php
 * (the same `.tiptap-content` CSS rules as the editor) and converts it via
 * spatie/browsershot, a real headless Chromium, never dompdf/wkhtmltopdf.
 *
 * Never throws: a misconfigured/missing Chrome executable, a timeout, or
 * any other Browsershot failure is reported back as null so the controller
 * can surface an explicit 422 instead of an uncaught exception or a
 * silently degraded file (Boundaries & Constraints, spec-2-4) — the whole
 * body runs inside a single try/catch, mirroring
 * ConvertDocumentToPreviewAction.
 *
 * Every `<img src="/documents/{id}/images/{file}">` in `content_html` is
 * resolved to a `data:` URI read straight off local disk before the Blade
 * view is rendered (resolveImageSources()) — Browsershot's Chromium runs
 * outside any HTTP request context, so a relative/absolute app URL would
 * never resolve (Boundaries & Constraints, spec-2-4). This is strictly a
 * transient, in-memory conversion: it never touches the `content_html`
 * persisted in the database, so it does not reopen AD-14's prohibition on
 * base64 in stored content (Design Notes, spec-2-4).
 */
class ExportDocumentToPdfAction
{
    public function __invoke(ExportDocumentToPdfData $data): ?string
    {
        $document = $data->document;

        try {
            $contentHtml = $this->resolveImageSources($document->content_html ?? '', $document->id);

            $html = view('documents.export-pdf', [
                'title' => $document->title,
                'contentHtml' => $contentHtml,
            ])->render();

            $browsershot = Browsershot::html($html)
                ->showBackground()
                ->timeout(60);

            $chromePath = config('services.browsershot.chrome_path');

            if (filled($chromePath)) {
                $browsershot->setChromePath($chromePath);
            }

            $pdf = $browsershot->pdf();

            // Browsershot::pdf() is only documented to throw on failure,
            // but an empty string returned instead (rather than an
            // exception) must be treated the same as a hard failure —
            // never a 200 with a degraded/empty file (Boundaries &
            // Constraints, spec-2-4).
            return $pdf !== '' ? $pdf : null;
        } catch (Throwable $exception) {
            Log::warning('Document PDF export failed.', [
                'document_id' => $document->id,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Replaces every `<img src="/documents/{$documentId}/images/{file}">`
     * with a base64 `data:` URI read straight from the `local` disk — the
     * only `src` shape a saved created-document's `content_html` ever
     * carries (SanitizesDocumentContent::relocateDraftImages()). A
     * reference whose file can no longer be found on disk is left
     * untouched rather than failing the whole export — Chromium simply
     * renders it as a broken image, same as a dead link would, instead of
     * a missing image blocking an otherwise-valid export.
     *
     * Public (rather than private) specifically so ExportDocumentToPdfTest
     * can assert directly on the `data:` URI/base64 payload it produces —
     * a black-box PDF-byte-count check can't actually tell a correctly
     * embedded image apart from a broken `src` left untouched (both
     * comfortably clear any size threshold small enough not to also flag a
     * plain no-image export).
     */
    public function resolveImageSources(string $contentHtml, int $documentId): string
    {
        $disk = Storage::disk('local');
        $prefix = "/documents/{$documentId}/images/";

        $resolved = preg_replace_callback(
            '#src="'.preg_quote($prefix, '#').'([^"]+)"#',
            function (array $matches) use ($disk, $documentId) {
                $filename = $matches[1];
                $path = "documents/{$documentId}/images/{$filename}";

                if (! $disk->exists($path)) {
                    return $matches[0];
                }

                $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
                $encoded = base64_encode($disk->get($path));

                return 'src="data:'.$mimeType.';base64,'.$encoded.'"';
            },
            $contentHtml
        );

        return $resolved ?? $contentHtml;
    }
}
