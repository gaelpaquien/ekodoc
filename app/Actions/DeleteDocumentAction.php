<?php

namespace App\Actions;

use App\DataTransferObjects\DeleteDocumentData;
use Illuminate\Support\Facades\Storage;

/**
 * Sole entry point for permanently deleting a document (AD-15) — no
 * `SoftDeletes`, no trash/undo in v1. Cleans up in a strict order so a
 * failure partway through never leaves the search index or the DB row
 * pointing at files that no longer exist:
 *
 * 1. Scout index (`unsearchable()`, called explicitly rather than relying
 *    on the model's `deleted` event). This project's configured driver is
 *    `database` (`Laravel\Scout\Engines\DatabaseEngine`), whose `delete()`
 *    is a no-op — it has no separate index and reads the `documents` table
 *    directly, so search results are actually cleared by step 5 (the row
 *    itself going away), not by this call. `unsearchable()` is kept anyway
 *    as defensive, driver-agnostic behavior: it's a correct no-op today and
 *    becomes load-bearing automatically if the driver ever changes to one
 *    with a real external index (e.g. Meilisearch/Algolia), where relying
 *    on the `deleted` event instead would be too late — Scout fires it only
 *    after the row is already gone, with nothing left to key the index
 *    removal off of.
 * 2. Original file + any extracted images, both living under the same
 *    `documents/{id}` directory (AD-15) — removed together via
 *    `deleteDirectory()`. This already removes each attachment's file too
 *    (they live under that same directory's `attachments/` subpath), so
 *    step 3 below only ever finds already-gone files.
 * 3. Each `DocumentAttachment` (spec-3-3, FR13) — file then row, per
 *    attachment, explicit and never a bare `cascadeOnDelete()` left to
 *    remove the rows on its own (Boundaries & Constraints: "jamais
 *    cascadeOnDelete() nu pour les fichiers"). The file deletion here is
 *    redundant with step 2 (`Storage::delete()` on an already-missing path
 *    is a no-op, `throw => false`) but kept for the same defensive reason
 *    `deleteDirectory()`/`delete()` never need an existence check first.
 * 4. Preview cache (`previews/{id}.pdf`), the same path
 *    `ConvertDocumentToPreviewAction` writes to.
 * 5. The `Document` row itself, last.
 *
 * `deleteDirectory()`/`delete()` never throw on a missing path on the
 * `local` disk (`throw => false`, same as `ImportDocumentAction::storeFile()`)
 * so a document whose source file is already missing (`sourceMissing`)
 * still deletes cleanly — no existence check needed beforehand.
 */
class DeleteDocumentAction
{
    private const PREVIEW_DIRECTORY = 'previews';

    public function __invoke(DeleteDocumentData $data): void
    {
        $document = $data->document;
        $disk = Storage::disk('local');

        $document->unsearchable();

        $disk->deleteDirectory("documents/{$document->id}");

        foreach ($document->attachments as $attachment) {
            $disk->delete($attachment->file_path);
            $attachment->delete();
        }

        $disk->delete(self::PREVIEW_DIRECTORY."/{$document->id}.pdf");

        $document->delete();
    }
}
