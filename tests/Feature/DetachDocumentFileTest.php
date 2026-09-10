<?php

use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function detachFixtureContents(string $name): string
{
    return file_get_contents(__DIR__.'/../Fixtures/'.$name);
}

function attachDocumentFile(Document $document, string $filename = 'contract.pdf', string $fixture = 'sample.pdf'): DocumentAttachment
{
    $file = UploadedFile::fake()->createWithContent($filename, detachFixtureContents($fixture));

    test()->post("/documents/{$document->id}/attachments", ['file' => $file]);

    return DocumentAttachment::sole();
}

// --- Retrait ---------------------------------------------------------------

it('deletes the file and the row, then redirects back', function () {
    $document = Document::factory()->create();
    $attachment = attachDocumentFile($document);
    $filePath = $attachment->file_path;

    Storage::disk('local')->assertExists($filePath);

    $response = test()->delete("/documents/{$document->id}/attachments/{$attachment->id}");

    $response->assertRedirect();
    expect(DocumentAttachment::find($attachment->id))->toBeNull();
    Storage::disk('local')->assertMissing($filePath);
});

it('resyncs the parent document\'s attachments_extracted_text after a detach, dropping it from search', function () {
    $document = Document::factory()->create(['extracted_text' => null]);
    $attachment = attachDocumentFile($document);

    $document->refresh();
    expect($document->attachments_extracted_text)->toContain('EkoDoc sample pdf content');

    test()->delete("/documents/{$document->id}/attachments/{$attachment->id}");

    $document->refresh();
    expect($document->attachments_extracted_text)->toBeNull();

    $response = test()->get('/?search=EkoDoc');
    $response->assertInertia(fn ($page) => $page->has('documents', 0));
});

it('keeps the remaining attachments\' text after detaching only one of several', function () {
    $document = Document::factory()->create();
    $kept = DocumentAttachment::factory()->create([
        'document_id' => $document->id,
        'extracted_text' => 'texte conservé',
    ]);
    $removed = DocumentAttachment::factory()->create([
        'document_id' => $document->id,
        'extracted_text' => 'texte retiré',
    ]);
    $document->syncAttachmentsExtractedText();

    test()->delete("/documents/{$document->id}/attachments/{$removed->id}");

    $document->refresh();
    expect($document->attachments_extracted_text)->toContain('texte conservé');
    expect($document->attachments_extracted_text)->not->toContain('texte retiré');
});

it('returns a 404 detaching an attachment id that does not exist', function () {
    $document = Document::factory()->create();

    $response = test()->delete("/documents/{$document->id}/attachments/999999");

    $response->assertNotFound();
});

it('returns a 404 detaching an attachment that belongs to a different document', function () {
    $document = Document::factory()->create();
    $otherDocument = Document::factory()->create();
    $attachment = DocumentAttachment::factory()->create(['document_id' => $otherDocument->id]);

    $response = test()->delete("/documents/{$document->id}/attachments/{$attachment->id}");

    $response->assertNotFound();
    expect(DocumentAttachment::find($attachment->id))->not->toBeNull();
});

it('never deletes the file via a bare cascade — deleting the parent document removes both, deliberately', function () {
    $document = Document::factory()->create();
    $attachment = attachDocumentFile($document);
    $filePath = $attachment->file_path;

    Storage::disk('local')->assertExists($filePath);

    test()->delete("/documents/{$document->id}");

    expect(DocumentAttachment::find($attachment->id))->toBeNull();
    Storage::disk('local')->assertMissing($filePath);
});
