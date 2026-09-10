<?php

namespace App\DataTransferObjects;

use App\Models\Document;

final readonly class SyncDocumentTagsData
{
    /**
     * @param  array<int, int>  $tagIds
     */
    public function __construct(
        public Document $document,
        public array $tagIds,
    ) {
    }
}
