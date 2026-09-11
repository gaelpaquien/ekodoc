<?php

use App\Http\Controllers\DocumentAttachmentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

// Matches exactly what UploadEditorImageAction/CreateDocumentAction's move
// step ever generate — a UUID filename with an extension — never anything
// a client could shape to escape its directory (Boundaries & Constraints,
// spec-2-2: no "/" or ".." in {token}/{filename}). A plain variable, not a
// `const` — this file is `require`d fresh per test, and a top-level
// `const` would fatal on the second declaration in the same process.
$editorImageFilenamePattern = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\.[a-zA-Z0-9]+';

Route::get('/', [DocumentController::class, 'index'])->name('documents.index');
Route::get('/recherche', [DocumentController::class, 'search'])->name('documents.search');
// Tag management surface (FR14, spec-3-5) — registered alongside the other
// top-level surfaces above, ahead of every /documents/* route below.
Route::get('/configuration', [TagController::class, 'index'])->name('tags.index');
Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
Route::patch('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
// Registered ahead of GET /documents/{document} — otherwise "create" would
// be captured by that route's model binding instead of reaching create().
Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
Route::post('/documents/create', [DocumentController::class, 'storeCreated'])->name('documents.storeCreated');
// Editor image upload/serve (spec-2-2) — registered alongside the editor's
// own routes above, ahead of GET /documents/{document}/images/{filename}
// below for the same reason "create" is registered ahead of {document}.
Route::post('/documents/create/images', [DocumentController::class, 'storeEditorImage'])->name('documents.editorImages.store');
Route::get('/documents/editor-images/tmp/{token}/{filename}', [DocumentController::class, 'serveDraftImage'])
    ->whereUuid('token')
    ->where('filename', $editorImageFilenamePattern)
    ->name('documents.editorImages.tmp');
// Draft attachment upload (spec-3-3) — registered alongside the editor
// image upload route above, ahead of GET /documents/{document} below for
// the same reason.
Route::post('/documents/create/attachments', [DocumentController::class, 'storeEditorAttachment'])->name('documents.editorAttachments.store');
Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
// Editing a previously created document (spec-2-3) — same distinct-suffix
// shape as preview/download/images below, so no ordering conflict with the
// bare GET/PATCH /documents/{document} routes on either side of it.
Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
Route::patch('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
// PDF export of a created document (spec-2-4) — same distinct-suffix shape
// as preview/download above, so no ordering conflict either.
Route::get('/documents/{document}/export/pdf', [DocumentController::class, 'exportPdf'])->name('documents.export.pdf');
// Word export of a created document (spec-2-5) — same distinct-suffix shape
// as the PDF export above, so no ordering conflict either.
Route::get('/documents/{document}/export/word', [DocumentController::class, 'exportWord'])->name('documents.export.word');
Route::get('/documents/{document}/images/{filename}', [DocumentController::class, 'serveDocumentImage'])
    ->where('filename', $editorImageFilenamePattern)
    ->name('documents.images.show');
Route::patch('/documents/{document}/tags', [DocumentController::class, 'updateTags'])->name('documents.tags.update');
// Immediate attach/detach/preview/download for an already-saved document
// (spec-3-3) — registered ahead of the bare DELETE /documents/{document}
// below, same distinct-suffix shape as preview/download/export above.
Route::post('/documents/{document}/attachments', [DocumentAttachmentController::class, 'store'])->name('documents.attachments.store');
Route::get('/documents/{document}/attachments/{attachment}/preview', [DocumentAttachmentController::class, 'preview'])->name('documents.attachments.preview');
Route::get('/documents/{document}/attachments/{attachment}/download', [DocumentAttachmentController::class, 'download'])->name('documents.attachments.download');
Route::delete('/documents/{document}/attachments/{attachment}', [DocumentAttachmentController::class, 'destroy'])->name('documents.attachments.destroy');
Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
