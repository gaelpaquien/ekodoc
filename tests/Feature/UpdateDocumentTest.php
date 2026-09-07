<?php

use App\Models\Category;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creates a `source=created` document the same way a real editor session
 * would — through the Story 2.1/2.2 create flow — so every test here
 * exercises UpdateDocumentAction against a document shaped exactly like the
 * ones it will actually run against in production.
 */
function createDocumentThroughEditor(array $overrides = []): Document
{
    $response = test()->post('/documents/create', array_merge([
        'title' => 'Compte rendu réunion',
        'content_html' => '<h1>Compte rendu</h1><p>Décisions prises en réunion.</p>',
    ], $overrides));

    // `Document::sole()` would break once a test creates more than one
    // document (e.g. to exercise cross-document forgery) — the redirect
    // target is this specific document regardless of how many exist.
    return Document::findOrFail(Str::afterLast($response->headers->get('Location'), '/'));
}

/**
 * Defaults every field to the document's own current value so each test
 * only needs to override the one field it's actually exercising — mirrors
 * how Editor.vue's form always submits title/content_html/category_id
 * together regardless of which one the user actually changed.
 */
function updateDocumentPayload(Document $document, array $overrides = []): array
{
    return array_merge([
        'title' => $document->title,
        'content_html' => $document->content_html,
        'category_id' => $document->category_id,
    ], $overrides);
}

/**
 * Uploads one draft image the same way the editor does, and returns its
 * flashed url/alt/filename — the same payload the editor's flash watch
 * would insert into `content_html` before "Enregistrer" is ever clicked.
 * Named distinctly from CreateDocumentTest's own identical helper — Pest
 * loads every test file's top-level functions into one global namespace,
 * so two files can't declare the same helper name — but it drives the same
 * upload endpoint (Boundaries & Constraints, spec-2-3: no dedicated
 * edit-mode upload route exists).
 */
function uploadDraftImageForUpdate(string $draftToken): array
{
    test()->post('/documents/create/images', [
        'draft_token' => $draftToken,
        'image' => UploadedFile::fake()->image('photo.jpg', 200, 200),
        'alt' => 'Photo de test',
    ]);

    return session('uploadedImage');
}

// --- Ouverture de l'édition --------------------------------------------------

it('opens the editor with content_html/title/category pre-loaded for a created document', function () {
    $category = Category::factory()->create(['name' => 'Comptes rendus']);
    $document = createDocumentThroughEditor(['category_id' => $category->id]);

    $response = test()->get("/documents/{$document->id}/edit");

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Editor')
        ->where('document.id', $document->id)
        ->where('document.title', $document->title)
        ->where('document.content_html', $document->content_html)
        ->where('document.category_id', $category->id)
    );
});

it('refuses to open the editor for an imported document', function () {
    $document = Document::factory()->create();

    test()->get("/documents/{$document->id}/edit")->assertForbidden();
});

// --- Enregistrement sans changement de catégorie -----------------------------

it('updates content_html and re-derives extracted_text without touching an unchanged category', function () {
    $category = Category::factory()->create();
    $document = createDocumentThroughEditor(['category_id' => $category->id]);

    $response = test()->patch("/documents/{$document->id}", updateDocumentPayload($document, [
        'content_html' => '<p>Texte modifié</p>',
        'category_id' => $category->id,
    ]));

    $response->assertRedirect("/documents/{$document->id}");
    $document->refresh();
    expect($document->content_html)->toBe('<p>Texte modifié</p>');
    expect($document->extracted_text)->toBe('Texte modifié');
    expect($document->category_id)->toBe($category->id);
});

it('rejects an empty title on update, persisting no change', function () {
    $document = createDocumentThroughEditor();

    $response = test()->patch("/documents/{$document->id}", updateDocumentPayload($document, [
        'title' => '',
    ]));

    $response->assertSessionHasErrors('title');
    expect($document->fresh()->title)->toBe($document->title);
});

// --- Enregistrement avec changement de catégorie (y compris vers "Non classé") ---

it('reassigns the category via CategorizeDocumentAction when the submitted value differs', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $document = createDocumentThroughEditor(['category_id' => $categoryA->id]);

    $response = test()->patch("/documents/{$document->id}", updateDocumentPayload($document, [
        'category_id' => $categoryB->id,
    ]));

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->fresh()->category_id)->toBe($categoryB->id);
});

it('clears the category back to Non classé when the submitted value is null, unlike creation', function () {
    $category = Category::factory()->create();
    $document = createDocumentThroughEditor(['category_id' => $category->id]);

    $response = test()->patch("/documents/{$document->id}", updateDocumentPayload($document, [
        'category_id' => null,
    ]));

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->fresh()->category_id)->toBeNull();
});

// --- Image insérée pendant l'édition -----------------------------------------

it('relocates a new draft image inserted during edition and rewrites content_html', function () {
    Storage::fake('local');

    $document = createDocumentThroughEditor();
    $draftToken = Str::uuid()->toString();
    $uploadedImage = uploadDraftImageForUpdate($draftToken);

    $response = test()->patch("/documents/{$document->id}", updateDocumentPayload($document, [
        'content_html' => "<p>Voici le schéma :</p><img src=\"{$uploadedImage['url']}\" alt=\"{$uploadedImage['alt']}\">",
        'draft_token' => $draftToken,
    ]));

    $response->assertRedirect("/documents/{$document->id}");
    $document->refresh();

    $finalSrc = "/documents/{$document->id}/images/{$uploadedImage['filename']}";
    expect($document->content_html)->toContain("<img src=\"{$finalSrc}\" alt=\"{$uploadedImage['alt']}\">");

    Storage::disk('local')->assertExists("documents/{$document->id}/images/{$uploadedImage['filename']}");
    Storage::disk('local')->assertMissing("documents/tmp/{$draftToken}/images/{$uploadedImage['filename']}");

    test()->get($finalSrc)->assertOk();
});

it('keeps a previously saved image intact across a resave instead of treating it as forged', function () {
    Storage::fake('local');

    $draftToken = Str::uuid()->toString();
    $uploadedImage = uploadDraftImageForUpdate($draftToken);
    $document = createDocumentThroughEditor([
        'content_html' => "<p>Schéma :</p><img src=\"{$uploadedImage['url']}\" alt=\"{$uploadedImage['alt']}\">",
        'draft_token' => $draftToken,
    ]);

    $existingSrc = "/documents/{$document->id}/images/{$uploadedImage['filename']}";
    expect($document->content_html)->toContain($existingSrc);

    $response = test()->patch("/documents/{$document->id}", updateDocumentPayload($document, [
        'content_html' => "<p>Texte modifié</p><img src=\"{$existingSrc}\" alt=\"{$uploadedImage['alt']}\">",
    ]));

    $response->assertRedirect("/documents/{$document->id}");
    $document->refresh();
    expect($document->content_html)->toContain($existingSrc);
    Storage::disk('local')->assertExists("documents/{$document->id}/images/{$uploadedImage['filename']}");
});

// --- content_html forgé référençant l'image d'un autre document -------------

it('drops an <img> tag whose src points at a different document\'s image directory (forged content_html)', function () {
    Storage::fake('local');

    $draftToken = Str::uuid()->toString();
    $uploadedImage = uploadDraftImageForUpdate($draftToken);
    $otherDocument = createDocumentThroughEditor([
        'content_html' => "<p>Schéma :</p><img src=\"{$uploadedImage['url']}\" alt=\"{$uploadedImage['alt']}\">",
        'draft_token' => $draftToken,
    ]);

    $document = createDocumentThroughEditor();
    $forgedSrc = "/documents/{$otherDocument->id}/images/{$uploadedImage['filename']}";

    $response = test()->patch("/documents/{$document->id}", updateDocumentPayload($document, [
        'content_html' => "<p>Texte</p><img src=\"{$forgedSrc}\" alt=\"vol\">",
    ]));

    $response->assertRedirect("/documents/{$document->id}");
    $document->refresh();
    expect($document->content_html)->toBe('<p>Texte</p>');
    // The other document's own image is left untouched.
    Storage::disk('local')->assertExists("documents/{$otherDocument->id}/images/{$uploadedImage['filename']}");
});

// --- Documents importés -------------------------------------------------------

it('refuses to update an imported document', function () {
    $document = Document::factory()->create();

    $response = test()->patch("/documents/{$document->id}", [
        'title' => 'Nouveau titre',
        'content_html' => '<p>x</p>',
    ]);

    $response->assertForbidden();
    expect($document->fresh()->title)->not->toBe('Nouveau titre');
});
