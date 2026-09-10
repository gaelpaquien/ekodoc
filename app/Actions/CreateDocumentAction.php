<?php

namespace App\Actions;

use App\Actions\Concerns\SanitizesDocumentContent;
use App\DataTransferObjects\CreateDocumentData;
use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

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
 *
 * A draft attachment (spec-3-3, FR13) follows the same "relocate at save
 * time" shape as an inline image, but is never referenced anywhere in
 * `content_html` — the client instead tells this action exactly which draft
 * attachments to keep via `$data->draftAttachments` (Design Notes,
 * spec-3-3). The `DocumentAttachment` rows created by
 * relocateDraftAttachments() are attached to the returned Document as its
 * `attachments` relation so the controller can dispatch one
 * ExtractDocumentTextJob per row after the transaction commits — mirroring
 * how ImportDocumentAction's own caller dispatches its job only once the
 * row is safely persisted.
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

            $createdAttachments = $this->relocateDraftAttachments($data->draftToken, $document, $data->draftAttachments);
            $document->setRelation('attachments', $createdAttachments);

            return $document;
        });
    }

    /**
     * No-op (empty collection) when the draft never had a token, or no
     * attachment was ever kept — nothing to relocate. Otherwise moves each
     * kept draft attachment from `documents/tmp/{token}/attachments/` to
     * `documents/{id}/attachments/` and creates its `DocumentAttachment` row
     * (`pending`) — mirrors `relocateDraftImages()` above (Code Map,
     * spec-3-3) but keyed off the client-supplied kept list rather than a
     * regex over `content_html`, since an attachment is never referenced
     * there.
     *
     * `mime_type` is never trusted from the client — redetected from the
     * moved file itself via `Storage::mimeType()` (Boundaries & Constraints:
     * "ne pas faire confiance au mime_type client pour la relocalisation
     * brouillon"). A kept filename no longer present under the draft's tmp
     * directory (already relocated, or never actually uploaded) is silently
     * skipped rather than failing the whole save.
     *
     * A move failure cleans up only the files this call itself already
     * relocated before rethrowing so the enclosing transaction rolls back —
     * the already-created `DocumentAttachment` rows roll back with it, same
     * transaction (Acceptance Criteria: "Échec relocalisation → rollback").
     */
    private function relocateDraftAttachments(?string $draftToken, Document $document, array $keptDraftAttachments): Collection
    {
        $created = collect();

        if ($draftToken === null || $keptDraftAttachments === []) {
            return $created;
        }

        $disk = Storage::disk('local');
        $sourceDirectory = "documents/tmp/{$draftToken}/attachments";
        $destinationDirectory = "documents/{$document->id}/attachments";
        $movedDestinationPaths = [];

        try {
            foreach ($keptDraftAttachments as $draftAttachment) {
                $filename = $draftAttachment['filename'];
                $sourcePath = "{$sourceDirectory}/{$filename}";

                if (! $disk->exists($sourcePath)) {
                    continue;
                }

                $destinationPath = "{$destinationDirectory}/{$filename}";

                if (! $disk->move($sourcePath, $destinationPath)) {
                    throw new RuntimeException('Storage::move() returned false while relocating a draft attachment.');
                }

                $movedDestinationPaths[] = $destinationPath;

                $mimeType = $disk->mimeType($destinationPath);

                // `mimeType()` returns `false` when it can't be determined
                // (code review finding) — never insert that into the
                // non-nullable `mime_type` column; treated the same as a
                // move failure, rolling back the whole transaction.
                if ($mimeType === false) {
                    throw new RuntimeException('Storage::mimeType() returned false while relocating a draft attachment.');
                }

                $created->push(DocumentAttachment::create([
                    'document_id' => $document->id,
                    'file_path' => $destinationPath,
                    'original_filename' => $draftAttachment['original_filename'],
                    'mime_type' => $mimeType,
                    'extraction_status' => ExtractionStatus::Pending,
                ]));
            }
        } catch (Throwable $exception) {
            foreach ($movedDestinationPaths as $movedDestinationPath) {
                $disk->delete($movedDestinationPath);
            }

            Log::error('Document save failed while relocating draft attachments to their final directory.', [
                'draft_token' => $draftToken,
                'document_id' => $document->id,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // Mirrors relocateDraftImages(): only reclaimed once nothing is left
        // behind in it. The sibling tmp/{token}/images (and, once emptied,
        // tmp/{token} itself) are left for their own cleanup path — same
        // already-accepted deferred-cleanup gap.
        if ($disk->allFiles($sourceDirectory) === []) {
            $disk->deleteDirectory($sourceDirectory);
        }

        return $created;
    }
}
