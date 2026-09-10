<?php

namespace App\Jobs;

use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Support\DocumentMimeTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Best-effort text extraction, run out-of-band from the import/attach
 * request (AD-6): a large/complex real-world document can take longer to
 * parse than any reasonable HTTP/proxy timeout allows. The row (`Document`
 * or, since spec-3-3, `DocumentAttachment`) and its stored file already
 * exist by the time this job runs, so nothing here can ever undo the
 * import/attach — at worst `extraction_status` ends up `failed` and
 * `extracted_text` stays null (AD-9).
 *
 * `$target` is either a `Document` (imported document, spec-1) or a
 * `DocumentAttachment` (spec-3-3) — both share the same
 * `file_path`/`mime_type`/`extracted_text`/`extraction_status` shape, so
 * every extraction step below is identical regardless of which one this
 * job was dispatched for. The one difference: after a `DocumentAttachment`
 * finishes (successfully or not), its parent Document's
 * `attachments_extracted_text` is resynced too, since that's the column
 * Scout's `database` driver actually queries (Design Notes, spec-3-3).
 */
class ExtractDocumentTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public Document|DocumentAttachment $target)
    {
    }

    public function handle(): void
    {
        $this->target->forceFill(['extraction_status' => ExtractionStatus::Processing])->save();

        // A pathological file (huge embedded images, deeply nested content)
        // can exhaust memory the same way it can exceed a time limit — a
        // fatal PHP error, uncatchable by the try/catch below, that would
        // otherwise crash the worker process and leave this job "reserved"
        // to be retried (and crash again) once its reservation expires.
        // This shutdown guard fires only if the script is about to die from
        // an uncaught fatal: it marks the target failed and removes the
        // job from the queue so it is never silently retried forever.
        register_shutdown_function(function () {
            $error = error_get_last();

            if ($error === null || ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            Log::error('Document text extraction crashed the worker process; marking as failed instead of letting it retry indefinitely.', [
                'target_type' => $this->target::class,
                'target_id' => $this->target->id,
                'error' => $error,
            ]);

            $this->target->forceFill(['extraction_status' => ExtractionStatus::Failed])->save();
            $this->resyncParentIfAttachment();
            $this->job?->delete();
        });

        try {
            $text = $this->extractText($this->target->file_path, $this->target->mime_type);

            $this->target->forceFill([
                'extracted_text' => $text,
                'extraction_status' => ExtractionStatus::Completed,
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Document text extraction failed; import/attach remains valid without searchable content.', [
                'target_type' => $this->target::class,
                'target_id' => $this->target->id,
                'exception' => $exception->getMessage(),
            ]);

            $this->target->forceFill(['extraction_status' => ExtractionStatus::Failed])->save();
        }

        $this->resyncParentIfAttachment();
    }

    /**
     * Laravel's authoritative "this job will never run again" hook — called
     * even when no attempt of `handle()` ever gets the chance to run again
     * (e.g. the worker was killed while this job was reserved, and by the
     * time it became available again `attempts` already exceeded `$tries`).
     * Without this, such a row would stay stuck on `processing` forever
     * with nothing left in the queue to ever revisit it.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Document text extraction job failed permanently (max attempts exhausted or worker lost).', [
            'target_type' => $this->target::class,
            'target_id' => $this->target->id,
            'exception' => $exception?->getMessage(),
        ]);

        $this->target->forceFill(['extraction_status' => ExtractionStatus::Failed])->save();
        $this->resyncParentIfAttachment();
    }

    /**
     * Keeps `documents.attachments_extracted_text` — the real column
     * Scout's `database` driver queries (Design Notes, spec-3-3) — in sync
     * every time an attachment's own extraction state changes, whatever the
     * outcome (completed, failed, or crashed). A no-op when `$target` is a
     * `Document` itself (nothing above it to resync).
     */
    private function resyncParentIfAttachment(): void
    {
        if ($this->target instanceof DocumentAttachment) {
            $this->target->document->syncAttachmentsExtractedText();
        }
    }

    /**
     * Dispatches on the file's actual detected MIME type (as validated by
     * ImportDocumentRequest/AttachDocumentFileRequest), never the
     * client-supplied filename extension — a file whose real content
     * doesn't match its filename extension must still be extracted
     * correctly.
     */
    private function extractText(string $path, ?string $mimeType): ?string
    {
        $absolutePath = Storage::disk('local')->path($path);

        $text = match ($this->formatFromMimeType($mimeType)) {
            'pdf' => (new PdfParser())->parseFile($absolutePath)->getText(),
            'docx' => $this->extractFromWord($absolutePath),
            'xlsx' => $this->extractFromSpreadsheet($absolutePath),
            default => null,
        };

        $text = $text !== null ? trim($text) : null;

        return $text !== '' ? $text : null;
    }

    private function formatFromMimeType(?string $mimeType): ?string
    {
        return DocumentMimeTypes::formatFromMime($mimeType);
    }

    private function extractFromWord(string $absolutePath): string
    {
        $phpWord = WordIOFactory::load($absolutePath);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText().' ';
                }
            }
        }

        return $text;
    }

    private function extractFromSpreadsheet(string $absolutePath): string
    {
        $spreadsheet = SpreadsheetIOFactory::load($absolutePath);
        $text = '';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $value = $cell->getFormattedValue();

                    if ($value !== null && $value !== '') {
                        $text .= $value.' ';
                    }
                }
            }
        }

        return $text;
    }
}
