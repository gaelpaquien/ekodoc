<?php

namespace App\DataTransferObjects;

use Illuminate\Http\UploadedFile;

final readonly class UploadDraftAttachmentData
{
    public function __construct(
        public string $draftToken,
        public UploadedFile $file,
    ) {
    }
}
