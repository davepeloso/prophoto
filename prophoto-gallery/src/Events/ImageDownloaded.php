<?php

namespace ProPhoto\Gallery\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Story 6.2 — Fired when a client downloads an image via a share link.
 *
 * Carries all data needed by listeners (e.g. notifications) so they
 * don't need to query back into gallery tables.
 *
 * Lives in prophoto-gallery (not contracts) — same convention as
 * GallerySubmitted. Promote to contracts if a second package needs it.
 */
class ImageDownloaded
{
    use Dispatchable;

    public function __construct(
        public readonly int    $galleryId,
        public readonly int    $galleryShareId,
        public readonly int    $studioId,
        public readonly string $galleryName,
        public readonly int    $imageId,
        public readonly string $imageFilename,
        public readonly string $downloadedByEmail,
        public readonly int    $shareDownloadCount,
        public readonly ?int   $shareMaxDownloads,
        public readonly int    $galleryDownloadCount,
        public readonly string $downloadedAt,
        public readonly ?int   $sharedByUserId = null,
    ) {}
}
