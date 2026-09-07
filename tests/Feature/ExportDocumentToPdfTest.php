<?php

use App\Actions\ExportDocumentToPdfAction;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creates a `source=created` document through the real editor save flow
 * (Story 2.1), so every export test here exercises ExportDocumentToPdfAction
 * against a document shaped exactly like the ones it runs against in
 * production. Named distinctly from UpdateDocumentTest's/CreateDocumentTest's
 * own identical helper — Pest loads every test file's top-level functions
 * into one global namespace, so two files can't declare the same helper
 * name.
 */
function createDocumentForExport(array $overrides = []): Document
{
    $response = test()->post('/documents/create', array_merge([
        'title' => 'Compte rendu réunion',
        'content_html' => '<h1>Compte rendu</h1><p>Décisions prises en réunion.</p>',
    ], $overrides));

    return Document::findOrFail(Str::afterLast($response->headers->get('Location'), '/'));
}

/**
 * Uploads one draft image the same way the editor does, and returns its
 * flashed url/alt/filename — the same payload the editor's flash watch
 * would insert into `content_html` before "Enregistrer" is ever clicked.
 */
function uploadDraftImageForExport(string $draftToken): array
{
    test()->post('/documents/create/images', [
        'draft_token' => $draftToken,
        'image' => UploadedFile::fake()->image('photo.jpg', 200, 200),
        'alt' => 'Photo de test',
    ]);

    return session('uploadedImage');
}

beforeEach(function () {
    Storage::fake('local');
});

// --- Export d'un document créé, sans image -----------------------------------

it('exports a created document without images as a downloadable PDF', function () {
    $document = createDocumentForExport();

    $response = test()->get("/documents/{$document->id}/export/pdf");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('x-content-type-options', 'nosniff');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

// --- Export d'un document créé, avec image inline (Story 2.2) ----------------

it('exports a created document with an inline image, embedding it in the generated PDF', function () {
    $draftToken = Str::uuid()->toString();
    $uploadedImage = uploadDraftImageForExport($draftToken);

    $document = createDocumentForExport([
        'content_html' => "<p>Schéma :</p><img src=\"{$uploadedImage['url']}\" alt=\"{$uploadedImage['alt']}\">",
        'draft_token' => $draftToken,
    ]);

    // Sanity check on the fixture itself: the saved document really does
    // reference its own relocated image (Story 2.2), the shape
    // ExportDocumentToPdfAction resolves to a data URI before rendering.
    expect($document->content_html)->toContain("/documents/{$document->id}/images/{$uploadedImage['filename']}");

    $response = test()->get("/documents/{$document->id}/export/pdf");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
    // Whether the image itself was actually embedded (vs. a broken,
    // unresolved <img src> left in place) is asserted below, directly
    // against resolveImageSources() — a PDF byte-count threshold here
    // can't tell the two apart: empirically, a no-image export already
    // clears ~19KB, and even a broken/unresolved <img src> still clears
    // ~12KB, so a "> 5000 bytes" check would pass either way and never
    // catch a resolveImageSources() regression (NFR5).
});

// --- resolveImageSources() : le src devient bien une data: URI ---------------

it('resolves an inline image reference to a data: URI carrying the stored file\'s own bytes', function () {
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

    $resolved = (new ExportDocumentToPdfAction)->resolveImageSources($document->content_html, $document->id);

    $expectedDataUri = 'data:'.Storage::disk('local')->mimeType($storedPath).';base64,'
        .base64_encode(Storage::disk('local')->get($storedPath));

    expect($resolved)->toContain('src="'.$expectedDataUri.'"');
    // The original app-relative src must not survive — Browsershot's
    // Chromium runs outside any HTTP request context, so it would never
    // resolve (Boundaries & Constraints, spec-2-4).
    expect($resolved)->not->toContain('src="'.$originalSrc.'"');
});

it('leaves an <img> untouched when its file can no longer be found on disk', function () {
    $document = createDocumentForExport();
    $missingSrc = "/documents/{$document->id}/images/".Str::uuid()->toString().'.jpg';
    $contentHtml = "<p>Schéma :</p><img src=\"{$missingSrc}\" alt=\"disparue\">";

    $resolved = (new ExportDocumentToPdfAction)->resolveImageSources($contentHtml, $document->id);

    expect($resolved)->toBe($contentHtml);
});

// --- Export d'un document importé ---------------------------------------------

it('refuses to export an imported document', function () {
    $document = Document::factory()->create();

    test()->get("/documents/{$document->id}/export/pdf")->assertForbidden();
});

// --- Échec de conversion Browsershot -------------------------------------------

it('reports a Browsershot conversion failure as a 422 with an explicit message, delivering no file', function () {
    // Points Browsershot at a Chrome executable that doesn't exist, forcing
    // the real conversion attempt to fail the same way an unavailable/
    // misconfigured Chromium would in production (AD-11: no faking the
    // engine itself, only pointing it at a broken path).
    config(['services.browsershot.chrome_path' => 'C:\\nonexistent\\chrome.exe']);

    $document = createDocumentForExport();

    $response = test()->get("/documents/{$document->id}/export/pdf");

    expect($response->status())->toBe(422);
    // Precisely "no file delivered" (a magic-byte check, not a substring
    // search) — a substring search over Laravel's own debug error page
    // would self-match on this very assertion's own source once inlined
    // into the page's stack-trace viewer.
    expect(substr($response->getContent(), 0, 4))->not->toBe('%PDF');
    // The debug error page HTML-escapes the apostrophe in the message
    // ("l'instant" -> "l&#039;instant"), so match on the unescaped part of
    // the explicit message instead of the literal string.
    expect($response->getContent())->toContain('Export PDF impossible pour l')
        ->toContain('instant, merci de réessayer.');
});

it('leaves the export button retryable — a second, correctly configured attempt still succeeds', function () {
    config(['services.browsershot.chrome_path' => 'C:\\nonexistent\\chrome.exe']);
    $document = createDocumentForExport();

    test()->get("/documents/{$document->id}/export/pdf")->assertStatus(422);

    config(['services.browsershot.chrome_path' => 'C:\Program Files\Google\Chrome\Application\chrome.exe']);

    $retryResponse = test()->get("/documents/{$document->id}/export/pdf");

    $retryResponse->assertOk();
    expect(substr($retryResponse->getContent(), 0, 4))->toBe('%PDF');
});
