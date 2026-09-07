<?php

namespace App\Actions;

use App\Actions\Concerns\SanitizesDocumentContent;
use App\DataTransferObjects\CreateDocumentData;
use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

/**
 * Sole write point for documents authored directly in the editor (Boundaries
 * & Constraints, spec-2-1). Unlike ImportDocumentAction there is no source
 * file to store on disk and no deferred extraction job to dispatch:
 * `content_html` is the document's content, so `extracted_text` is derived
 * from it synchronously, right here, and marked complete immediately
 * (AD-9) — never left pending/null the way an imported document's can be
 * while its background job runs (or fails).
 *
 * `file_path`/`mime_type` are deliberately left null — a created document
 * has no original file, its content lives in `content_html` instead.
 *
 * An inline `<img>` (spec-2-2) is the one exception to "no file at all":
 * it was already uploaded to a temporary, draft-token-keyed area by
 * UploadEditorImageAction before this document had an `id` (AD-14 without
 * a `document_id` yet available). Closing the draft therefore means two
 * things happen together — the `Document` row is created, and that
 * temporary area is moved into the document's own `documents/{id}/images/`
 * home with `content_html` rewritten to match — wrapped in one
 * `DB::transaction()` (Boundaries & Constraints, spec-2-2) so a move
 * failure rolls back the row too, never a document left pointing at a
 * broken image.
 *
 * The sanitization/derivation/relocation steps themselves live in
 * SanitizesDocumentContent (spec-2-3), shared unchanged with
 * UpdateDocumentAction — here they're always invoked with a single allowed
 * `src` prefix (this draft's own tmp directory), since a document being
 * created has no final directory of its own yet.
 */
class CreateDocumentAction
{
    use SanitizesDocumentContent;

    public function __invoke(CreateDocumentData $data): Document
    {
        $allowedImageSrcPrefixes = $data->draftToken !== null
            ? [self::DRAFT_IMAGE_SRC_PREFIX.$data->draftToken.'/']
            : [];

        $contentHtml = $this->sanitizeContentHtml($data->contentHtml, $allowedImageSrcPrefixes);

        return DB::transaction(function () use ($data, $contentHtml) {
            $document = Document::create([
                'title' => $data->title,
                'source' => DocumentSource::Created,
                'content_html' => $contentHtml,
                'extracted_text' => $this->deriveExtractedText($contentHtml),
                'extraction_status' => ExtractionStatus::Completed,
            ]);

            $finalContentHtml = $this->relocateDraftImages($data->draftToken, $document, $contentHtml);

            if ($finalContentHtml !== $contentHtml) {
                $document->forceFill(['content_html' => $finalContentHtml])->save();
            }

            return $document;
        });
    }
}
