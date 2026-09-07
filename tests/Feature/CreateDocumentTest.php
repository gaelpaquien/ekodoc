<?php

use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Models\Category;
use App\Models\Document;

function createDocumentPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Compte rendu réunion',
        'content_html' => '<h1>Compte rendu</h1><p>Décisions prises en réunion.</p>',
    ], $overrides);
}

// --- Enregistrement, catégorie déjà choisie ---------------------------------

it('creates a document with the chosen category, derives extracted_text, and redirects to the document page', function () {
    $category = Category::factory()->create(['name' => 'Comptes rendus']);

    $response = test()->post('/documents/create', createDocumentPayload(['category_id' => $category->id]));

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->source)->toBe(DocumentSource::Created);
    expect($document->title)->toBe('Compte rendu réunion');
    expect($document->content_html)->toBe('<h1>Compte rendu</h1><p>Décisions prises en réunion.</p>');
    expect($document->extracted_text)->toBe('Compte rendu Décisions prises en réunion.');
    expect($document->extraction_status)->toBe(ExtractionStatus::Completed);
    expect($document->file_path)->toBeNull();
    expect($document->mime_type)->toBeNull();
    expect($document->category_id)->toBe($category->id);
});

// --- Enregistrement, catégorie non renseignée -------------------------------

it('creates a document with no category chosen, leaving category_id null (Non classé)', function () {
    $response = test()->post('/documents/create', createDocumentPayload());

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    // CreateDocumentAction itself must never touch category_id —
    // CategorizeDocumentAction remains the sole write point (AD-16), same
    // guarantee already enforced for ImportDocumentAction.
    expect($document->category_id)->toBeNull();
});

// --- Titre vide --------------------------------------------------------------

it('rejects an empty title, creates no document, and surfaces the error under the title field', function () {
    $response = test()->post('/documents/create', createDocumentPayload(['title' => '']));

    $response->assertSessionHasErrors('title');
    expect(Document::count())->toBe(0);
});

it('rejects a request with no content_html and creates no document', function () {
    $response = test()->post('/documents/create', createDocumentPayload(['content_html' => '']));

    $response->assertSessionHasErrors('content_html');
    expect(Document::count())->toBe(0);
});

it('rejects an invalid category id and creates no document', function () {
    $response = test()->post('/documents/create', createDocumentPayload(['category_id' => 999999]));

    $response->assertSessionHasErrors('category_id');
    expect(Document::count())->toBe(0);
});

// --- Contenu HTML uniquement des balises (aucun texte) ----------------------

it('creates a document whose content is tag-only with no text, storing a null extracted_text', function () {
    $response = test()->post('/documents/create', createDocumentPayload([
        'content_html' => '<table><tbody><tr><td></td><td></td></tr></tbody></table><ul><li></li></ul>',
    ]));

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->extracted_text)->toBeNull();
    expect($document->extraction_status)->toBe(ExtractionStatus::Completed);
});

// --- Document créé retrouvable -----------------------------------------------

it('renders the created document on its detail page with content_html and source created', function () {
    test()->post('/documents/create', createDocumentPayload());

    $document = Document::sole();

    $response = test()->get("/documents/{$document->id}");

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Show')
        ->where('document.id', $document->id)
        ->where('document.source', 'created')
        ->where('document.content_html', $document->content_html)
    );
});

it('lists the created document on the library index page', function () {
    test()->post('/documents/create', createDocumentPayload(['title' => 'Notes de projet']));

    $document = Document::sole();

    $response = test()->get('/');

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
        ->where('documents.0.title', 'Notes de projet')
    );
});

it('is immediately filterable via type=created alongside imported documents (Story 1.7)', function () {
    test()->post('/documents/create', createDocumentPayload());
    Document::factory()->create(); // an imported document, excluded by the filter

    $response = test()->get('/?type[]=created');

    $response->assertInertia(fn ($page) => $page
        ->has('documents', 1)
        ->where('documents.0.source', 'created')
    );
});

it('is immediately searchable via its derived extracted_text (Story 1.6)', function () {
    test()->post('/documents/create', createDocumentPayload([
        'content_html' => '<p>Compte rendu du budget trimestriel</p>',
    ]));

    $document = Document::sole();

    $response = test()->get('/?search=trimestriel');

    $response->assertInertia(fn ($page) => $page
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
    );
});

// --- Sanitization -------------------------------------------------------------

it('strips disallowed tags and all attributes from content_html before storing it', function () {
    test()->post('/documents/create', createDocumentPayload([
        'content_html' => '<script>alert(1)</script><p onclick="evil()" class="x">Texte</p><img src=x onerror=alert(2)>',
    ]));

    $document = Document::sole();

    expect($document->content_html)->toBe('<p>Texte</p>');
    expect($document->extracted_text)->toBe('Texte');
});

// --- Éditeur -------------------------------------------------------------------

it('renders the empty editor page at GET /documents/create', function () {
    $response = test()->get('/documents/create');

    $response->assertInertia(fn ($page) => $page->component('Documents/Editor'));
});
