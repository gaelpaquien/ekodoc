<?php

namespace App\DataTransferObjects;

final readonly class ConvertDocumentToPreviewData
{
    public function __construct(
        public int $documentId,
        public string $sourcePath,
    ) {
    }
}
