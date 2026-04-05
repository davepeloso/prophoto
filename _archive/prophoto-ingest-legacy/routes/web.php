<?php

use Illuminate\Support\Facades\Route;
use ProPhoto\Ingest\Http\Controllers\IngestController;
use ProPhoto\Ingest\Http\Controllers\IngestSettingsController;

Route::get('/', [IngestController::class, 'index'])->name('ingest.index');

Route::post('/upload', [IngestController::class, 'upload'])->name('ingest.upload');
Route::patch('/photos/{uuid}', [IngestController::class, 'update'])->name('ingest.update');
Route::post('/photos/batch', [IngestController::class, 'batchUpdate'])->name('ingest.batch');
Route::post('/photos/reorder', [IngestController::class, 'reorder'])->name('ingest.reorder');
Route::delete('/photos/{uuid}', [IngestController::class, 'destroy'])->name('ingest.destroy');
Route::post('/preview-status', [IngestController::class, 'previewStatus'])->name('ingest.preview-status');
Route::post('/enhance', [IngestController::class, 'enhance'])->name('ingest.enhance');

Route::post('/ingest', [IngestController::class, 'ingest'])->name('ingest.process');

Route::get('/tags', [IngestController::class, 'tags'])->name('ingest.tags');
Route::post('/tags', [IngestController::class, 'createTag'])->name('ingest.tags.create');
Route::post('/photos/{uuid}/tags', [IngestController::class, 'addTags'])->name('ingest.tags.add');
Route::put('/photos/{uuid}/tags', [IngestController::class, 'assignTags'])->name('ingest.tags.assign');
Route::delete('/photos/{uuid}/tags/{tagId}', [IngestController::class, 'removeTag'])->name('ingest.tags.remove');

Route::get('/settings', [IngestSettingsController::class, 'edit'])->name('ingest.settings.edit');
Route::patch('/settings', [IngestSettingsController::class, 'update'])->name('ingest.settings.update');
