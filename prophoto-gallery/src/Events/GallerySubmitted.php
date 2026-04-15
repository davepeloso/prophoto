<?php

namespace ProPhoto\Gallery\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Story 5.1 — Fired when a client submits their proofing selections.
 *
 * Carries all data needed by listeners (e.g. notifications) so they
 * don't need to query back into gallery tables.
 *
 * Lives in prophoto-gallery (not contracts) because only one consumer
 * currently exists. Promote to contracts if a second package needs it.
 */
class GallerySubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly int    $galleryId,
        public readonly int    $galleryShareId,
        public readonly int    $studioId,
        public readonly string $galleryName,
        public readonly string $submittedByEmail,
        public readonly int    $approvedCount,
        public readonly int    $pendingCount,
        public readonly int    $totalImages,
        public readonly string $submittedAt,
        public readonly ?int   $sharedByUserId = null,
    ) {}
}
