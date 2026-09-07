<?php

namespace App\DataTransferObjects;

use App\Models\Document;

final readonly class UpdateDocumentData
{
    public function __construct(
        public Document $document,
        public string $title,
        public string $contentHtml,
        // Non-null means "reassign to this category"; null means "clear
        // back to Non classé" — both are meaningful, distinct requests
        // (Boundaries & Constraints, spec-2-3), unlike CreateDocumentData
        // where only a non-null value ever triggers CategorizeDocumentAction.
        public ?int $categoryId = null,
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
