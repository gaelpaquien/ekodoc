<?php

use App\Support\DocumentMimeTypes;

// Single source of truth for the mime-type/format mapping (Epic 1/2
// retrospectives, action item 3) — covers formatFromMime() for the 3
// recognized mime types plus the null/unknown fallback (spec-corrections-
// post-retrospective, adversarial review item 4).

it('resolves the pdf format from its mime type', function () {
    expect(DocumentMimeTypes::formatFromMime('application/pdf'))->toBe('pdf');
});

it('resolves the docx format from its mime type', function () {
    expect(DocumentMimeTypes::formatFromMime(
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ))->toBe('docx');
});

it('resolves the xlsx format from its mime type', function () {
    expect(DocumentMimeTypes::formatFromMime(
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ))->toBe('xlsx');
});

it('returns null for an unrecognized mime type', function () {
    expect(DocumentMimeTypes::formatFromMime('text/plain'))->toBeNull();
});

it('returns null for a null mime type', function () {
    expect(DocumentMimeTypes::formatFromMime(null))->toBeNull();
});
