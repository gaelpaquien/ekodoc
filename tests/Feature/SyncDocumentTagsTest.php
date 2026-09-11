<?php

use App\Models\Document;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function syncTagsFixtureContents(string $name): string
{
    return file_get_contents(__DIR__.'/../Fixtures/'.$name);
}

function importDocumentWithTags(array $payload = []): Document
{
    $file = UploadedFile::fake()->createWithContent('contract.pdf', syncTagsFixtureContents('sample.pdf'));

    test()->post('/documents', array_merge(['file' => $file], $payload));

    return Document::sole();
}

// --- Import sans tag ----------------------------------------------------

it('imports a document with no tag, leaving it with zero tags', function () {
    $document = importDocumentWithTags();

    expect($document->tags)->toHaveCount(0);

    $response = test()->get("/documents/{$document->id}");

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Show')
        ->has('document.tags', 0)
    );
});

// --- Import avec assignation multiple ------------------------------------

it('imports a document and assigns it the three chosen existing tags', function () {
    $tags = Tag::factory()->count(3)->create();

    $document = importDocumentWithTags(['tag_ids' => $tags->pluck('id')->all()]);

    expect($document->fresh()->tags->pluck('id')->sort()->values()->all())
        ->toBe($tags->pluck('id')->sort()->values()->all());

    $response = test()->get("/documents/{$document->id}");

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Show')
        ->has('document.tags', 3)
    );
});

it('rejects an import with an invalid tag id and creates no document', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', syncTagsFixtureContents('sample.pdf'));

    $response = test()->post('/documents', ['file' => $file, 'tag_ids' => [999999]]);

    $response->assertSessionHasErrors('tag_ids.0');
    expect(Document::count())->toBe(0);
});

// --- Remplacement complet via sync() -------------------------------------

it('replaces a document\'s tags entirely via PATCH, leaving no orphaned pivot row', function () {
    $kept = Tag::factory()->create();
    $removedA = Tag::factory()->create();
    $removedB = Tag::factory()->create();
    $added = Tag::factory()->create();

    $document = Document::factory()->create();
    $document->tags()->sync([$kept->id, $removedA->id, $removedB->id]);

    $response = test()->patch("/documents/{$document->id}/tags", [
        'tag_ids' => [$kept->id, $added->id],
    ]);

    $response->assertRedirect();
    $document->refresh();

    expect($document->tags->pluck('id')->sort()->values()->all())
        ->toBe(collect([$kept->id, $added->id])->sort()->values()->all());
    expect(DB::table('document_tag')->where('document_id', $document->id)->count())->toBe(2);
});

it('rejects a PATCH with an invalid tag id, leaving the document\'s tags unchanged', function () {
    $tag = Tag::factory()->create();
    $document = Document::factory()->create();
    $document->tags()->sync([$tag->id]);

    $response = test()->patch("/documents/{$document->id}/tags", ['tag_ids' => [999999]]);

    $response->assertSessionHasErrors('tag_ids.0');
    expect($document->fresh()->tags->pluck('id')->all())->toBe([$tag->id]);
});

// --- Retour à zéro tag -----------------------------------------------------

it('clears every tag when tag_ids is submitted empty', function () {
    $tag = Tag::factory()->create();
    $document = Document::factory()->create();
    $document->tags()->sync([$tag->id]);

    $response = test()->patch("/documents/{$document->id}/tags", ['tag_ids' => []]);

    $response->assertRedirect();
    expect($document->fresh()->tags)->toHaveCount(0);
});

it('does not block any other action when clearing every tag', function () {
    $tag = Tag::factory()->create();
    $document = Document::factory()->create();
    $document->tags()->sync([$tag->id]);

    test()->patch("/documents/{$document->id}/tags", ['tag_ids' => []]);

    // The document itself remains fully reachable/functional afterwards.
    $response = test()->get("/documents/{$document->id}");
    $response->assertOk();
});

// --- Sole write point --------------------------------------------------------

it('never assigns a tag on import besides through the deliberate second-step Action call', function () {
    // No tag chosen at import: the created document must end up with zero
    // tags — nothing in ImportDocumentAction is allowed to touch
    // document_tag, matching the Boundaries & Constraints in spec-3-1.
    $document = importDocumentWithTags();

    expect($document->tags)->toHaveCount(0);
});

it('exposes tags in the shared Inertia prop on the library index', function () {
    Tag::factory()->create(['name' => 'Zèbres']);
    Tag::factory()->create(['name' => 'Alphas']);

    $response = test()->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('tags', 2)
        ->where('tags.0.name', 'Alphas')
        ->where('tags.1.name', 'Zèbres')
    );
});

// Regression guard (spec-corrections-post-retrospective, adversarial review
// item 4): DocumentTypeBadge/HandleInertiaRequests/Index.vue all derive from
// DocumentMimeTypes::TYPE_TO_MIME/TYPE_LABELS now — without this assertion,
// the two constants desynchronizing (or the shared prop disappearing
// entirely) would silently drop the PDF/Word/Excel type filters from the
// library UI with no test failing anywhere.
it('exposes documentTypeOptions in the shared Inertia prop on the library index', function () {
    $response = test()->get('/');

    $response->assertInertia(fn ($page) => $page
        ->where('documentTypeOptions', [
            ['value' => 'pdf', 'label' => 'PDF'],
            ['value' => 'word', 'label' => 'Word'],
            ['value' => 'excel', 'label' => 'Excel'],
        ])
    );
});

// --- Anciennes routes catégorie retirées (Code review, spec-3-1) -----------

it('no longer serves the removed category-assignment route', function () {
    $document = Document::factory()->create();

    $response = test()->patch("/documents/{$document->id}/category", ['category_id' => 1]);

    $response->assertNotFound();
});

it('no longer serves the removed category-creation route', function () {
    $response = test()->post('/categories', ['name' => 'Nouvelle catégorie']);

    $response->assertNotFound();
});

it('shows the assigned tags on the library card', function () {
    $tag = Tag::factory()->create(['name' => 'Contrats']);
    $document = Document::factory()->create();
    $document->tags()->sync([$tag->id]);

    $response = test()->get('/');

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('documents.data.0.id', $document->id)
        ->where('documents.data.0.tags.0.name', 'Contrats')
    );
});
