<?php

namespace App\Actions;

use App\DataTransferObjects\ExportDocumentToWordData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use Throwable;

/**
 * On-demand, never-cached Word export of a `source=created` document (FR12,
 * spec-2-5, AD-12) — converts `content_html` to `.docx` via phpoffice/phpword
 * (`Html::addHtml()` + `IOFactory::createWriter(..., 'Word2007')`), never an
 * alternative engine. Mirrors ExportDocumentToPdfAction's shape exactly: the
 * whole body runs inside a single try/catch, and any failure — a PhpWord
 * exception during `addHtml()`/`save()`, or anything else — is reported back
 * as null so the controller can surface an explicit 422 instead of an
 * uncaught exception or a silently degraded file (Boundaries & Constraints,
 * spec-2-5).
 *
 * Unlike the PDF export (Browsershot's Chromium, which runs outside any HTTP
 * request context and therefore needs a `data:` URI), PhpWord's `Html::
 * addHtml()` loads a local `<img src="...">` directly from a disk path — so
 * every `<img src="/documents/{id}/images/{file}">` in `content_html` is
 * resolved to an absolute disk path before the PhpWord call
 * (resolveImageSources()), not a `data:` URI.
 *
 * `Html::addHtml()` parses its input with `DOMDocument::loadXML()` — strict
 * XML, not HTML5 — so a void element serialized the HTML5 way (`<img
 * src="...">`, `<br>`, `<hr>`, exactly what SanitizesDocumentContent's own
 * `DOMDocument::saveHTML()` produces when it persists `content_html`) is a
 * malformed-XML fatal ("Opening and ending tag mismatch"), not merely a
 * cosmetic issue. `selfCloseVoidElements()` rewrites every such tag to its
 * self-closed XHTML form (`<img ... />`) right before the PhpWord call —
 * `content_html` itself is never rewritten in the database, this is
 * strictly an in-memory step ahead of conversion, same as
 * resolveImageSources().
 */
class ExportDocumentToWordAction
{
    public function __invoke(ExportDocumentToWordData $data): ?string
    {
        $document = $data->document;

        $temporaryPath = tempnam(sys_get_temp_dir(), 'ekodoc_word_');

        try {
            $contentHtml = $this->resolveImageSources($document->content_html ?? '', $document->id);
            $contentHtml = $this->selfCloseVoidElements($contentHtml);

            $phpWord = new PhpWord();
            $section = $phpWord->addSection();

            Html::addHtml($section, $contentHtml);

            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($temporaryPath);

            $contents = file_get_contents($temporaryPath);

            // save()/file_get_contents() are only documented to throw or
            // return false on failure, but an empty/false read must be
            // treated the same as a hard failure — never a 200 with a
            // degraded/empty file (Boundaries & Constraints, spec-2-5).
            return $contents !== false && $contents !== '' ? $contents : null;
        } catch (Throwable $exception) {
            Log::warning('Document Word export failed.', [
                'document_id' => $document->id,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        } finally {
            if ($temporaryPath !== false && is_file($temporaryPath)) {
                // unlink() failing (e.g. a file lock from antivirus/indexing
                // on Windows, or a permissions issue) must never escape this
                // finally block and turn an otherwise-successful export (or
                // an already-reported conversion failure) into an unhandled
                // 500 — a leftover temp file is a non-fatal cleanup miss,
                // logged the same way the main try/catch above reports its
                // own failures.
                try {
                    unlink($temporaryPath);
                } catch (Throwable $cleanupException) {
                    Log::warning('Document Word export temp file cleanup failed.', [
                        'document_id' => $document->id,
                        'path' => $temporaryPath,
                        'exception' => $cleanupException->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Replaces every `<img src="/documents/{$documentId}/images/{file}">`
     * with an absolute disk path — the only `src` shape a saved
     * created-document's `content_html` ever carries
     * (SanitizesDocumentContent::relocateDraftImages()). Unlike
     * ExportDocumentToPdfAction::resolveImageSources() (which leaves a
     * broken `src` untouched — Chromium just renders it as a broken image),
     * a reference whose file can no longer be found on disk here has its
     * whole `<img>` tag dropped instead: PhpWord's `Html::addHtml()` throws
     * when an `<img src>` doesn't resolve to a readable file
     * (`PhpOffice\PhpWord\Shared\Html::parseImage()`), which would fail the
     * entire conversion for one missing image — so the tag is removed
     * *before* the PhpWord call, keeping the rest of the export intact
     * (Boundaries & Constraints, spec-2-5: best-effort, not guaranteed at
     * the PDF export's level).
     *
     * Public (rather than private) specifically so ExportDocumentToWordTest
     * can assert directly on the resolved disk path/dropped tag it
     * produces, same reasoning as
     * ExportDocumentToPdfAction::resolveImageSources().
     */
    public function resolveImageSources(string $contentHtml, int $documentId): string
    {
        $disk = Storage::disk('local');
        $prefix = "/documents/{$documentId}/images/";

        $resolved = preg_replace_callback(
            '#<img\b[^>]*\bsrc="'.preg_quote($prefix, '#').'([^"]+)"[^>]*/?>#',
            function (array $matches) use ($disk, $documentId, $prefix) {
                $filename = $matches[1];
                $path = "documents/{$documentId}/images/{$filename}";

                if (! $disk->exists($path)) {
                    return '';
                }

                $absolutePath = str_replace('\\', '/', $disk->path($path));

                return str_replace('src="'.$prefix.$filename.'"', 'src="'.$absolutePath.'"', $matches[0]);
            },
            $contentHtml
        );

        return $resolved ?? $contentHtml;
    }

    /**
     * Self-closes every `<img ...>`/`<br>`/`<hr>` — the three void elements
     * `SanitizesDocumentContent::ALLOWED_TAGS` ever lets through — so
     * `Html::addHtml()`'s strict `DOMDocument::loadXML()` parse doesn't fail
     * on them (see the class docblock). A tag that's already self-closed
     * (defensively, in case a future caller passes already-XHTML content)
     * is left untouched rather than gaining a doubled `//`.
     */
    private function selfCloseVoidElements(string $contentHtml): string
    {
        $result = preg_replace_callback(
            '#<(img|br|hr)\b([^>]*)>#i',
            function (array $matches) {
                $attributes = rtrim($matches[2]);

                if (str_ends_with($attributes, '/')) {
                    return '<'.$matches[1].$attributes.'>';
                }

                return '<'.$matches[1].$attributes.' />';
            },
            $contentHtml
        );

        return $result ?? $contentHtml;
    }
}
