<?php

namespace App\DataTransferObjects;

final readonly class CreateDocumentData
{
    public function __construct(
        public string $title,
        public string $contentHtml,
        // The client-generated draft token (spec-2-2, Design Notes) an
        // image inserted before this document ever had an `id` was
        // temporarily stored under — null when the document was saved with
        // no image ever inserted, in which case there is no tmp/{token}
        // directory to move.
        public ?string $draftToken = null,
    ) {
    }
}
