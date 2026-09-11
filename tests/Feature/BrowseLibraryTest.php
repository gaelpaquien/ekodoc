<?php

use App\Enums\DocumentSource;
use App\Models\Document;

it('renders the library with no documents and an empty documents prop', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 0)
    );
});

it('lists several documents sorted from most recent to oldest', function () {
    $oldest = Document::factory()->create(['created_at' => now()->subDays(2)]);
    $middle = Document::factory()->create(['created_at' => now()->subDay()]);
    $newest = Document::factory()->create(['created_at' => now()]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 3)
        ->where('documents.data.0.id', $newest->id)
        ->where('documents.data.1.id', $middle->id)
        ->where('documents.data.2.id', $oldest->id)
    );
});

it('exposes the mime type needed to derive the PDF/Word/Excel type badge for each document', function () {
    $pdf = Document::factory()->create([
        'mime_type' => 'application/pdf',
        'created_at' => now()->subMinutes(3),
    ]);
    $docx = Document::factory()->create([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'created_at' => now()->subMinutes(2),
    ]);
    $xlsx = Document::factory()->create([
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'created_at' => now()->subMinute(),
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 3)
        ->where('documents.data.0.mime_type', $xlsx->mime_type)
        ->where('documents.data.1.mime_type', $docx->mime_type)
        ->where('documents.data.2.mime_type', $pdf->mime_type)
    );
});

it('does not fail and still lists an imported document with an unrecognized mime type', function () {
    $document = Document::factory()->create([
        'source' => DocumentSource::Imported,
        'mime_type' => 'application/zip',
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $document->id)
        ->where('documents.data.0.mime_type', 'application/zip')
        ->where('documents.data.0.source', 'imported')
    );
});

it('does not fail and still lists a created document with a null mime type', function () {
    $created = Document::factory()->create([
        'source' => DocumentSource::Created,
        'mime_type' => null,
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $created->id)
        ->where('documents.data.0.mime_type', null)
        ->where('documents.data.0.source', 'created')
    );
});

// I/O matrix "Bibliothèque, page 2" — AC "pagination fonctionnelle": 20 per
// page, 25 documents total ⇒ page 2 holds the remaining 5, with coherent
// pagination links (current/last page, a `url` on both the "previous" and
// numbered links, none on "next" since page 2 is the last one).
it('paginates the library at 20 documents per page, page 2 holding the remaining 5 of 25', function () {
    $documents = Document::factory()->count(25)->sequence(
        fn ($sequence) => ['created_at' => now()->subMinutes(25 - $sequence->index)],
    )->create();

    $firstPage = $this->get('/');

    $firstPage->assertOk();
    $firstPage->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 20)
        ->where('documents.current_page', 1)
        ->where('documents.last_page', 2)
        ->where('documents.total', 25)
    );

    $secondPage = $this->get('/?page=2');

    $secondPage->assertOk();
    $secondPage->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents.data', 5)
        ->where('documents.current_page', 2)
        ->where('documents.next_page_url', null)
        ->where('documents.prev_page_url', fn ($url) => $url !== null)
        // Page 2 holds the 5 oldest documents (index 0-4), still ordered
        // most-recent-first: index 4 (the newest of the remainder) leads,
        // index 0 (the very oldest) trails.
        ->where('documents.data.0.id', $documents->get(4)->id)
        ->where('documents.data.4.id', $documents->first()->id)
    );
});
