<?php

use Illuminate\Support\Facades\Route;
use ProPhoto\Gallery\Http\Controllers\DownloadController;
use ProPhoto\Gallery\Http\Controllers\GalleryViewerController;
use ProPhoto\Gallery\Http\Controllers\IdentityGateController;
use ProPhoto\Gallery\Http\Controllers\ProofingActionController;

/*
|--------------------------------------------------------------------------
| Gallery Public Web Routes
|--------------------------------------------------------------------------
|
| Public-facing routes that do NOT require authentication.
| The /g/{token} pattern resolves a share token to a gallery viewer.
|
*/

Route::middleware(['web'])->group(function () {
    // Gallery viewer — resolves share token to the correct view
    Route::get('g/{token}', [GalleryViewerController::class, 'show'])
        ->name('gallery.viewer.show');

    // Identity gate — email confirmation for proofing galleries
    Route::post('g/{token}/confirm', [IdentityGateController::class, 'confirmIdentity'])
        ->name('gallery.viewer.confirm');

    // Proofing actions — approval pipeline endpoints (Story 3.4)
    Route::post('g/{token}/approve/{image}', [ProofingActionController::class, 'approve'])
        ->name('gallery.viewer.approve');

    Route::post('g/{token}/pending/{image}', [ProofingActionController::class, 'pending'])
        ->name('gallery.viewer.pending');

    Route::post('g/{token}/clear/{image}', [ProofingActionController::class, 'clear'])
        ->name('gallery.viewer.clear');

    Route::post('g/{token}/rate/{image}', [ProofingActionController::class, 'rate'])
        ->name('gallery.viewer.rate');

    Route::post('g/{token}/submit', [ProofingActionController::class, 'submit'])
        ->name('gallery.viewer.submit');

    // Story 6.1 — Public image download (share-scoped, enforces can_download + max_downloads)
    Route::get('g/{token}/download/{image}', [DownloadController::class, 'download'])
        ->name('gallery.viewer.download')
        ->middleware('throttle:60,1');
});
