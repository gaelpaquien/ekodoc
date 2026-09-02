<?php

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function deleteFixtureContents(string $name): string
{
    return file_get_contents(__DIR__.'/../Fixtures/'.$name);
}

function importDeleteDocument(string $filename = 'contract.pdf', string $fixture = 'sample.pdf'): Document
{
    $file = UploadedFile::fake()->createWithContent($filename, deleteFixtureContents($fixture));

    test()->post('/documents', ['file' => $file]);

    return Document::sole();
}

it('permanently deletes the file, the preview cache and the document row, then redirects to the library', function () {
    $document = importDeleteDocument();
    $disk = Storage::disk('local');

    // Simulate a previously-cached office preview to prove the preview
    // cache also gets cleaned up, not just the original file.
    $disk->put("previews/{$document->id}.pdf", deleteFixtureContents('sample.pdf'));

    // Simulate an associated image left alongside the original in the same
    // documents/{id} directory (AD-15) — the directory-empty assertion
    // below only proves multi-file cleanup if there's more than one known
    // file in there to begin with.
    $disk->put("documents/{$document->id}/page-1.png", 'fake image bytes');

    $disk->assertExists($document->file_path);
    $disk->assertExists("documents/{$document->id}/page-1.png");
    $disk->assertExists("previews/{$document->id}.pdf");

    $response = test()->delete("/documents/{$document->id}");

    $response->assertRedirect('/');
    expect(Document::find($document->id))->toBeNull();
    $disk->assertDirectoryEmpty("documents/{$document->id}");
    $disk->assertMissing("previews/{$document->id}.pdf");

    $indexResponse = test()->get('/');
    $indexResponse->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->where('documents', [])
    );
});

it('deletes cleanly when the source file is already missing, without erroring', function () {
    $document = importDeleteDocument();
    $disk = Storage::disk('local');

    $disk->delete($document->file_path);
    $disk->assertMissing($document->file_path);

    $response = test()->delete("/documents/{$document->id}");

    $response->assertRedirect('/');
    expect(Document::find($document->id))->toBeNull();
});

it('deletes a native PDF (never converted, so no preview cache exists) without attempting to delete a nonexistent preview file', function () {
    $document = importDeleteDocument();
    $disk = Storage::disk('local');

    $disk->assertMissing("previews/{$document->id}.pdf");

    $response = test()->delete("/documents/{$document->id}");

    $response->assertRedirect('/');
    expect(Document::find($document->id))->toBeNull();
});

it('returns a 404 when deleting a document id that does not exist', function () {
    $response = test()->delete('/documents/999999');

    $response->assertNotFound();
});

it('returns a 404 on a retried delete of an already-deleted document', function () {
    $document = importDeleteDocument();
    $id = $document->id;

    $firstResponse = test()->delete("/documents/{$id}");
    $firstResponse->assertRedirect('/');
    expect(Document::find($id))->toBeNull();

    // Second request against the now-nonexistent id — same "Document
    // introuvable" row from the I/O matrix, whether the id never existed
    // or existed and was just deleted.
    $secondResponse = test()->delete("/documents/{$id}");
    $secondResponse->assertNotFound();
});

it('leaves the document untouched when no delete request is sent', function () {
    $document = importDeleteDocument();

    // Mirrors the "cancelled" scenario from the I/O matrix: the confirmation
    // dialog is client-side only, so nothing to assert server-side beyond
    // "no DELETE request means no deletion" — the document simply persists.
    expect(Document::find($document->id))->not->toBeNull();
    Storage::disk('local')->assertExists($document->file_path);
});
