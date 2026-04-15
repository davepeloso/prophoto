<?php

namespace ProPhoto\Gallery\Tests\Feature;

use Illuminate\Support\Facades\DB;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryAccessLog;
use ProPhoto\Gallery\Models\GalleryActivityLog;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Services\GalleryActivityLogger;
use ProPhoto\Gallery\Tests\TestCase;

/**
 * Story 3.5 — Gallery Activity & Access Logging tests.
 *
 * Verifies:
 *  1. GalleryActivityLog model reads from gallery_activity_log table
 *  2. Gallery::activityLogs() relationship returns log entries
 *  3. GalleryAccessLog model reads from gallery_access_logs table
 *  4. Gallery::accessLogs() relationship returns log entries
 *  5. Activity log entries have correct metadata JSON decoding
 */
class GalleryActivityLoggingTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeGallery(array $attrs = []): Gallery
    {
        return Gallery::create(array_merge([
            'subject_name'    => 'Test Gallery',
            'studio_id'       => 1,
            'organization_id' => 1,
            'type'            => Gallery::TYPE_PROOFING,
            'status'          => Gallery::STATUS_ACTIVE,
            'image_count'     => 0,
        ], $attrs));
    }

    private function makeShare(Gallery $gallery): GalleryShare
    {
        return GalleryShare::create([
            'gallery_id'        => $gallery->id,
            'shared_by_user_id' => 1,
            'shared_with_email' => 'client@example.com',
            'can_view'          => true,
            'can_download'      => false,
            'can_approve'       => true,
            'can_comment'       => true,
            'can_share'         => false,
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_activity_log_model_reads_from_table(): void
    {
        $gallery = $this->makeGallery();

        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'gallery_created',
            actorType: 'studio_user',
            actorEmail: 'photographer@studio.com',
        );

        $log = GalleryActivityLog::where('gallery_id', $gallery->id)->first();

        $this->assertNotNull($log);
        $this->assertEquals('gallery_created', $log->action_type);
        $this->assertEquals('studio_user', $log->actor_type);
        $this->assertEquals('photographer@studio.com', $log->actor_email);
    }

    public function test_gallery_activity_logs_relationship(): void
    {
        $gallery = $this->makeGallery();

        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'gallery_created',
            actorType: 'studio_user',
        );

        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'share_created',
            actorType: 'studio_user',
            actorEmail: 'photographer@studio.com',
        );

        $logs = $gallery->activityLogs;

        $this->assertCount(2, $logs);
        $this->assertEquals('gallery_created', $logs[0]->action_type);
        $this->assertEquals('share_created', $logs[1]->action_type);
    }

    public function test_access_log_model_reads_from_table(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        GalleryAccessLog::create([
            'gallery_id'    => $gallery->id,
            'action'        => GalleryAccessLog::ACTION_VIEW,
            'resource_type' => 'share',
            'resource_id'   => $share->id,
            'ip_address'    => '192.168.1.1',
            'user_agent'    => 'PHPUnit Test',
        ]);

        $log = GalleryAccessLog::where('gallery_id', $gallery->id)->first();

        $this->assertNotNull($log);
        $this->assertEquals('view', $log->action);
        $this->assertEquals('192.168.1.1', $log->ip_address);
    }

    public function test_gallery_access_logs_relationship(): void
    {
        $gallery = $this->makeGallery();

        GalleryAccessLog::create([
            'gallery_id'    => $gallery->id,
            'action'        => GalleryAccessLog::ACTION_VIEW,
            'resource_type' => 'gallery',
            'resource_id'   => $gallery->id,
            'ip_address'    => '10.0.0.1',
        ]);

        $logs = $gallery->accessLogs;

        $this->assertCount(1, $logs);
        $this->assertEquals('view', $logs[0]->action);
    }

    public function test_activity_log_metadata_decodes_correctly(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'share_created',
            actorType: 'studio_user',
            actorEmail: 'photographer@studio.com',
            galleryShareId: $share->id,
            metadata: [
                'recipient'    => 'client@example.com',
                'gallery_type' => 'proofing',
                'can_download' => false,
            ],
        );

        $log = GalleryActivityLog::where('gallery_id', $gallery->id)->first();

        $this->assertIsArray($log->metadata);
        $this->assertEquals('client@example.com', $log->metadata['recipient']);
        $this->assertEquals('proofing', $log->metadata['gallery_type']);
        $this->assertFalse($log->metadata['can_download']);
    }
}
