<?php

namespace ProPhoto\Ingest\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use ProPhoto\Ingest\Events\IngestSessionConfirmed;
use ProPhoto\Ingest\Models\IngestFile;
use ProPhoto\Ingest\Models\IngestImageTag;
use ProPhoto\Ingest\Models\UploadSession;
use ProPhoto\Ingest\Tests\TestCase;

/**
 * Sprint 7 — IngestController HTTP Feature Tests
 *
 * Tests all JSON API endpoints through the full HTTP stack.
 * Auth middleware is bypassed via withoutMiddleware() — we're testing
 * controller logic, not the auth layer, which belongs in the host app.
 *
 * Endpoints covered:
 *   - GET  sessions/{id}/progress
 *   - POST sessions/{id}/confirm
 *   - GET  sessions/{id}/preview-status
 *   - DELETE sessions/{id}/unlink-calendar
 *   - POST sessions/{id}/files           (registerFiles)
 *   - POST sessions/{id}/upload          (uploadFile)
 *   - POST sessions/{id}/files/{fid}/tags (applyTag)
 *   - DELETE sessions/{id}/files/{fid}/tags/{tag} (removeTag)
 *   - PATCH sessions/{id}/files/batch    (batchUpdateFiles)
 */
class IngestControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Event::fake([IngestSessionConfirmed::class]);
    }

    protected function defineRoutes($router): void
    {
        // Register ingest routes without the auth:sanctum middleware — we test
        // controller logic here, not the authentication layer.
        $router->middleware(['api'])
            ->prefix('api/ingest')
            ->group(function () use ($router) {
                $router->get('sessions/{sessionId}/progress',
                    [\ProPhoto\Ingest\Http\Controllers\IngestController::class, 'sessionProgress']);
                $router->post('sessions/{sessionId}/confirm',
                    [\ProPhoto\Ingest\Http\Controllers\IngestController::class, 'confirmSession']);
                $router->get('sessions/{sessionId}/preview-status',
                    [\ProPhoto\Ingest\Http\Controllers\IngestController::class, 'previewStatus']);
                $router->delete('sessions/{sessionId}/unlink-calendar',
                    [\ProPhoto\Ingest\Http\Controllers\IngestController::class, 'unlinkCalendar']);
                $router->post('sessions/{sessionId}/files',
                    [\ProPhoto\Ingest\Http\Controllers\IngestController::class, 'registerFiles']);
                $router->post('sessions/{sessionId}/upload',
                    [\ProPhoto\Ingest\Http\Controllers\IngestController::class, 'uploadFile']);
                $router->patch('sessions/{sessionId}/files/batch',
                    [\ProPhoto\Ingest\Http\Controllers\IngestController::class, 'batchUpdateFiles']);
                $router->post('sessions/{sessionId}/files/{fileId}/tags',
                    [\ProPhoto\Ingest\Http\Controllers\IngestController::class, 'applyTag']);
                $router->delete('sessions/{sessionId}/files/{fileId}/tags/{tag}',
                    [\ProPhoto\Ingest\Http\Controllers\IngestController::class, 'removeTag']);
            });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeSession(string $status = UploadSession::STATUS_UPLOADING): UploadSession
    {
        return UploadSession::create([
            'studio_id'  => 1,
            'user_id'    => 1,
            'status'     => $status,
            'file_count' => 0,
        ]);
    }

    private function makeFile(string $sessionId, string $status = IngestFile::STATUS_PENDING): IngestFile
    {
        return IngestFile::create([
            'upload_session_id' => $sessionId,
            'original_filename' => 'test.jpg',
            'file_size_bytes'   => 1024,
            'file_type'         => 'image/jpeg',
            'upload_status'     => $status,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GET /api/ingest/sessions/{id}/progress
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_session_progress_returns_200_with_progress_data(): void
    {
        $session = $this->makeSession();

        $this->getJson("/api/ingest/sessions/{$session->id}/progress")
            ->assertStatus(200)
            ->assertJsonStructure([
                'session_id', 'status', 'file_count',
                'completed_file_count', 'percent_complete', 'is_uploading',
            ])
            ->assertJsonFragment(['session_id' => $session->id]);
    }

    public function test_session_progress_returns_404_for_unknown_session(): void
    {
        $this->getJson('/api/ingest/sessions/non-existent-uuid/progress')
            ->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /api/ingest/sessions/{id}/confirm
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_confirm_session_transitions_status_to_confirmed(): void
    {
        $session = $this->makeSession(UploadSession::STATUS_UPLOADING);

        $this->postJson("/api/ingest/sessions/{$session->id}/confirm")
            ->assertStatus(200)
            ->assertJsonFragment(['status' => UploadSession::STATUS_CONFIRMED]);

        $this->assertEquals(UploadSession::STATUS_CONFIRMED, $session->fresh()->status);
    }

    public function test_confirm_session_dispatches_ingest_session_confirmed_event(): void
    {
        $session = $this->makeSession(UploadSession::STATUS_UPLOADING);

        $this->postJson("/api/ingest/sessions/{$session->id}/confirm");

        Event::assertDispatched(IngestSessionConfirmed::class, fn ($e) =>
            $e->sessionId === $session->id
        );
    }

    public function test_confirm_session_accepts_optional_gallery_id(): void
    {
        $session = $this->makeSession(UploadSession::STATUS_UPLOADING);

        $this->postJson("/api/ingest/sessions/{$session->id}/confirm", ['gallery_id' => 42])
            ->assertStatus(200)
            ->assertJsonFragment(['gallery_id' => 42]);
    }

    public function test_confirm_session_returns_422_when_not_confirmable(): void
    {
        $session = $this->makeSession(UploadSession::STATUS_INITIATED);

        $this->postJson("/api/ingest/sessions/{$session->id}/confirm")
            ->assertStatus(422);
    }

    public function test_confirm_session_includes_already_processed_false_on_fresh_confirm(): void
    {
        $session = $this->makeSession(UploadSession::STATUS_UPLOADING);

        $this->postJson("/api/ingest/sessions/{$session->id}/confirm")
            ->assertStatus(200)
            ->assertJsonFragment(['already_processed' => false]);
    }

    public function test_confirm_session_is_idempotent_when_already_confirmed(): void
    {
        // Pre-set the session to confirmed (as if a prior call already ran)
        $session = $this->makeSession(UploadSession::STATUS_CONFIRMED);

        $this->postJson("/api/ingest/sessions/{$session->id}/confirm")
            ->assertStatus(200)
            ->assertJsonFragment([
                'session_id'        => $session->id,
                'status'            => UploadSession::STATUS_CONFIRMED,
                'already_processed' => true,
            ]);

        // Critical: event must NOT be dispatched again
        Event::assertNotDispatched(IngestSessionConfirmed::class);
    }

    public function test_confirm_session_is_idempotent_when_already_completed(): void
    {
        // Pre-set the session to completed (as in the sandbox smoke-test scenario)
        $session = $this->makeSession(UploadSession::STATUS_COMPLETED);

        $this->postJson("/api/ingest/sessions/{$session->id}/confirm")
            ->assertStatus(200)
            ->assertJsonFragment([
                'session_id'        => $session->id,
                'status'            => UploadSession::STATUS_COMPLETED,
                'already_processed' => true,
            ]);

        // Critical: event must NOT be dispatched again (would duplicate assets)
        Event::assertNotDispatched(IngestSessionConfirmed::class);
    }

    public function test_confirm_session_still_rejects_failed_status(): void
    {
        // failed is NOT in the idempotency set — it requires manual intervention,
        // not a silent success. The 422 guides the caller to investigate.
        $session = $this->makeSession(UploadSession::STATUS_FAILED);

        $this->postJson("/api/ingest/sessions/{$session->id}/confirm")
            ->assertStatus(422)
            ->assertJsonFragment(['error' => "Cannot confirm session [{$session->id}] with status [failed]."]);
    }

    public function test_confirm_session_still_rejects_initiated_status(): void
    {
        // initiated means no files have been registered/uploaded yet — not confirmable
        $session = $this->makeSession(UploadSession::STATUS_INITIATED);

        $this->postJson("/api/ingest/sessions/{$session->id}/confirm")
            ->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GET /api/ingest/sessions/{id}/preview-status
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_preview_status_returns_200_with_expected_shape(): void
    {
        $session = $this->makeSession(UploadSession::STATUS_CONFIRMED);

        $this->getJson("/api/ingest/sessions/{$session->id}/preview-status")
            ->assertStatus(200)
            ->assertJsonStructure([
                'session_id', 'session_status', 'total_files',
                'assets_created', 'is_complete', 'thumbnails',
            ]);
    }

    public function test_preview_status_is_complete_true_when_session_completed(): void
    {
        $session = $this->makeSession(UploadSession::STATUS_COMPLETED);

        $this->getJson("/api/ingest/sessions/{$session->id}/preview-status")
            ->assertStatus(200)
            ->assertJsonFragment(['is_complete' => true]);
    }

    public function test_preview_status_returns_404_for_unknown_session(): void
    {
        $this->getJson('/api/ingest/sessions/bad-uuid/preview-status')
            ->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DELETE /api/ingest/sessions/{id}/unlink-calendar
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_unlink_calendar_clears_all_calendar_fields(): void
    {
        $session = UploadSession::create([
            'studio_id'                 => 1,
            'user_id'                   => 1,
            'status'                    => UploadSession::STATUS_UPLOADING,
            'file_count'                => 0,
            'calendar_event_id'         => 'google-evt-123',
            'calendar_provider'         => 'google',
            'calendar_match_confidence' => 0.9,
        ]);

        $this->deleteJson("/api/ingest/sessions/{$session->id}/unlink-calendar")
            ->assertStatus(200)
            ->assertJsonFragment(['calendar_event_id' => null]);

        $session->refresh();
        $this->assertNull($session->calendar_event_id);
        $this->assertNull($session->calendar_provider);
    }

    public function test_unlink_calendar_returns_404_for_unknown_session(): void
    {
        $this->deleteJson('/api/ingest/sessions/bad-uuid/unlink-calendar')
            ->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /api/ingest/sessions/{id}/files  (registerFiles)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_register_files_creates_ingest_file_records(): void
    {
        $session = $this->makeSession();

        $this->postJson("/api/ingest/sessions/{$session->id}/files", [
            'files' => [
                ['filename' => 'IMG_001.jpg', 'file_size' => 2048, 'file_type' => 'image/jpeg', 'exif' => []],
                ['filename' => 'IMG_002.jpg', 'file_size' => 3072, 'file_type' => 'image/jpeg', 'exif' => []],
            ],
        ])
            ->assertStatus(201)
            ->assertJsonStructure(['files' => [['file_id', 'filename']]]);

        $this->assertEquals(2, IngestFile::where('upload_session_id', $session->id)->count());
    }

    public function test_register_files_auto_applies_exif_metadata_tags(): void
    {
        $session = $this->makeSession();

        $this->postJson("/api/ingest/sessions/{$session->id}/files", [
            'files' => [[
                'filename'  => 'IMG_EXIF.jpg',
                'file_size' => 1024,
                'file_type' => 'image/jpeg',
                'exif'      => ['iso' => 800, 'aperture' => 2.8, 'focalLength' => 85],
            ]],
        ]);

        $file = IngestFile::where('upload_session_id', $session->id)->first();
        $tags = IngestImageTag::where('ingest_file_id', $file->id)->pluck('tag')->toArray();

        $this->assertContains('iso-800', $tags);
        $this->assertContains('f2.8', $tags);
        $this->assertContains('85mm', $tags);
    }

    public function test_register_files_returns_404_for_unknown_session(): void
    {
        $this->postJson('/api/ingest/sessions/bad-uuid/files', [
            'files' => [
                ['filename' => 'IMG_001.jpg', 'file_size' => 1024, 'file_type' => 'image/jpeg'],
            ],
        ])
            ->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /api/ingest/sessions/{id}/upload  (uploadFile)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_upload_file_stores_file_and_marks_completed(): void
    {
        $session = $this->makeSession();
        $file    = $this->makeFile($session->id);

        // Use post() not postJson() — file uploads must be multipart, not JSON
        $this->post("/api/ingest/sessions/{$session->id}/upload", [
            'file_id' => $file->id,
            'file'    => UploadedFile::fake()->image('test.jpg', 400, 300),
        ])
            ->assertStatus(200)
            ->assertJsonFragment(['file_id' => $file->id, 'status' => 'uploaded']);

        $this->assertEquals(IngestFile::STATUS_COMPLETED, $file->fresh()->upload_status);
    }

    public function test_upload_file_returns_404_when_file_belongs_to_different_session(): void
    {
        $session1 = $this->makeSession();
        $session2 = $this->makeSession();
        $file     = $this->makeFile($session2->id);

        $this->post("/api/ingest/sessions/{$session1->id}/upload", [
            'file_id' => $file->id,
            'file'    => UploadedFile::fake()->image('test.jpg'),
        ])
            ->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // POST /api/ingest/sessions/{id}/files/{fileId}/tags  (applyTag)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_apply_tag_creates_tag_and_returns_201(): void
    {
        $session = $this->makeSession();
        $file    = $this->makeFile($session->id);

        $this->postJson("/api/ingest/sessions/{$session->id}/files/{$file->id}/tags", [
            'tag' => 'Wedding', 'tag_type' => 'user',
        ])
            ->assertStatus(201)
            ->assertJsonFragment(['tag' => 'wedding', 'tag_type' => 'user']);

        $this->assertEquals(1, IngestImageTag::where('ingest_file_id', $file->id)->count());
    }

    public function test_apply_tag_is_idempotent_on_duplicate(): void
    {
        $session = $this->makeSession();
        $file    = $this->makeFile($session->id);

        $this->postJson("/api/ingest/sessions/{$session->id}/files/{$file->id}/tags",
            ['tag' => 'outdoor', 'tag_type' => 'user']);
        $this->postJson("/api/ingest/sessions/{$session->id}/files/{$file->id}/tags",
            ['tag' => 'outdoor', 'tag_type' => 'user']);

        $this->assertEquals(1,
            IngestImageTag::where('ingest_file_id', $file->id)->where('tag', 'outdoor')->count()
        );
    }

    public function test_apply_tag_returns_404_for_file_in_different_session(): void
    {
        $session1 = $this->makeSession();
        $session2 = $this->makeSession();
        $file     = $this->makeFile($session2->id);

        $this->postJson("/api/ingest/sessions/{$session1->id}/files/{$file->id}/tags",
            ['tag' => 'test', 'tag_type' => 'user'])
            ->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DELETE /api/ingest/sessions/{id}/files/{fileId}/tags/{tag}  (removeTag)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_remove_tag_deletes_tag_and_returns_204(): void
    {
        $session = $this->makeSession();
        $file    = $this->makeFile($session->id);

        IngestImageTag::create([
            'ingest_file_id' => $file->id, 'tag' => 'sunset', 'tag_type' => 'user',
        ]);

        $this->deleteJson("/api/ingest/sessions/{$session->id}/files/{$file->id}/tags/sunset")
            ->assertStatus(204);

        $this->assertEquals(0, IngestImageTag::where('ingest_file_id', $file->id)->count());
    }

    public function test_remove_tag_returns_204_even_when_tag_never_existed(): void
    {
        $session = $this->makeSession();
        $file    = $this->makeFile($session->id);

        $this->deleteJson("/api/ingest/sessions/{$session->id}/files/{$file->id}/tags/ghost-tag")
            ->assertStatus(204);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PATCH /api/ingest/sessions/{id}/files/batch  (batchUpdateFiles)
    // ═══════════════════════════════════════════════════════════════════════════

    public function test_batch_update_culls_files_in_a_single_bulk_query(): void
    {
        $session = $this->makeSession();
        $file1   = $this->makeFile($session->id);
        $file2   = $this->makeFile($session->id);

        $this->patchJson("/api/ingest/sessions/{$session->id}/files/batch", [
            'ids'     => [$file1->id, $file2->id],
            'updates' => ['culled' => true],
        ])
            ->assertStatus(200)
            ->assertJsonFragment(['updated' => 2]);

        $this->assertTrue((bool) $file1->fresh()->culled);
        $this->assertTrue((bool) $file2->fresh()->culled);
    }

    public function test_batch_update_sets_star_rating(): void
    {
        $session = $this->makeSession();
        $file    = $this->makeFile($session->id);

        $this->patchJson("/api/ingest/sessions/{$session->id}/files/batch", [
            'ids'     => [$file->id],
            'updates' => ['rating' => 4],
        ])
            ->assertStatus(200)
            ->assertJsonFragment(['updated' => 1]);

        $this->assertEquals(4, $file->fresh()->rating);
    }

    public function test_batch_update_clamps_rating_to_max_5(): void
    {
        $session = $this->makeSession();
        $file    = $this->makeFile($session->id);

        $this->patchJson("/api/ingest/sessions/{$session->id}/files/batch", [
            'ids' => [$file->id], 'updates' => ['rating' => 99],
        ]);

        $this->assertEquals(5, $file->fresh()->rating);
    }

    public function test_batch_update_ignores_ids_from_other_sessions(): void
    {
        $session1    = $this->makeSession();
        $session2    = $this->makeSession();
        $foreignFile = $this->makeFile($session2->id);

        $this->patchJson("/api/ingest/sessions/{$session1->id}/files/batch", [
            'ids'     => [$foreignFile->id],
            'updates' => ['culled' => true],
        ])
            ->assertStatus(200)
            ->assertJsonFragment(['updated' => 0]);

        $this->assertFalse((bool) $foreignFile->fresh()->culled);
    }

    public function test_batch_update_returns_zero_for_empty_ids(): void
    {
        $session = $this->makeSession();

        $this->patchJson("/api/ingest/sessions/{$session->id}/files/batch", [
            'ids' => [], 'updates' => ['culled' => true],
        ])
            ->assertStatus(200)
            ->assertJsonFragment(['updated' => 0]);
    }
}
