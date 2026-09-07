<?php

namespace App\Actions;

use App\DataTransferObjects\CreateDocumentData;
use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Models\Document;

/**
 * Sole write point for documents authored directly in the editor (Boundaries
 * & Constraints, spec-2-1). Unlike ImportDocumentAction there is no file to
 * store on disk and no deferred extraction job to dispatch: `content_html`
 * is the document's content, so `extracted_text` is derived from it
 * synchronously, right here, and marked complete immediately (AD-9) —
 * never left pending/null the way an imported document's can be while its
 * background job runs (or fails).
 *
 * `file_path`/`mime_type` are deliberately left null — a created document
 * has no original file, its content lives in `content_html` instead.
 *
 * A single `Document::create()` call is inherently atomic; no transaction
 * is needed here the way ImportDocumentAction needs one for its two
 * separate writes (row + file).
 */
class CreateDocumentAction
{
    /**
     * Tags the editor's toolbar (FR8: headings, lists, tables) can ever
     * produce. Anything else arriving in `content_html` — a `<script>`,
     * an `onerror=` attribute, a raw API call bypassing the editor
     * entirely — is stripped in `sanitizeContentHtml()` before the
     * document is persisted, since it is later rendered unescaped
     * (`v-html`) on the document's detail page.
     */
    private const ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'p', 'ul', 'ol', 'li', 'strong', 'em', 's',
        'code', 'pre', 'blockquote', 'hr', 'br',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    public function __invoke(CreateDocumentData $data): Document
    {
        $contentHtml = $this->sanitizeContentHtml($data->contentHtml);

        return Document::create([
            'title' => $data->title,
            'source' => DocumentSource::Created,
            'content_html' => $contentHtml,
            'extracted_text' => $this->deriveExtractedText($contentHtml),
            'extraction_status' => ExtractionStatus::Completed,
        ]);
    }

    /**
     * Rebuilds `content_html` keeping only the allowed tags, stripped of
     * every attribute — none of the toolbar's own output ever carries one
     * (no classes/styles/ids), so this closes off attribute-based
     * injection without touching legitimate content.
     */
    private function sanitizeContentHtml(string $html): string
    {
        $document = new \DOMDocument();
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

        $this->sanitizeNode($root);

        $sanitized = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $sanitized .= $document->saveHTML($child);
        }

        return $sanitized;
    }

    private function sanitizeNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if (! $child instanceof \DOMElement || ! in_array($child->tagName, self::ALLOWED_TAGS, true)) {
                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                $child->removeAttribute($attribute->name);
            }

            $this->sanitizeNode($child);
        }
    }

    /**
     * Mirrors the I/O matrix (spec-2-1): tag-only content (an empty table
     * or list with no text) strips down to an empty string via
     * `strip_tags()`/`trim()`, normalized to null rather than persisted as
     * `''` — consistent with how a failed extraction already represents
     * "no text" as null for imported documents. A space is inserted at
     * every tag boundary first so adjacent blocks (`</h1><p>`) don't fuse
     * into one word once the tags themselves are stripped.
     */
    private function deriveExtractedText(string $contentHtml): ?string
    {
        $spaced = preg_replace('/</', ' <', $contentHtml);
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($spaced)));

        return $text === '' ? null : $text;
    }
}
