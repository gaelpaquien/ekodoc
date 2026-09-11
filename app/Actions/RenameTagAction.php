<?php

namespace App\Actions;

use App\DataTransferObjects\RenameTagData;
use App\Models\Tag;

/**
 * Sole entry point for renaming a tag (Boundaries & Constraints, spec-3-5)
 * — only the `name` column changes, the `document_tag` pivot is never
 * touched here: every document already carrying this tag keeps it, under
 * its new name, with no re-sync needed.
 */
class RenameTagAction
{
    public function __invoke(RenameTagData $data): Tag
    {
        $data->tag->update(['name' => $data->name]);

        return $data->tag;
    }
}
