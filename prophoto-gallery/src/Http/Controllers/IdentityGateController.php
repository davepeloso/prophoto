<?php

namespace ProPhoto\Gallery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Services\GalleryActivityLogger;

/**
 * Story 3.3 — Identity gate for proofing galleries.
 *
 * Before a visitor can interact with a proofing gallery they must confirm
 * their email address. This is a trust-based gate (no OTP in Phase 2).
 *
 * The confirmed_email may differ from shared_with_email — both are recorded
 * in the activity ledger for audit purposes.
 */
class IdentityGateController extends Controller
{
    /**
     * Show the identity confirmation form.
     */
    public function showGate(Gallery $gallery, GalleryShare $share)
    {
        return view('prophoto-gallery::viewer.identity-gate', [
            'gallery' => $gallery,
            'share'   => $share,
        ]);
    }

    /**
     * POST /g/{token}/confirm
     *
     * Validate the email, confirm identity on the share, log to the
     * activity ledger, and redirect back to the gallery viewer.
     */
    public function confirmIdentity(Request $request, string $token)
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

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $share->confirmIdentity($validated['email']);

        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'identity_confirmed',
            actorType: 'share_identity',
            actorEmail: $validated['email'],
            galleryShareId: $share->id,
            metadata: [
                'shared_with_email' => $share->shared_with_email,
                'ip'                => $request->ip(),
                'user_agent'        => $request->userAgent(),
            ],
        );

        return redirect()->route('gallery.viewer.show', ['token' => $token]);
    }
}
