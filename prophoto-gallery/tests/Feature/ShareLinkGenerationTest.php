<?php

namespace ProPhoto\Gallery\Tests\Feature;

use Illuminate\Support\Facades\DB;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Services\GalleryActivityLogger;
use ProPhoto\Gallery\Tests\TestCase;

/**
 * Story 3.1 — Share Link Generation tests.
 *
 * Verifies:
 *  1. GalleryShare auto-generates a unique token on creation
 *  2. isValid() returns false for expired / revoked shares
 *  3. incrementViewCount() tracks access timestamps and count
 *  4. GET /g/{token} returns 200 with valid share
 *  5. GET /g/{token} returns 410 for expired shares
 *  6. GET /g/{token} returns 404 for invalid tokens
 *  7. GalleryActivityLogger writes to gallery_activity_log
 */
class ShareLinkGenerationTest extends TestCase
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

    private function makeShare(Gallery $gallery, array $attrs = []): GalleryShare
    {
        return GalleryShare::create(array_merge([
            'gallery_id'        => $gallery->id,
            'shared_by_user_id' => 1,
            'shared_with_email' => 'client@example.com',
            'can_view'          => true,
            'can_download'      => false,
            'can_approve'       => $gallery->isProofing(),
            'can_comment'       => $gallery->isProofing(),
            'can_share'         => false,
        ], $attrs));
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_share_auto_generates_unique_token(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        $this->assertNotNull($share->share_token);
        $this->assertEquals(32, strlen($share->share_token));

        // Second share gets a different token
        $share2 = $this->makeShare($gallery, ['shared_with_email' => 'client2@example.com']);
        $this->assertNotEquals($share->share_token, $share2->share_token);
    }

    public function test_is_valid_returns_false_for_expired_share(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($share->isValid());
        $this->assertTrue($share->isExpired());
    }

    public function test_is_valid_returns_false_for_revoked_share(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'revoked_at' => now(),
        ]);

        $this->assertFalse($share->isValid());
    }

    public function test_increment_view_count_tracks_access(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        $this->assertNull($share->accessed_at);
        $this->assertEquals(0, $share->access_count);

        $share->incrementViewCount();
        $share->refresh();

        $this->assertNotNull($share->accessed_at);
        $this->assertNotNull($share->last_accessed_at);
        $this->assertEquals(1, $share->access_count);

        // Second access updates last_accessed_at and increments
        $share->incrementViewCount();
        $share->refresh();

        $this->assertEquals(2, $share->access_count);
    }

    public function test_viewer_route_returns_200_for_valid_share(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        $response->assertSee($gallery->subject_name);
    }

    public function test_viewer_route_returns_410_for_expired_share(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(410);
        $response->assertSee('This link has expired');
    }

    public function test_viewer_route_returns_404_for_invalid_token(): void
    {
        $response = $this->get('/g/nonexistent-token-abc123');

        $response->assertStatus(404);
    }

    public function test_activity_logger_writes_to_log_table(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'share_created',
            actorType: 'studio_user',
            actorEmail: 'photographer@studio.com',
            galleryShareId: $share->id,
            metadata: ['recipient' => 'client@example.com'],
        );

        $this->assertDatabaseHas('gallery_activity_log', [
            'gallery_id'       => $gallery->id,
            'gallery_share_id' => $share->id,
            'action_type'      => 'share_created',
            'actor_type'       => 'studio_user',
            'actor_email'      => 'photographer@studio.com',
        ]);

        // Verify metadata was stored as JSON
        $row = DB::table('gallery_activity_log')->first();
        $meta = json_decode($row->metadata, true);
        $this->assertEquals('client@example.com', $meta['recipient']);
    }

    public function test_viewer_route_logs_gallery_viewed_activity(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        $this->get("/g/{$share->share_token}");

        $this->assertDatabaseHas('gallery_activity_log', [
            'gallery_id'  => $gallery->id,
            'action_type' => 'gallery_viewed',
        ]);
    }
}
