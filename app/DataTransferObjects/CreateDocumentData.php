<?php

namespace App\DataTransferObjects;

final readonly class CreateDocumentData
{
    public function __construct(
        public string $title,
        public string $contentHtml,
    ) {
    }
}
