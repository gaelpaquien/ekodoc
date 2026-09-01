<?php

use App\Enums\DocumentSource;
use App\Models\Document;

it('renders the library with no documents and an empty documents prop', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents', 0)
    );
});

it('lists several documents sorted from most recent to oldest', function () {
    $oldest = Document::factory()->create(['created_at' => now()->subDays(2)]);
    $middle = Document::factory()->create(['created_at' => now()->subDay()]);
    $newest = Document::factory()->create(['created_at' => now()]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents', 3)
        ->where('documents.0.id', $newest->id)
        ->where('documents.1.id', $middle->id)
        ->where('documents.2.id', $oldest->id)
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
        ->has('documents', 3)
        ->where('documents.0.mime_type', $xlsx->mime_type)
        ->where('documents.1.mime_type', $docx->mime_type)
        ->where('documents.2.mime_type', $pdf->mime_type)
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
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
        ->where('documents.0.mime_type', 'application/zip')
        ->where('documents.0.source', 'imported')
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
        ->has('documents', 1)
        ->where('documents.0.id', $created->id)
        ->where('documents.0.mime_type', null)
        ->where('documents.0.source', 'created')
    );
});
