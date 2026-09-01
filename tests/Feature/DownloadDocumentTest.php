<?php

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('streams the original file unchanged via the dedicated download route', function () {
    $contents = file_get_contents(__DIR__.'/../Fixtures/sample.pdf');
    $file = UploadedFile::fake()->createWithContent('contract.pdf', $contents);

    $this->post('/documents', ['file' => $file]);
    $document = Document::sole();

    $response = $this->get("/documents/{$document->id}/download");

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    expect($response->streamedContent())->toBe($contents);
});

it('keeps the download route available while an office document preview is being converted', function () {
    Process::fake([
        '*' => function () {
            // Never resolves within the test — simulates a conversion still
            // in flight when a concurrent download request comes in.
            return Process::result(output: '', errorOutput: '', exitCode: 1);
        },
    ]);

    $contents = file_get_contents(__DIR__.'/../Fixtures/sample.xlsx');
    $file = UploadedFile::fake()->createWithContent('budget.xlsx', $contents);

    $this->post('/documents', ['file' => $file]);
    $document = Document::sole();

    // download() never touches the preview cache or the per-document
    // conversion lock, so it stays reachable regardless of preview state.
    $response = $this->get("/documents/{$document->id}/download");

    $response->assertOk();
    expect($response->streamedContent())->toBe($contents);
});
