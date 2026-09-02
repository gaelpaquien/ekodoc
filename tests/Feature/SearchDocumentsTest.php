<?php

use App\Models\Document;

it('filters documents whose extracted text contains the search term', function () {
    $matching = Document::factory()->create(['extracted_text' => 'Voici la facture du mois de janvier.']);
    $other = Document::factory()->create(['extracted_text' => 'Compte-rendu de réunion hebdomadaire.']);

    $response = $this->get('/?search=facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('search', 'facture')
        ->has('documents', 1)
        ->where('documents.0.id', $matching->id)
    );

    expect($response)->not->toBeNull();
    expect($other)->not->toBeNull();
});

it('returns the unfiltered, most-recent-first list when the search term is empty', function () {
    $oldest = Document::factory()->create(['created_at' => now()->subDays(2)]);
    $newest = Document::factory()->create(['created_at' => now()]);

    $response = $this->get('/?search=');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('search', '')
        ->has('documents', 2)
        ->where('documents.0.id', $newest->id)
        ->where('documents.1.id', $oldest->id)
    );
});

it('returns the unfiltered, most-recent-first list when the search term is absent', function () {
    $oldest = Document::factory()->create(['created_at' => now()->subDays(2)]);
    $newest = Document::factory()->create(['created_at' => now()]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('search', '')
        ->has('documents', 2)
        ->where('documents.0.id', $newest->id)
        ->where('documents.1.id', $oldest->id)
    );
});

it('returns an empty list without failing when no document matches the search term', function () {
    Document::factory()->create(['extracted_text' => 'Compte-rendu de réunion hebdomadaire.']);

    $response = $this->get('/?search=zzz-introuvable-zzz');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('search', 'zzz-introuvable-zzz')
        ->has('documents', 0)
    );
});

it('never matches a document by title or category, only by extracted text', function () {
    $document = Document::factory()->create([
        'title' => 'Facture janvier.pdf',
        'extracted_text' => 'Contenu sans rapport avec le titre.',
    ]);

    $response = $this->get('/?search=Facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents', 0)
    );

    expect($document->title)->toContain('Facture');
});

it('finds a document even when the search term only matches its extracted text, not its title', function () {
    $document = Document::factory()->create([
        'title' => 'Rapport annuel.pdf',
        'extracted_text' => 'Ce document mentionne une facture impayée.',
    ]);

    $response = $this->get('/?search=impayée');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
    );
});

it('trims leading and trailing whitespace from the search term before matching and echoing it back', function () {
    $document = Document::factory()->create(['extracted_text' => 'Voici la facture du mois de janvier.']);

    $response = $this->get('/?search=%20facture%20');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
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

    $response = $this->get('/?search=facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('search', 'facture')
        ->has('documents', 2)
        ->where('documents.0.id', $newest->id)
        ->where('documents.1.id', $oldest->id)
    );
});
