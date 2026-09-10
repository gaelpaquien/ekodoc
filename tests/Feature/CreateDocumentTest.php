<?php

use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function createDocumentPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Compte rendu réunion',
        'content_html' => '<h1>Compte rendu</h1><p>Décisions prises en réunion.</p>',
    ], $overrides);
}

/**
 * Uploads one draft image the same way the editor does, and returns its
 * flashed url/alt/filename — the same payload the editor's flash watch
 * would insert into `content_html` before "Enregistrer" is ever clicked.
 */
function uploadDraftImage(string $draftToken): array
{
    test()->post('/documents/create/images', [
        'draft_token' => $draftToken,
        'image' => UploadedFile::fake()->image('photo.jpg', 200, 200),
        'alt' => 'Photo de test',
    ]);

    return session('uploadedImage');
}

/**
 * Uploads one draft attachment the same way the editor does, and returns
 * its flashed filename/original_filename/mime_type — the same payload the
 * editor's AttachmentsPanel would keep in `draft_attachments` before
 * "Enregistrer" is ever clicked (spec-3-3).
 */
function uploadDraftAttachment(string $draftToken, string $filename = 'annexe.pdf', string $fixture = 'sample.pdf'): array
{
    $file = UploadedFile::fake()->createWithContent($filename, file_get_contents(__DIR__.'/../Fixtures/'.$fixture));

    test()->post('/documents/create/attachments', [
        'draft_token' => $draftToken,
        'file' => $file,
    ]);

    return session('uploadedAttachment');
}

// --- Enregistrement, tags déjà choisis ---------------------------------

it('creates a document with the chosen tags, derives extracted_text, and redirects to the document page', function () {
    $tag = Tag::factory()->create(['name' => 'Comptes rendus']);

    $response = test()->post('/documents/create', createDocumentPayload(['tag_ids' => [$tag->id]]));

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->source)->toBe(DocumentSource::Created);
    expect($document->title)->toBe('Compte rendu réunion');
    expect($document->content_html)->toBe('<h1>Compte rendu</h1><p>Décisions prises en réunion.</p>');
    expect($document->extracted_text)->toBe('Compte rendu Décisions prises en réunion.');
    expect($document->extraction_status)->toBe(ExtractionStatus::Completed);
    expect($document->file_path)->toBeNull();
    expect($document->mime_type)->toBeNull();
    expect($document->tags->pluck('id')->all())->toBe([$tag->id]);
});

// --- Enregistrement, aucun tag renseigné -------------------------------

it('creates a document with no tag chosen, leaving it with zero tags', function () {
    $response = test()->post('/documents/create', createDocumentPayload());

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    // CreateDocumentAction itself must never touch document_tag —
    // SyncDocumentTagsAction remains the sole write point, same guarantee
    // already enforced for ImportDocumentAction.
    expect($document->tags)->toHaveCount(0);
});

// --- Titre vide --------------------------------------------------------------

it('rejects an empty title, creates no document, and surfaces the error under the title field', function () {
    $response = test()->post('/documents/create', createDocumentPayload(['title' => '']));

    $response->assertSessionHasErrors('title');
    expect(Document::count())->toBe(0);
});

it('rejects a request with no content_html and creates no document', function () {
    $response = test()->post('/documents/create', createDocumentPayload(['content_html' => '']));

    $response->assertSessionHasErrors('content_html');
    expect(Document::count())->toBe(0);
});

it('rejects an invalid tag id and creates no document', function () {
    $response = test()->post('/documents/create', createDocumentPayload(['tag_ids' => [999999]]));

    $response->assertSessionHasErrors('tag_ids.0');
    expect(Document::count())->toBe(0);
});

// --- Contenu HTML uniquement des balises (aucun texte) ----------------------

it('creates a document whose content is tag-only with no text, storing a null extracted_text', function () {
    $response = test()->post('/documents/create', createDocumentPayload([
        'content_html' => '<table><tbody><tr><td></td><td></td></tr></tbody></table><ul><li></li></ul>',
    ]));

    $document = Document::sole();

    $response->assertRedirect("/documents/{$document->id}");
    expect($document->extracted_text)->toBeNull();
    expect($document->extraction_status)->toBe(ExtractionStatus::Completed);
});

// --- Document créé retrouvable -----------------------------------------------

it('renders the created document on its detail page with content_html and source created', function () {
    test()->post('/documents/create', createDocumentPayload());

    $document = Document::sole();

    $response = test()->get("/documents/{$document->id}");

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Show')
        ->where('document.id', $document->id)
        ->where('document.source', 'created')
        ->where('document.content_html', $document->content_html)
    );
});

it('lists the created document on the library index page', function () {
    test()->post('/documents/create', createDocumentPayload(['title' => 'Notes de projet']));

    $document = Document::sole();

    $response = test()->get('/');

    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Index')
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
        ->where('documents.0.title', 'Notes de projet')
    );
});

it('is immediately filterable via type=created alongside imported documents (Story 1.7)', function () {
    test()->post('/documents/create', createDocumentPayload());
    Document::factory()->create(); // an imported document, excluded by the filter

    $response = test()->get('/?type[]=created');

    $response->assertInertia(fn ($page) => $page
        ->has('documents', 1)
        ->where('documents.0.source', 'created')
    );
});

it('is immediately searchable via its derived extracted_text (Story 1.6)', function () {
    test()->post('/documents/create', createDocumentPayload([
        'content_html' => '<p>Compte rendu du budget trimestriel</p>',
    ]));

    $document = Document::sole();

    $response = test()->get('/?search=trimestriel');

    $response->assertInertia(fn ($page) => $page
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
    );
});

// --- Sanitization -------------------------------------------------------------

it('strips disallowed tags and all attributes from content_html before storing it', function () {
    test()->post('/documents/create', createDocumentPayload([
        'content_html' => '<script>alert(1)</script><p onclick="evil()" class="x">Texte</p><img src=x onerror=alert(2)>',
    ]));

    $document = Document::sole();

    expect($document->content_html)->toBe('<p>Texte</p>');
    expect($document->extracted_text)->toBe('Texte');
});

// --- Éditeur -------------------------------------------------------------------

it('renders the empty editor page at GET /documents/create', function () {
    $response = test()->get('/documents/create');

    $response->assertInertia(fn ($page) => $page->component('Documents/Editor'));
});

// --- Image insérée en brouillon, déplacement + réécriture au save -----------

it('moves a draft image into the document directory and rewrites content_html with its final url', function () {
    Storage::fake('local');

    $draftToken = Str::uuid()->toString();
    $uploadedImage = uploadDraftImage($draftToken);

    $response = test()->post('/documents/create', createDocumentPayload([
        'content_html' => "<p>Voici le schéma :</p><img src=\"{$uploadedImage['url']}\" alt=\"{$uploadedImage['alt']}\">",
        'draft_token' => $draftToken,
    ]));

    $document = Document::sole();
    $response->assertRedirect("/documents/{$document->id}");

    $finalSrc = "/documents/{$document->id}/images/{$uploadedImage['filename']}";
    expect($document->content_html)->toContain("<img src=\"{$finalSrc}\" alt=\"{$uploadedImage['alt']}\">");

    Storage::disk('local')->assertExists("documents/{$document->id}/images/{$uploadedImage['filename']}");
    Storage::disk('local')->assertMissing("documents/tmp/{$draftToken}/images/{$uploadedImage['filename']}");

    // Visible et fonctionnelle sur la Fiche document, servie par la route
    // applicative dédiée (Acceptance Criteria, spec-2-2).
    test()->get($finalSrc)->assertOk();
});

it('creates a document with no image ever inserted as a no-op on the (never-created) tmp directory', function () {
    Storage::fake('local');

    $draftToken = Str::uuid()->toString();

    $response = test()->post('/documents/create', createDocumentPayload(['draft_token' => $draftToken]));

    $document = Document::sole();
    $response->assertRedirect("/documents/{$document->id}");
    Storage::disk('local')->assertDirectoryEmpty("documents/{$document->id}");
});

it('drops an <img> tag whose src does not match this draft\'s own tmp image url (forged content_html)', function () {
    Storage::fake('local');

    $response = test()->post('/documents/create', createDocumentPayload([
        'content_html' => '<p>Texte</p><img src="https://evil.example/x.jpg" alt="x" onerror="alert(1)">',
    ]));

    $document = Document::sole();
    $response->assertRedirect("/documents/{$document->id}");
    expect($document->content_html)->toBe('<p>Texte</p>');
});

it('drops an <img> tag pointing at a different draft\'s tmp directory (cross-token forgery)', function () {
    Storage::fake('local');

    $ownToken = Str::uuid()->toString();
    $otherToken = Str::uuid()->toString();
    $otherImage = uploadDraftImage($otherToken);

    $response = test()->post('/documents/create', createDocumentPayload([
        'content_html' => "<p>Texte</p><img src=\"{$otherImage['url']}\" alt=\"vol\">",
        'draft_token' => $ownToken,
    ]));

    $document = Document::sole();
    $response->assertRedirect("/documents/{$document->id}");
    expect($document->content_html)->toBe('<p>Texte</p>');
    // The other draft's own image is left untouched — only this document's
    // own draft directory is ever a relocation target.
    Storage::disk('local')->assertExists("documents/tmp/{$otherToken}/images/{$otherImage['filename']}");
});

// --- Pièces jointes en attente, relocalisation au save (spec-3-3) -----------

it('relocates a kept draft attachment into the document directory and creates its row, dispatching extraction after commit', function () {
    Queue::fake();
    Storage::fake('local');

    $draftToken = Str::uuid()->toString();
    $uploadedAttachment = uploadDraftAttachment($draftToken, 'annexe.pdf', 'sample.pdf');

    $response = test()->post('/documents/create', createDocumentPayload([
        'draft_token' => $draftToken,
        'draft_attachments' => [[
            'filename' => $uploadedAttachment['filename'],
            'original_filename' => $uploadedAttachment['original_filename'],
        ]],
    ]));

    $document = Document::sole();
    $response->assertRedirect("/documents/{$document->id}");

    $attachment = DocumentAttachment::sole();
    expect($attachment->document_id)->toBe($document->id);
    expect($attachment->original_filename)->toBe('annexe.pdf');
    expect($attachment->file_path)->toBe("documents/{$document->id}/attachments/{$uploadedAttachment['filename']}");
    expect($attachment->mime_type)->toBe('application/pdf');
    expect($attachment->extraction_status)->toBe(ExtractionStatus::Pending);

    Storage::disk('local')->assertExists($attachment->file_path);
    Storage::disk('local')->assertMissing("documents/tmp/{$draftToken}/attachments/{$uploadedAttachment['filename']}");

    Queue::assertPushed(ExtractDocumentTextJob::class, fn ($job) => $job->target->is($attachment));
});

it('relocates two kept draft attachments in the same transaction', function () {
    Storage::fake('local');

    $draftToken = Str::uuid()->toString();
    $first = uploadDraftAttachment($draftToken, 'annexe-1.pdf', 'sample.pdf');
    $second = uploadDraftAttachment($draftToken, 'annexe-2.docx', 'sample.docx');

    $response = test()->post('/documents/create', createDocumentPayload([
        'draft_token' => $draftToken,
        'draft_attachments' => [
            ['filename' => $first['filename'], 'original_filename' => $first['original_filename']],
            ['filename' => $second['filename'], 'original_filename' => $second['original_filename']],
        ],
    ]));

    $document = Document::sole();
    $response->assertRedirect("/documents/{$document->id}");
    expect(DocumentAttachment::count())->toBe(2);
    expect($document->attachments->pluck('original_filename')->sort()->values()->all())
        ->toBe(['annexe-1.pdf', 'annexe-2.docx']);
});

it('leaves an uploaded draft attachment in tmp/, creating no row, when it is never listed in draft_attachments', function () {
    Storage::fake('local');

    $draftToken = Str::uuid()->toString();
    $uploadedAttachment = uploadDraftAttachment($draftToken);

    $response = test()->post('/documents/create', createDocumentPayload(['draft_token' => $draftToken]));

    $document = Document::sole();
    $response->assertRedirect("/documents/{$document->id}");
    expect(DocumentAttachment::count())->toBe(0);
    Storage::disk('local')->assertExists("documents/tmp/{$draftToken}/attachments/{$uploadedAttachment['filename']}");
});

it('is immediately searchable through its relocated draft attachment\'s extracted text', function () {
    Storage::fake('local');

    $draftToken = Str::uuid()->toString();
    $uploadedAttachment = uploadDraftAttachment($draftToken, 'annexe.pdf', 'sample.pdf');

    test()->post('/documents/create', createDocumentPayload([
        'draft_token' => $draftToken,
        'draft_attachments' => [[
            'filename' => $uploadedAttachment['filename'],
            'original_filename' => $uploadedAttachment['original_filename'],
        ]],
    ]));

    $document = Document::sole();
    $document->refresh();
    expect($document->attachments_extracted_text)->toContain('EkoDoc sample pdf content');

    $response = test()->get('/?search=EkoDoc');
    $response->assertInertia(fn ($page) => $page
        ->has('documents', 1)
        ->where('documents.0.id', $document->id)
    );
});
