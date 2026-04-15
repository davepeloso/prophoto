<?php

namespace ProPhoto\Gallery\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Story 6.3 — Fired when a client views a gallery at a notification threshold.
 *
 * NOT dispatched on every page load — only when the share's access_count
 * hits a specific milestone (1st view, 5th, 10th, 25th, 50th).
 *
 * Carries all data needed by listeners so they don't need to query
 * back into gallery tables.
 */
class GalleryViewed
{
    use Dispatchable;

    public function __construct(
        public readonly int    $galleryId,
        public readonly int    $galleryShareId,
        public readonly int    $studioId,
        public readonly string $galleryName,
        public readonly string $viewedByEmail,
        public readonly int    $viewCount,
        public readonly string $viewedAt,
        public readonly ?int   $sharedByUserId = null,
    ) {}
}
