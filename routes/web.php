<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentController::class, 'index'])->name('documents.index');
Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
Route::patch('/documents/{document}/category', [DocumentController::class, 'updateCategory'])->name('documents.category.update');

Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
