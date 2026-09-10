<?php

namespace App\DataTransferObjects;

use App\Models\Document;

final readonly class UpdateDocumentData
{
    /**
     * @param  array<int, int>  $tagIds
     */
    public function __construct(
        public Document $document,
        public string $title,
        public string $contentHtml,
        // Always re-synced in full, including an empty array to clear
        // every previously assigned tag (Boundaries & Constraints,
        // spec-3-1) — unlike CreateDocumentData's tags, which only ever go
        // through SyncDocumentTagsAction once, right after creation.
        public array $tagIds = [],
        // The current edit session's draft token (Design Notes, spec-2-2) —
        // Editor.vue generates and sends one on every save regardless of
        // whether an image was actually inserted, so in real traffic this
        // is effectively always set; null is only a defensive fallback for
        // a request that never went through the editor at all. Either way,
        // relocateDraftImages() is a no-op when the token's tmp directory
        // was never created (no image ever uploaded this session).
        public ?string $draftToken = null,
    ) {
    }
}
