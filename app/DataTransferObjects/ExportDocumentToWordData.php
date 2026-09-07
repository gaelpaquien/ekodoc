<?php

namespace App\DataTransferObjects;

use App\Models\Document;

final readonly class ExportDocumentToWordData
{
    public function __construct(
        public Document $document,
    ) {
    }
}
