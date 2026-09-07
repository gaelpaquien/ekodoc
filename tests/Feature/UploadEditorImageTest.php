<?php

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
});

function editorImageUploadPayload(array $overrides = []): array
{
    return array_merge([
        'draft_token' => Str::uuid()->toString(),
        'image' => UploadedFile::fake()->image('photo.jpg', 200, 200),
        'alt' => 'Photo de test',
    ], $overrides);
}

// --- Insertion réussie en brouillon -----------------------------------------

it('stores the image under tmp/{token}/images/ and returns its url/alt via the shared flash prop', function () {
    $token = Str::uuid()->toString();

    $response = test()->post('/documents/create/images', editorImageUploadPayload(['draft_token' => $token]));

    $response->assertRedirect();
    $response->assertSessionHas('uploadedImage');

    $uploadedImage = session('uploadedImage');

    expect($uploadedImage['alt'])->toBe('Photo de test');
    expect($uploadedImage['filename'])->toMatch('/^[0-9a-f\-]{36}\.jpg$/');
    expect($uploadedImage['url'])->toBe("/documents/editor-images/tmp/{$token}/{$uploadedImage['filename']}");

    Storage::disk('local')->assertExists("documents/tmp/{$token}/images/{$uploadedImage['filename']}");
});

it('exposes the uploaded image through the shared flash.uploadedImage Inertia prop on the next request', function () {
    $token = Str::uuid()->toString();

    test()->post('/documents/create/images', editorImageUploadPayload([
        'draft_token' => $token,
        'alt' => 'Photo de test',
    ]));

    $uploadedImage = session('uploadedImage');

    // Reads it the same way Editor.vue actually does — through a real
    // Inertia response's shared props, not session('uploadedImage')
    // directly (as the other tests here and in CreateDocumentTest do) —
    // so a regression renaming/dropping the prop in
    // HandleInertiaRequests::share() would fail this test even though it
    // wouldn't fail those.
    test()->get('/documents/create')->assertInertia(fn ($page) => $page
        ->where('flash.uploadedImage.url', $uploadedImage['url'])
        ->where('flash.uploadedImage.alt', $uploadedImage['alt'])
        ->where('flash.uploadedImage.draftToken', $token)
    );
});

it('serves the stored draft image back from its tmp route, visible immediately in the editor', function () {
    $token = Str::uuid()->toString();

    test()->post('/documents/create/images', editorImageUploadPayload(['draft_token' => $token]));

    $uploadedImage = session('uploadedImage');

    $response = test()->get($uploadedImage['url']);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');
});

// --- Alt manquant ------------------------------------------------------------

it('rejects an image upload with no alt text and stores no file', function () {
    $token = Str::uuid()->toString();

    $response = test()->post('/documents/create/images', editorImageUploadPayload([
        'draft_token' => $token,
        'alt' => '',
    ]));

    $response->assertSessionHasErrors('alt');
    Storage::disk('local')->assertDirectoryEmpty("documents/tmp/{$token}");
});

// --- Fichier non-image --------------------------------------------------------

it('rejects a non-image file and stores nothing', function () {
    $token = Str::uuid()->toString();

    $response = test()->post('/documents/create/images', editorImageUploadPayload([
        'draft_token' => $token,
        'image' => UploadedFile::fake()->create('notes.pdf', 10),
    ]));

    $response->assertSessionHasErrors('image');
    Storage::disk('local')->assertDirectoryEmpty("documents/tmp/{$token}");
});

it('rejects an image over the 5MB limit', function () {
    $token = Str::uuid()->toString();

    // A real (tiny) image so the `image` rule itself passes — only its
    // reported size is inflated past the 5MB `max()` limit, isolating this
    // test to that one rule.
    $oversizedImage = UploadedFile::fake()->image('big.jpg', 10, 10)->size(5 * 1024 + 1);

    $response = test()->post('/documents/create/images', editorImageUploadPayload([
        'draft_token' => $token,
        'image' => $oversizedImage,
    ]));

    $response->assertSessionHasErrors('image');
});

it('rejects a request with no draft_token', function () {
    $response = test()->post('/documents/create/images', editorImageUploadPayload(['draft_token' => '']));

    $response->assertSessionHasErrors('draft_token');
});

it('rejects a request with a malformed (non-uuid) draft_token', function () {
    $response = test()->post('/documents/create/images', editorImageUploadPayload(['draft_token' => 'not-a-uuid']));

    $response->assertSessionHasErrors('draft_token');
});

// --- Service d'image finale (post-déplacement) --------------------------------

it('serves a document image from its final directory once moved there', function () {
    $document = Document::factory()->create();
    $filename = Str::uuid()->toString().'.jpg';

    Storage::disk('local')->put("documents/{$document->id}/images/{$filename}", 'fake-jpeg-bytes');

    $response = test()->get("/documents/{$document->id}/images/{$filename}");

    $response->assertOk();
});

it('returns 404 for a document image filename that does not exist', function () {
    $document = Document::factory()->create();

    $response = test()->get("/documents/{$document->id}/images/00000000-0000-0000-0000-000000000000.jpg");

    $response->assertNotFound();
});

it('returns 404 for a draft image that does not exist', function () {
    $token = Str::uuid()->toString();

    $response = test()->get("/documents/editor-images/tmp/{$token}/00000000-0000-0000-0000-000000000000.jpg");

    $response->assertNotFound();
});

// --- Segments de route contraints (anti path traversal) -----------------------

it('never matches the draft image route for a non-uuid token', function () {
    $response = test()->get('/documents/editor-images/tmp/not-a-uuid/00000000-0000-0000-0000-000000000000.jpg');

    $response->assertNotFound();
});

it('never matches the document image route for a filename shaped for path traversal', function () {
    $document = Document::factory()->create();

    $response = test()->get("/documents/{$document->id}/images/..%2f..%2f..%2fetc%2fpasswd");

    $response->assertNotFound();
});
