<?php

namespace App\DataTransferObjects;

use App\Models\Document;

final readonly class DeleteDocumentData
{
    public function __construct(
        public Document $document,
    ) {
    }
}
