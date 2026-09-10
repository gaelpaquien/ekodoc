<?php

namespace App\Actions;

use App\Actions\Concerns\SanitizesDocumentContent;
use App\DataTransferObjects\SyncDocumentTagsData;
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
 * - Tags are re-synced unconditionally on every save, not just when
 *   changed: SyncDocumentTagsAction (Boundaries & Constraints, spec-3-1,
 *   the sole write point for `document_tag`) is always invoked with the
 *   submitted `tagIds`, including an empty array to clear every previously
 *   assigned tag — `sync()` is itself a no-op when nothing actually
 *   changed, so there's no correctness reason to diff against the
 *   document's current tags first.
 *
 * The whole thing — relocating any new draft images, rewriting
 * `content_html`, updating the `Document` row, and re-syncing tags — runs
 * inside one `DB::transaction()` so a failure at any step rolls back
 * everything (Boundaries & Constraints, spec-2-3).
 */
class UpdateDocumentAction
{
    use SanitizesDocumentContent;

    public function __construct(private SyncDocumentTagsAction $syncTags)
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

            ($this->syncTags)(new SyncDocumentTagsData(
                document: $document,
                tagIds: $data->tagIds,
            ));

            return $document;
        });
    }
}
