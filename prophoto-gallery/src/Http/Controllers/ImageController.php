<?php

namespace ProPhoto\Gallery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProPhoto\Access\Permissions;
use ProPhoto\Assets\Services\Assets\AssetCreationService;
use ProPhoto\Gallery\Http\Resources\ImageResource;
use ProPhoto\Gallery\Models\GalleryAccessLog;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\Image;

class ImageController extends Controller
{
    /**
     * Get all images for a gallery.
     */
    public function index(Request $request, Gallery $gallery)
    {
        if (!$request->user()->can('view', $gallery)) {
            abort(403);
        }

        $images = $gallery->images()
            ->with(['interactions'])
            ->paginate(50);

        // Log access
        GalleryAccessLog::create([
            'gallery_id' => $gallery->id,
            'user_id' => $request->user()->id,
            'action' => GalleryAccessLog::ACTION_VIEW,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return ImageResource::collection($images);
    }

    /**
     * Get a specific image.
     */
    public function show(Request $request, Gallery $gallery, Image $image)
    {
        if ($image->gallery_id !== $gallery->id) {
            abort(404, 'Image not found in this gallery');
        }

        if (!$request->user()->can('view', $gallery)) {
            abort(403);
        }

        $image->load(['interactions']);

        return new ImageResource($image);
    }

    /**
     * Upload images to a gallery.
     */
    public function store(Request $request, Gallery $gallery)
    {
        if (!$request->user()->can(Permissions::UPLOAD_IMAGES)) {
            abort(403, 'You do not have permission to upload images.');
        }

        if (!$request->user()->can('update', $gallery)) {
            abort(403, 'You cannot upload to this gallery.');
        }

        $validated = $request->validate([
            'images' => 'required|array',
            'images.*' => 'required|image|max:51200', // 50MB max
        ]);

        $uploadedImages = [];
        $writeEnabled = (bool) config('prophoto-gallery.asset_spine.write_enabled', true);
        $writeFailOpen = (bool) config('prophoto-gallery.asset_spine.write_fail_open', false);
        $defaultAssetDisk = (string) config('prophoto-assets.storage.disk', 'local');

        foreach ($request->file('images') as $file) {
            $tmpPath = $file->store('tmp/gallery-uploads', 'local');
            $absolutePath = Storage::disk('local')->path($tmpPath);

            try {
                $dimensions = @getimagesize($absolutePath) ?: [null, null];

                $asset = null;
                if ($writeEnabled) {
                    /** @var AssetCreationService $assetCreation */
                    $assetCreation = app(AssetCreationService::class);
                    $asset = $assetCreation->createFromFile(
                        sourcePath: $absolutePath,
                        attributes: [
                            'studio_id' => (string) $gallery->studio_id,
                            'organization_id' => $gallery->organization_id,
                            'original_filename' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType(),
                            'logical_path' => 'galleries/' . $gallery->id,
                            'storage_driver' => $defaultAssetDisk,
                            'metadata_source' => 'gallery-upload',
                            'metadata_context' => [
                                'gallery_id' => $gallery->id,
                                'uploaded_by_user_id' => $request->user()->id,
                            ],
                            'raw_metadata' => [
                                'file_name' => $file->getClientOriginalName(),
                                'file_size' => $file->getSize(),
                                'mime_type' => $file->getMimeType(),
                                'ImageWidth' => is_numeric($dimensions[0] ?? null) ? (int) $dimensions[0] : null,
                                'ImageHeight' => is_numeric($dimensions[1] ?? null) ? (int) $dimensions[1] : null,
                                'source' => 'gallery-upload',
                            ],
                        ]
                    );
                }

                $sortOrder = ((int) $gallery->images()->max('sort_order')) + 1;
                $image = Image::create([
                    'gallery_id' => $gallery->id,
                    'asset_id' => $asset?->id,
                    'filename' => $file->getClientOriginalName(),
                    'original_filename' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'width' => is_numeric($dimensions[0] ?? null) ? (int) $dimensions[0] : null,
                    'height' => is_numeric($dimensions[1] ?? null) ? (int) $dimensions[1] : null,
                    'uploaded_by_user_id' => $request->user()->id,
                    'uploaded_at' => now(),
                    'sort_order' => $sortOrder,
                    'metadata' => [
                        'source' => 'gallery-upload',
                        'legacy_tmp_path' => $tmpPath,
                    ],
                    'imagekit_url' => $asset !== null
                        ? null
                        : Storage::disk('local')->url($tmpPath),
                ]);

                $uploadedImages[] = $image;
            } catch (\Throwable $e) {
                if (!$writeFailOpen) {
                    throw $e;
                }

                Log::error('Gallery asset write failed (fail-open fallback)', [
                    'gallery_id' => $gallery->id,
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);

                $sortOrder = ((int) $gallery->images()->max('sort_order')) + 1;
                $uploadedImages[] = Image::create([
                    'gallery_id' => $gallery->id,
                    'filename' => $file->getClientOriginalName(),
                    'original_filename' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by_user_id' => $request->user()->id,
                    'uploaded_at' => now(),
                    'sort_order' => $sortOrder,
                    'metadata' => [
                        'source' => 'gallery-upload-fail-open',
                        'error' => substr($e->getMessage(), 0, 200),
                    ],
                    'imagekit_url' => Storage::disk('local')->url($tmpPath),
                ]);
            } finally {
                Storage::disk('local')->delete($tmpPath);
            }
        }

        return ImageResource::collection($uploadedImages);
    }

    /**
     * Download an image.
     */
    public function download(Request $request, Gallery $gallery, Image $image)
    {
        if ($image->gallery_id !== $gallery->id) {
            abort(404);
        }

        if (!$request->user()->can(Permissions::DOWNLOAD_IMAGES)) {
            abort(403, 'You do not have permission to download images.');
        }

        // Log download
        GalleryAccessLog::create([
            'gallery_id' => $gallery->id,
            'user_id' => $request->user()->id,
            'action' => GalleryAccessLog::ACTION_DOWNLOAD,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['image_id' => $image->id],
        ]);

        if ($image->resolved_url) {
            return redirect()->away($image->resolved_url);
        }

        abort(404, 'Download source not found for this image.');
    }

    /**
     * Rate an image.
     */
    public function rate(Request $request, Gallery $gallery, Image $image)
    {
        if ($image->gallery_id !== $gallery->id) {
            abort(404);
        }

        if (!$request->user()->can(Permissions::RATE_IMAGES)) {
            abort(403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        // Create or update rating interaction
        $image->interactions()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'interaction_type' => 'rating',
            ],
            [
                'metadata' => ['rating' => $validated['rating']],
            ]
        );

        return response()->json(['message' => 'Image rated successfully']);
    }

    /**
     * Approve image for marketing.
     */
    public function approve(Request $request, Gallery $gallery, Image $image)
    {
        if ($image->gallery_id !== $gallery->id) {
            abort(404);
        }

        if (!$request->user()->can(Permissions::APPROVE_IMAGES)) {
            abort(403);
        }

        $metadata = is_array($image->metadata) ? $image->metadata : [];
        $metadata['is_marketing_approved'] = true;
        $metadata['approved_at'] = now()->toISOString();
        $metadata['approved_by_user_id'] = $request->user()->id;

        $image->update(['metadata' => $metadata]);

        return response()->json(['message' => 'Image approved for marketing']);
    }
}
