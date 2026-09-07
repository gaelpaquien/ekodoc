<?php

namespace App\Actions;

use App\Actions\Concerns\SanitizesDocumentContent;
use App\DataTransferObjects\CategorizeDocumentData;
use App\DataTransferObjects\UpdateDocumentData;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

/**
 * Sole write point for saving changes made to a document authored in the
 * editor (spec-2-3) — the edit-mode counterpart to CreateDocumentAction,
 * sharing its sanitization/derivation/relocation logic via
 * SanitizesDocumentContent instead of duplicating it.
 *
 * Two things differ from creation:
 *
 * - The sanitizer's allowed `src` prefixes include the document's own
 *   already-final `documents/{id}/images/` directory (its previously saved
 *   images) alongside this edit session's tmp draft directory, if any new
 *   image was inserted — otherwise a re-save of unchanged content would
 *   have every existing `<img>` stripped as if forged (Boundaries &
 *   Constraints, spec-2-3).
 * - `category_id` is re-evaluated on every save, not just when non-null:
 *   CategorizeDocumentAction (AD-16, the sole write point for it) is
 *   invoked whenever the submitted value differs from what's already
 *   stored, including a reassignment back to `null` ("Non classé") — unlike
 *   CreateDocumentAction/storeCreated(), which only ever calls it for a
 *   non-null choice because a brand-new document already starts
 *   uncategorized.
 *
 * The whole thing — relocating any new draft images, rewriting
 * `content_html`, updating the `Document` row, and the conditional
 * recategorization — runs inside one `DB::transaction()` so a failure at
 * any step rolls back everything (Boundaries & Constraints, spec-2-3).
 */
class UpdateDocumentAction
{
    use SanitizesDocumentContent;

    public function __construct(private CategorizeDocumentAction $categorize)
    {
    }

    public function __invoke(UpdateDocumentData $data): Document
    {
        $document = $data->document;

        $allowedImageSrcPrefixes = array_values(array_filter([
            "/documents/{$document->id}/images/",
            $data->draftToken !== null ? self::DRAFT_IMAGE_SRC_PREFIX.$data->draftToken.'/' : null,
        ]));

        $contentHtml = $this->sanitizeContentHtml($data->contentHtml, $allowedImageSrcPrefixes);

        return DB::transaction(function () use ($data, $document, $contentHtml) {
            $finalContentHtml = $this->relocateDraftImages($data->draftToken, $document, $contentHtml);

            $document->update([
                'title' => $data->title,
                'content_html' => $finalContentHtml,
                'extracted_text' => $this->deriveExtractedText($finalContentHtml),
            ]);

            if ($data->categoryId !== $document->category_id) {
                ($this->categorize)(new CategorizeDocumentData(
                    document: $document,
                    categoryId: $data->categoryId,
                ));
            }

            return $document;
        });
    }
}
