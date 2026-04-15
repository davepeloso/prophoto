<?php

namespace ProPhoto\Gallery\Tests\Feature;

use ProPhoto\Assets\Models\Asset;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryPendingType;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Models\Image;
use ProPhoto\Gallery\Models\ImageApprovalState;
use ProPhoto\Gallery\Tests\TestCase;

/**
 * Story 3.4 — Proofing Gallery Viewer tests.
 *
 * Verifies:
 *  1. Confirmed proofing share renders proofing view
 *  2. Approve action creates ImageApprovalState
 *  3. Pending action creates state with pending_type_id
 *  4. Clear action resets approval state
 *  5. Rate action logs to activity ledger
 *  6. Submit locks the share and logs activity
 *  7. Locked share renders read-only
 *  8. Sequential pipeline: pending blocked until approved first
 *  9. Unconfirmed share shows identity gate, not proofing view
 * 10. Presentation gallery never renders proofing view
 */
class ProofingViewerTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeGallery(array $attrs = []): Gallery
    {
        return Gallery::create(array_merge([
            'subject_name'    => 'Smith Wedding',
            'studio_id'       => 1,
            'organization_id' => 1,
            'type'            => Gallery::TYPE_PROOFING,
            'status'          => Gallery::STATUS_ACTIVE,
            'image_count'     => 0,
            'mode_config'     => Gallery::DEFAULT_MODE_CONFIG,
        ], $attrs));
    }

    private function makeConfirmedShare(Gallery $gallery, array $attrs = []): GalleryShare
    {
        return GalleryShare::create(array_merge([
            'gallery_id'            => $gallery->id,
            'shared_by_user_id'     => 1,
            'shared_with_email'     => 'bride@example.com',
            'confirmed_email'       => 'bride@example.com',
            'identity_confirmed_at' => now(),
            'can_view'              => true,
            'can_download'          => false,
            'can_approve'           => true,
            'can_comment'           => true,
            'can_share'             => false,
        ], $attrs));
    }

    private function makeAsset(string $filename = 'IMG_001.jpg'): Asset
    {
        return Asset::create([
            'studio_id'            => 1,
            'type'                 => 'image',
            'original_filename'    => $filename,
            'mime_type'            => 'image/jpeg',
            'bytes'                => 1024000,
            'checksum_sha256'      => hash('sha256', uniqid()),
            'storage_driver'       => 'local',
            'storage_key_original' => "ingest/test/{$filename}",
            'logical_path'         => "studio/1/{$filename}",
            'status'               => 'ready',
        ]);
    }

    private function makeImage(Gallery $gallery, int $sortOrder = 0, string $filename = 'IMG_001.jpg'): Image
    {
        $asset = $this->makeAsset($filename);

        return Image::create([
            'gallery_id'        => $gallery->id,
            'asset_id'          => $asset->id,
            'filename'          => $filename,
            'original_filename' => $filename,
            'sort_order'        => $sortOrder,
        ]);
    }

    private function makePendingType(Gallery $gallery, string $name = 'Retouching'): GalleryPendingType
    {
        return GalleryPendingType::create([
            'gallery_id'  => $gallery->id,
            'name'        => $name,
            'sort_order'  => 0,
            'is_enabled'  => true,
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_confirmed_proofing_share_renders_proofing_view(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeConfirmedShare($gallery);
        $this->makeImage($gallery);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        $response->assertSee('Proofing');
        $response->assertSee('bride@example.com');
        $response->assertSee('Submit My Selections');
    }

    public function test_approve_action_creates_approval_state(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeConfirmedShare($gallery);
        $image   = $this->makeImage($gallery);

        $response = $this->postJson("/g/{$share->share_token}/approve/{$image->id}");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'approved', 'imageId' => $image->id]);

        $this->assertDatabaseHas('image_approval_states', [
            'gallery_id'       => $gallery->id,
            'image_id'         => $image->id,
            'gallery_share_id' => $share->id,
            'status'           => 'approved',
            'actor_email'      => 'bride@example.com',
        ]);
    }

    public function test_pending_action_creates_state_with_pending_type(): void
    {
        $gallery     = $this->makeGallery();
        $share       = $this->makeConfirmedShare($gallery);
        $image       = $this->makeImage($gallery);
        $pendingType = $this->makePendingType($gallery);

        // First approve (sequential pipeline requires it)
        $this->postJson("/g/{$share->share_token}/approve/{$image->id}");

        $response = $this->postJson("/g/{$share->share_token}/pending/{$image->id}", [
            'pending_type_id' => $pendingType->id,
            'pending_note'    => 'Please fix the lighting',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'approved_pending']);

        $this->assertDatabaseHas('image_approval_states', [
            'image_id'        => $image->id,
            'status'          => 'approved_pending',
            'pending_type_id' => $pendingType->id,
            'pending_note'    => 'Please fix the lighting',
        ]);
    }

    public function test_clear_action_resets_approval_state(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeConfirmedShare($gallery);
        $image   = $this->makeImage($gallery);

        // Approve first
        $this->postJson("/g/{$share->share_token}/approve/{$image->id}");

        // Then clear
        $response = $this->postJson("/g/{$share->share_token}/clear/{$image->id}");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'cleared']);

        $this->assertDatabaseHas('image_approval_states', [
            'image_id' => $image->id,
            'status'   => 'cleared',
        ]);
    }

    public function test_rate_action_logs_to_activity_ledger(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeConfirmedShare($gallery);
        $image   = $this->makeImage($gallery);

        $response = $this->postJson("/g/{$share->share_token}/rate/{$image->id}", [
            'rating' => 4,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['rating' => 4]);

        $this->assertDatabaseHas('gallery_activity_log', [
            'gallery_id'  => $gallery->id,
            'image_id'    => $image->id,
            'action_type' => 'rated',
            'actor_email' => 'bride@example.com',
        ]);
    }

    public function test_submit_locks_share_and_logs_activity(): void
    {
        $gallery = $this->makeGallery(['mode_config' => array_merge(
            Gallery::DEFAULT_MODE_CONFIG,
            ['min_approvals' => null], // no minimum — allows submit with zero approvals
        )]);
        $share   = $this->makeConfirmedShare($gallery);

        $response = $this->postJson("/g/{$share->share_token}/submit");

        $response->assertStatus(200);
        $response->assertJson(['submitted' => true]);

        $share->refresh();
        $this->assertTrue($share->is_locked);
        $this->assertNotNull($share->submitted_at);

        $this->assertDatabaseHas('gallery_activity_log', [
            'gallery_id'  => $gallery->id,
            'action_type' => 'gallery_submitted',
        ]);
    }

    public function test_locked_share_renders_read_only(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeConfirmedShare($gallery, [
            'is_locked'    => true,
            'submitted_at' => now(),
        ]);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        $response->assertSee('this gallery is now read-only');
        $response->assertDontSee('@click="submitSelections()"', false);
    }

    public function test_sequential_pipeline_blocks_pending_without_approval(): void
    {
        $gallery     = $this->makeGallery(['mode_config' => array_merge(
            Gallery::DEFAULT_MODE_CONFIG,
            ['pipeline_sequential' => true],
        )]);
        $share       = $this->makeConfirmedShare($gallery);
        $image       = $this->makeImage($gallery);
        $pendingType = $this->makePendingType($gallery);

        // Try to mark pending without approving first
        $response = $this->postJson("/g/{$share->share_token}/pending/{$image->id}", [
            'pending_type_id' => $pendingType->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Image must be approved before adding a pending request.']);
    }

    public function test_unconfirmed_share_shows_identity_gate_not_proofing(): void
    {
        $gallery = $this->makeGallery();
        $share   = GalleryShare::create([
            'gallery_id'        => $gallery->id,
            'shared_by_user_id' => 1,
            'shared_with_email' => 'bride@example.com',
            'can_view'          => true,
            'can_download'      => false,
            'can_approve'       => true,
            'can_comment'       => true,
            'can_share'         => false,
        ]);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        $response->assertSee('Confirm Your Identity');
        $response->assertDontSee('Submit My Selections');
    }

    // ── Story 4.2 — Constraint enforcement tests ───────────────────────

    public function test_approve_blocked_at_max_approvals(): void
    {
        $gallery = $this->makeGallery(['mode_config' => array_merge(
            Gallery::DEFAULT_MODE_CONFIG,
            ['max_approvals' => 2],
        )]);
        $share  = $this->makeConfirmedShare($gallery);
        $image1 = $this->makeImage($gallery, 0, 'IMG_001.jpg');
        $image2 = $this->makeImage($gallery, 1, 'IMG_002.jpg');
        $image3 = $this->makeImage($gallery, 2, 'IMG_003.jpg');

        // Approve two images (the cap)
        $this->postJson("/g/{$share->share_token}/approve/{$image1->id}")->assertStatus(200);
        $this->postJson("/g/{$share->share_token}/approve/{$image2->id}")->assertStatus(200);

        // Third should be blocked
        $response = $this->postJson("/g/{$share->share_token}/approve/{$image3->id}");

        $response->assertStatus(422);
        $response->assertJson([
            'constraint' => 'max_approvals',
            'max'        => 2,
        ]);
    }

    public function test_approve_allowed_after_clear_frees_slot(): void
    {
        $gallery = $this->makeGallery(['mode_config' => array_merge(
            Gallery::DEFAULT_MODE_CONFIG,
            ['max_approvals' => 2],
        )]);
        $share  = $this->makeConfirmedShare($gallery);
        $image1 = $this->makeImage($gallery, 0, 'IMG_001.jpg');
        $image2 = $this->makeImage($gallery, 1, 'IMG_002.jpg');
        $image3 = $this->makeImage($gallery, 2, 'IMG_003.jpg');

        // Fill the cap
        $this->postJson("/g/{$share->share_token}/approve/{$image1->id}")->assertStatus(200);
        $this->postJson("/g/{$share->share_token}/approve/{$image2->id}")->assertStatus(200);

        // Clear one
        $this->postJson("/g/{$share->share_token}/clear/{$image1->id}")->assertStatus(200);

        // Now a new approval should succeed
        $response = $this->postJson("/g/{$share->share_token}/approve/{$image3->id}");
        $response->assertStatus(200);
        $response->assertJson(['status' => 'approved']);
    }

    public function test_pending_blocked_at_max_pending(): void
    {
        $gallery = $this->makeGallery(['mode_config' => array_merge(
            Gallery::DEFAULT_MODE_CONFIG,
            ['max_pending' => 1],
        )]);
        $share       = $this->makeConfirmedShare($gallery);
        $image1      = $this->makeImage($gallery, 0, 'IMG_001.jpg');
        $image2      = $this->makeImage($gallery, 1, 'IMG_002.jpg');
        $pendingType = $this->makePendingType($gallery);

        // Approve both images
        $this->postJson("/g/{$share->share_token}/approve/{$image1->id}")->assertStatus(200);
        $this->postJson("/g/{$share->share_token}/approve/{$image2->id}")->assertStatus(200);

        // Mark first as pending (fills the cap)
        $this->postJson("/g/{$share->share_token}/pending/{$image1->id}", [
            'pending_type_id' => $pendingType->id,
        ])->assertStatus(200);

        // Second pending should be blocked
        $response = $this->postJson("/g/{$share->share_token}/pending/{$image2->id}", [
            'pending_type_id' => $pendingType->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'constraint' => 'max_pending',
            'max'        => 1,
        ]);
    }

    public function test_submit_blocked_below_min_approvals(): void
    {
        $gallery = $this->makeGallery(['mode_config' => array_merge(
            Gallery::DEFAULT_MODE_CONFIG,
            ['min_approvals' => 3],
        )]);
        $share  = $this->makeConfirmedShare($gallery);
        $image1 = $this->makeImage($gallery, 0, 'IMG_001.jpg');
        $image2 = $this->makeImage($gallery, 1, 'IMG_002.jpg');

        // Approve 2 of the required 3
        $this->postJson("/g/{$share->share_token}/approve/{$image1->id}")->assertStatus(200);
        $this->postJson("/g/{$share->share_token}/approve/{$image2->id}")->assertStatus(200);

        $response = $this->postJson("/g/{$share->share_token}/submit");

        $response->assertStatus(422);
        $response->assertJson([
            'constraint' => 'min_approvals',
            'current'    => 2,
            'min'        => 3,
        ]);

        // Share should NOT be locked
        $share->refresh();
        $this->assertFalse($share->is_locked);
    }

    public function test_submit_allowed_at_min_approvals(): void
    {
        $gallery = $this->makeGallery(['mode_config' => array_merge(
            Gallery::DEFAULT_MODE_CONFIG,
            ['min_approvals' => 2],
        )]);
        $share  = $this->makeConfirmedShare($gallery);
        $image1 = $this->makeImage($gallery, 0, 'IMG_001.jpg');
        $image2 = $this->makeImage($gallery, 1, 'IMG_002.jpg');

        // Approve exactly the minimum
        $this->postJson("/g/{$share->share_token}/approve/{$image1->id}")->assertStatus(200);
        $this->postJson("/g/{$share->share_token}/approve/{$image2->id}")->assertStatus(200);

        $response = $this->postJson("/g/{$share->share_token}/submit");

        $response->assertStatus(200);
        $response->assertJson(['submitted' => true]);

        $share->refresh();
        $this->assertTrue($share->is_locked);
    }

    public function test_constraints_return_structured_error_json(): void
    {
        $gallery = $this->makeGallery(['mode_config' => array_merge(
            Gallery::DEFAULT_MODE_CONFIG,
            ['max_approvals' => 1],
        )]);
        $share  = $this->makeConfirmedShare($gallery);
        $image1 = $this->makeImage($gallery, 0, 'IMG_001.jpg');
        $image2 = $this->makeImage($gallery, 1, 'IMG_002.jpg');

        // Fill the cap
        $this->postJson("/g/{$share->share_token}/approve/{$image1->id}")->assertStatus(200);

        // Verify structured error response
        $response = $this->postJson("/g/{$share->share_token}/approve/{$image2->id}");

        $response->assertStatus(422);
        $response->assertJsonStructure(['error', 'constraint', 'current', 'max']);
        $this->assertEquals('max_approvals', $response->json('constraint'));
        $this->assertEquals(1, $response->json('current'));
        $this->assertEquals(1, $response->json('max'));
    }

    // ── Original Sprint 3 tests (continued) ──────────────────────────────

    public function test_presentation_gallery_never_renders_proofing_view(): void
    {
        $gallery = $this->makeGallery(['type' => Gallery::TYPE_PRESENTATION]);
        $share   = $this->makeConfirmedShare($gallery);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        $response->assertDontSee('Submit My Selections');
        $response->assertDontSee('Approve');
    }
}
