<?php

namespace App\Actions;

use App\DataTransferObjects\CreateDocumentData;
use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
 */
class CreateDocumentAction
{
    /**
     * Tags the editor's toolbar (FR8: headings, lists, tables) and the
     * image insertion path (FR9, spec-2-2) can ever produce. Anything else
     * arriving in `content_html` — a `<script>`, an `onerror=` attribute, a
     * raw API call bypassing the editor entirely — is stripped in
     * `sanitizeContentHtml()` before the document is persisted, since it is
     * later rendered unescaped (`v-html`) on the document's detail page.
     */
    private const ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'p', 'ul', 'ol', 'li', 'strong', 'em', 's',
        'code', 'pre', 'blockquote', 'hr', 'br',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'img',
    ];

    /**
     * `<img>` is the only allowed tag that keeps any attribute at all
     * (Boundaries & Constraints, spec-2-2: the sanitizer "ne conserve que
     * `src`/`alt`") — every other allowed tag still strips down to zero
     * attributes below, unchanged from Story 2.1.
     */
    private const ALLOWED_IMG_ATTRIBUTES = ['src', 'alt'];

    /**
     * Prefix of the only `src` shape ever produced by the editor's own
     * upload flow while a document is still a draft — see
     * `serveDraftImage()`/`documents.editorImages.tmp` in
     * DocumentController. Mirrored in the route's own `{token}`/`{filename}`
     * constraints (routes/web.php).
     */
    private const DRAFT_IMAGE_SRC_PREFIX = '/documents/editor-images/tmp/';

    private const DRAFT_IMAGE_FILENAME_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\.[a-zA-Z0-9]+';

    public function __invoke(CreateDocumentData $data): Document
    {
        $contentHtml = $this->sanitizeContentHtml($data->contentHtml, $data->draftToken);

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

    /**
     * Rebuilds `content_html` keeping only the allowed tags. Every allowed
     * tag but `img` is stripped of every attribute — none of the toolbar's
     * own output ever carries one (no classes/styles/ids) — closing off
     * attribute-based injection without touching legitimate content. `img`
     * keeps `src`/`alt` only, and only when `src` matches the exact draft
     * image shape this specific `$draftToken` could have produced
     * (`isAllowedDraftImageSrc()`) — any other `src` (an external URL, a
     * different draft's token, a `data:`/base64 URI, `javascript:`, ...)
     * gets the whole tag dropped, same as any other non-conforming content
     * (I/O matrix, spec-2-2: forged `content_html`).
     */
    private function sanitizeContentHtml(string $html, ?string $draftToken): string
    {
        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $document->getElementsByTagName('div')->item(0);

        if ($root === null) {
            return '';
        }

        $this->sanitizeNode($root, $draftToken);

        $sanitized = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $sanitized .= $document->saveHTML($child);
        }

        return $sanitized;
    }

    private function sanitizeNode(\DOMNode $node, ?string $draftToken): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if (! $child instanceof \DOMElement || ! in_array($child->tagName, self::ALLOWED_TAGS, true)) {
                $node->removeChild($child);

                continue;
            }

            if ($child->tagName === 'img') {
                if (! $this->isAllowedDraftImageSrc($child->getAttribute('src'), $draftToken)) {
                    $node->removeChild($child);

                    continue;
                }

                foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                    if (! in_array($attribute->name, self::ALLOWED_IMG_ATTRIBUTES, true)) {
                        $child->removeAttribute($attribute->name);
                    }
                }

                // `img` is a void element — no children to recurse into.
                continue;
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                $child->removeAttribute($attribute->name);
            }

            $this->sanitizeNode($child, $draftToken);
        }
    }

    /**
     * True only for `/documents/editor-images/tmp/{$draftToken}/{uuid}.ext`
     * — the exact URL UploadEditorImageAction/serveDraftImage produce for
     * *this* draft. Requiring the token to match (rather than accepting any
     * syntactically valid tmp URL) closes off one draft's saved
     * `content_html` ever pointing at another draft's still-unmoved images.
     * With no `$draftToken` at all (a request that never went through the
     * editor's upload flow), every `img` is rejected.
     */
    private function isAllowedDraftImageSrc(string $src, ?string $draftToken): bool
    {
        if ($draftToken === null || $src === '') {
            return false;
        }

        $pattern = '#^'.preg_quote(self::DRAFT_IMAGE_SRC_PREFIX.$draftToken.'/', '#').self::DRAFT_IMAGE_FILENAME_PATTERN.'$#';

        return preg_match($pattern, $src) === 1;
    }

    /**
     * No-op (Boundaries & Constraints, spec-2-2: "Enregistrement sans
     * image") when the draft never had a token, or the token's tmp
     * directory doesn't exist — nothing was ever uploaded, or it was
     * already relocated/never inserted. Otherwise moves only the files
     * still referenced by the sanitized `$contentHtml`
     * (`referencedDraftImageFilenames()`) from `documents/tmp/{token}/` to
     * `documents/{id}/` (preserving the `images/` sub-path) and rewrites
     * `content_html`'s matching prefix — a plain string replacement, safe
     * because `isAllowedDraftImageSrc()` already guaranteed every
     * remaining `<img src>` in `$contentHtml` shares that exact prefix
     * (Design Notes, spec-2-2).
     *
     * A file uploaded into the draft and then removed from the document
     * before "Enregistrer" (e.g. inserted, then deleted) is deliberately
     * left behind in `tmp/{token}` rather than moved into the document's
     * permanent home — the already-accepted deferred-cleanup gap for
     * abandoned draft directories covers it, same as a draft that's never
     * saved at all.
     *
     * A move failure cleans up whatever was already relocated into the
     * destination directory — mirroring ImportDocumentAction::storeFile()
     * — then rethrows so the enclosing transaction rolls back the
     * just-created Document row. Per Boundaries & Constraints ("Never"),
     * the source `tmp/{token}` directory is deliberately left as-is on
     * failure — cleaning up abandoned draft directories is out of scope
     * for this story.
     */
    private function relocateDraftImages(?string $draftToken, Document $document, string $contentHtml): string
    {
        if ($draftToken === null) {
            return $contentHtml;
        }

        $disk = Storage::disk('local');
        $sourceDirectory = "documents/tmp/{$draftToken}";

        if (! $disk->exists($sourceDirectory)) {
            return $contentHtml;
        }

        $referencedFilenames = $this->referencedDraftImageFilenames($contentHtml, $draftToken);
        $destinationDirectory = "documents/{$document->id}";

        try {
            foreach ($disk->allFiles($sourceDirectory) as $sourcePath) {
                if (! in_array(basename($sourcePath), $referencedFilenames, true)) {
                    // Uploaded during this draft, but no longer referenced
                    // by the content being saved — left in place, see the
                    // method doc comment above.
                    continue;
                }

                $relativePath = Str::after($sourcePath, "{$sourceDirectory}/");
                $destinationPath = "{$destinationDirectory}/{$relativePath}";

                if (! $disk->move($sourcePath, $destinationPath)) {
                    throw new RuntimeException('Storage::move() returned false while relocating a draft editor image.');
                }
            }
        } catch (Throwable $exception) {
            $disk->deleteDirectory($destinationDirectory);

            Log::error('Document creation failed while relocating draft editor images to their final directory.', [
                'draft_token' => $draftToken,
                'document_id' => $document->id,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // Only reclaimed once nothing unreferenced is left behind in it —
        // an unreferenced file's directory must survive so it stays where
        // the deferred-cleanup gap expects to find it.
        if ($disk->allFiles($sourceDirectory) === []) {
            $disk->deleteDirectory($sourceDirectory);
        }

        return str_replace(
            self::DRAFT_IMAGE_SRC_PREFIX.$draftToken.'/',
            "/documents/{$document->id}/images/",
            $contentHtml,
        );
    }

    /**
     * Every draft image filename (`{uuid}.ext`) still referenced by an
     * `<img src>` in the sanitized `$contentHtml` for this exact draft
     * token — the allowlist `relocateDraftImages()` moves against, so a
     * file uploaded and then removed from the document before saving is
     * never relocated. Safe to match with a plain prefixed regex because
     * `isAllowedDraftImageSrc()` already guaranteed every remaining `<img
     * src>` shares this precise prefix/filename shape.
     *
     * @return list<string>
     */
    private function referencedDraftImageFilenames(string $contentHtml, string $draftToken): array
    {
        $pattern = '#'.preg_quote(self::DRAFT_IMAGE_SRC_PREFIX.$draftToken.'/', '#').'('.self::DRAFT_IMAGE_FILENAME_PATTERN.')#';

        preg_match_all($pattern, $contentHtml, $matches);

        return $matches[1];
    }

    /**
     * Mirrors the I/O matrix (spec-2-1): tag-only content (an empty table
     * or list with no text) strips down to an empty string via
     * `strip_tags()`/`trim()`, normalized to null rather than persisted as
     * `''` — consistent with how a failed extraction already represents
     * "no text" as null for imported documents. A space is inserted at
     * every tag boundary first so adjacent blocks (`</h1><p>`) don't fuse
     * into one word once the tags themselves are stripped — an `<img>` is
     * no exception, it strips down to that same single space (it carries
     * no inner text; its `alt` is not indexed).
     */
    private function deriveExtractedText(string $contentHtml): ?string
    {
        $spaced = preg_replace('/</', ' <', $contentHtml);
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($spaced)));

        return $text === '' ? null : $text;
    }
}
