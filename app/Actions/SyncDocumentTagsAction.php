<?php

namespace App\Actions;

use App\DataTransferObjects\SyncDocumentTagsData;
use App\Models\Document;

/**
 * Sole write point for the `document_tag` pivot (Boundaries & Constraints,
 * spec-3-1) — every assignment, from import, editor save (create + update),
 * or the Document Detail page, goes through here, always via a full
 * `sync()`. Never `attach()`/`detach()` incrementally: an empty `$tagIds`
 * clears every existing tag, and a partial list replaces the pivot
 * entirely rather than merging with what was already there — no other code
 * may call `$document->tags()->attach()/detach()/sync()` directly.
 *
 * A document's tags are always optional and never blocking: this action
 * performs no existence check of its own — each id in `$tagIds` is
 * validated (must reference an existing tag) by the calling Form Request
 * before the DTO is even built.
 */
class SyncDocumentTagsAction
{
    public function __invoke(SyncDocumentTagsData $data): Document
    {
        $data->document->tags()->sync($data->tagIds);

        return $data->document;
    }
}
