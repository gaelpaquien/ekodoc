<?php

namespace App\DataTransferObjects;

use App\Models\Document;
use Illuminate\Http\UploadedFile;

final readonly class AttachDocumentFileData
{
    public function __construct(
        public Document $document,
        public UploadedFile $file,
    ) {
    }
}
