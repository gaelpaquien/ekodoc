<?php

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function previewFixtureContents(string $name): string
{
    return file_get_contents(__DIR__.'/../Fixtures/'.$name);
}

function importPreviewDocument(string $filename, string $fixture): Document
{
    $file = UploadedFile::fake()->createWithContent($filename, previewFixtureContents($fixture));

    test()->post('/documents', ['file' => $file]);

    return Document::sole();
}

it('renders a native PDF preview directly, with no conversion ever attempted', function () {
    Process::fake();

    $document = importPreviewDocument('contract.pdf', 'sample.pdf');

    $response = $this->get("/documents/{$document->id}/preview");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('x-content-type-options', 'nosniff');
    Process::assertNothingRan();
});

it('converts a docx to PDF on first preview and caches the result', function () {
    Process::fake();

    $document = importPreviewDocument('report.docx', 'sample.docx');

    // Simulates the file soffice would have written to its document-scoped
    // --outdir before our code renames it into place: {original-basename}.pdf
    // under previews/tmp/{document_id}/, not {id}.pdf under previews/.
    Storage::disk('local')->put("previews/tmp/{$document->id}/report.pdf", previewFixtureContents('sample.pdf'));

    Storage::disk('local')->assertMissing("previews/{$document->id}.pdf");

    $response = $this->get("/documents/{$document->id}/preview");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('x-content-type-options', 'nosniff');
    Storage::disk('local')->assertExists("previews/{$document->id}.pdf");
    Process::assertRan(function ($process) {
        expect($process->command[0])->toBe('soffice');

        return str_contains(implode(' ', $process->command), '--convert-to');
    });
});

it('shells out to the LIBREOFFICE_BINARY override instead of the default soffice command', function () {
    config(['services.libreoffice.binary' => 'custom-soffice']);
    Process::fake();

    $document = importPreviewDocument('report.docx', 'sample.docx');

    Storage::disk('local')->put("previews/tmp/{$document->id}/report.pdf", previewFixtureContents('sample.pdf'));

    $response = $this->get("/documents/{$document->id}/preview");

    $response->assertOk();
    Storage::disk('local')->assertExists("previews/{$document->id}.pdf");
    Process::assertRan(fn ($process) => $process->command[0] === 'custom-soffice');
});

it('reuses an already-cached PDF preview without reconverting', function () {
    Process::fake();

    $document = importPreviewDocument('budget.xlsx', 'sample.xlsx');

    Storage::disk('local')->put("previews/{$document->id}.pdf", previewFixtureContents('sample.pdf'));

    $response = $this->get("/documents/{$document->id}/preview");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('x-content-type-options', 'nosniff');
    Process::assertNothingRan();
});

it('reports the source file as missing, disabling download, when it can no longer be found', function () {
    $document = importPreviewDocument('contract.pdf', 'sample.pdf');

    Storage::disk('local')->delete($document->file_path);

    $showResponse = $this->get("/documents/{$document->id}");
    $showResponse->assertInertia(fn ($page) => $page
        ->component('Documents/Show')
        ->where('sourceMissing', true)
    );

    $this->get("/documents/{$document->id}/preview")->assertNotFound();
    $this->get("/documents/{$document->id}/download")->assertNotFound();
});

it('reports a failed docx conversion as unavailable while keeping download active', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'soffice: conversion failed', exitCode: 1),
    ]);

    $document = importPreviewDocument('scanned.docx', 'sample-corrupt.docx');

    $showResponse = $this->get("/documents/{$document->id}");
    $showResponse->assertInertia(fn ($page) => $page
        ->component('Documents/Show')
        ->where('sourceMissing', false)
    );

    $previewResponse = $this->get("/documents/{$document->id}/preview");
    expect($previewResponse->status())->toBeGreaterThanOrEqual(400);
    Storage::disk('local')->assertMissing("previews/{$document->id}.pdf");

    $downloadResponse = $this->get("/documents/{$document->id}/download");
    $downloadResponse->assertOk();
    $downloadResponse->assertHeader('content-disposition');
    expect($downloadResponse->headers->get('content-disposition'))->toContain('attachment');
});
