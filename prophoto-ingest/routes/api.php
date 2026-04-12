<?php

use Illuminate\Support\Facades\Route;
use ProPhoto\Ingest\Http\Controllers\IngestController;

/*
|--------------------------------------------------------------------------
| ProPhoto Ingest — API Routes
|--------------------------------------------------------------------------
|
| All ingest routes are prefixed with /api/ingest and protected by the
| 'auth:sanctum' middleware. The prefix and middleware can be overridden
| by the host application via config/prophoto-ingest.php.
|
| These routes are loaded in IngestServiceProvider::boot() via:
|   $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
|
| Story 1a.4 — Sprint 2
|
*/

// ── Inertia page (web middleware, not API) ────────────────────────────────────
Route::middleware(['web', 'auth'])
    ->group(function (): void {
        Route::get('/ingest', [IngestController::class, 'entrypoint'])
            ->name('prophoto.ingest.entrypoint');
    });

// ── JSON API endpoints ────────────────────────────────────────────────────────
Route::middleware(['api', 'auth:sanctum'])
    ->prefix('api/ingest')
    ->name('prophoto.ingest.')
    ->group(function (): void {

        // ── Calendar Matching + Session Creation ──────────────────────────────
        // Called immediately after MetadataExtractor.ts finishes on the frontend.
        // Returns ranked calendar matches and creates the UploadSession.
        Route::post('match-calendar', [IngestController::class, 'matchCalendar'])
            ->name('match-calendar');

        // ── Session Progress Polling ──────────────────────────────────────────
        // Frontend polls this while files are uploading in the background.
        Route::get('sessions/{sessionId}/progress', [IngestController::class, 'sessionProgress'])
            ->name('sessions.progress');

        // ── Session Confirmation ──────────────────────────────────────────────
        // Triggered when the user clicks "Confirm" in the gallery UI.
        // Dispatches downstream events for asset creation.
        Route::post('sessions/{sessionId}/confirm', [IngestController::class, 'confirmSession'])
            ->name('sessions.confirm');

        // ── Sprint 3: File Registration + Upload + Tagging ───────────────────

        // Register files (assigns UUIDs before upload begins)
        Route::post('sessions/{sessionId}/files', [IngestController::class, 'registerFiles'])
            ->name('sessions.files.register');

        // Binary file upload (called per-file by UploadManager XHR)
        Route::post('sessions/{sessionId}/upload', [IngestController::class, 'uploadFile'])
            ->name('sessions.upload');

        // Batch update: cull toggle + star rating
        Route::patch('sessions/{sessionId}/files/batch', [IngestController::class, 'batchUpdateFiles'])
            ->name('sessions.files.batch');

        // Per-file tagging
        Route::post('sessions/{sessionId}/files/{fileId}/tags', [IngestController::class, 'applyTag'])
            ->name('sessions.files.tags.apply');

        Route::delete('sessions/{sessionId}/files/{fileId}/tags/{tag}', [IngestController::class, 'removeTag'])
            ->name('sessions.files.tags.remove');

        // ── Sprint 4: Calendar unlink ─────────────────────────────────────────
        Route::delete('sessions/{sessionId}/unlink-calendar', [IngestController::class, 'unlinkCalendar'])
            ->name('sessions.unlink-calendar');

        // ── Sprint 5: Asset creation progress polling ─────────────────────────
        // Frontend polls this after "Confirm" while assets are being created.
        Route::get('sessions/{sessionId}/preview-status', [IngestController::class, 'previewStatus'])
            ->name('sessions.preview-status');
    });
