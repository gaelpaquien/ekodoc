<?php

use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function fixtureContents(string $name): string
{
    return file_get_contents(__DIR__.'/../Fixtures/'.$name);
}

it('imports a valid PDF, extracts its text and redirects to the document page', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    $response = $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->source)->toBe(DocumentSource::Imported);
    expect($document->title)->toBe('contract.pdf');
    expect($document->file_path)->toBe("documents/{$document->id}/contract.pdf");
    expect($document->extracted_text)->toContain('EkoDoc sample pdf content');
    expect($document->extraction_status)->toBe(ExtractionStatus::Completed);
    Storage::disk('local')->assertExists($document->file_path);
});

it('imports a valid docx and extracts its text via phpword', function () {
    $file = UploadedFile::fake()->createWithContent('report.docx', fixtureContents('sample.docx'));

    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    expect($document->source)->toBe(DocumentSource::Imported);
    expect($document->extracted_text)->toContain('EkoDoc sample docx content');
    Storage::disk('local')->assertExists($document->file_path);
});

it('imports a valid xlsx and extracts its text via phpspreadsheet', function () {
    $file = UploadedFile::fake()->createWithContent('budget.xlsx', fixtureContents('sample.xlsx'));

    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    expect($document->source)->toBe(DocumentSource::Imported);
    expect($document->extracted_text)->toContain('EkoDoc sample xlsx content');
    Storage::disk('local')->assertExists($document->file_path);
});

it('rejects an unsupported format, creates no document, and names the accepted formats', function () {
    $file = UploadedFile::fake()->create('slides.pptx', 10);

    $response = $this->post('/documents', ['file' => $file]);

    $response->assertSessionHasErrors('file');
    expect(session('errors')->first('file'))->toContain('PDF')
        ->toContain('.docx')
        ->toContain('.xlsx');
    expect(Document::count())->toBe(0);
});

it('rejects the request when no file is provided', function () {
    $response = $this->post('/documents', []);

    $response->assertSessionHasErrors('file');
    expect(Document::count())->toBe(0);
});

it('rejects a file over the 20MB limit and creates no document', function () {
    $file = UploadedFile::fake()->create('big.pdf', 20 * 1024 + 1);

    $response = $this->post('/documents', ['file' => $file]);

    $response->assertSessionHasErrors('file');
    expect(Document::count())->toBe(0);
});

it('extracts text based on the real file content even when the filename extension does not match', function () {
    // Laravel's fake UploadedFile reports its MIME type from the filename
    // extension by default (unlike a real upload, which detects it from
    // the actual bytes via fileinfo) — override it explicitly here to
    // simulate a real docx file whose client-supplied name doesn't match.
    $file = UploadedFile::fake()
        ->createWithContent('mystery-file.bin', fixtureContents('sample.docx'))
        ->mimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $response = $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->extracted_text)->toContain('EkoDoc sample docx content');
    Storage::disk('local')->assertExists($document->file_path);
});

it('keeps the import when text extraction fails on an otherwise valid, unreadable file', function () {
    $file = UploadedFile::fake()->createWithContent('scanned.pdf', fixtureContents('sample-corrupt.pdf'));

    $response = $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->source)->toBe(DocumentSource::Imported);
    expect($document->extracted_text)->toBeNull();
    expect($document->extraction_status)->toBe(ExtractionStatus::Failed);
    Storage::disk('local')->assertExists($document->file_path);
});

it('renders the document detail page with title, type and date after import', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response = $this->get("/documents/{$document->id}");

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Show')
        ->where('document.id', $document->id)
        ->where('document.title', 'contract.pdf')
        ->where('document.source', 'imported')
    );
});

it('serves the import modal from the library index page', function () {
    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page->component('Documents/Index'));
});

it('returns immediately with the document pending extraction, dispatching the job instead of running it inline', function () {
    Queue::fake();

    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    $response = $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->extraction_status)->toBe(ExtractionStatus::Pending);
    expect($document->extracted_text)->toBeNull();
    Storage::disk('local')->assertExists($document->file_path);

    Queue::assertPushed(ExtractDocumentTextJob::class, fn ($job) => $job->document->is($document));
});

it('lists documents still pending or processing extraction in the shared pendingExtractions prop', function () {
    Queue::fake();

    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));
    $this->post('/documents', ['file' => $file]);

    $pending = Document::sole();

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('pendingExtractions', 1)
        ->where('pendingExtractions.0.id', $pending->id)
        ->where('pendingExtractions.0.extraction_status', 'pending')
    );
});

it('excludes completed or failed documents from the shared pendingExtractions prop', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));
    $this->post('/documents', ['file' => $file]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page->has('pendingExtractions', 0));
});

it('lists previously imported documents on the index page', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));
    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
        ->where('documents.0.title', 'contract.pdf')
    );
});
