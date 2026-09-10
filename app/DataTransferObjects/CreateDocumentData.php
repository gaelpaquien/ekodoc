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
        // The draft attachments to keep (spec-3-3, Design Notes) — unlike an
        // inline `<img>`, an attachment is never referenced from within
        // `content_html`, so the server cannot infer which of the files
        // sitting under this draft's tmp directory are still wanted. Each
        // entry carries the server-generated `filename` (the tmp file to
        // relocate) and the client-supplied `original_filename` (display
        // name only, never trusted for the stored path). Empty when the
        // document was saved with no attachment ever added.
        //
        // @var list<array{filename: string, original_filename: string}>
        public array $draftAttachments = [],
    ) {
    }
}
