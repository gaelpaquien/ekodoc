<?php

namespace App\Actions\Concerns;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Shared `content_html` sanitization/derivation/relocation logic behind both
 * CreateDocumentAction (spec-2-1/spec-2-2) and UpdateDocumentAction
 * (spec-2-3) — extracted verbatim from CreateDocumentAction so neither the
 * allowed-tag list, the `img`-specific `src` allowlisting, the
 * extracted-text derivation, nor the draft-image relocation step is ever
 * duplicated or drifts between the two call sites (Code Map, spec-2-3).
 *
 * The one axis that legitimately differs between "creating a document" and
 * "editing one" is *which* `src` prefixes an `<img>` is allowed to carry:
 * a brand-new draft only ever has one (its own tmp directory), while an
 * edit in progress must also keep recognizing the document's own
 * already-saved images (Boundaries & Constraints, spec-2-3) — so every
 * sanitizing method here takes that allowlist as a parameter instead of
 * assuming a single draft token.
 */
trait SanitizesDocumentContent
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
     * upload flow while an image's document is still a draft (whether that
     * draft is a brand-new document or an existing one being edited) — see
     * `serveDraftImage()`/`documents.editorImages.tmp` in
     * DocumentController. Mirrored in the route's own `{token}`/`{filename}`
     * constraints (routes/web.php). Only ever read via `self::` from
     * CreateDocumentAction/UpdateDocumentAction (both use this trait), never
     * from outside it.
     */
    private const DRAFT_IMAGE_SRC_PREFIX = '/documents/editor-images/tmp/';

    private const DRAFT_IMAGE_FILENAME_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\.[a-zA-Z0-9]+';

    /**
     * Rebuilds `content_html` keeping only the allowed tags. Every allowed
     * tag but `img` is stripped of every attribute — none of the toolbar's
     * own output ever carries one (no classes/styles/ids) — closing off
     * attribute-based injection without touching legitimate content. `img`
     * keeps `src`/`alt` only, and only when `src` matches one of
     * `$allowedImageSrcPrefixes` (`isAllowedImageSrc()`) — any other `src`
     * (an external URL, another draft's token, another document's
     * directory, a `data:`/base64 URI, `javascript:`, ...) gets the whole
     * tag dropped, same as any other non-conforming content (I/O matrix,
     * spec-2-2/spec-2-3: forged `content_html`).
     *
     * @param  list<string>  $allowedImageSrcPrefixes
     */
    private function sanitizeContentHtml(string $html, array $allowedImageSrcPrefixes): string
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

        $this->sanitizeNode($root, $allowedImageSrcPrefixes);

        $sanitized = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $sanitized .= $document->saveHTML($child);
        }

        return $sanitized;
    }

    /**
     * @param  list<string>  $allowedImageSrcPrefixes
     */
    private function sanitizeNode(\DOMNode $node, array $allowedImageSrcPrefixes): void
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
                if (! $this->isAllowedImageSrc($child->getAttribute('src'), $allowedImageSrcPrefixes)) {
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

            $this->sanitizeNode($child, $allowedImageSrcPrefixes);
        }
    }

    /**
     * True only when `$src` matches `{prefix}{uuid}.ext` for one of the
     * given `$allowedImageSrcPrefixes` exactly — never a merely-similar
     * shape. CreateDocumentAction passes a single prefix (this draft's own
     * tmp directory, or none at all without a draft token);
     * UpdateDocumentAction passes up to two (the document's own final
     * directory, plus this edit session's tmp directory) — see Boundaries &
     * Constraints, spec-2-3. An empty `$allowedImageSrcPrefixes` (no draft
     * token and, for an update, somehow no document directory either)
     * rejects every `img` outright.
     *
     * @param  list<string>  $allowedImageSrcPrefixes
     */
    private function isAllowedImageSrc(string $src, array $allowedImageSrcPrefixes): bool
    {
        if ($src === '') {
            return false;
        }

        foreach ($allowedImageSrcPrefixes as $prefix) {
            $pattern = '#^'.preg_quote($prefix, '#').self::DRAFT_IMAGE_FILENAME_PATTERN.'$#';

            if (preg_match($pattern, $src) === 1) {
                return true;
            }
        }

        return false;
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
     * because `isAllowedImageSrc()` already guaranteed every remaining
     * `<img src>` pointing at this draft token's tmp directory shares that
     * exact prefix (Design Notes, spec-2-2).
     *
     * Reused unchanged by UpdateDocumentAction: `$document` there already
     * has an `id` (and, potentially, pre-existing images under its final
     * directory), so this simply adds this edit session's newly relocated
     * files alongside them — filenames are UUIDs, so no collision is
     * possible (Design Notes, spec-2-3).
     *
     * A file uploaded into the draft and then removed from the document
     * before "Enregistrer" (e.g. inserted, then deleted) is deliberately
     * left behind in `tmp/{token}` rather than moved into the document's
     * permanent home — the already-accepted deferred-cleanup gap for
     * abandoned draft directories covers it, same as a draft that's never
     * saved at all.
     *
     * A move failure cleans up only the files this call itself already
     * relocated into the destination directory before rethrowing so the
     * enclosing transaction rolls back — never the whole destination
     * directory, which (unlike a brand-new document's) can already hold the
     * document's own previously-saved images by the time an edit relocates
     * a new one (Boundaries & Constraints, spec-2-3: existing images must
     * survive a resave). Per Boundaries & Constraints ("Never"), the source
     * `tmp/{token}` directory is deliberately left as-is on failure —
     * cleaning up abandoned draft directories is out of scope for this
     * story.
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
        $movedDestinationPaths = [];

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

                $movedDestinationPaths[] = $destinationPath;
            }
        } catch (Throwable $exception) {
            foreach ($movedDestinationPaths as $movedDestinationPath) {
                $disk->delete($movedDestinationPath);
            }

            Log::error('Document save failed while relocating draft editor images to their final directory.', [
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
     * `isAllowedImageSrc()` already guaranteed every remaining `<img src>`
     * pointing at this draft token's tmp directory shares this precise
     * prefix/filename shape.
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
