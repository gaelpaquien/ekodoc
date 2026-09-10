<?php

namespace App\Actions;

use App\DataTransferObjects\UploadDraftAttachmentData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sole write point for a file attached to the editor before its document
 * has an `id` (spec-3-3, FR13, mirroring UploadEditorImageAction for
 * spec-2-2's inline images). Stores the file under a temporary area keyed
 * by the client-generated draft token — `documents/tmp/{token}/attachments/
 * {uuid}.ext` on the private disk — the same `draftToken` already generated
 * for images, mirroring the final `documents/{id}/attachments/` layout
 * CreateDocumentAction::relocateDraftAttachments() moves it into at save
 * time.
 *
 * No `DocumentAttachment` row is ever created here, and no extraction job
 * is ever dispatched — unlike an already-saved document's immediate
 * AttachDocumentFileAction, a draft attachment has no `document_id` to
 * attach to yet (AD-14 without one yet available). The row/job only ever
 * come into existence once the draft is actually saved.
 *
 * The stored filename is always a freshly generated UUID, never the
 * client-supplied one (Boundaries & Constraints) — nothing here is derived
 * from user input. The original filename is returned separately, purely
 * for display, never trusted for the stored path.
 */
class UploadDraftAttachmentAction
{
    public function __invoke(UploadDraftAttachmentData $data): array
    {
        $extension = $data->file->extension();
        $filename = Str::uuid()->toString().($extension !== '' ? ".{$extension}" : '');
        $directory = "documents/tmp/{$data->draftToken}/attachments";

        try {
            $path = $data->file->storeAs($directory, $filename, 'local');

            if ($path === false) {
                throw new RuntimeException('Storage::storeAs() returned false for the uploaded draft attachment.');
            }
        } catch (Throwable $exception) {
            Log::error('Draft attachment upload failed while storing the file on the private disk.', [
                'draft_token' => $data->draftToken,
                'filename' => $filename,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return [
            'filename' => $filename,
            'original_filename' => $data->file->getClientOriginalName(),
            'mime_type' => $data->file->getMimeType(),
            // Round-tripped back to Editor.vue's flash watcher so it can
            // reject an upload meant for a different draft/tab sharing the
            // same session — mirrors uploadedImage's own draftToken echo.
            'draftToken' => $data->draftToken,
        ];
    }
}
