<?php

namespace ProPhoto\Gallery\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ProPhoto\Assets\Models\Asset;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\Image;
use ProPhoto\Ingest\Events\IngestSessionConfirmed;

/**
 * GalleryContextProjectionListener
 *
 * Handles IngestSessionConfirmed. When a photographer confirms their ingest
 * session, this listener projects the created Assets into the Gallery as
 * Image records — making them immediately visible in the gallery UI.
 *
 * Flow:
 *   1. Resolve the Gallery by galleryId on the event.
 *   2. Find all Assets whose metadata.session_id matches the confirmed session.
 *   3. Bulk-insert Image records (one per asset), skipping any already linked.
 *   4. Update Gallery aggregate: image_count + last_activity_at.
 *
 * Performance target: link 100 assets to a gallery in < 10 seconds.
 * Achieved via chunked bulk insert (DB::table) rather than per-row Eloquent creates.
 *
 * Sprint 6 — Story 1c.6
 */
class GalleryContextProjectionListener
{
    /**
     * Handle the IngestSessionConfirmed event.
     */
    public function handle(IngestSessionConfirmed $event): void
    {
        // Only project when a gallery is actually linked to this session
        if ($event->galleryId === null) {
            Log::info('GalleryContextProjectionListener: no gallery linked — skipping', [
                'session_id' => $event->sessionId,
            ]);
            return;
        }

        $gallery = Gallery::find($event->galleryId);

        if (! $gallery) {
            Log::warning('GalleryContextProjectionListener: gallery not found', [
                'session_id' => $event->sessionId,
                'gallery_id' => $event->galleryId,
            ]);
            return;
        }

        Log::info('GalleryContextProjectionListener: starting projection', [
            'session_id' => $event->sessionId,
            'gallery_id' => $event->galleryId,
        ]);

        $projectedCount = 0;
        $skippedCount   = 0;
        $now            = now()->toDateTimeString();

        // Find all assets created by this ingest session.
        // Assets store session_id in their JSON metadata (set by IngestSessionConfirmedListener).
        // Process in chunks of 50 to stay within memory + query size limits.
        Asset::whereJsonContains('metadata->session_id', $event->sessionId)
            ->orderBy('id')
            ->chunk(50, function ($assets) use ($gallery, $event, $now, &$projectedCount, &$skippedCount) {

                // Get the set of asset_ids already linked to this gallery
                // to make the insert idempotent (safe to re-run on retry).
                $existingAssetIds = DB::table('images')
                    ->where('gallery_id', $gallery->id)
                    ->whereIn('asset_id', $assets->pluck('id'))
                    ->whereNull('deleted_at')
                    ->pluck('asset_id')
                    ->flip()   // flip so we can use isset() for O(1) lookup
                    ->toArray();

                $rows = [];

                foreach ($assets as $sortOffset => $asset) {
                    if (isset($existingAssetIds[$asset->id])) {
                        $skippedCount++;
                        continue;
                    }

                    $assetMeta   = $asset->metadata ?? [];
                    $ingestTags  = $assetMeta['tags'] ?? [];
                    $ingestFileId = $assetMeta['ingest_file_id'] ?? null;

                    // Resolve thumbnail path from asset metadata (set by GenerateAssetThumbnail job)
                    $thumbPath = $assetMeta['storage_key_thumb'] ?? null;

                    $rows[] = [
                        'gallery_id'        => $gallery->id,
                        'asset_id'          => $asset->id,
                        'filename'          => $asset->original_filename,
                        'original_filename' => $asset->original_filename,
                        'mime_type'         => $asset->mime_type,
                        'file_size'         => $asset->bytes,
                        'thumbnail_path'    => $thumbPath,
                        'sort_order'        => $projectedCount + $sortOffset,
                        'uploaded_at'       => $asset->ingested_at?->toDateTimeString() ?? $now,
                        'metadata'          => json_encode([
                            'source'          => 'ingest',
                            'session_id'      => $event->sessionId,
                            'calendar_event'  => $event->calendarEventId,
                            'confidence'      => $event->calendarMatchConfidence,
                        ]),
                        // Sprint 6 ingest context columns
                        'ingest_session_id' => $event->sessionId,
                        'ingest_file_id'    => $ingestFileId,
                        'ingest_tags'       => json_encode($ingestTags),
                        'calendar_event_id' => $event->calendarEventId,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];

                    $projectedCount++;
                }

                if (! empty($rows)) {
                    DB::table('images')->insert($rows);
                }
            });

        // Update gallery aggregate counts
        if ($projectedCount > 0) {
            DB::table('galleries')
                ->where('id', $gallery->id)
                ->update([
                    'image_count'      => DB::raw("image_count + {$projectedCount}"),
                    'last_activity_at' => $now,
                    'updated_at'       => $now,
                ]);
        }

        Log::info('GalleryContextProjectionListener: projection complete', [
            'session_id'      => $event->sessionId,
            'gallery_id'      => $event->galleryId,
            'projected_count' => $projectedCount,
            'skipped_count'   => $skippedCount,
        ]);
    }
}
