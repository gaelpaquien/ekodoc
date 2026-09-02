<?php

namespace App\Actions;

use App\DataTransferObjects\CategorizeDocumentData;
use App\Models\Document;

/**
 * Sole write point for `documents.category_id` (AD-16). Every assignment —
 * choosing a category at import time, reassigning from the Document Detail
 * page, or clearing back to "Uncategorized" (`categoryId: null`) — goes
 * through here. No other code may call Document::update()/save() with
 * `category_id`, including ImportDocumentAction, which never touches it.
 *
 * A category is always optional and never blocking: this action performs
 * no existence check of its own — `category_id` is validated (nullable,
 * must reference an existing category) by the calling Form Request before
 * the DTO is even built.
 */
class CategorizeDocumentAction
{
    public function __invoke(CategorizeDocumentData $data): Document
    {
        $data->document->forceFill([
            'category_id' => $data->categoryId,
        ])->save();

        return $data->document;
    }
}
