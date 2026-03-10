<?php

namespace ProPhoto\Gallery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ProPhoto\Access\Permissions;
use ProPhoto\Gallery\Http\Resources\ShareResource;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Models\GalleryAccessLog;
use ProPhoto\Gallery\Models\Gallery;

class ShareController extends Controller
{
    /**
     * Get all share links for a gallery.
     */
    public function index(Request $request, Gallery $gallery)
    {
        if (!$request->user()->can('view', $gallery)) {
            abort(403);
        }

        $shares = $gallery->shares()
            ->with(['createdBy'])
            ->latest()
            ->paginate(20);

        return ShareResource::collection($shares);
    }

    /**
     * Create a new share link.
     */
    public function store(Request $request, Gallery $gallery)
    {
        if (!$request->user()->can(Permissions::CREATE_SHARE_LINK)) {
            abort(403);
        }

        $validated = $request->validate([
            'shared_with_email' => 'required|email',
            'shared_with_user_id' => 'nullable|exists:users,id',
            'password' => 'nullable|string|min:6',
            'expires_at' => 'nullable|date|after:now',
            'can_view' => 'boolean',
            'can_download' => 'boolean',
            'can_approve' => 'boolean',
            'can_comment' => 'boolean',
            'can_share' => 'boolean',
            'max_downloads' => 'nullable|integer|min:1',
            'ip_whitelist' => 'nullable|array',
            'message' => 'nullable|string',
        ]);

        $share = GalleryShare::create([
            'gallery_id' => $gallery->id,
            'shared_by_user_id' => $request->user()->id,
            'shared_with_email' => $validated['shared_with_email'],
            'shared_with_user_id' => $validated['shared_with_user_id'] ?? null,
            'share_token' => Str::random(32),
            'password_hash' => isset($validated['password']) ? Hash::make($validated['password']) : null,
            'expires_at' => $validated['expires_at'] ?? null,
            'can_view' => $validated['can_view'] ?? true,
            'can_download' => $validated['can_download'] ?? false,
            'can_approve' => $validated['can_approve'] ?? false,
            'can_comment' => $validated['can_comment'] ?? false,
            'can_share' => $validated['can_share'] ?? false,
            'max_downloads' => $validated['max_downloads'] ?? null,
            'ip_whitelist' => $validated['ip_whitelist'] ?? null,
            'message' => $validated['message'] ?? null,
        ]);

        return new ShareResource($share);
    }

    /**
     * Access a gallery via share token.
     */
    public function show(Request $request, string $token)
    {
        $share = GalleryShare::where('share_token', $token)
            ->with(['gallery.images'])
            ->firstOrFail();

        // Check if share is valid
        if (!$share->isValid()) {
            abort(403, 'This share link has expired or reached its view limit.');
        }

        // Check password if required
        if ($share->password_hash) {
            $request->validate(['password' => 'required|string']);

            if (!Hash::check($request->password, $share->password_hash)) {
                abort(403, 'Invalid password');
            }
        }

        // Increment view count
        $share->incrementViewCount();

        // Log access
        GalleryAccessLog::create([
            'gallery_id' => $share->gallery_id,
            'user_id' => $request->user()?->id,
            'action' => GalleryAccessLog::ACTION_VIEW,
            'resource_type' => 'share',
            'resource_id' => $share->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return new ShareResource($share);
    }

    /**
     * Revoke a share link.
     */
    public function destroy(Request $request, Gallery $gallery, GalleryShare $share)
    {
        if ($share->gallery_id !== $gallery->id) {
            abort(404);
        }

        if (!$request->user()->can('delete', $share)) {
            abort(403);
        }

        $share->delete();

        return response()->json(['message' => 'Share link revoked successfully']);
    }

    /**
     * Get analytics for a share link.
     */
    public function analytics(Request $request, Gallery $gallery, GalleryShare $share)
    {
        if ($share->gallery_id !== $gallery->id) {
            abort(404);
        }

        if (!$request->user()->can('viewAnalytics', $share)) {
            abort(403);
        }

        $accessLogs = $share->accessLogs()
            ->select('action', \DB::raw('count(*) as count'))
            ->groupBy('action')
            ->get();

        return response()->json([
            'share_token' => $share->share_token,
            'view_count' => $share->access_count,
            'max_views' => $share->max_downloads,
            'expires_at' => $share->expires_at,
            'is_valid' => $share->isValid(),
            'access_logs' => $accessLogs,
            'unique_ips' => $share->accessLogs()->distinct('ip_address')->count(),
        ]);
    }
}
