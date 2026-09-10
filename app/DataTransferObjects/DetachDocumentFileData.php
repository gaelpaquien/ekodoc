<?php

namespace App\DataTransferObjects;

use App\Models\DocumentAttachment;

final readonly class DetachDocumentFileData
{
    public function __construct(
        public DocumentAttachment $attachment,
    ) {
    }
}
