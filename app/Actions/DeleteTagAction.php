<?php

namespace App\Actions;

use App\DataTransferObjects\DeleteTagData;

/**
 * Sole entry point for deleting a tag (Boundaries & Constraints, spec-3-5)
 * — no file on disk is ever involved here (unlike DeleteDocumentAction), so
 * the "never a bare cascadeOnDelete()" rule that protects against orphaned
 * files doesn't apply (Design Notes): `document_tag` is a pure DB relation,
 * already covered by the `cascadeOnDelete()` on `document_tag.tag_id`
 * (migration 2026_09_09_100100). The affected document count is captured
 * before deletion — once the tag row is gone, the pivot rows the cascade
 * removed are no longer reachable to count.
 *
 * Never calls SyncDocumentTagsAction: that action is the sole write point
 * only for a *document's* full tag assignment, not for detaching a tag
 * that's being deleted outright.
 */
class DeleteTagAction
{
    public function __invoke(DeleteTagData $data): int
    {
        $count = $data->tag->documents()->count();

        $data->tag->delete();

        return $count;
    }
}
