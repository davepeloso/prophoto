<?php

namespace ProPhoto\Gallery\Services;

use Illuminate\Support\Facades\DB;
use ProPhoto\Gallery\Models\Gallery;

/**
 * Single write path to the gallery_activity_log table.
 *
 * Every attributed action in a gallery (share created, identity confirmed,
 * image approved, gallery submitted, etc.) is recorded through this service.
 * No other code should insert into gallery_activity_log directly.
 *
 * The table is append-only — rows are never updated or deleted.
 *
 * action_type vocabulary:
 *   gallery_created | share_created | identity_confirmed |
 *   image_added | image_removed | approved | approved_pending | cleared |
 *   rated | commented | version_uploaded | gallery_submitted | gallery_locked |
 *   download | gallery_viewed
 */
class GalleryActivityLogger
{
    /**
     * Log an action to the gallery activity ledger.
     */
    public static function log(
        Gallery $gallery,
        string  $actionType,
        string  $actorType = 'studio_user',
        ?string $actorEmail = null,
        ?int    $galleryShareId = null,
        ?int    $imageId = null,
        ?array  $metadata = null,
    ): void {
        DB::table('gallery_activity_log')->insert([
            'gallery_id'       => $gallery->id,
            'gallery_share_id' => $galleryShareId,
            'image_id'         => $imageId,
            'action_type'      => $actionType,
            'actor_type'       => $actorType,
            'actor_email'      => $actorEmail,
            'metadata'         => $metadata !== null ? json_encode($metadata) : null,
            'occurred_at'      => now(),
            'created_at'       => now(),
        ]);
    }
}
