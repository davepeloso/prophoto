<?php

namespace ProPhoto\Gallery\Tests\Feature;

use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Models\AssetDerivative;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Models\Image;
use ProPhoto\Gallery\Tests\TestCase;

/**
 * Story 3.2 — Presentation Gallery Viewer tests.
 *
 * Verifies:
 *  1. Presentation gallery returns 200 with image grid
 *  2. Images render in sort_order
 *  3. Download button visible when can_download = true
 *  4. Download button hidden when can_download = false
 *  5. Proofing gallery does NOT render presentation view
 *  6. Empty gallery renders gracefully
 */
class PresentationViewerTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeGallery(array $attrs = []): Gallery
    {
        return Gallery::create(array_merge([
            'subject_name'    => 'Smith Family — Spring 2026',
            'studio_id'       => 1,
            'organization_id' => 1,
            'type'            => Gallery::TYPE_PRESENTATION,
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
            'can_approve'       => false,
            'can_comment'       => false,
            'can_share'         => false,
        ], $attrs));
    }

    private function makeAsset(array $attrs = []): Asset
    {
        return Asset::create(array_merge([
            'studio_id'            => 1,
            'type'                 => 'image',
            'original_filename'    => 'IMG_001.jpg',
            'mime_type'            => 'image/jpeg',
            'bytes'                => 1024000,
            'checksum_sha256'      => hash('sha256', uniqid()),
            'storage_driver'       => 'local',
            'storage_key_original' => 'ingest/test/IMG_001.jpg',
            'logical_path'         => 'studio/1/IMG_001.jpg',
            'status'               => 'ready',
        ], $attrs));
    }

    private function makeImageWithAsset(Gallery $gallery, int $sortOrder, string $filename = 'IMG_001.jpg'): Image
    {
        $asset = $this->makeAsset([
            'original_filename'    => $filename,
            'checksum_sha256'      => hash('sha256', uniqid()),
            'storage_key_original' => "ingest/test/{$filename}",
        ]);

        return Image::create([
            'gallery_id'        => $gallery->id,
            'asset_id'          => $asset->id,
            'filename'          => $filename,
            'original_filename' => $filename,
            'sort_order'        => $sortOrder,
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_presentation_gallery_returns_200_with_images(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        $this->makeImageWithAsset($gallery, 0, 'IMG_001.jpg');
        $this->makeImageWithAsset($gallery, 1, 'IMG_002.jpg');

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        $response->assertSee('Smith Family');
        $response->assertSee('2 images');
    }

    public function test_images_render_in_sort_order(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        // Create in reverse order to verify sort_order is respected
        $this->makeImageWithAsset($gallery, 2, 'IMG_THIRD.jpg');
        $this->makeImageWithAsset($gallery, 0, 'IMG_FIRST.jpg');
        $this->makeImageWithAsset($gallery, 1, 'IMG_SECOND.jpg');

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);

        // Verify ordering by checking that FIRST appears before THIRD in the HTML
        $content = $response->getContent();
        $firstPos = strpos($content, 'IMG_FIRST.jpg');
        $secondPos = strpos($content, 'IMG_SECOND.jpg');
        $thirdPos = strpos($content, 'IMG_THIRD.jpg');

        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertNotFalse($thirdPos);
        $this->assertLessThan($secondPos, $firstPos);
        $this->assertLessThan($thirdPos, $secondPos);
    }

    public function test_download_button_visible_when_can_download_true(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, ['can_download' => true]);

        $this->makeImageWithAsset($gallery, 0);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        $response->assertSee('Download');
    }

    public function test_download_button_hidden_when_can_download_false(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, ['can_download' => false]);

        $this->makeImageWithAsset($gallery, 0);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        $response->assertDontSee('Download');
    }

    public function test_proofing_gallery_does_not_render_presentation_view(): void
    {
        $gallery = $this->makeGallery(['type' => Gallery::TYPE_PROOFING]);
        $share   = $this->makeShare($gallery);

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        // Proofing with unconfirmed identity shows identity gate, not presentation view
        $response->assertSee('Confirm Your Identity');
        $response->assertDontSee('galleryViewer()');
    }

    public function test_empty_gallery_renders_gracefully(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        // No images added

        $response = $this->get("/g/{$share->share_token}");

        $response->assertStatus(200);
        $response->assertSee('No images in this gallery yet');
        $response->assertSee('0 images');
    }
}
