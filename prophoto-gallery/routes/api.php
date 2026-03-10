<?php

use Illuminate\Support\Facades\Route;
use ProPhoto\Gallery\Http\Controllers\GalleryController;
use ProPhoto\Gallery\Http\Controllers\ImageController;
use ProPhoto\Gallery\Http\Controllers\CollectionController;
use ProPhoto\Gallery\Http\Controllers\ShareController;

/*
|--------------------------------------------------------------------------
| Gallery API Routes
|--------------------------------------------------------------------------
|
| These routes use Sanctum authentication and are prefixed with /api/galleries
|
*/

// Public share link access (no auth required)
Route::get('shares/{token}', [ShareController::class, 'show'])
    ->name('api.shares.show');

// Protected routes (require authentication)
Route::middleware(['auth:sanctum'])->group(function () {

    // Gallery routes
    Route::apiResource('galleries', GalleryController::class);
    Route::get('galleries/{gallery}/stats', [GalleryController::class, 'stats'])
        ->name('api.galleries.stats');

    // Image routes
    Route::prefix('galleries/{gallery}')->group(function () {
        Route::get('images', [ImageController::class, 'index'])
            ->name('api.images.index');
        Route::post('images', [ImageController::class, 'store'])
            ->name('api.images.store');
        Route::get('images/{image}', [ImageController::class, 'show'])
            ->name('api.images.show');
        Route::get('images/{image}/download', [ImageController::class, 'download'])
            ->name('api.images.download');
        Route::post('images/{image}/rate', [ImageController::class, 'rate'])
            ->name('api.images.rate');
        Route::post('images/{image}/approve', [ImageController::class, 'approve'])
            ->name('api.images.approve');

        // Share link management
        Route::get('shares', [ShareController::class, 'index'])
            ->name('api.gallery.shares.index');
        Route::post('shares', [ShareController::class, 'store'])
            ->name('api.gallery.shares.store');
        Route::delete('shares/{share}', [ShareController::class, 'destroy'])
            ->name('api.gallery.shares.destroy');
        Route::get('shares/{share}/analytics', [ShareController::class, 'analytics'])
            ->name('api.gallery.shares.analytics');
    });

    // Collection routes
    Route::apiResource('collections', CollectionController::class);
    Route::post('collections/{collection}/galleries', [CollectionController::class, 'addGalleries'])
        ->name('api.collections.addGalleries');
    Route::delete('collections/{collection}/galleries', [CollectionController::class, 'removeGalleries'])
        ->name('api.collections.removeGalleries');
});
