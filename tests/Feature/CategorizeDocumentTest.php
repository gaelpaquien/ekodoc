<?php

use App\Models\Category;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function categorizeFixtureContents(string $name): string
{
    return file_get_contents(__DIR__.'/../Fixtures/'.$name);
}

function importCategorizeDocument(array $payload = []): Document
{
    $file = UploadedFile::fake()->createWithContent('contract.pdf', categorizeFixtureContents('sample.pdf'));

    test()->post('/documents', array_merge(['file' => $file], $payload));

    return Document::sole();
}

// --- Import sans catégorie ---------------------------------------------

it('imports a document with no category, leaving category_id null and shown as Uncategorized', function () {
    $document = importCategorizeDocument();

    expect($document->category_id)->toBeNull();

    $response = test()->get("/documents/{$document->id}");

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Show')
        ->where('document.category_id', null)
        ->where('document.category', null)
    );
});

// --- Import avec catégorie existante ------------------------------------

it('imports a document and assigns it to the chosen existing category', function () {
    $category = Category::factory()->create(['name' => 'Contrats']);

    $document = importCategorizeDocument(['category_id' => $category->id]);

    expect($document->fresh()->category_id)->toBe($category->id);

    $response = test()->get("/documents/{$document->id}");

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Show')
        ->where('document.category_id', $category->id)
        ->where('document.category.name', 'Contrats')
    );
});

it('rejects an import with an invalid category id and creates no document', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', categorizeFixtureContents('sample.pdf'));

    $response = test()->post('/documents', ['file' => $file, 'category_id' => 999999]);

    $response->assertSessionHasErrors('category_id');
    expect(Document::count())->toBe(0);
});

// --- Réassignation --------------------------------------------------------

it('reassigns a document to a different category via PATCH', function () {
    $original = Category::factory()->create();
    $newCategory = Category::factory()->create();
    $document = Document::factory()->create(['category_id' => $original->id]);

    $response = test()->patch("/documents/{$document->id}/category", ['category_id' => $newCategory->id]);

    $response->assertRedirect();
    expect($document->fresh()->category_id)->toBe($newCategory->id);
});

it('rejects a reassignment with an invalid category id, leaving the document unchanged', function () {
    $category = Category::factory()->create();
    $document = Document::factory()->create(['category_id' => $category->id]);

    $response = test()->patch("/documents/{$document->id}/category", ['category_id' => 999999]);

    $response->assertSessionHasErrors('category_id');
    expect($document->fresh()->category_id)->toBe($category->id);
});

// --- Retour à "Non classé" -------------------------------------------------

it('clears a document back to Uncategorized when category_id is set to null', function () {
    $category = Category::factory()->create();
    $document = Document::factory()->create(['category_id' => $category->id]);

    $response = test()->patch("/documents/{$document->id}/category", ['category_id' => null]);

    $response->assertRedirect();
    expect($document->fresh()->category_id)->toBeNull();
});

it('does not block any other action when clearing a category', function () {
    $category = Category::factory()->create();
    $document = Document::factory()->create(['category_id' => $category->id]);

    test()->patch("/documents/{$document->id}/category", ['category_id' => null]);

    // The document itself remains fully reachable/functional afterwards.
    $response = test()->get("/documents/{$document->id}");
    $response->assertOk();
});

// --- Création catégorie ----------------------------------------------------

it('creates a category, immediately available in the shared categories prop', function () {
    $response = test()->post('/categories', ['name' => 'Factures']);

    $response->assertRedirect();
    expect(Category::where('name', 'Factures')->exists())->toBeTrue();

    $indexResponse = test()->get('/');

    $indexResponse->assertInertia(fn ($page) => $page
        ->has('categories', 1)
        ->where('categories.0.name', 'Factures')
    );
});

it('rejects a duplicate category name case-insensitively and creates no duplicate', function () {
    Category::factory()->create(['name' => 'Contrats']);

    $response = test()->post('/categories', ['name' => 'CONTRATS']);

    $response->assertSessionHasErrors('name');
    expect(Category::where('name', 'CONTRATS')->count())->toBe(0);
    expect(Category::count())->toBe(1);
});

it('rejects an empty category name', function () {
    $response = test()->post('/categories', ['name' => '']);

    $response->assertSessionHasErrors('name');
    expect(Category::count())->toBe(0);
});

it('trims a category name before storing it', function () {
    test()->post('/categories', ['name' => '  Factures  ']);

    expect(Category::where('name', 'Factures')->exists())->toBeTrue();
});

// --- Sole write point (AD-16) ----------------------------------------------

it('never sets category_id on import besides through the deliberate second-step Action call', function () {
    // No category chosen at import: the created document's category_id
    // must remain null — nothing in ImportDocumentAction is allowed to
    // touch it, matching the Boundaries & Constraints in spec-1-5.
    $document = importCategorizeDocument();

    expect($document->category_id)->toBeNull();
});

it('exposes categories in the shared Inertia prop on the library index', function () {
    Category::factory()->create(['name' => 'Zèbres']);
    Category::factory()->create(['name' => 'Alphas']);

    $response = test()->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('categories', 2)
        ->where('categories.0.name', 'Alphas')
        ->where('categories.1.name', 'Zèbres')
    );
});

it('shows the assigned category on the library card', function () {
    $category = Category::factory()->create(['name' => 'Contrats']);
    $document = Document::factory()->create(['category_id' => $category->id]);

    $response = test()->get('/');

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('documents.0.id', $document->id)
        ->where('documents.0.category.name', 'Contrats')
    );
});
