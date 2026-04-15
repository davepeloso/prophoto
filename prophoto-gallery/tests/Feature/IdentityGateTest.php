<?php

namespace ProPhoto\Gallery\Tests\Feature;

use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Tests\TestCase;

/**
 * Story 3.3 — Proofing Gallery Identity Gate tests.
 *
 * Verifies:
 *  1. Proofing gallery with unconfirmed share shows identity gate form
 *  2. POST with valid email confirms identity and redirects
 *  3. POST with invalid email returns validation error
 *  4. Already-confirmed share skips gate, shows proofing placeholder
 *  5. Identity confirmation logs identity_confirmed to activity ledger
 *  6. Presentation gallery never shows identity gate
 *  7. confirmed_email is recorded even if different from shared_with_email
 */
class IdentityGateTest extends TestCase
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
        ], $attrs));
    }

    private function makeShare(Gallery $gallery, array $attrs = []): GalleryShare
    {
        return GalleryShare::create(array_merge([
            'gallery_id'        => $gallery->id,
            'shared_by_user_id' => 1,
            'shared_with_email' => 'bride@example.com',
            'can_view'          => true,
            'can_download'      => false,
            'can_approve'       => true,
            'can_comment'       => true,
            'can_share'         => false,
        ], $attrs));
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_unconfirmed_proofing_share_shows_identity_gate(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        $response->assertSee('Confirm Your Identity');
        $response->assertSee('Smith Wedding');
        $response->assertSee('bride@example.com'); // pre-filled from shared_with_email
    }

    public function test_post_valid_email_confirms_identity_and_redirects(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        $response = $this->post("/g/{$share->share_token}/confirm", [
            'email' => 'bride@example.com',
        ]);

        $response->assertRedirect("/g/{$share->share_token}");

        $share->refresh();
        $this->assertEquals('bride@example.com', $share->confirmed_email);
        $this->assertNotNull($share->identity_confirmed_at);
    }

    public function test_post_invalid_email_returns_validation_error(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        $response = $this->post("/g/{$share->share_token}/confirm", [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_confirmed_share_skips_gate_shows_proofing_viewer(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'confirmed_email'       => 'bride@example.com',
            'identity_confirmed_at' => now(),
        ]);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        // Should see the proofing viewer, not the identity gate
        $response->assertSee('Proofing');
        $response->assertSee('bride@example.com');
        $response->assertDontSee('Confirm Your Identity');
    }

    public function test_identity_confirmation_logs_to_activity_ledger(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        $this->post("/g/{$share->share_token}/confirm", [
            'email' => 'bride@example.com',
        ]);

        $this->assertDatabaseHas('gallery_activity_log', [
            'gallery_id'       => $gallery->id,
            'gallery_share_id' => $share->id,
            'action_type'      => 'identity_confirmed',
            'actor_type'       => 'share_identity',
            'actor_email'      => 'bride@example.com',
        ]);
    }

    public function test_presentation_gallery_never_shows_identity_gate(): void
    {
        $gallery = $this->makeGallery(['type' => Gallery::TYPE_PRESENTATION]);
        $share   = $this->makeShare($gallery);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        $response->assertDontSee('Confirm Your Identity');
    }

    public function test_confirmed_email_can_differ_from_shared_with_email(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'shared_with_email' => 'bride@example.com',
        ]);

        $this->post("/g/{$share->share_token}/confirm", [
            'email' => 'mother-of-bride@example.com',
        ]);

        $share->refresh();
        $this->assertEquals('mother-of-bride@example.com', $share->confirmed_email);
        $this->assertEquals('bride@example.com', $share->shared_with_email);

        // Activity log records both emails
        $this->assertDatabaseHas('gallery_activity_log', [
            'action_type' => 'identity_confirmed',
            'actor_email' => 'mother-of-bride@example.com',
        ]);
    }
}
