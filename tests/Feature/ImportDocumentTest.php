<?php

use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use App\Models\Tag;
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

it('imports a valid PDF, extracts its text and redirects back to the import review step', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    // `store()` now redirects via back() (Code Map,
    // spec-corrections-documents-ui) rather than a fixed route — mirrors
    // how a real browser navigation from /documents/import carries its own
    // Referer header; the test client needs the same simulated here via
    // from(), otherwise back() falls through to the app root.
    $response = $this->from('/documents/import')->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect('/documents/import');
    expect($document->source)->toBe(DocumentSource::Imported);
    expect($document->title)->toBe('contract.pdf');
    expect($document->file_path)->toBe("documents/{$document->id}/contract.pdf");
    expect($document->extracted_text)->toContain('BMAD Démo sample pdf content');
    expect($document->extraction_status)->toBe(ExtractionStatus::Completed);
    Storage::disk('local')->assertExists($document->file_path);
});

it('redirects to the import page even with no previous URL in session (code review fix: back() fallback)', function () {
    // No ->from() here, unlike the test above — simulates a session with no
    // prior GET to /documents/import (e.g. a fresh session/direct POST),
    // where back() would otherwise fall through to the app root and lose
    // the flashed uploadedDocument, so step 2 would never appear.
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    $response = $this->post('/documents', ['file' => $file]);

    $response->assertRedirect('/documents/import');
});

it('imports a valid docx and extracts its text via phpword', function () {
    $file = UploadedFile::fake()->createWithContent('report.docx', fixtureContents('sample.docx'));

    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    expect($document->source)->toBe(DocumentSource::Imported);
    expect($document->extracted_text)->toContain('BMAD Démo sample docx content');
    Storage::disk('local')->assertExists($document->file_path);
});

it('imports a valid xlsx and extracts its text via phpspreadsheet', function () {
    $file = UploadedFile::fake()->createWithContent('budget.xlsx', fixtureContents('sample.xlsx'));

    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    expect($document->source)->toBe(DocumentSource::Imported);
    expect($document->extracted_text)->toContain('BMAD Démo sample xlsx content');
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

    $response = $this->from('/documents/import')->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect('/documents/import');
    expect($document->extracted_text)->toContain('BMAD Démo sample docx content');
    Storage::disk('local')->assertExists($document->file_path);
});

it('keeps the import when text extraction fails on an otherwise valid, unreadable file', function () {
    $file = UploadedFile::fake()->createWithContent('scanned.pdf', fixtureContents('sample-corrupt.pdf'));

    $response = $this->from('/documents/import')->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect('/documents/import');
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

it('renders the library index page at GET /', function () {
    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page->component('Documents/Index'));
});

// --- Page d'import dédiée (spec-import-document-page) -----------------------

it('renders the dedicated import page at GET /documents/import', function () {
    $response = $this->get('/documents/import');

    $response->assertInertia(fn ($page) => $page->component('Documents/Import'));
});

it('returns immediately with the document pending extraction, dispatching the job instead of running it inline', function () {
    Queue::fake();

    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));

    $response = $this->from('/documents/import')->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response->assertRedirect('/documents/import');
    expect($document->extraction_status)->toBe(ExtractionStatus::Pending);
    expect($document->extracted_text)->toBeNull();
    Storage::disk('local')->assertExists($document->file_path);

    Queue::assertPushed(ExtractDocumentTextJob::class, fn ($job) => $job->target->is($document));
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
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $document->id)
        ->where('documents.data.0.title', 'contract.pdf')
    );
});

// --- Étape 2 : revue (Supprimer / Enregistrer, spec-corrections-documents-ui) ----

it('deletes the imported document and redirects back to the import page when ?redirect=import is passed', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));
    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();

    $response = $this->delete("/documents/{$document->id}?redirect=import");

    $response->assertRedirect('/documents/import');
    expect(Document::find($document->id))->toBeNull();
});

it('syncs tags and redirects to the document page when ?redirect=show is passed', function () {
    $file = UploadedFile::fake()->createWithContent('contract.pdf', fixtureContents('sample.pdf'));
    $this->post('/documents', ['file' => $file]);

    $document = Document::sole();
    $tag = Tag::factory()->create();

    $response = $this->patch("/documents/{$document->id}/tags?redirect=show", [
        'tag_ids' => [$tag->id],
    ]);

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->fresh()->tags->pluck('id')->all())->toBe([$tag->id]);
});
