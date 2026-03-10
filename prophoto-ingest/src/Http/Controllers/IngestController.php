<?php

namespace ProPhoto\Ingest\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use ProPhoto\Ingest\Models\ProxyImage;
use ProPhoto\Ingest\Models\Tag;
use ProPhoto\Ingest\Services\MetadataExtractor;
use ProPhoto\Ingest\Jobs\ProcessImageIngestJob;
use ProPhoto\Ingest\Jobs\ProcessPreviewJob;
use ProPhoto\Ingest\Jobs\EnhancePreviewJob;

class IngestController extends Controller
{
    public function __construct(
        protected MetadataExtractor $metadataExtractor
    ) {}

    /**
     * Display the main ingest panel
     */
    public function index(Request $request): Response
    {
        $photos = ProxyImage::forUser($request->user()->id)
            ->orderBy('order_index')
            ->get()
            ->map(fn($photo) => $photo->toReactArray());

        $tags = Tag::orderBy('name')->get()->map(function($tag) {
            return [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
                'tag_type' => $tag->tag_type ?? 'normal',
            ];
        });
        $quickTags = config('ingest.tagging.quick_tags', []);

        return Inertia::render('Ingest/Panel', [
            'initialPhotos' => $photos,
            'availableTags' => $tags,
            'quickTags' => $quickTags,
            'config' => [
                'maxFileSize' => 100 * 1024 * 1024, // 100MB
                'acceptedTypes' => [
                    'image/jpeg', 'image/png', 'image/tiff',
                    // Adobe DNG
                    'image/dng', 'image/x-adobe-dng',
                    // Canon RAW
                    'image/x-canon-cr2', 'image/x-canon-cr3',
                    // Nikon RAW
                    'image/x-nikon-nef',
                    // Sony RAW
                    'image/x-sony-arw',
                    // Olympus RAW
                    'image/x-olympus-orf',
                    // Panasonic RAW
                    'image/x-panasonic-rw2',
                    // Pentax RAW
                    'image/x-pentax-pef',
                    // Fujifilm RAW
                    'image/x-fuji-raf',
                    // Samsung RAW
                    'image/x-samsung-srw',
                ],
            ],
        ])->rootView('ingest::app');
    }

    /**
     * Handle file upload (creates proxy record) - FAST PATH
     *
     * This method prioritizes speed by:
     * 1. Extracting metadata with ExifTool -fast2 flag
     * 2. Using embedded ThumbnailImage (~160px) instead of generating
     * 3. Deferring preview generation to a background job
     *
     * Target: <500ms per file (was 2-5 seconds)
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max
        ]);

        $file = $request->file('file');
        $uuid = Str::uuid()->toString();
        $startTime = microtime(true);

        // Store temp file
        $tempDisk = config('ingest.storage.temp_disk', 'local');
        $tempPath = config('ingest.storage.temp_path', 'ingest-temp');
        $storedPath = $file->storeAs($tempPath, $uuid . '.' . $file->getClientOriginalExtension(), $tempDisk);

        $fullPath = Storage::disk($tempDisk)->path($storedPath);

        // FAST: Extract metadata with speed optimizations
        $extractionResult = $this->metadataExtractor->extractFast($fullPath);

        $metadataTime = round((microtime(true) - $startTime) * 1000, 2);

        // FAST: Extract embedded thumbnail only (tiny ~160px, not full preview)
        $thumbnailPath = null;
        if (config('ingest.exif.thumbnail.enabled', true)) {
            $thumbnailPath = $this->metadataExtractor->extractEmbeddedThumbnail($fullPath, $uuid);

            // If no embedded thumbnail, we'll get one from the background job
            // Don't block here trying to generate one
        }

        $thumbnailTime = round((microtime(true) - $startTime) * 1000, 2) - $metadataTime;

        // Create proxy record - NO PREVIEW YET (deferred to background)
        $proxy = ProxyImage::create([
            'uuid' => $uuid,
            'user_id' => $request->user()->id,
            'filename' => $file->getClientOriginalName(),
            'temp_path' => $storedPath,
            'thumbnail_path' => $thumbnailPath,
            'preview_path' => null, // Will be set by background job
            'preview_status' => 'pending', // NEW FIELD
            'metadata' => $extractionResult['metadata'],
            'metadata_raw' => $extractionResult['metadata_raw'],
            'metadata_error' => $extractionResult['error'],
            'extraction_method' => $extractionResult['extraction_method'],
            'order_index' => ProxyImage::forUser($request->user()->id)->max('order_index') + 1,
        ]);

        // Dispatch background job for preview generation
        ProcessPreviewJob::dispatch($uuid);

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        \Log::info('Fast upload completed', [
            'uuid' => $uuid,
            'filename' => $file->getClientOriginalName(),
            'total_ms' => $totalTime,
            'metadata_ms' => $metadataTime,
            'thumbnail_ms' => $thumbnailTime,
            'has_thumbnail' => $thumbnailPath !== null,
            'extraction_method' => $extractionResult['extraction_method'],
        ]);

        return response()->json([
            'photo' => $proxy->toReactArray(),
        ]);
    }

    /**
     * Update a proxy image (cull, star, tag, rotate, reorder)
     */
    public function update(Request $request, string $uuid)
    {
        $proxy = ProxyImage::where('uuid', $uuid)
            ->forUser($request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'is_culled' => 'sometimes|boolean',
            'is_starred' => 'sometimes|boolean',
            'rating' => 'sometimes|integer|min:0|max:5',
            'rotation' => 'sometimes|integer',
            'order_index' => 'sometimes|integer',
            'tags_json' => 'sometimes|array',
        ]);

        $proxy->update($validated);

        return response()->json([
            'photo' => $proxy->fresh()->toReactArray(),
        ]);
    }

    /**
     * Batch update multiple proxy images
     */
    public function batchUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'string',
            'updates' => 'required|array',
            'updates.is_culled' => 'sometimes|boolean',
            'updates.is_starred' => 'sometimes|boolean',
            'updates.rating' => 'sometimes|integer|min:0|max:5',
            'updates.rotation' => 'sometimes|integer',
            'updates.order_index' => 'sometimes|integer',
            'updates.tags_json' => 'sometimes|array',
            'updates.tags_json.*' => 'string',
            'updates.tags' => 'sometimes|array',
            'updates.tags.*.name' => 'required_with:updates.tags|string|max:50',
            'updates.tags.*.tag_type' => 'sometimes|string|in:normal,project,filename',
        ]);

        $updated = [];

        foreach ($validated['ids'] as $uuid) {
            $proxy = ProxyImage::where('uuid', $uuid)
                ->forUser($request->user()->id)
                ->first();

            if ($proxy) {
                $updates = $validated['updates'] ?? [];

                // Handle new tag relationship format (preferred)
                if (array_key_exists('tags', $updates)) {
                    $tagIds = [];
                    $projectTagCount = 0;
                    $filenameTagCount = 0;

                    // Count existing special tags
                    $existingTags = $proxy->tags()->get();
                    foreach ($existingTags as $existingTag) {
                        if ($existingTag->tag_type === Tag::TYPE_PROJECT) {
                            $projectTagCount++;
                        }
                        if ($existingTag->tag_type === Tag::TYPE_FILENAME) {
                            $filenameTagCount++;
                        }
                    }

                    foreach ($updates['tags'] as $tagData) {
                        $tagType = $tagData['tag_type'] ?? 'normal';
                        
                        // Skip if exceeds limits
                        if ($tagType === Tag::TYPE_PROJECT && $projectTagCount >= 1) {
                            continue;
                        }
                        if ($tagType === Tag::TYPE_FILENAME && $filenameTagCount >= 1) {
                            continue;
                        }

                        $tag = Tag::findOrCreateByName($tagData['name'], $tagType);
                        
                        if (!in_array($tag->id, $tagIds)) {
                            $tagIds[] = $tag->id;
                            
                            if ($tagType === Tag::TYPE_PROJECT) $projectTagCount++;
                            if ($tagType === Tag::TYPE_FILENAME) $filenameTagCount++;
                        }
                    }

                    // Attach new tags (append mode)
                    $existingTagIds = $proxy->tags()->pluck('id')->toArray();
                    $newTagIds = array_diff($tagIds, $existingTagIds);
                    $proxy->tags()->attach($newTagIds);

                    // Update tags_json for backwards compatibility
                    $updates['tags_json'] = $proxy->tags()->pluck('name')->toArray();
                    
                    // Remove tags from updates array so it doesn't try to update a non-existent column
                    unset($updates['tags']);
                }
                // Handle legacy tags_json format (backwards compatibility)
                elseif (array_key_exists('tags_json', $updates)) {
                    $existingTags = is_array($proxy->tags_json) ? $proxy->tags_json : [];
                    $newTags = is_array($updates['tags_json']) ? $updates['tags_json'] : [];

                    $mergedTags = array_values(array_unique(array_filter(
                        array_map(fn ($tag) => trim((string) $tag), array_merge($existingTags, $newTags)),
                        fn ($tag) => $tag !== ''
                    )));

                    $updates['tags_json'] = $mergedTags;
                    
                    // Also sync to relationship table
                    $tagIds = [];
                    foreach ($mergedTags as $tagName) {
                        $tag = Tag::findOrCreateByName($tagName);
                        $tagIds[] = $tag->id;
                    }
                    $proxy->tags()->sync($tagIds);
                }

                $proxy->update($updates);
                $updated[] = $proxy->fresh()->toReactArray();
            }
        }

        return response()->json([
            'photos' => $updated,
        ]);
    }

    /**
     * Reorder photos (drag-drop)
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'string',
        ]);

        foreach ($validated['order'] as $index => $uuid) {
            ProxyImage::where('uuid', $uuid)
                ->forUser($request->user()->id)
                ->update(['order_index' => $index]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Delete a proxy image
     */
    public function destroy(Request $request, string $uuid)
    {
        $proxy = ProxyImage::where('uuid', $uuid)
            ->forUser($request->user()->id)
            ->firstOrFail();

        // Delete temp files
        $tempDisk = config('ingest.storage.temp_disk', 'local');
        Storage::disk($tempDisk)->delete($proxy->temp_path);
        if ($proxy->thumbnail_path) {
            Storage::disk($tempDisk)->delete($proxy->thumbnail_path);
        }
        if ($proxy->preview_path) {
            Storage::disk($tempDisk)->delete($proxy->preview_path);
        }

        $proxy->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Start the ingest process for selected photos
     */
    public function ingest(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'string',
            'association' => 'nullable|array',
            'association.type' => 'nullable|string',
            'association.id' => 'nullable|integer',
        ]);

        // DEBUG: Log ingest trigger
        \Log::info('Ingest request received', [
            'user_id' => $request->user()->id,
            'requested_ids' => $validated['ids'],
            'association' => $validated['association'] ?? null,
        ]);

        $proxies = ProxyImage::whereIn('uuid', $validated['ids'])
            ->forUser($request->user()->id)
            ->notCulled()
            ->orderBy('order_index')
            ->get();

        // DEBUG: Log proxy query results
        \Log::info('Proxy images queried', [
            'user_id' => $request->user()->id,
            'requested_ids' => $validated['ids'],
            'found_count' => $proxies->count(),
            'proxy_uuids' => $proxies->pluck('uuid')->toArray(),
            'proxy_temp_paths' => $proxies->pluck('temp_path')->toArray(),
        ]);

        if ($proxies->isEmpty()) {
            \Log::warning('No proxy images found for ingest', [
                'user_id' => $request->user()->id,
                'requested_ids' => $validated['ids'],
            ]);
            return response()->json(['error' => 'No valid photos to ingest'], 422);
        }

        // Dispatch jobs for each photo
        $sequence = config('ingest.schema.sequence_start', 1);

        foreach ($proxies as $proxy) {
            // DEBUG: Log each job dispatch
            \Log::info('Dispatching ingest job', [
                'proxy_uuid' => $proxy->uuid,
                'sequence' => $sequence,
                'association' => $validated['association'] ?? null,
                'temp_path' => $proxy->temp_path,
                'metadata_keys' => array_keys($proxy->metadata ?? []),
            ]);

            ProcessImageIngestJob::dispatch(
                $proxy,
                $sequence,
                $validated['association'] ?? null
            );
            $sequence++;
        }

        return response()->json([
            'message' => "Queued {$proxies->count()} photos for ingest",
            'count' => $proxies->count(),
        ]);
    }

    /**
     * Get available tags for autocomplete
     */
    public function tags(Request $request)
    {
        $search = $request->get('q', '');
        $type = $request->get('type'); // Optional filter by type

        $tags = Tag::query()
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($type, fn($q) => $q->where('tag_type', $type))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'color' => $tag->color,
                    'tag_type' => $tag->tag_type ?? 'normal',
                ];
            });

        return response()->json($tags);
    }

    /**
     * Create a new tag
     */
    public function createTag(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'tag_type' => 'sometimes|string|in:normal,project,filename',
            'color' => 'sometimes|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $tagType = $validated['tag_type'] ?? 'normal';
        $tag = Tag::findOrCreateByName($validated['name'], $tagType);
        
        // Update color if provided
        if (isset($validated['color']) && $tag->color !== $validated['color']) {
            $tag->color = $validated['color'];
            $tag->save();
        }

        return response()->json([
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
                'tag_type' => $tag->tag_type,
            ]
        ]);
    }

    /**
     * Assign tags to a proxy image
     */
    public function assignTags(Request $request, string $uuid)
    {
        $proxy = ProxyImage::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'tags' => 'required|array',
            'tags.*.name' => 'required|string|max:50',
            'tags.*.tag_type' => 'sometimes|string|in:normal,project,filename',
        ]);

        $tagIds = [];
        $projectTagCount = 0;
        $filenameTagCount = 0;

        // Check existing tags on this proxy
        $existingTags = $proxy->tags()->get();
        foreach ($existingTags as $existingTag) {
            if ($existingTag->tag_type === Tag::TYPE_PROJECT) {
                $projectTagCount++;
            }
            if ($existingTag->tag_type === Tag::TYPE_FILENAME) {
                $filenameTagCount++;
            }
        }

        foreach ($validated['tags'] as $tagData) {
            $tagType = $tagData['tag_type'] ?? 'normal';
            
            // Validate tag type limits
            if ($tagType === Tag::TYPE_PROJECT) {
                if ($projectTagCount >= 1) {
                    return response()->json([
                        'error' => 'Only one project tag allowed per image',
                        'tag' => $tagData['name'],
                    ], 422);
                }
                $projectTagCount++;
            }
            
            if ($tagType === Tag::TYPE_FILENAME) {
                if ($filenameTagCount >= 1) {
                    return response()->json([
                        'error' => 'Only one filename tag allowed per image',
                        'tag' => $tagData['name'],
                    ], 422);
                }
                $filenameTagCount++;
            }

            $tag = Tag::findOrCreateByName($tagData['name'], $tagType);
            $tagIds[] = $tag->id;
        }

        // Sync tags (this will replace existing tags)
        $proxy->tags()->sync($tagIds);

        // Also update tags_json for backwards compatibility
        $proxy->tags_json = $proxy->tags()->pluck('name')->toArray();
        $proxy->save();

        return response()->json([
            'message' => 'Tags assigned successfully',
            'tags' => $proxy->tags()->get()->map(function($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'color' => $tag->color,
                    'tag_type' => $tag->tag_type,
                ];
            }),
        ]);
    }

    /**
     * Add tags to a proxy image (append mode)
     */
    public function addTags(Request $request, string $uuid)
    {
        $proxy = ProxyImage::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'tags' => 'required|array',
            'tags.*.name' => 'required|string|max:50',
            'tags.*.tag_type' => 'sometimes|string|in:normal,project,filename',
        ]);

        $existingTagIds = $proxy->tags()->pluck('id')->toArray();
        $newTagIds = [];

        // Count existing special tags
        $existingTags = $proxy->tags()->get();
        $projectTagCount = $existingTags->where('tag_type', Tag::TYPE_PROJECT)->count();
        $filenameTagCount = $existingTags->where('tag_type', Tag::TYPE_FILENAME)->count();

        foreach ($validated['tags'] as $tagData) {
            $tagType = $tagData['tag_type'] ?? 'normal';
            
            // Validate tag type limits
            if ($tagType === Tag::TYPE_PROJECT && $projectTagCount >= 1) {
                return response()->json([
                    'error' => 'Only one project tag allowed per image',
                    'tag' => $tagData['name'],
                ], 422);
            }
            
            if ($tagType === Tag::TYPE_FILENAME && $filenameTagCount >= 1) {
                return response()->json([
                    'error' => 'Only one filename tag allowed per image',
                    'tag' => $tagData['name'],
                ], 422);
            }

            $tag = Tag::findOrCreateByName($tagData['name'], $tagType);
            
            if (!in_array($tag->id, $existingTagIds)) {
                $newTagIds[] = $tag->id;
                
                if ($tagType === Tag::TYPE_PROJECT) $projectTagCount++;
                if ($tagType === Tag::TYPE_FILENAME) $filenameTagCount++;
            }
        }

        // Attach new tags without detaching existing ones
        $proxy->tags()->attach($newTagIds);

        // Update tags_json for backwards compatibility
        $proxy->tags_json = $proxy->tags()->pluck('name')->toArray();
        $proxy->save();

        return response()->json([
            'message' => 'Tags added successfully',
            'tags' => $proxy->tags()->get()->map(function($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'color' => $tag->color,
                    'tag_type' => $tag->tag_type,
                ];
            }),
        ]);
    }

    /**
     * Remove a tag from a proxy image
     */
    public function removeTag(Request $request, string $uuid, int $tagId)
    {
        $proxy = ProxyImage::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $proxy->tags()->detach($tagId);

        // Update tags_json for backwards compatibility
        $proxy->tags_json = $proxy->tags()->pluck('name')->toArray();
        $proxy->save();

        return response()->json([
            'message' => 'Tag removed successfully',
        ]);
    }

    /**
     * Get preview status for multiple photos
     *
     * Frontend polls this endpoint to check if previews are ready.
     * Returns only photos that have changed status since they were pending.
     */
    public function previewStatus(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'string|uuid',
        ]);

        $photos = ProxyImage::whereIn('uuid', $request->ids)
            ->where('user_id', $request->user()->id)
            ->get(['uuid', 'thumbnail_path', 'preview_path', 'preview_status'])
            ->map(fn($photo) => [
                'id' => $photo->uuid,
                'thumbnailUrl' => $photo->thumbnail_path
                    ? Storage::disk(config('ingest.storage.temp_disk', 'local'))->url($photo->thumbnail_path)
                    : null,
                'previewUrl' => $photo->preview_path
                    ? Storage::disk(config('ingest.storage.temp_disk', 'local'))->url($photo->preview_path)
                    : null,
                'previewStatus' => $photo->preview_status,
                'previewReady' => $photo->preview_status === 'ready',
            ]);

        return response()->json([
            'photos' => $photos,
        ]);
    }

    /**
     * Enhance preview quality for selected photos
     *
     * Increases preview size by 25% (capped at 4096px) and regenerates
     * from existing preview or source file in background job.
     */
    public function enhance(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'string|uuid',
        ]);

        $enhanced = [];
        $maxWidth = 4096; // Previews can be larger than thumbnails
        $defaultWidth = config('ingest.exif.preview.max_dimension', 2048);

        foreach ($request->ids as $uuid) {
            $proxy = ProxyImage::where('uuid', $uuid)
                ->forUser($request->user()->id)
                ->first();

            if (!$proxy) {
                continue;
            }

            // Calculate new width (25% increase)
            $currentWidth = $proxy->preview_width ?? $defaultWidth;
            $newWidth = (int) round($currentWidth * 1.25);

            // Cap at max width
            if ($newWidth > $maxWidth) {
                $newWidth = $maxWidth;
            }

            // Skip if already at max
            if ($currentWidth >= $maxWidth) {
                $enhanced[] = [
                    'id' => $uuid,
                    'status' => 'already_max',
                    'width' => $currentWidth,
                ];
                continue;
            }

            // Mark as requested and dispatch job
            $proxy->update([
                'enhancement_status' => 'requested',
                'enhancement_requested_at' => now(),
            ]);

            EnhancePreviewJob::dispatch($uuid, $newWidth);

            $enhanced[] = [
                'id' => $uuid,
                'status' => 'queued',
                'from_width' => $currentWidth,
                'to_width' => $newWidth,
            ];
        }

        return response()->json([
            'enhanced' => $enhanced,
        ]);
    }
}
