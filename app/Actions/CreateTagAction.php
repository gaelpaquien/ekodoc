<?php

namespace App\Actions;

use App\DataTransferObjects\CreateTagData;
use App\Models\Tag;

/**
 * One of the three sole entry points (with RenameTagAction/DeleteTagAction)
 * for creating/renaming/deleting a tag (Boundaries & Constraints, spec-3-5)
 * — the case-insensitive duplicate check already happened in
 * CreateTagRequest, so this action performs no validation of its own.
 */
class CreateTagAction
{
    public function __invoke(CreateTagData $data): Tag
    {
        return Tag::create(['name' => $data->name]);
    }
}
