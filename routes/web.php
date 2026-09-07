<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

// Matches exactly what UploadEditorImageAction/CreateDocumentAction's move
// step ever generate — a UUID filename with an extension — never anything
// a client could shape to escape its directory (Boundaries & Constraints,
// spec-2-2: no "/" or ".." in {token}/{filename}). A plain variable, not a
// `const` — this file is `require`d fresh per test, and a top-level
// `const` would fatal on the second declaration in the same process.
$editorImageFilenamePattern = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\.[a-zA-Z0-9]+';

Route::get('/', [DocumentController::class, 'index'])->name('documents.index');
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
Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
// Editing a previously created document (spec-2-3) — same distinct-suffix
// shape as preview/download/images below, so no ordering conflict with the
// bare GET/PATCH /documents/{document} routes on either side of it.
Route::get('/documents/{document}/edit', [DocumentController::class, 'edit'])->name('documents.edit');
Route::patch('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
Route::get('/documents/{document}/images/{filename}', [DocumentController::class, 'serveDocumentImage'])
    ->where('filename', $editorImageFilenamePattern)
    ->name('documents.images.show');
Route::patch('/documents/{document}/category', [DocumentController::class, 'updateCategory'])->name('documents.category.update');
Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
