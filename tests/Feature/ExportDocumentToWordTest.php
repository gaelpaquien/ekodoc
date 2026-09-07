<?php

use App\Actions\ExportDocumentToWordAction;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reuses createDocumentForExport()/uploadDraftImageForExport() declared in
 * ExportDocumentToPdfTest.php (Code Map, spec-2-5) — Pest loads every test
 * file's top-level functions into one global namespace (see that file's own
 * comment), so they're already available here without being redeclared.
 */
beforeEach(function () {
    Storage::fake('local');
});

// --- Export d'un document créé, sans image -----------------------------------

it('exports a created document without images as a downloadable docx', function () {
    $document = createDocumentForExport();

    $response = test()->get("/documents/{$document->id}/export/word");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $response->assertHeader('x-content-type-options', 'nosniff');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    // A .docx is a zip archive — its magic bytes are the local-file-header
    // signature "PK\x03\x04", the same check used to confirm a genuinely
    // generated PhpWord file rather than a degraded/empty one.
    expect(substr($response->getContent(), 0, 4))->toBe("PK\x03\x04");
});

// --- Export d'un document créé, avec image inline (Story 2.2) ----------------

it('exports a created document with an inline image, embedding it in the generated docx', function () {
    $draftToken = Str::uuid()->toString();
    $uploadedImage = uploadDraftImageForExport($draftToken);

    $document = createDocumentForExport([
        'content_html' => "<p>Schéma :</p><img src=\"{$uploadedImage['url']}\" alt=\"{$uploadedImage['alt']}\">",
        'draft_token' => $draftToken,
    ]);

    // Sanity check on the fixture itself: the saved document really does
    // reference its own relocated image (Story 2.2), the shape
    // ExportDocumentToWordAction resolves to a disk path before conversion.
    expect($document->content_html)->toContain("/documents/{$document->id}/images/{$uploadedImage['filename']}");

    $response = test()->get("/documents/{$document->id}/export/word");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect(substr($response->getContent(), 0, 4))->toBe("PK\x03\x04");

    // resolveImageSources() (asserted in isolation below) only proves the
    // <img src> was rewritten to a readable disk path before ever reaching
    // PhpWord — it doesn't prove PhpWord actually embedded that image into
    // the produced archive. A .docx is a zip: unzip the actual response
    // body and confirm a media entry exists under word/media/, which is
    // where PhpWord's Word2007 writer places every embedded image.
    $docxPath = tempnam(sys_get_temp_dir(), 'ekodoc_word_test_');
    file_put_contents($docxPath, $response->getContent());

    try {
        $zip = new ZipArchive();
        expect($zip->open($docxPath))->toBe(true);

        $hasEmbeddedMedia = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'word/media/')) {
                $hasEmbeddedMedia = true;

                break;
            }
        }

        $zip->close();

        expect($hasEmbeddedMedia)->toBeTrue();
    } finally {
        unlink($docxPath);
    }
});

// --- resolveImageSources() : le src devient bien un chemin disque absolu -----

it('resolves an inline image reference to an absolute disk path carrying the stored file', function () {
    $draftToken = Str::uuid()->toString();
    $uploadedImage = uploadDraftImageForExport($draftToken);

    $document = createDocumentForExport([
        'content_html' => "<p>Schéma :</p><img src=\"{$uploadedImage['url']}\" alt=\"{$uploadedImage['alt']}\">",
        'draft_token' => $draftToken,
    ]);

    $storedPath = "documents/{$document->id}/images/{$uploadedImage['filename']}";
    $originalSrc = "/documents/{$document->id}/images/{$uploadedImage['filename']}";

    // Fixture sanity: the saved document really does reference its own
    // relocated image (Story 2.2) before resolveImageSources() ever runs.
    expect($document->content_html)->toContain('src="'.$originalSrc.'"');

    $resolved = (new ExportDocumentToWordAction)->resolveImageSources($document->content_html, $document->id);

    $expectedAbsolutePath = str_replace('\\', '/', Storage::disk('local')->path($storedPath));

    expect($resolved)->toContain('src="'.$expectedAbsolutePath.'"');
    // The original app-relative src must not survive — PhpWord's
    // Html::addHtml() runs outside any HTTP request context, so it would
    // never resolve (Boundaries & Constraints, spec-2-5).
    expect($resolved)->not->toContain('src="'.$originalSrc.'"');
    expect($resolved)->toContain('alt="'.$uploadedImage['alt'].'"');
});

// --- Image référencée mais absente du disque ----------------------------------

it('drops an <img> tag entirely — rather than leave a broken src — when its file can no longer be found on disk', function () {
    $document = createDocumentForExport();
    $missingFilename = Str::uuid()->toString().'.jpg';
    $missingSrc = "/documents/{$document->id}/images/{$missingFilename}";
    $contentHtml = "<p>Avant</p><img src=\"{$missingSrc}\" alt=\"disparue\"><p>Après</p>";

    $resolved = (new ExportDocumentToWordAction)->resolveImageSources($contentHtml, $document->id);

    // Left untouched (as ExportDocumentToPdfAction does) would carry an
    // unresolved src into PhpWord's Html::addHtml(), which throws when an
    // <img src> doesn't resolve to a readable file — failing the whole
    // export over one missing image (Boundaries & Constraints, spec-2-5).
    // Dropping the tag instead keeps the rest of the content intact.
    expect($resolved)->not->toContain('<img');
    expect($resolved)->not->toContain($missingSrc);
    expect($resolved)->toContain('<p>Avant</p>');
    expect($resolved)->toContain('<p>Après</p>');
});

it('exports a created document referencing a missing image without blocking the whole export', function () {
    // A forged "/documents/{id}/images/..." src could never survive
    // creation's own sanitizer (only a draft-token src is allowed at
    // creation time) — so the missing-file scenario is reproduced the way
    // it can actually occur in production: a legitimately relocated image
    // (Story 2.2) whose file is later removed from disk out from under the
    // still-referencing document.
    $draftToken = Str::uuid()->toString();
    $uploadedImage = uploadDraftImageForExport($draftToken);

    $document = createDocumentForExport([
        'content_html' => "<p>Schéma :</p><img src=\"{$uploadedImage['url']}\" alt=\"{$uploadedImage['alt']}\">",
        'draft_token' => $draftToken,
    ]);

    Storage::disk('local')->delete("documents/{$document->id}/images/{$uploadedImage['filename']}");

    $response = test()->get("/documents/{$document->id}/export/word");

    $response->assertOk();
    expect(substr($response->getContent(), 0, 4))->toBe("PK\x03\x04");
});

// --- Export d'un document importé ---------------------------------------------

it('refuses to export an imported document', function () {
    $document = Document::factory()->create();

    test()->get("/documents/{$document->id}/export/word")->assertForbidden();
});

// --- Échec de conversion PhpWord -------------------------------------------

it('reports a PhpWord conversion failure as a 422 with an explicit message, delivering no file', function () {
    // Forces a genuine PhpWord failure — not a faked engine (AD-12,
    // mirroring AD-11's "no faking the engine itself" for the PDF export):
    // the uploaded "image" file on disk is overwritten with bytes that
    // aren't a valid image, so PhpWord's own Image element throws
    // InvalidImageException while trying to read its dimensions
    // (getimagesize()) during Html::addHtml().
    $draftToken = Str::uuid()->toString();
    $uploadedImage = uploadDraftImageForExport($draftToken);

    $document = createDocumentForExport([
        'content_html' => "<p>Schéma :</p><img src=\"{$uploadedImage['url']}\" alt=\"{$uploadedImage['alt']}\">",
        'draft_token' => $draftToken,
    ]);

    $storedPath = "documents/{$document->id}/images/{$uploadedImage['filename']}";
    Storage::disk('local')->put($storedPath, 'this is not a valid image file');

    $response = test()->get("/documents/{$document->id}/export/word");

    expect($response->status())->toBe(422);
    // Precisely "no file delivered" (a magic-byte check, not a substring
    // search) — a substring search over Laravel's own debug error page
    // would self-match on this very assertion's own source once inlined
    // into the page's stack-trace viewer.
    expect(substr($response->getContent(), 0, 4))->not->toBe("PK\x03\x04");
    // The debug error page HTML-escapes the apostrophe in the message
    // ("l'instant" -> "l&#039;instant"), so match on the unescaped part of
    // the explicit message instead of the literal string.
    expect($response->getContent())->toContain('Export Word impossible pour l')
        ->toContain('instant, merci de réessayer.');
});

it('leaves the export button retryable — a second, correctly configured attempt still succeeds', function () {
    $draftToken = Str::uuid()->toString();
    $uploadedImage = uploadDraftImageForExport($draftToken);

    $document = createDocumentForExport([
        'content_html' => "<p>Schéma :</p><img src=\"{$uploadedImage['url']}\" alt=\"{$uploadedImage['alt']}\">",
        'draft_token' => $draftToken,
    ]);

    $storedPath = "documents/{$document->id}/images/{$uploadedImage['filename']}";
    $validImageBytes = Storage::disk('local')->get($storedPath);
    Storage::disk('local')->put($storedPath, 'this is not a valid image file');

    test()->get("/documents/{$document->id}/export/word")->assertStatus(422);

    Storage::disk('local')->put($storedPath, $validImageBytes);

    $retryResponse = test()->get("/documents/{$document->id}/export/word");

    $retryResponse->assertOk();
    expect(substr($retryResponse->getContent(), 0, 4))->toBe("PK\x03\x04");
});
