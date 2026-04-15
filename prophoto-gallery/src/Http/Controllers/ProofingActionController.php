<?php

namespace ProPhoto\Gallery\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Models\Image;
use ProPhoto\Gallery\Models\ImageApprovalState;
use ProPhoto\Gallery\Events\GallerySubmitted;
use ProPhoto\Gallery\Services\GalleryActivityLogger;

/**
 * Story 3.4 + 4.2 + 5.1 — Proofing action endpoints.
 *
 * All mutation endpoints for the proofing gallery viewer.
 * Every action validates the share token, checks lock state,
 * and logs to the activity ledger.
 *
 * Story 4.2: Constraint enforcement (max_approvals, max_pending, min_approvals).
 * Story 5.1: Dispatches GallerySubmitted event on successful submit.
 *
 * Responses are JSON for Alpine.js fetch() calls.
 */
class ProofingActionController extends Controller
{
    /**
     * POST /g/{token}/approve/{image}
     *
     * Set image status to 'approved'.
     * Enforces max_approvals constraint from mode_config.
     */
    public function approve(Request $request, string $token, int $imageId): JsonResponse
    {
        [$gallery, $share, $image] = $this->resolveContext($token, $imageId);

        // Check max_approvals constraint — count approved + approved_pending
        $modeConfig    = $gallery->getModeConfig();
        $maxApprovals  = $modeConfig['max_approvals'] ?? null;

        if ($maxApprovals !== null) {
            $currentApproved = ImageApprovalState::where('gallery_id', $gallery->id)
                ->where('gallery_share_id', $share->id)
                ->whereIn('status', [
                    ImageApprovalState::STATUS_APPROVED,
                    ImageApprovalState::STATUS_APPROVED_PENDING,
                ])
                ->where('image_id', '!=', $image->id) // exclude current image (upsert)
                ->count();

            if ($currentApproved >= $maxApprovals) {
                return response()->json([
                    'error'      => "Maximum of {$maxApprovals} approvals reached.",
                    'constraint' => 'max_approvals',
                    'current'    => $currentApproved,
                    'max'        => $maxApprovals,
                ], 422);
            }
        }

        $state = $this->upsertState($gallery, $share, $image, ImageApprovalState::STATUS_APPROVED);

        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'approved',
            actorType: 'share_identity',
            actorEmail: $share->confirmed_email,
            galleryShareId: $share->id,
            imageId: $image->id,
        );

        return response()->json([
            'status'  => $state->status,
            'imageId' => $image->id,
        ]);
    }

    /**
     * POST /g/{token}/pending/{image}
     *
     * Set image status to 'approved_pending' with a pending type and optional note.
     * Enforces sequential pipeline and max_pending constraint from mode_config.
     */
    public function pending(Request $request, string $token, int $imageId): JsonResponse
    {
        $validated = $request->validate([
            'pending_type_id' => ['required', 'integer', 'exists:gallery_pending_types,id'],
            'pending_note'    => ['nullable', 'string', 'max:500'],
        ]);

        [$gallery, $share, $image] = $this->resolveContext($token, $imageId);

        $modeConfig = $gallery->getModeConfig();

        // Sequential pipeline: must be approved before marking pending
        if ($modeConfig['pipeline_sequential'] ?? true) {
            $currentState = ImageApprovalState::where('gallery_id', $gallery->id)
                ->where('image_id', $image->id)
                ->where('gallery_share_id', $share->id)
                ->first();

            if (! $currentState || ! $currentState->isApproved()) {
                return response()->json([
                    'error' => 'Image must be approved before adding a pending request.',
                ], 422);
            }
        }

        // Check max_pending constraint
        $maxPending = $modeConfig['max_pending'] ?? null;

        if ($maxPending !== null) {
            $currentPending = ImageApprovalState::where('gallery_id', $gallery->id)
                ->where('gallery_share_id', $share->id)
                ->where('status', ImageApprovalState::STATUS_APPROVED_PENDING)
                ->where('image_id', '!=', $image->id) // exclude current image (upsert)
                ->count();

            if ($currentPending >= $maxPending) {
                return response()->json([
                    'error'      => "Maximum of {$maxPending} pending requests reached.",
                    'constraint' => 'max_pending',
                    'current'    => $currentPending,
                    'max'        => $maxPending,
                ], 422);
            }
        }

        $state = $this->upsertState(
            $gallery, $share, $image,
            ImageApprovalState::STATUS_APPROVED_PENDING,
            $validated['pending_type_id'],
            $validated['pending_note'] ?? null,
        );

        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'approved_pending',
            actorType: 'share_identity',
            actorEmail: $share->confirmed_email,
            galleryShareId: $share->id,
            imageId: $image->id,
            metadata: [
                'pending_type_id' => $validated['pending_type_id'],
                'pending_note'    => $validated['pending_note'] ?? null,
            ],
        );

        return response()->json([
            'status'        => $state->status,
            'imageId'       => $image->id,
            'pendingTypeId' => $state->pending_type_id,
        ]);
    }

    /**
     * POST /g/{token}/clear/{image}
     *
     * Reset image approval state to 'cleared'.
     */
    public function clear(Request $request, string $token, int $imageId): JsonResponse
    {
        [$gallery, $share, $image] = $this->resolveContext($token, $imageId);

        $state = $this->upsertState($gallery, $share, $image, ImageApprovalState::STATUS_CLEARED);

        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'cleared',
            actorType: 'share_identity',
            actorEmail: $share->confirmed_email,
            galleryShareId: $share->id,
            imageId: $image->id,
        );

        return response()->json([
            'status'  => $state->status,
            'imageId' => $image->id,
        ]);
    }

    /**
     * POST /g/{token}/rate/{image}
     *
     * Rate an image 1–5 stars. Stored in image_approval_states metadata
     * and logged to the activity ledger.
     */
    public function rate(Request $request, string $token, int $imageId): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        [$gallery, $share, $image] = $this->resolveContext($token, $imageId);

        $modeConfig = $gallery->getModeConfig();
        if (! ($modeConfig['ratings_enabled'] ?? true)) {
            return response()->json(['error' => 'Ratings are disabled for this gallery.'], 422);
        }

        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'rated',
            actorType: 'share_identity',
            actorEmail: $share->confirmed_email,
            galleryShareId: $share->id,
            imageId: $image->id,
            metadata: ['rating' => $validated['rating']],
        );

        return response()->json([
            'imageId' => $image->id,
            'rating'  => $validated['rating'],
        ]);
    }

    /**
     * POST /g/{token}/submit
     *
     * Submit the client's selections. Locks the share token read-only.
     */
    public function submit(Request $request, string $token): JsonResponse
    {
        $share = GalleryShare::where('share_token', $token)
            ->with('gallery')
            ->firstOrFail();

        if (! $share->isValid()) {
            return response()->json(['error' => 'Share link is no longer valid.'], 410);
        }

        $gallery = $share->gallery;

        if ($share->is_locked) {
            return response()->json(['error' => 'Selections have already been submitted.'], 422);
        }

        // Check min_approvals constraint before allowing submission
        $modeConfig   = $gallery->getModeConfig();
        $minApprovals = $modeConfig['min_approvals'] ?? null;

        if ($minApprovals !== null) {
            $approvedCount = ImageApprovalState::where('gallery_id', $gallery->id)
                ->where('gallery_share_id', $share->id)
                ->whereIn('status', [
                    ImageApprovalState::STATUS_APPROVED,
                    ImageApprovalState::STATUS_APPROVED_PENDING,
                ])
                ->count();

            if ($approvedCount < $minApprovals) {
                return response()->json([
                    'error'      => "At least {$minApprovals} images must be approved before submitting.",
                    'constraint' => 'min_approvals',
                    'current'    => $approvedCount,
                    'min'        => $minApprovals,
                ], 422);
            }
        }

        $share->update([
            'submitted_at' => now(),
            'is_locked'    => true,
        ]);

        // Gather stats for logging and event
        $approvedCount = ImageApprovalState::where('gallery_id', $gallery->id)
            ->where('gallery_share_id', $share->id)
            ->approved()
            ->count();

        $pendingCount = ImageApprovalState::where('gallery_id', $gallery->id)
            ->where('gallery_share_id', $share->id)
            ->where('status', ImageApprovalState::STATUS_APPROVED_PENDING)
            ->count();

        $totalImages = $gallery->images()->count();

        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'gallery_submitted',
            actorType: 'share_identity',
            actorEmail: $share->confirmed_email,
            galleryShareId: $share->id,
            metadata: [
                'approved_count' => $approvedCount,
            ],
        );

        // Story 5.1 — Notify listeners (e.g. prophoto-notifications)
        GallerySubmitted::dispatch(
            galleryId:        $gallery->id,
            galleryShareId:   $share->id,
            studioId:         $gallery->studio_id,
            galleryName:      $gallery->subject_name ?? 'Untitled Gallery',
            submittedByEmail: $share->confirmed_email,
            approvedCount:    $approvedCount,
            pendingCount:     $pendingCount,
            totalImages:      $totalImages,
            submittedAt:      $share->submitted_at->toIso8601String(),
            sharedByUserId:   $share->shared_by_user_id,
        );

        return response()->json([
            'submitted' => true,
            'message'   => 'Your selections have been submitted.',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Resolve and validate the share token, gallery, and image.
     * Aborts if locked, invalid, or image doesn't belong to gallery.
     *
     * @return array{0: Gallery, 1: GalleryShare, 2: Image}
     */
    private function resolveContext(string $token, int $imageId): array
    {
        $share = GalleryShare::where('share_token', $token)
            ->with('gallery')
            ->firstOrFail();

        if (! $share->isValid()) {
            abort(410, 'Share link is no longer valid.');
        }

        if (! $share->isIdentityConfirmed()) {
            abort(403, 'Identity not confirmed.');
        }

        if ($share->is_locked) {
            abort(422, 'Selections have been submitted. Gallery is read-only.');
        }

        $gallery = $share->gallery;

        $image = Image::where('id', $imageId)
            ->where('gallery_id', $gallery->id)
            ->firstOrFail();

        return [$gallery, $share, $image];
    }

    /**
     * Create or update the approval state for an image + share combination.
     */
    private function upsertState(
        Gallery $gallery,
        GalleryShare $share,
        Image $image,
        string $status,
        ?int $pendingTypeId = null,
        ?string $pendingNote = null,
    ): ImageApprovalState {
        return ImageApprovalState::updateOrCreate(
            [
                'gallery_id'       => $gallery->id,
                'image_id'         => $image->id,
                'gallery_share_id' => $share->id,
            ],
            [
                'status'          => $status,
                'pending_type_id' => $pendingTypeId,
                'pending_note'    => $pendingNote,
                'actor_email'     => $share->confirmed_email,
                'set_at'          => now(),
            ],
        );
    }
}
