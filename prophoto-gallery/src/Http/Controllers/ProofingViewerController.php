<?php

namespace ProPhoto\Gallery\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Models\ImageApprovalState;
use ProPhoto\Gallery\Services\ViewerTemplateRegistry;

/**
 * Story 3.4 + 4.1 — Proofing gallery viewer (post-identity-gate).
 *
 * Loads the gallery images with their approval states for the current
 * share token and renders the proofing UI.
 *
 * Story 4.1: Added rating lookup from activity ledger, pending count
 * for constraint feedback.
 */
class ProofingViewerController extends Controller
{
    /**
     * Render the proofing gallery viewer.
     */
    public function show(Gallery $gallery, GalleryShare $share)
    {
        $images = $gallery->imagesWithAssets()
            ->orderBy('sort_order')
            ->get();

        // Load approval states for this share token, keyed by image_id
        $approvalStates = ImageApprovalState::where('gallery_id', $gallery->id)
            ->where('gallery_share_id', $share->id)
            ->get()
            ->keyBy('image_id');

        // Load enabled pending types for the pending-reason picker
        $pendingTypes = $gallery->pendingTypes()->enabled()->get();

        $modeConfig = $gallery->getModeConfig();

        // Count approved images for this share (approved + approved_pending)
        $approvedCount = $approvalStates->filter(function ($state) {
            return $state->isApproved();
        })->count();

        // Count pending images for constraint UI
        $pendingCount = $approvalStates->filter(function ($state) {
            return $state->status === ImageApprovalState::STATUS_APPROVED_PENDING;
        })->count();

        // Pull latest rating per image from activity ledger
        // Ratings are stored as metadata JSON in gallery_activity_log
        $imageIds = $images->pluck('id')->all();
        $ratings  = [];

        if (! empty($imageIds)) {
            $ratingRows = DB::table('gallery_activity_log')
                ->where('gallery_id', $gallery->id)
                ->where('gallery_share_id', $share->id)
                ->where('action_type', 'rated')
                ->whereIn('image_id', $imageIds)
                ->orderBy('occurred_at', 'desc')
                ->get(['image_id', 'metadata']);

            // Take the latest rating per image
            foreach ($ratingRows as $row) {
                if (! isset($ratings[$row->image_id])) {
                    $meta = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;
                    $ratings[$row->image_id] = $meta['rating'] ?? 0;
                }
            }
        }

        // Pre-build image data for Alpine.js
        $imageData = $images->map(function ($img) use ($approvalStates, $ratings) {
            $state = $approvalStates->get($img->id);

            return [
                'id'             => $img->id,
                'thumbUrl'       => $img->resolvedThumbnailUrl(),
                'fullUrl'        => $img->resolved_url ?? $img->resolvedThumbnailUrl(),
                'title'          => $img->title,
                'caption'        => $img->caption,
                'alt'            => $img->alt_text ?? $img->title ?? $img->original_filename,
                'filename'       => $img->original_filename,
                'status'         => $state?->status ?? ImageApprovalState::STATUS_UNAPPROVED,
                'pendingTypeId'  => $state?->pending_type_id,
                'pendingNote'    => $state?->pending_note,
                'rating'         => $ratings[$img->id] ?? 0,
            ];
        })->values();

        // Story 7.4 — Dynamic view resolution from viewer_template
        $registry = app(ViewerTemplateRegistry::class);
        $templateSlug = $gallery->getEffectiveViewerTemplate();
        $viewName = $registry->resolveView('proofing', $templateSlug);
        $fontsUrl = $registry->fontsUrl($templateSlug);

        return view($viewName, [
            'gallery'        => $gallery,
            'share'          => $share,
            'images'         => $images,
            'imageData'      => $imageData,
            'pendingTypes'   => $pendingTypes,
            'modeConfig'     => $modeConfig,
            'approvedCount'  => $approvedCount,
            'pendingCount'   => $pendingCount,
            'isLocked'       => (bool) $share->is_locked,
            'canDownload'    => (bool) $share->can_download,
            'fontsUrl'       => $fontsUrl,
        ]);
    }
}
