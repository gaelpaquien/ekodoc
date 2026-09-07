<?php

namespace App\DataTransferObjects;

use Illuminate\Http\UploadedFile;

final readonly class UploadEditorImageData
{
    public function __construct(
        public string $draftToken,
        public UploadedFile $image,
        public string $alt,
    ) {
    }
}
