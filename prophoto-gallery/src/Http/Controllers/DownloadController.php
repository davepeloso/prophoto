<?php

namespace ProPhoto\Gallery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ProPhoto\Gallery\Events\ImageDownloaded;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryAccessLog;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Models\Image;
use ProPhoto\Gallery\Services\GalleryActivityLogger;

/**
 * Story 6.1 — Public download endpoint for share-scoped image downloads.
 *
 * Clients access this via GET /g/{token}/download/{image}.
 * Enforces can_download permission and max_downloads limit on the share.
 * Increments download counters on both GalleryShare and Gallery atomically.
 * Dispatches ImageDownloaded event for the notifications package.
 */
class DownloadController extends Controller
{
    /**
     * GET /g/{token}/download/{image}
     *
     * Validate the share, check permissions, increment counters,
     * log the download, dispatch the event, and redirect to the image URL.
     */
    public function download(Request $request, string $token, int $imageId)
    {
        // ── Resolve share ────────────────────────────────────────────────
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

        // ── Check download permission ────────────────────────────────────
        if (! $share->can_download) {
            abort(403, 'Downloads are not enabled for this share link.');
        }

        if ($share->hasReachedMaxDownloads()) {
            abort(403, 'Download limit reached. Contact the photographer for access.');
        }

        // ── Resolve image ────────────────────────────────────────────────
        $image = Image::where('id', $imageId)
            ->where('gallery_id', $gallery->id)
            ->first();

        if ($image === null) {
            abort(404, 'Image not found in this gallery.');
        }

        // ── Resolve download URL ─────────────────────────────────────────
        $downloadUrl = $image->resolved_url;

        if ($downloadUrl === null) {
            abort(404, 'Image file is not available for download.');
        }

        // ── Increment counters (atomic) ──────────────────────────────────
        $share->incrementDownloadCount();
        $gallery->incrementDownloadCount();

        // ── Log to activity ledger ───────────────────────────────────────
        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'download',
            actorType: $share->isIdentityConfirmed() ? 'share_identity' : 'studio_user',
            actorEmail: $share->confirmed_email,
            galleryShareId: $share->id,
            imageId: $image->id,
            metadata: [
                'ip'              => $request->ip(),
                'user_agent'      => $request->userAgent(),
                'image_filename'  => $image->original_filename ?? $image->filename,
                'download_count'  => $share->download_count,
                'max_downloads'   => $share->max_downloads,
            ],
        );

        // ── Log to access log ────────────────────────────────────────────
        GalleryAccessLog::create([
            'gallery_id'    => $gallery->id,
            'action'        => GalleryAccessLog::ACTION_DOWNLOAD,
            'resource_type' => 'share',
            'resource_id'   => $share->id,
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
            'metadata'      => [
                'image_id' => $image->id,
                'filename' => $image->original_filename ?? $image->filename,
            ],
        ]);

        // ── Dispatch event (Story 6.2 — notifications will listen) ───────
        ImageDownloaded::dispatch(
            galleryId:            $gallery->id,
            galleryShareId:       $share->id,
            studioId:             $gallery->studio_id,
            galleryName:          $gallery->subject_name ?? 'Untitled Gallery',
            imageId:              $image->id,
            imageFilename:        $image->original_filename ?? $image->filename ?? 'unknown',
            downloadedByEmail:    $share->confirmed_email ?? 'unknown',
            shareDownloadCount:   $share->download_count,
            shareMaxDownloads:    $share->max_downloads,
            galleryDownloadCount: $gallery->download_count,
            downloadedAt:         now()->toIso8601String(),
            sharedByUserId:       $share->shared_by_user_id,
        );

        // ── Redirect to file ─────────────────────────────────────────────
        return redirect()->away($downloadUrl);
    }
}
