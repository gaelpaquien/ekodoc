<?php

use App\Models\Document;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

// --- Page Configuration ---------------------------------------------------

it('lists every existing tag with its document count, ordered by name', function () {
    $zebras = Tag::factory()->create(['name' => 'Zèbres']);
    Tag::factory()->create(['name' => 'Alphas']);
    $document = Document::factory()->create();
    $document->tags()->sync([$zebras->id]);

    $response = test()->get('/configuration');

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Configuration')
        ->has('tags', 2)
        ->where('tags.0.name', 'Alphas')
        ->where('tags.0.documents_count', 0)
        ->where('tags.1.name', 'Zèbres')
        ->where('tags.1.documents_count', 1)
    );
});

it('shows the neutral empty state when no tag exists', function () {
    $response = test()->get('/configuration');

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Configuration')
        ->has('tags', 0)
    );
});

// --- Création --------------------------------------------------------------

it('creates a tag with a free name', function () {
    $response = test()->post('/tags', ['name' => 'Finance']);

    $response->assertRedirect();
    expect(Tag::where('name', 'Finance')->exists())->toBeTrue();
});

it('rejects an empty tag name', function () {
    $response = test()->post('/tags', ['name' => '']);

    $response->assertSessionHasErrors('name');
    expect(Tag::count())->toBe(0);
});

it('rejects a tag name over 255 characters', function () {
    $response = test()->post('/tags', ['name' => str_repeat('a', 256)]);

    $response->assertSessionHasErrors('name');
    expect(Tag::count())->toBe(0);
});

it('rejects creating a tag whose name is a case-insensitive duplicate of an existing one', function () {
    Tag::factory()->create(['name' => 'Finance']);

    $response = test()->post('/tags', ['name' => 'finance']);

    $response->assertSessionHasErrors('name');
    expect(Tag::count())->toBe(1);
});

it('allows creating a tag with the same name as an existing one only up to case sensitivity, never actually duplicating it', function () {
    Tag::factory()->create(['name' => 'Finance']);

    test()->post('/tags', ['name' => 'FINANCE']);

    expect(Tag::count())->toBe(1);
});

// --- Renommage ---------------------------------------------------------------

it('renames a tag, leaving the document_tag pivot untouched', function () {
    $tag = Tag::factory()->create(['name' => 'Fiance']);
    $document = Document::factory()->create();
    $document->tags()->sync([$tag->id]);

    $response = test()->patch("/tags/{$tag->id}", ['name' => 'Finance']);

    $response->assertRedirect();
    expect($tag->fresh()->name)->toBe('Finance');
    expect(DB::table('document_tag')->where('tag_id', $tag->id)->where('document_id', $document->id)->exists())->toBeTrue();
});

it('rejects renaming a tag to a name already taken by another tag, case-insensitive', function () {
    Tag::factory()->create(['name' => 'Finance']);
    $tag = Tag::factory()->create(['name' => 'RH']);

    $response = test()->patch("/tags/{$tag->id}", ['name' => 'finance']);

    $response->assertSessionHasErrors('name');
    expect($tag->fresh()->name)->toBe('RH');
});

it('allows renaming a tag to a case-variant of its own current name, excluding itself from the duplicate check', function () {
    $tag = Tag::factory()->create(['name' => 'Finance']);

    $response = test()->patch("/tags/{$tag->id}", ['name' => 'finance']);

    $response->assertRedirect();
    expect($tag->fresh()->name)->toBe('finance');
});

it('returns a 404 when renaming a tag id that does not exist', function () {
    $response = test()->patch('/tags/999999', ['name' => 'Finance']);

    $response->assertNotFound();
});

// --- Suppression -------------------------------------------------------------

it('detaches a tag used by N documents on deletion, deletes the tag row, and never touches the documents themselves', function () {
    $tag = Tag::factory()->create();
    $documentA = Document::factory()->create();
    $documentB = Document::factory()->create();
    $documentA->tags()->sync([$tag->id]);
    $documentB->tags()->sync([$tag->id]);

    $response = test()->delete("/tags/{$tag->id}");

    $response->assertRedirect();
    expect(Tag::find($tag->id))->toBeNull();
    expect(DB::table('document_tag')->where('tag_id', $tag->id)->count())->toBe(0);
    expect(Document::find($documentA->id))->not->toBeNull();
    expect(Document::find($documentB->id))->not->toBeNull();
});

it('flashes the factual post-deletion message naming the detached document count', function () {
    $tag = Tag::factory()->create();
    $documentA = Document::factory()->create();
    $documentB = Document::factory()->create();
    $documentA->tags()->sync([$tag->id]);
    $documentB->tags()->sync([$tag->id]);

    $response = test()->delete("/tags/{$tag->id}");

    $response->assertSessionHas('tagDeleted', fn ($value) => $value['count'] === 2);

    // Reads it the same way Configuration.vue actually does — through a
    // real Inertia response's shared props (same pattern as
    // UploadEditorImageTest's flash.uploadedImage assertion).
    test()->get('/configuration')->assertInertia(fn ($page) => $page
        ->where('flash.tagDeleted.count', 2)
    );
});

it('deletes an unused tag cleanly, flashing a count of zero', function () {
    $tag = Tag::factory()->create();

    $response = test()->delete("/tags/{$tag->id}");

    $response->assertRedirect();
    $response->assertSessionHas('tagDeleted', fn ($value) => $value['count'] === 0);
    expect(Tag::find($tag->id))->toBeNull();
});

it('returns a 404 when deleting a tag id that does not exist', function () {
    $response = test()->delete('/tags/999999');

    $response->assertNotFound();
});

it('never assigns tags through SyncDocumentTagsAction when deleting a tag', function () {
    // DeleteTagAction relies solely on the DB cascade to clear the pivot —
    // a document that still exists afterwards must keep every *other* tag
    // it had, proving no sync()/attach()/detach() call ever touched its
    // full tag set as a side effect of an unrelated tag's deletion.
    $tagToDelete = Tag::factory()->create();
    $keptTag = Tag::factory()->create();
    $document = Document::factory()->create();
    $document->tags()->sync([$tagToDelete->id, $keptTag->id]);

    test()->delete("/tags/{$tagToDelete->id}");

    expect($document->fresh()->tags->pluck('id')->all())->toBe([$keptTag->id]);
});
