<?php

namespace App\DataTransferObjects;

use App\Models\Document;

final readonly class ExportDocumentToPdfData
{
    public function __construct(
        public Document $document,
    ) {
    }
}
