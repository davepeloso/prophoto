<?php

namespace ProPhoto\Gallery\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Story 7.5 — Fired when a photographer marks a gallery as "delivered."
 *
 * Triggers notification to ALL active shares so every client with access
 * knows their images are ready. Carries all data needed by listeners
 * so they don't need to query back into gallery tables.
 */
class GalleryDelivered
{
    use Dispatchable;

    public function __construct(
        public readonly int     $galleryId,
        public readonly int     $studioId,
        public readonly string  $galleryName,
        public readonly ?string $deliveryMessage,
        public readonly string  $deliveredAt,
        public readonly ?int    $deliveredByUserId = null,
        /** @var array<int, array{share_id: int, email: string, share_token: string}> */
        public readonly array   $activeShares = [],
    ) {}
}
