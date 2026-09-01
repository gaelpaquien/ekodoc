<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentController::class, 'index'])->name('documents.index');
Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
