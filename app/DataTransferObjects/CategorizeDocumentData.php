<?php

namespace App\DataTransferObjects;

use App\Models\Document;

final readonly class CategorizeDocumentData
{
    public function __construct(
        public Document $document,
        public ?int $categoryId,
    ) {
    }
}
