<?php

namespace ProPhoto\Gallery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ProPhoto\Gallery\Events\GalleryViewed;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Services\GalleryActivityLogger;

/**
 * Story 3.1 — Public gallery viewer controller.
 *
 * Resolves a share token to a gallery and routes to the correct view:
 *   - Presentation → image grid, no gate (Story 3.2)
 *   - Proofing → identity gate first, then image grid (Stories 3.3 / 3.4)
 *
 * This is a public route — no auth middleware.
 */
class GalleryViewerController extends Controller
{
    /**
     * View count thresholds that trigger a GalleryViewed event.
     * Only these milestones send notifications — not every page load.
     */
    private const VIEW_NOTIFICATION_THRESHOLDS = [1, 5, 10, 25, 50];

    /**
     * GET /g/{token}
     *
     * Resolve the share token, validate it, log access, and route to the
     * correct view based on gallery type.
     */
    public function show(Request $request, string $token)
    {
        $share = GalleryShare::where('share_token', $token)
            ->with('gallery')
            ->first();

        if ($share === null) {
            abort(404);
        }

        if (! $share->isValid()) {
            return response()->view('prophoto-gallery::viewer.expired', [
                'gallery' => $share->gallery,
            ], 410);
        }

        $gallery = $share->gallery;

        if ($gallery === null || $gallery->trashed()) {
            abort(404);
        }

        // Track the access
        $share->incrementViewCount();

        // Log to activity ledger
        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'gallery_viewed',
            actorType: $share->isIdentityConfirmed() ? 'share_identity' : 'studio_user',
            actorEmail: $share->confirmed_email,
            galleryShareId: $share->id,
            metadata: [
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );

        // Story 6.3 — Dispatch GalleryViewed at milestone thresholds only
        if (in_array($share->access_count, self::VIEW_NOTIFICATION_THRESHOLDS, true)) {
            GalleryViewed::dispatch(
                galleryId:      $gallery->id,
                galleryShareId: $share->id,
                studioId:       $gallery->studio_id,
                galleryName:    $gallery->subject_name ?? 'Untitled Gallery',
                viewedByEmail:  $share->confirmed_email ?? $share->shared_with_email ?? 'unknown',
                viewCount:      $share->access_count,
                viewedAt:       now()->toIso8601String(),
                sharedByUserId: $share->shared_by_user_id,
            );
        }

        // Route based on gallery type
        if ($gallery->isPresentation()) {
            return app(PresentationViewerController::class)->show($gallery, $share);
        }

        // Proofing — identity gate check (Story 3.3)
        if (! $share->isIdentityConfirmed()) {
            return app(IdentityGateController::class)->showGate($gallery, $share);
        }

        // Proofing — identity confirmed, show proofing viewer (Story 3.4)
        return app(ProofingViewerController::class)->show($gallery, $share);
    }
}
