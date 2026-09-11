<?php

use App\Models\Document;
use App\Models\Tag;

it('filters documents whose extracted text contains the search term', function () {
    $matching = Document::factory()->create(['extracted_text' => 'Voici la facture du mois de janvier.']);
    $other = Document::factory()->create(['extracted_text' => 'Compte-rendu de réunion hebdomadaire.']);

    $response = $this->get('/recherche?search=facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', 'facture')
        ->has('documents', 1)
        ->where('documents.0.id', $matching->id)
    );

    expect($response)->not->toBeNull();
    expect($other)->not->toBeNull();
});

// I/O matrix "Recherche vide au chargement" — AC2: Recherche never shows
// the whole library, unlike the old Index.vue behavior.
it('returns an empty result set, never the whole library, when the search term is empty', function () {
    Document::factory()->count(2)->create();

    $response = $this->get('/recherche?search=');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', '')
        ->has('documents', 0)
    );
});

// Same rule as an empty `search=`, when the parameter is absent entirely.
it('returns an empty result set, never the whole library, when the search term is absent', function () {
    Document::factory()->count(2)->create();

    $response = $this->get('/recherche');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', '')
        ->has('documents', 0)
    );
});

// I/O matrix "Filtre tag seul, terme vide" — AC2: a tag filter alone never
// bypasses the empty-term short-circuit.
it('returns an empty result set when a tag filter is active but the search term is empty', function () {
    $tag = Tag::factory()->create();
    $document = Document::factory()->create();
    $document->tags()->sync([$tag->id]);

    $response = $this->get("/recherche?tag_id[]={$tag->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', '')
        ->where('tagFilters', [$tag->id])
        ->has('documents', 0)
    );
});

it('returns an empty list without failing when no document matches the search term', function () {
    Document::factory()->create(['extracted_text' => 'Compte-rendu de réunion hebdomadaire.']);

    $response = $this->get('/recherche?search=zzz-introuvable-zzz');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', 'zzz-introuvable-zzz')
        ->has('documents', 0)
    );
});

it('never matches a document by title, only by extracted text', function () {
    $document = Document::factory()->create([
        'title' => 'Facture janvier.pdf',
        'extracted_text' => 'Contenu sans rapport avec le titre.',
    ]);

    $response = $this->get('/recherche?search=Facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->has('documents', 0)
    );

    expect($document->title)->toContain('Facture');
});

it('never matches a document by its tag name, only by extracted text (tags are a filter, never a search term)', function () {
    $tag = Tag::factory()->create(['name' => 'Facture']);
    $document = Document::factory()->create([
        'extracted_text' => 'Contenu sans rapport avec le nom du tag.',
    ]);
    $document->tags()->sync([$tag->id]);

    $response = $this->get('/recherche?search=Facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->has('documents', 0)
    );
});

it('finds a document even when the search term only matches its extracted text, not its title', function () {
    $document = Document::factory()->create([
        'title' => 'Rapport annuel.pdf',
        'extracted_text' => 'Ce document mentionne une facture impayée.',
    ]);

    $response = $this->get('/recherche?search=impayée');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
    );
});

it('trims leading and trailing whitespace from the search term before matching and echoing it back', function () {
    $document = Document::factory()->create(['extracted_text' => 'Voici la facture du mois de janvier.']);

    $response = $this->get('/recherche?search=%20facture%20');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', 'facture')
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
    );
});

it('orders search results most-recent-first, same as the unfiltered list', function () {
    $oldest = Document::factory()->create([
        'extracted_text' => 'Facture de janvier.',
        'created_at' => now()->subDays(2),
    ]);
    $newest = Document::factory()->create([
        'extracted_text' => 'Facture de février.',
        'created_at' => now(),
    ]);

    $response = $this->get('/recherche?search=facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', 'facture')
        ->has('documents', 2)
        ->where('documents.0.id', $newest->id)
        ->where('documents.1.id', $oldest->id)
    );
});

it('combines a tag filter with an active search term through the same query entry point', function () {
    $tag = Tag::factory()->create();
    $otherTag = Tag::factory()->create();

    $matching = Document::factory()->create(['extracted_text' => 'Voici la facture du mois de janvier.']);
    $matching->tags()->sync([$tag->id]);

    $wrongTag = Document::factory()->create(['extracted_text' => 'Voici la facture du mois de février.']);
    $wrongTag->tags()->sync([$otherTag->id]);

    $wrongSearch = Document::factory()->create(['extracted_text' => 'Compte-rendu de réunion hebdomadaire.']);
    $wrongSearch->tags()->sync([$tag->id]);

    $response = $this->get("/recherche?search=facture&tag_id[]={$tag->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', 'facture')
        ->where('tagFilters', [$tag->id])
        ->has('documents', 1)
        ->where('documents.0.id', $matching->id)
    );

    expect($wrongTag)->not->toBeNull();
    expect($wrongSearch)->not->toBeNull();
});
