<?php

use App\Enums\DocumentSource;
use App\Models\Document;
use App\Models\Tag;

it('filters documents by a single tag', function () {
    $tag = Tag::factory()->create();
    $other = Tag::factory()->create();

    $matching = Document::factory()->create();
    $matching->tags()->sync([$tag->id]);

    $excluded = Document::factory()->create();
    $excluded->tags()->sync([$other->id]);

    $response = $this->get("/?tag_id[]={$tag->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('tagFilters', [$tag->id])
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $matching->id)
    );

    expect($excluded)->not->toBeNull();
});

// Code review finding (spec-3-4): pagination links must carry the active
// filter forward, never silently drop it on page 2+.
it('keeps the active tag filter in the pagination links', function () {
    $tag = Tag::factory()->create();
    $matching = Document::factory()->count(25)->create();
    foreach ($matching as $document) {
        $document->tags()->sync([$tag->id]);
    }

    $response = $this->get("/?tag_id[]={$tag->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 20)
        ->where('documents.links', fn ($links) => collect($links)
            ->filter(fn ($link) => $link['url'] !== null)
            ->every(fn ($link) => str_contains($link['url'], (string) $tag->id)))
    );
});

it('filters documents by a single type', function () {
    $pdf = Document::factory()->create(['mime_type' => 'application/pdf']);
    $word = Document::factory()->create([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $response = $this->get('/?type[]=pdf');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('typeFilters', ['pdf'])
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $pdf->id)
    );

    expect($word)->not->toBeNull();
});

it('ORs multiple selected tags within the tag group', function () {
    $tagA = Tag::factory()->create();
    $tagB = Tag::factory()->create();
    $tagC = Tag::factory()->create();

    $inA = Document::factory()->create();
    $inA->tags()->sync([$tagA->id]);
    $inB = Document::factory()->create();
    $inB->tags()->sync([$tagB->id]);
    $inC = Document::factory()->create();
    $inC->tags()->sync([$tagC->id]);

    $response = $this->get("/?tag_id[]={$tagA->id}&tag_id[]={$tagB->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 2)
        ->where('documents.data', fn ($documents) => collect($documents)->pluck('id')->sort()->values()->all()
            === collect([$inA->id, $inB->id])->sort()->values()->all())
    );

    expect($inC)->not->toBeNull();
});

it('matches a document tagged with several of the selected tags only once', function () {
    $tagA = Tag::factory()->create();
    $tagB = Tag::factory()->create();

    $document = Document::factory()->create();
    $document->tags()->sync([$tagA->id, $tagB->id]);

    $response = $this->get("/?tag_id[]={$tagA->id}&tag_id[]={$tagB->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $document->id)
    );
});

it('drops a malformed tag_id value instead of erroring', function () {
    $document = Document::factory()->create();

    $response = $this->get('/?tag_id[]=abc');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('tagFilters', [])
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $document->id)
    );
});

it('orders filtered results most-recent-first, same as the unfiltered list', function () {
    $tag = Tag::factory()->create();

    $oldest = Document::factory()->create(['created_at' => now()->subDays(2)]);
    $oldest->tags()->sync([$tag->id]);
    $newest = Document::factory()->create(['created_at' => now()]);
    $newest->tags()->sync([$tag->id]);

    $response = $this->get("/?tag_id[]={$tag->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 2)
        ->where('documents.data.0.id', $newest->id)
        ->where('documents.data.1.id', $oldest->id)
    );
});

it('combines tag and type filters with AND between the two groups', function () {
    $tag = Tag::factory()->create();
    $otherTag = Tag::factory()->create();

    $matching = Document::factory()->create(['mime_type' => 'application/pdf']);
    $matching->tags()->sync([$tag->id]);

    $wrongTag = Document::factory()->create(['mime_type' => 'application/pdf']);
    $wrongTag->tags()->sync([$otherTag->id]);

    $wrongType = Document::factory()->create([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
    $wrongType->tags()->sync([$tag->id]);

    $response = $this->get("/?tag_id[]={$tag->id}&type[]=pdf");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $matching->id)
    );

    expect($wrongTag)->not->toBeNull();
    expect($wrongType)->not->toBeNull();
});

it('ORs multiple selected types within the type group', function () {
    $pdf = Document::factory()->create(['mime_type' => 'application/pdf']);
    $excel = Document::factory()->create([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
    $word = Document::factory()->create([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $response = $this->get('/?type[]=pdf&type[]=excel');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 2)
        ->where('documents.data', fn ($documents) => collect($documents)->pluck('id')->sort()->values()->all()
            === collect([$pdf->id, $excel->id])->sort()->values()->all())
    );

    expect($word)->not->toBeNull();
});

it('filters on the created type by source rather than mime type', function () {
    $created = Document::factory()->create([
        'source' => DocumentSource::Created,
        'mime_type' => null,
    ]);
    $imported = Document::factory()->create([
        'source' => DocumentSource::Imported,
        'mime_type' => 'application/pdf',
    ]);

    $response = $this->get('/?type[]=created');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $created->id)
    );

    expect($imported)->not->toBeNull();
});

it('returns an empty list without failing when active filters match no document', function () {
    Document::factory()->create(['mime_type' => 'application/pdf']);

    $tag = Tag::factory()->create();

    $response = $this->get("/?tag_id[]={$tag->id}&type[]=excel");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('tagFilters', [$tag->id])
        ->where('typeFilters', ['excel'])
        ->has('documents.data', 0)
    );
});

it('echoes back an empty tagFilters/typeFilters when none are active', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('tagFilters', [])
        ->where('typeFilters', [])
    );
});

it('drops a type value outside the four recognized types instead of erroring', function () {
    Document::factory()->create(['mime_type' => 'application/pdf']);

    $response = $this->get('/?type[]=exe');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('typeFilters', [])
        ->has('documents.data', 1)
    );
});
