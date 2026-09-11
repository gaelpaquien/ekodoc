<?php

use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function attachFixtureContents(string $name): string
{
    return file_get_contents(__DIR__.'/../Fixtures/'.$name);
}

// --- Ajout immédiat ------------------------------------------------------

it('attaches a valid PDF to an already-saved document, extracts its text, and redirects back', function () {
    $document = Document::factory()->create();
    $file = UploadedFile::fake()->createWithContent('contract.pdf', attachFixtureContents('sample.pdf'));

    $response = test()->post("/documents/{$document->id}/attachments", ['file' => $file]);

    $response->assertRedirect();
    $attachment = DocumentAttachment::sole();

    expect($attachment->document_id)->toBe($document->id);
    expect($attachment->original_filename)->toBe('contract.pdf');
    expect($attachment->mime_type)->toBe('application/pdf');
    expect($attachment->extracted_text)->toContain('EkoDoc sample pdf content');
    expect($attachment->extraction_status)->toBe(ExtractionStatus::Completed);
    Storage::disk('local')->assertExists($attachment->file_path);
});

it('stores the attached file under documents/{id}/attachments/ with a server-generated UUID filename, never the client name', function () {
    $document = Document::factory()->create();
    $file = UploadedFile::fake()->createWithContent('contract.pdf', attachFixtureContents('sample.pdf'));

    test()->post("/documents/{$document->id}/attachments", ['file' => $file]);

    $attachment = DocumentAttachment::sole();

    expect($attachment->file_path)->toStartWith("documents/{$document->id}/attachments/");
    expect($attachment->file_path)->not->toContain('contract.pdf');
    expect(basename($attachment->file_path))->toMatch('/^[0-9a-f\-]{36}\.pdf$/');
});

it('returns immediately with the attachment pending extraction, dispatching the job instead of running it inline', function () {
    Queue::fake();

    $document = Document::factory()->create();
    $file = UploadedFile::fake()->createWithContent('contract.pdf', attachFixtureContents('sample.pdf'));

    test()->post("/documents/{$document->id}/attachments", ['file' => $file]);

    $attachment = DocumentAttachment::sole();

    expect($attachment->extraction_status)->toBe(ExtractionStatus::Pending);
    expect($attachment->extracted_text)->toBeNull();

    Queue::assertPushed(ExtractDocumentTextJob::class, fn ($job) => $job->target->is($attachment));
});

it('becomes searchable through the parent document once its text is extracted, with no separate Scout entry', function () {
    $document = Document::factory()->create(['extracted_text' => null]);
    $file = UploadedFile::fake()->createWithContent('contract.pdf', attachFixtureContents('sample.pdf'));

    test()->post("/documents/{$document->id}/attachments", ['file' => $file]);

    $document->refresh();
    expect($document->attachments_extracted_text)->toContain('EkoDoc sample pdf content');

    $response = test()->get('/recherche?search=EkoDoc');

    $response->assertInertia(fn ($page) => $page
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
    );
});

// --- Format non supporté ---------------------------------------------------

it('rejects an unsupported format, creates no attachment, and names the accepted formats', function () {
    $document = Document::factory()->create();
    $file = UploadedFile::fake()->create('slides.pptx', 10);

    $response = test()->post("/documents/{$document->id}/attachments", ['file' => $file]);

    $response->assertSessionHasErrors('file');
    expect(session('errors')->first('file'))->toContain('PDF')
        ->toContain('.docx')
        ->toContain('.xlsx');
    expect(DocumentAttachment::count())->toBe(0);
});

it('rejects the request when no file is provided', function () {
    $document = Document::factory()->create();

    $response = test()->post("/documents/{$document->id}/attachments", []);

    $response->assertSessionHasErrors('file');
    expect(DocumentAttachment::count())->toBe(0);
});

it('rejects a file over the 20MB limit and creates no attachment', function () {
    $document = Document::factory()->create();
    $file = UploadedFile::fake()->create('big.pdf', 20 * 1024 + 1);

    $response = test()->post("/documents/{$document->id}/attachments", ['file' => $file]);

    $response->assertSessionHasErrors('file');
    expect(DocumentAttachment::count())->toBe(0);
});

it('returns a 404 when attaching to a document id that does not exist', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', attachFixtureContents('sample.pdf'));

    $response = test()->post('/documents/999999/attachments', ['file' => $file]);

    $response->assertNotFound();
});

// --- Fiche document / Éditeur -----------------------------------------------

it('loads attachments on the document detail page', function () {
    $document = Document::factory()->create();
    DocumentAttachment::factory()->create(['document_id' => $document->id, 'original_filename' => 'contrat.pdf']);

    $response = test()->get("/documents/{$document->id}");

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Show')
        ->has('document.attachments', 1)
        ->where('document.attachments.0.original_filename', 'contrat.pdf')
    );
});

it('previews and downloads an attachment without converting it (docx included)', function () {
    $document = Document::factory()->create();
    $file = UploadedFile::fake()->createWithContent('report.docx', attachFixtureContents('sample.docx'));

    test()->post("/documents/{$document->id}/attachments", ['file' => $file]);
    $attachment = DocumentAttachment::sole();

    $previewResponse = test()->get("/documents/{$document->id}/attachments/{$attachment->id}/preview");
    $previewResponse->assertOk();

    $downloadResponse = test()->get("/documents/{$document->id}/attachments/{$attachment->id}/download");
    $downloadResponse->assertOk();
});

it('returns a 404 previewing an attachment that belongs to a different document', function () {
    $document = Document::factory()->create();
    $otherDocument = Document::factory()->create();
    $attachment = DocumentAttachment::factory()->create(['document_id' => $otherDocument->id]);

    $response = test()->get("/documents/{$document->id}/attachments/{$attachment->id}/preview");

    $response->assertNotFound();
});

it('returns a 404 downloading an attachment that belongs to a different document', function () {
    $document = Document::factory()->create();
    $otherDocument = Document::factory()->create();
    $attachment = DocumentAttachment::factory()->create(['document_id' => $otherDocument->id]);

    $response = test()->get("/documents/{$document->id}/attachments/{$attachment->id}/download");

    $response->assertNotFound();
});

it('returns a 404 previewing/downloading an attachment whose file is missing from disk', function () {
    $document = Document::factory()->create();
    $file = UploadedFile::fake()->createWithContent('contract.pdf', attachFixtureContents('sample.pdf'));
    test()->post("/documents/{$document->id}/attachments", ['file' => $file]);
    $attachment = DocumentAttachment::sole();

    Storage::disk('local')->delete($attachment->file_path);

    $previewResponse = test()->get("/documents/{$document->id}/attachments/{$attachment->id}/preview");
    $previewResponse->assertNotFound();

    $downloadResponse = test()->get("/documents/{$document->id}/attachments/{$attachment->id}/download");
    $downloadResponse->assertNotFound();
});
