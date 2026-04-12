<?php

namespace ProPhoto\Ingest\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use ProPhoto\Ingest\Events\IngestSessionConfirmed;
use ProPhoto\Ingest\IngestServiceProvider;
use ProPhoto\Ingest\Models\IngestFile;
use ProPhoto\Ingest\Models\IngestImageTag;
use ProPhoto\Ingest\Models\UploadSession;
use ProPhoto\Ingest\Services\UploadSessionService;

/**
 * UploadSessionService Unit Tests
 * Stories 1a.5 + 1a.6 — Sprint 1
 */
class UploadSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UploadSessionService $service;

    protected function getPackageProviders($app): array
    {
        return [IngestServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([IngestSessionConfirmed::class]);
        $this->service = $this->app->make(UploadSessionService::class);
    }

    // ─── createSession() ──────────────────────────────────────────────────────

    /** @test */
    public function it_creates_a_session_with_initiated_status(): void
    {
        $session = $this->service->createSession([
            'studio_id' => 1,
            'user_id'   => 1,
        ]);

        $this->assertInstanceOf(UploadSession::class, $session);
        $this->assertEquals(UploadSession::STATUS_INITIATED, $session->status);
        $this->assertNotEmpty($session->id);
        $this->assertEquals(0, $session->file_count);
    }

    /** @test */
    public function it_creates_a_session_with_calendar_match(): void
    {
        $session = $this->service->createSession([
            'studio_id'                 => 1,
            'user_id'                   => 1,
            'calendar_event_id'         => 'google-event-abc123',
            'calendar_provider'         => 'google',
            'calendar_match_confidence' => 0.92,
            'calendar_match_evidence'   => [
                'time_proximity_score'     => 0.98,
                'location_proximity_score' => 0.85,
                'batch_coherence_score'    => 0.95,
            ],
        ]);

        $this->assertTrue($session->hasCalendarMatch());
        $this->assertEquals('google-event-abc123', $session->calendar_event_id);
        $this->assertEquals(0.92, $session->calendar_match_confidence);
        $this->assertEquals('google', $session->calendar_provider);
    }

    /** @test */
    public function it_creates_a_session_without_calendar_match(): void
    {
        $session = $this->service->createSession([
            'studio_id' => 1,
            'user_id'   => 1,
        ]);

        $this->assertFalse($session->hasCalendarMatch());
        $this->assertNull($session->calendar_event_id);
    }

    /** @test */
    public function it_generates_a_uuid_for_session_id(): void
    {
        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $session->id
        );
    }

    // ─── registerFile() ───────────────────────────────────────────────────────

    /** @test */
    public function it_registers_a_file_and_increments_session_file_count(): void
    {
        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);

        $this->service->registerFile($session->id, [
            'original_filename' => 'IMG_001.CR2',
            'file_size_bytes'   => 52_000_000,
            'file_type'         => 'raw',
            'mime_type'         => 'image/x-canon-cr2',
        ]);

        $session->refresh();
        $this->assertEquals(1, $session->file_count);
        $this->assertEquals(52_000_000, $session->total_size_bytes);
    }

    /** @test */
    public function it_auto_applies_metadata_tags_on_file_registration(): void
    {
        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);

        $file = $this->service->registerFile($session->id, [
            'original_filename' => 'IMG_001.CR2',
            'file_size_bytes'   => 52_000_000,
            'file_type'         => 'raw',
            'exif_data'         => [
                'iso'         => 400,
                'aperture'    => 1.8,
                'focalLength' => 50,
                'camera'      => 'Canon 5D Mark IV',
            ],
        ]);

        $tags = $file->tags()->pluck('tag')->toArray();

        $this->assertContains('iso-400', $tags);
        $this->assertContains('f1.8', $tags);
        $this->assertContains('50mm', $tags);
        $this->assertContains('canon-5d-mark-iv', $tags);
    }

    /** @test */
    public function it_handles_files_with_no_exif_without_error(): void
    {
        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);

        $file = $this->service->registerFile($session->id, [
            'original_filename' => 'scan.jpg',
            'file_size_bytes'   => 1_000_000,
            'file_type'         => 'jpg',
        ]);

        $this->assertInstanceOf(IngestFile::class, $file);
        $this->assertEquals(0, $file->tags()->count());
    }

    // ─── recordFileUploaded() ─────────────────────────────────────────────────

    /** @test */
    public function it_marks_a_file_as_uploaded_and_updates_session_count(): void
    {
        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);
        $file = $this->service->registerFile($session->id, [
            'original_filename' => 'IMG_001.CR2',
            'file_size_bytes'   => 52_000_000,
            'file_type'         => 'raw',
        ]);

        $this->service->recordFileUploaded($file->id);

        $file->refresh();
        $session->refresh();

        $this->assertEquals(IngestFile::STATUS_COMPLETED, $file->upload_status);
        $this->assertNotNull($file->uploaded_at);
        $this->assertEquals(1, $session->completed_file_count);
    }

    /** @test */
    public function it_is_idempotent_when_recording_same_file_upload_twice(): void
    {
        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);
        $file = $this->service->registerFile($session->id, [
            'original_filename' => 'IMG_001.CR2',
            'file_size_bytes'   => 52_000_000,
            'file_type'         => 'raw',
        ]);

        $this->service->recordFileUploaded($file->id);
        $this->service->recordFileUploaded($file->id); // Second call — should be no-op

        $session->refresh();
        $this->assertEquals(1, $session->completed_file_count);
    }

    // ─── applyTag() ───────────────────────────────────────────────────────────

    /** @test */
    public function it_applies_a_user_tag_to_a_file(): void
    {
        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);
        $file = $this->service->registerFile($session->id, [
            'original_filename' => 'IMG_001.CR2',
            'file_size_bytes'   => 52_000_000,
            'file_type'         => 'raw',
        ]);

        $this->service->applyTag($file->id, 'Portrait', 'user');

        $tags = $file->tags()->pluck('tag')->toArray();
        $this->assertContains('portrait', $tags); // Tag is lowercased
    }

    /** @test */
    public function it_ignores_duplicate_tags(): void
    {
        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);
        $file = $this->service->registerFile($session->id, [
            'original_filename' => 'IMG_001.CR2',
            'file_size_bytes'   => 52_000_000,
            'file_type'         => 'raw',
        ]);

        $this->service->applyTag($file->id, 'favorite', 'user');
        $this->service->applyTag($file->id, 'favorite', 'user'); // Duplicate

        $this->assertEquals(1, $file->tags()->where('tag', 'favorite')->count());
    }

    /** @test */
    public function it_throws_on_empty_tag(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);
        $file = $this->service->registerFile($session->id, [
            'original_filename' => 'IMG_001.CR2',
            'file_size_bytes'   => 52_000_000,
            'file_type'         => 'raw',
        ]);

        $this->service->applyTag($file->id, '   '); // Whitespace only
    }

    /** @test */
    public function it_removes_a_tag_from_a_file(): void
    {
        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);
        $file = $this->service->registerFile($session->id, [
            'original_filename' => 'IMG_001.CR2',
            'file_size_bytes'   => 52_000_000,
            'file_type'         => 'raw',
        ]);

        $this->service->applyTag($file->id, 'cull', 'user');
        $this->service->removeTag($file->id, 'cull');

        $this->assertEquals(0, $file->tags()->where('tag', 'cull')->count());
    }

    // ─── confirmSession() ─────────────────────────────────────────────────────

    /** @test */
    public function it_confirms_a_session_in_uploading_status(): void
    {
        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);
        $session->update(['status' => UploadSession::STATUS_UPLOADING]);

        $confirmed = $this->service->confirmSession($session->id, galleryId: 42);

        $this->assertEquals(UploadSession::STATUS_CONFIRMED, $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertEquals(42, $confirmed->gallery_id);
    }

    /** @test */
    public function it_throws_when_confirming_a_completed_session(): void
    {
        $this->expectException(\RuntimeException::class);

        $session = $this->service->createSession(['studio_id' => 1, 'user_id' => 1]);
        $session->update(['status' => UploadSession::STATUS_COMPLETED]);

        $this->service->confirmSession($session->id);
    }

    /** @test */
    public function it_throws_when_session_not_found(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->getProgress('non-existent-session-id');
    }
}
