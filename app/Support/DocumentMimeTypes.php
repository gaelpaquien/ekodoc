<?php

namespace App\Support;

/**
 * Single source of truth for the mime-type/format/type mapping previously
 * duplicated across `DocumentController::TYPE_MIME_MAP`/`previewFormat()`
 * and `ExtractDocumentTextJob::formatFromMimeType()` (Epic 1/2
 * retrospectives, action item 3). `created` is deliberately absent from
 * every table here: it filters on `source`, not a mime type, and stays
 * handled separately by each consumer (Design Notes, spec-corrections-
 * post-retrospective).
 *
 * Kept as constants + a static method rather than a PHP enum: `TYPE_TO_MIME`/
 * `TYPE_LABELS` are associative arrays (short key => value), not naturally
 * representable by an `enum:string` without losing the double lookup table.
 */
final class DocumentMimeTypes
{
    /**
     * Detected mime type => internal format key, used to route text
     * extraction and preview conversion.
     */
    public const MIME_TO_FORMAT = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    /**
     * `type[]`-filter value => matching `mime_type`, consumed by the
     * type-filter parsing/application in `DocumentController::applyFilters()`.
     */
    public const TYPE_TO_MIME = [
        'pdf' => 'application/pdf',
        'word' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * `type[]`-filter value => human-readable label, shared with the Vue
     * client through the `documentTypeOptions` Inertia prop.
     */
    public const TYPE_LABELS = [
        'pdf' => 'PDF',
        'word' => 'Word',
        'excel' => 'Excel',
    ];

    /**
     * Mirrors the previous `DocumentController::previewFormat()`/
     * `ExtractDocumentTextJob::formatFromMimeType()` bodies exactly (same
     * keys, same values, same `null` cases) — the same mime routing now
     * drives both text extraction and preview conversion from one place.
     */
    public static function formatFromMime(?string $mimeType): ?string
    {
        // `$mimeType === null` short-circuits before the array lookup —
        // `self::MIME_TO_FORMAT[$mimeType] ?? null` alone would still
        // evaluate the array access first, triggering a "null used as
        // array offset" deprecation notice on every null-mime-type
        // document, unlike the original `match()` bodies this mirrors.
        return $mimeType === null ? null : (self::MIME_TO_FORMAT[$mimeType] ?? null);
    }

    /**
     * Static-only utility (constants + one static method) — never meant to
     * be instantiated or extended.
     */
    private function __construct()
    {
    }
}
