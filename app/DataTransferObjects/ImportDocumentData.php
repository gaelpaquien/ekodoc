<?php

namespace App\DataTransferObjects;

use Illuminate\Http\UploadedFile;

final readonly class ImportDocumentData
{
    public function __construct(
        public UploadedFile $file,
    ) {
    }
}
