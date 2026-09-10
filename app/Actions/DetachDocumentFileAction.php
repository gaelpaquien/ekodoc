<?php

namespace App\Actions;

use App\DataTransferObjects\DetachDocumentFileData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sole write point for removing a previously attached file from an
 * already-saved document (spec-3-3, FR13) — immediate, no confirmation
 * dialog beyond the client's own "Retirer" (Boundaries & Constraints:
 * out of scope here, this action is never reached without it already
 * having happened). File first, then row — never a bare `delete()` left to
 * cascade the file away on its own (Boundaries & Constraints: "jamais
 * cascadeOnDelete() nu pour les fichiers") — then the parent document's
 * `attachments_extracted_text` is resynced so a detached attachment's text
 * stops being searchable immediately.
 *
 * The row delete and the resync are wrapped in one `DB::transaction()`
 * (code review finding): without it, a resync failure right after a
 * successful row delete would leave `attachments_extracted_text` stale —
 * still listing the just-removed attachment's text as searchable even
 * though its row (and file) are already gone. Wrapping both together means
 * either both commit or neither does, logged the same way
 * AttachDocumentFileAction/ImportDocumentAction log their own failures.
 */
class DetachDocumentFileAction
{
    public function __invoke(DetachDocumentFileData $data): void
    {
        $attachment = $data->attachment;
        $document = $attachment->document;

        Storage::disk('local')->delete($attachment->file_path);

        try {
            DB::transaction(function () use ($attachment, $document) {
                $attachment->delete();

                $document->syncAttachmentsExtractedText();
            });
        } catch (Throwable $exception) {
            Log::error('Document attachment detach failed while removing the row/resyncing the parent document, after the file was already deleted.', [
                'document_id' => $document->id,
                'attachment_id' => $attachment->id,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
