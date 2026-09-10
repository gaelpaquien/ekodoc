<?php

namespace App\Actions;

use App\DataTransferObjects\AttachDocumentFileData;
use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\DocumentAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sole write point for attaching a file to an already-saved document
 * (spec-3-3, FR13) — mirrors ImportDocumentAction's shape exactly: the row
 * and the stored file are created/written within one transaction (AD-6/
 * AD-9), then text extraction is dispatched as a queued job after commit,
 * never run inline.
 *
 * Unlike ImportDocumentAction (whose `documents.file_path` is nullable, so
 * the row can be created first and `forceFill()`ed with the path once the
 * file is stored), `document_attachments.file_path` is non-nullable — the
 * file is stored *before* the row is created here, in a single insert that
 * already carries its final `file_path` (Code Map, spec-3-3).
 *
 * Only reached once `props.document` already has an `id` (Boundaries &
 * Constraints) — a not-yet-saved draft goes through
 * UploadDraftAttachmentAction/CreateDocumentAction::relocateDraftAttachments()
 * instead.
 */
class AttachDocumentFileAction
{
    public function __invoke(AttachDocumentFileData $data): DocumentAttachment
    {
        $attachment = DB::transaction(function () use ($data) {
            $path = $this->storeFile($data);

            // The file above is already safely on disk by the time this
            // insert runs — if it then fails (code review finding: a defect
            // this try/catch closes), the file must not be left orphaned
            // with no row ever pointing at it, mirroring storeFile()'s own
            // cleanup-and-rethrow shape.
            try {
                return DocumentAttachment::create([
                    'document_id' => $data->document->id,
                    'file_path' => $path,
                    'original_filename' => $data->file->getClientOriginalName(),
                    'mime_type' => $data->file->getMimeType(),
                    'extraction_status' => ExtractionStatus::Pending,
                ]);
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($path);

                Log::error('Document attach failed while creating the attachment row after the file was already stored.', [
                    'document_id' => $data->document->id,
                    'file_path' => $path,
                    'exception' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        });

        ExtractDocumentTextJob::dispatch($attachment);

        return $attachment;
    }

    /**
     * Stores the file on the private disk under the document's own
     * `attachments/` directory. The stored filename is always a freshly
     * generated UUID, never the client-supplied one (Boundaries &
     * Constraints) — nothing here is derived from user input. Any failure
     * cleans up, logs, and rethrows so the enclosing transaction rolls back
     * — mirrors ImportDocumentAction::storeFile(), just with no row yet to
     * roll back alongside the file (none has been created yet).
     */
    private function storeFile(AttachDocumentFileData $data): string
    {
        $extension = $data->file->extension();
        $filename = Str::uuid()->toString().($extension !== '' ? ".{$extension}" : '');
        $directory = "documents/{$data->document->id}/attachments";

        try {
            $path = $data->file->storeAs($directory, $filename, 'local');

            if ($path === false) {
                throw new RuntimeException('Storage::storeAs() returned false for the attached file.');
            }

            return $path;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete("{$directory}/{$filename}");

            Log::error('Document attach failed while storing the file on the private disk.', [
                'document_id' => $data->document->id,
                'filename' => $filename,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
