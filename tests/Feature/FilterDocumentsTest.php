<?php

use App\Enums\DocumentSource;
use App\Models\Category;
use App\Models\Document;

it('filters documents by a single category', function () {
    $category = Category::factory()->create();
    $other = Category::factory()->create();

    $matching = Document::factory()->create(['category_id' => $category->id]);
    $excluded = Document::factory()->create(['category_id' => $other->id]);

    $response = $this->get("/?category_id[]={$category->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('categoryFilters', [$category->id])
        ->has('documents', 1)
        ->where('documents.0.id', $matching->id)
    );

    expect($excluded)->not->toBeNull();
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
        ->has('documents', 1)
        ->where('documents.0.id', $pdf->id)
    );

    expect($word)->not->toBeNull();
});

it('ORs multiple selected categories within the category group', function () {
    $categoryA = Category::factory()->create();
    $categoryB = Category::factory()->create();
    $categoryC = Category::factory()->create();

    $inA = Document::factory()->create(['category_id' => $categoryA->id]);
    $inB = Document::factory()->create(['category_id' => $categoryB->id]);
    $inC = Document::factory()->create(['category_id' => $categoryC->id]);

    $response = $this->get("/?category_id[]={$categoryA->id}&category_id[]={$categoryB->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents', 2)
        ->where('documents', fn ($documents) => collect($documents)->pluck('id')->sort()->values()->all()
            === collect([$inA->id, $inB->id])->sort()->values()->all())
    );

    expect($inC)->not->toBeNull();
});

it('drops a malformed category_id value instead of erroring', function () {
    $document = Document::factory()->create();

    $response = $this->get('/?category_id[]=abc');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('categoryFilters', [])
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
    );
});

it('orders filtered results most-recent-first, same as the unfiltered list', function () {
    $category = Category::factory()->create();

    $oldest = Document::factory()->create([
        'category_id' => $category->id,
        'created_at' => now()->subDays(2),
    ]);
    $newest = Document::factory()->create([
        'category_id' => $category->id,
        'created_at' => now(),
    ]);

    $response = $this->get("/?category_id[]={$category->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents', 2)
        ->where('documents.0.id', $newest->id)
        ->where('documents.1.id', $oldest->id)
    );
});

it('combines category and type filters with AND between the two groups', function () {
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();

    $matching = Document::factory()->create([
        'category_id' => $category->id,
        'mime_type' => 'application/pdf',
    ]);
    $wrongCategory = Document::factory()->create([
        'category_id' => $otherCategory->id,
        'mime_type' => 'application/pdf',
    ]);
    $wrongType = Document::factory()->create([
        'category_id' => $category->id,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $response = $this->get("/?category_id[]={$category->id}&type[]=pdf");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents', 1)
        ->where('documents.0.id', $matching->id)
    );

    expect($wrongCategory)->not->toBeNull();
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
        ->has('documents', 2)
        ->where('documents', fn ($documents) => collect($documents)->pluck('id')->sort()->values()->all()
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
        ->has('documents', 1)
        ->where('documents.0.id', $created->id)
    );

    expect($imported)->not->toBeNull();
});

it('combines a type filter with an active search term through the same query entry point', function () {
    $matching = Document::factory()->create([
        'mime_type' => 'application/pdf',
        'extracted_text' => 'Voici la facture du mois de janvier.',
    ]);
    $wrongType = Document::factory()->create([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'extracted_text' => 'Voici la facture du mois de février.',
    ]);
    $wrongSearch = Document::factory()->create([
        'mime_type' => 'application/pdf',
        'extracted_text' => 'Compte-rendu de réunion hebdomadaire.',
    ]);

    $response = $this->get('/?search=facture&type[]=pdf');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('search', 'facture')
        ->where('typeFilters', ['pdf'])
        ->has('documents', 1)
        ->where('documents.0.id', $matching->id)
    );

    expect($wrongType)->not->toBeNull();
    expect($wrongSearch)->not->toBeNull();
});

it('returns an empty list without failing when active filters match no document', function () {
    Document::factory()->create(['mime_type' => 'application/pdf']);

    $category = Category::factory()->create();

    $response = $this->get("/?category_id[]={$category->id}&type[]=excel");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('categoryFilters', [$category->id])
        ->where('typeFilters', ['excel'])
        ->has('documents', 0)
    );
});

it('echoes back an empty categoryFilters/typeFilters when none are active', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('categoryFilters', [])
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
        ->has('documents', 1)
    );
});
