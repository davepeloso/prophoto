# Two-Phase Fast Ingestion Implementation

## Overview

Refactor the photo ingestion system from synchronous thumbnail/preview generation to a two-phase architecture that prioritizes speed for the initial user experience.

**Current Problem:** Uploading photos is slow because we generate full thumbnails and previews synchronously during upload, blocking the UI.

**Solution:** Split ingestion into two phases:
1. **Phase 1 (Instant):** Upload file → extract EXIF metadata + embedded thumbnail → return to UI immediately
2. **Phase 2 (Background):** Queue job generates high-quality preview → updates record → notifies frontend

---

## Architecture Diagram

```
CURRENT (SLOW):
┌─────────────────────────────────────────────────────────────────┐
│ Upload Request (blocking, 2-5 seconds per RAW file)            │
├─────────────────────────────────────────────────────────────────┤
│ 1. Store file to disk                                          │
│ 2. ExifTool metadata extraction (~100ms)                       │
│ 3. generateThumbnail() - reads full RAW (~1-3s) ← BOTTLENECK   │
│ 4. generatePreview() - renders from RAW (~2-5s) ← BOTTLENECK   │
│ 5. Create DB record                                            │
│ 6. Return JSON to frontend                                     │
└─────────────────────────────────────────────────────────────────┘

NEW (FAST):
┌─────────────────────────────────────────────────────────────────┐
│ Phase 1: Upload Request (target: <500ms per file)              │
├─────────────────────────────────────────────────────────────────┤
│ 1. Store file to disk                                          │
│ 2. ExifTool fast metadata extraction (-fast2 flag)             │
│ 3. Extract embedded ThumbnailImage ONLY (~50ms)                │
│ 4. Create DB record with preview_status='pending'              │
│ 5. Dispatch background job                                     │
│ 6. Return JSON immediately                                     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ Phase 2: Background Job (async, no user waiting)               │
├─────────────────────────────────────────────────────────────────┤
│ 1. Extract PreviewImage (high-res embedded preview)            │
│ 2. If no embedded preview, generate from source                │
│ 3. Generate quality thumbnail FROM preview (not RAW)           │
│ 4. Update DB record: preview_path, thumbnail_path, status      │
│ 5. Broadcast event for real-time UI update (optional)          │
└─────────────────────────────────────────────────────────────────┘
```

---

## Task 1: Database Migration

Create a migration to add preview processing status tracking.

**File:** `database/migrations/xxxx_xx_xx_add_preview_status_to_proxy_images.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingest_proxy_images', function (Blueprint $table) {
            // Preview generation status: 'pending', 'processing', 'ready', 'failed'
            $table->string('preview_status', 20)->default('pending')->after('preview_path');
            
            // Track when preview was last attempted (for retry logic)
            $table->timestamp('preview_attempted_at')->nullable()->after('preview_status');
            
            // Store error message if preview generation fails
            $table->string('preview_error')->nullable()->after('preview_attempted_at');
            
            // Index for queue worker queries
            $table->index(['preview_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ingest_proxy_images', function (Blueprint $table) {
            $table->dropIndex(['preview_status', 'created_at']);
            $table->dropColumn(['preview_status', 'preview_attempted_at', 'preview_error']);
        });
    }
};
```

**Update the ProxyImage model** to include the new fields in `$fillable` and `$casts`:

```php
// In src/Models/ProxyImage.php

protected $fillable = [
    // ... existing fields ...
    'preview_status',
    'preview_attempted_at', 
    'preview_error',
];

protected $casts = [
    // ... existing casts ...
    'preview_attempted_at' => 'datetime',
];

// Add helper methods
public function isPreviewReady(): bool
{
    return $this->preview_status === 'ready' && $this->preview_path !== null;
}

public function isPreviewPending(): bool
{
    return in_array($this->preview_status, ['pending', 'processing']);
}
```

**Update `toReactArray()`** to include preview status:

```php
public function toReactArray(): array
{
    return [
        // ... existing fields ...
        'previewStatus' => $this->preview_status,
        'previewReady' => $this->isPreviewReady(),
    ];
}
```

---

## Task 2: New Fast Extraction Methods

Modify `MetadataExtractor` to support fast-path extraction.

**File:** `src/Services/MetadataExtractor.php`

### 2.1 Add Fast Metadata Extraction Method

```php
/**
 * Fast metadata extraction - optimized for speed during upload
 * 
 * Uses ExifTool with -fast2 flag which skips:
 * - Maker notes (camera-specific proprietary data)
 * - Large binary data
 * - Slower parsing operations
 * 
 * This is ~2-3x faster than full extraction while still getting
 * all the metadata we need for the UI (ISO, aperture, shutter, etc.)
 *
 * @param string $filePath Absolute path to the image file
 * @return array Extraction result with metadata, raw data, and status
 */
public function extractFast(string $filePath): array
{
    $result = [
        'metadata' => [],
        'metadata_raw' => null,
        'extraction_method' => 'none',
        'error' => null,
    ];

    // Basic file info (always available)
    $result['metadata']['FileSize'] = @filesize($filePath) ?: null;
    $result['metadata']['FileName'] = basename($filePath);

    if ($this->exifToolAvailable) {
        try {
            // Use fast2 mode for speed - skips maker notes
            $rawMetadata = $this->exifToolService->extractMetadata($filePath, [
                'speed_mode' => 'fast2',
            ]);

            if ($rawMetadata) {
                $result['metadata_raw'] = $rawMetadata;
                $result['extraction_method'] = 'exiftool_fast';
                
                // Normalize to application schema
                $normalized = $this->exifToolService->normalizeMetadata($rawMetadata);
                $result['metadata'] = array_merge($result['metadata'], $normalized);
                $result['metadata'] = array_merge($result['metadata'], $this->extractAdditionalFields($rawMetadata));
            }
        } catch (\Exception $e) {
            Log::warning('Fast ExifTool extraction failed', [
                'file' => basename($filePath),
                'error' => $e->getMessage(),
            ]);
            $result['error'] = $e->getMessage();
        }
    }

    // Fallback to PHP exif if ExifTool failed
    if ($result['extraction_method'] === 'none') {
        $result = $this->extractWithPhpExif($filePath, $result);
    }

    return $result;
}
```

### 2.2 Add Embedded Thumbnail Extraction (Tiny, Fast)

```php
/**
 * Extract ONLY the small embedded EXIF thumbnail (~160px)
 * 
 * This is NOT the same as PreviewImage (which is large, ~1-2MB).
 * The ThumbnailImage tag contains a tiny ~160x120px JPEG that's
 * embedded in virtually all camera files. It's perfect for:
 * - Instant display during upload
 * - Low bandwidth
 * - Fast extraction (~20-50ms)
 * 
 * We'll replace this with a better thumbnail later in the background job.
 *
 * @param string $filePath Source file path
 * @param string $uuid UUID for output filename
 * @return string|null Path to extracted thumbnail or null if not found
 */
public function extractEmbeddedThumbnail(string $filePath, string $uuid): ?string
{
    if (!$this->exifToolAvailable) {
        return null;
    }

    $tempDisk = config('ingest.storage.temp_disk', 'local');
    $tempPath = config('ingest.storage.temp_path', 'ingest-temp');
    $thumbnailDir = Storage::disk($tempDisk)->path($tempPath . '/thumbs');

    // Ensure directory exists
    if (!is_dir($thumbnailDir)) {
        mkdir($thumbnailDir, 0755, true);
    }

    $outputPath = $thumbnailDir . '/' . $uuid . '.jpg';

    try {
        // Force ThumbnailImage tag only (the tiny ~160px one)
        $result = $this->exifToolService->extractPreview($filePath, $outputPath, 'ThumbnailImage');

        if ($result !== false && file_exists($outputPath) && filesize($outputPath) > 0) {
            Log::debug('Extracted embedded thumbnail', [
                'file' => basename($filePath),
                'size' => filesize($outputPath),
            ]);
            return $tempPath . '/thumbs/' . $uuid . '.jpg';
        }
    } catch (\Exception $e) {
        Log::debug('No embedded thumbnail found', [
            'file' => basename($filePath),
            'error' => $e->getMessage(),
        ]);
    }

    return null;
}
```

### 2.3 Add Method to Generate Thumbnail from Preview

```php
/**
 * Generate a quality thumbnail from an already-extracted preview image
 * 
 * This is MUCH faster than generating from the original RAW file because:
 * - Preview is already a JPEG (no RAW decoding)
 * - Preview is already ~2048px (less data to process)
 * - No Imagick RAW delegate overhead
 *
 * @param string $previewPath Path to the preview image
 * @param string $uuid UUID for output filename
 * @return string|null Path to generated thumbnail or null on failure
 */
public function generateThumbnailFromPreview(string $previewPath, string $uuid): ?string
{
    $config = config('ingest.exif.thumbnail', []);
    $width = $config['width'] ?? 400;
    $height = $config['height'] ?? 400;
    $quality = $config['quality'] ?? 80;

    $tempDisk = config('ingest.storage.temp_disk', 'local');
    $tempPath = config('ingest.storage.temp_path', 'ingest-temp');
    $thumbnailPath = $tempPath . '/thumbs/' . $uuid . '.jpg';

    // Get full path to preview
    $fullPreviewPath = Storage::disk($tempDisk)->path($previewPath);
    
    if (!file_exists($fullPreviewPath)) {
        Log::warning('Preview file not found for thumbnail generation', [
            'preview_path' => $previewPath,
        ]);
        return null;
    }

    try {
        $image = $this->imageManager->read($fullPreviewPath);

        // Cover crop to square
        $image->cover($width, $height);

        // Auto-orient based on EXIF
        $image->orient();

        // Encode as JPEG
        $encoded = $image->toJpeg($quality);

        // Store
        Storage::disk($tempDisk)->put($thumbnailPath, $encoded);

        Log::debug('Generated thumbnail from preview', [
            'uuid' => $uuid,
            'size' => strlen($encoded),
        ]);

        return $thumbnailPath;
    } catch (\Exception $e) {
        Log::warning('Thumbnail generation from preview failed', [
            'uuid' => $uuid,
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}
```

### 2.4 Update ExifToolService for Speed Modes

**File:** `src/Services/ExifToolService.php`

Update the `buildMetadataArgs()` method to support speed modes:

```php
/**
 * Build ExifTool arguments for metadata extraction
 *
 * @param array $options Options including 'speed_mode' (fast, fast2, full)
 * @return array Command arguments
 */
protected function buildMetadataArgs(array $options = []): array
{
    $args = ['-json', '-n']; // JSON output, numeric values
    
    // Speed mode handling
    $speedMode = $options['speed_mode'] ?? config('ingest.exiftool.speed_mode', 'fast');
    
    switch ($speedMode) {
        case 'fast2':
            // Fastest - skips maker notes and some metadata groups
            $args[] = '-fast2';
            break;
        case 'fast':
            // Fast - skips maker notes
            $args[] = '-fast';
            break;
        case 'full':
        default:
            // Full extraction - no speed flags
            break;
    }
    
    // Add standard exclusions
    $args[] = '-charset';
    $args[] = 'filename=utf8';
    
    return $args;
}
```

---

## Task 3: Background Preview Generation Job

Create a new job that processes previews asynchronously.

**File:** `src/Jobs/ProcessPreviewJob.php`

```php
<?php

namespace prophoto\Ingest\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use prophoto\Ingest\Models\ProxyImage;
use prophoto\Ingest\Services\MetadataExtractor;

class ProcessPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $uuid
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MetadataExtractor $extractor): void
    {
        $proxy = ProxyImage::where('uuid', $this->uuid)->first();

        if (!$proxy) {
            Log::warning('ProcessPreviewJob: ProxyImage not found', ['uuid' => $this->uuid]);
            return;
        }

        // Skip if already processed
        if ($proxy->preview_status === 'ready') {
            Log::debug('ProcessPreviewJob: Preview already ready', ['uuid' => $this->uuid]);
            return;
        }

        // Mark as processing
        $proxy->update([
            'preview_status' => 'processing',
            'preview_attempted_at' => now(),
        ]);

        $tempDisk = config('ingest.storage.temp_disk', 'local');
        $fullPath = Storage::disk($tempDisk)->path($proxy->temp_path);

        if (!file_exists($fullPath)) {
            $this->markFailed($proxy, 'Source file not found');
            return;
        }

        try {
            $startTime = microtime(true);

            // Step 1: Generate high-quality preview
            $previewPath = $extractor->generatePreview($fullPath, $this->uuid);

            // Step 2: If we got a preview, generate a proper thumbnail from it
            $thumbnailPath = $proxy->thumbnail_path; // Keep existing tiny thumbnail as fallback
            
            if ($previewPath) {
                $newThumbnailPath = $extractor->generateThumbnailFromPreview($previewPath, $this->uuid);
                if ($newThumbnailPath) {
                    $thumbnailPath = $newThumbnailPath;
                }
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Update record
            $proxy->update([
                'preview_path' => $previewPath,
                'thumbnail_path' => $thumbnailPath,
                'preview_status' => 'ready',
                'preview_error' => null,
            ]);

            Log::info('ProcessPreviewJob: Preview generated', [
                'uuid' => $this->uuid,
                'duration_ms' => $duration,
                'has_preview' => $previewPath !== null,
                'thumbnail_upgraded' => $thumbnailPath !== $proxy->thumbnail_path,
            ]);

            // Optional: Broadcast event for real-time UI update
            // event(new PreviewReadyEvent($proxy));

        } catch (\Exception $e) {
            Log::error('ProcessPreviewJob: Preview generation failed', [
                'uuid' => $this->uuid,
                'error' => $e->getMessage(),
            ]);

            $this->markFailed($proxy, $e->getMessage());

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Mark the preview as failed
     */
    protected function markFailed(ProxyImage $proxy, string $error): void
    {
        $proxy->update([
            'preview_status' => 'failed',
            'preview_error' => substr($error, 0, 255),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $proxy = ProxyImage::where('uuid', $this->uuid)->first();
        
        if ($proxy) {
            $proxy->update([
                'preview_status' => 'failed',
                'preview_error' => 'Max retries exceeded: ' . substr($exception->getMessage(), 0, 200),
            ]);
        }

        Log::error('ProcessPreviewJob: Job failed permanently', [
            'uuid' => $this->uuid,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

## Task 4: Update Upload Controller

Modify the upload endpoint to use the fast path.

**File:** `src/Http/Controllers/IngestController.php`

Replace the existing `upload()` method:

```php
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

    Log::info('Fast upload completed', [
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
```

Add the import at the top of the file:

```php
use prophoto\Ingest\Jobs\ProcessPreviewJob;
```

---

## Task 5: Preview Status Polling Endpoint

Add an endpoint for the frontend to check preview status.

**File:** `src/Http/Controllers/IngestController.php`

Add this new method:

```php
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
```

**Add the route** in `routes/ingest.php`:

```php
Route::post('/preview-status', [IngestController::class, 'previewStatus'])->name('ingest.preview-status');
```

---

## Task 6: Frontend Updates

### 6.1 Update Photo Type

**File:** `resources/js/types.ts` (or wherever Photo type is defined)

```typescript
export interface Photo {
  id: string
  filename: string
  thumbnail: string | null
  preview: string | null
  previewStatus: 'pending' | 'processing' | 'ready' | 'failed'
  previewReady: boolean
  // ... existing fields ...
  camera: string
  iso: number
  aperture: number
  focalLength: number
  dateTaken: string
  culled: boolean
  starred: boolean
  rating: number
  tags: string[]
  userOrder: number
}
```

### 6.2 Add Preview Polling Hook

**File:** `resources/js/hooks/usePreviewPolling.ts` (NEW FILE)

```typescript
import { useState, useEffect, useCallback, useRef } from 'react'
import type { Photo } from '../types'

interface UsePreviewPollingOptions {
  photos: Photo[]
  onPhotosUpdate: (updates: Partial<Photo>[]) => void
  pollInterval?: number // ms
  enabled?: boolean
}

export function usePreviewPolling({
  photos,
  onPhotosUpdate,
  pollInterval = 2000,
  enabled = true,
}: UsePreviewPollingOptions) {
  const [isPolling, setIsPolling] = useState(false)
  const timeoutRef = useRef<NodeJS.Timeout | null>(null)
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

  const pendingIds = photos
    .filter(p => p.previewStatus === 'pending' || p.previewStatus === 'processing')
    .map(p => p.id)

  const poll = useCallback(async () => {
    if (pendingIds.length === 0) {
      setIsPolling(false)
      return
    }

    try {
      const response = await fetch('/ingest/preview-status', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ ids: pendingIds }),
      })

      if (response.ok) {
        const data = await response.json()
        
        // Find photos that have changed
        const updates = data.photos
          .filter((p: any) => p.previewStatus === 'ready' || p.previewStatus === 'failed')
          .map((p: any) => ({
            id: p.id,
            thumbnail: p.thumbnailUrl,
            preview: p.previewUrl,
            previewStatus: p.previewStatus,
            previewReady: p.previewReady,
          }))

        if (updates.length > 0) {
          onPhotosUpdate(updates)
        }
      }
    } catch (error) {
      console.error('Preview polling failed:', error)
    }

    // Schedule next poll if there are still pending photos
    if (pendingIds.length > 0 && enabled) {
      timeoutRef.current = setTimeout(poll, pollInterval)
    }
  }, [pendingIds, pollInterval, enabled, csrfToken, onPhotosUpdate])

  // Start/stop polling based on pending photos
  useEffect(() => {
    if (enabled && pendingIds.length > 0 && !isPolling) {
      setIsPolling(true)
      // Initial delay before first poll
      timeoutRef.current = setTimeout(poll, 1000)
    }

    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current)
      }
    }
  }, [enabled, pendingIds.length, isPolling, poll])

  return {
    isPolling,
    pendingCount: pendingIds.length,
  }
}
```

### 6.3 Update Panel.tsx

**File:** `resources/js/Pages/Ingest/Panel.tsx`

Add the polling hook and update handler:

```typescript
// Add import at top
import { usePreviewPolling } from '../../hooks/usePreviewPolling'

// Inside the Panel component, after the existing state declarations:

// Handle preview status updates from polling
const handlePreviewUpdates = useCallback((updates: Partial<Photo>[]) => {
  setPhotos(prev => prev.map(photo => {
    const update = updates.find(u => u.id === photo.id)
    if (update) {
      return {
        ...photo,
        thumbnail: update.thumbnail ?? photo.thumbnail,
        preview: update.preview ?? photo.preview,
        previewStatus: update.previewStatus ?? photo.previewStatus,
        previewReady: update.previewReady ?? photo.previewReady,
      }
    }
    return photo
  }))
}, [])

// Enable preview polling
const { isPolling, pendingCount } = usePreviewPolling({
  photos,
  onPhotosUpdate: handlePreviewUpdates,
  pollInterval: 2000,
  enabled: true,
})
```

### 6.4 Update Thumbnail Component for Loading State

**File:** `resources/js/Components/ThumbnailBrowser.tsx`

Update the thumbnail rendering to show a loading indicator for pending previews:

```tsx
// Add a loading spinner component or use existing UI library

// In the thumbnail rendering:
{photo.previewStatus === 'pending' || photo.previewStatus === 'processing' ? (
  <div className="absolute inset-0 flex items-center justify-center bg-black/20">
    <div className="w-4 h-4 border-2 border-white/50 border-t-white rounded-full animate-spin" />
  </div>
) : null}

// For the thumbnail image, handle missing thumbnails gracefully:
{photo.thumbnail ? (
  <img 
    src={photo.thumbnail} 
    alt={photo.filename}
    className={cn(
      "w-full h-full object-cover",
      // Tiny embedded thumbnails may be blurry - that's OK
      photo.previewStatus !== 'ready' && "blur-[1px]"
    )}
  />
) : (
  <div className="w-full h-full flex items-center justify-center bg-muted">
    <ImageIcon className="w-8 h-8 text-muted-foreground" />
  </div>
)}
```

### 6.5 Update ImagePreview Component

**File:** `resources/js/Components/ImagePreview.tsx`

Handle the case where preview isn't ready yet:

```tsx
// If preview isn't ready, show thumbnail with loading indicator
{!photo.previewReady ? (
  <div className="relative w-full h-full flex items-center justify-center">
    {photo.thumbnail ? (
      <>
        <img 
          src={photo.thumbnail} 
          alt={photo.filename}
          className="max-w-full max-h-full object-contain blur-sm"
        />
        <div className="absolute inset-0 flex items-center justify-center">
          <div className="flex flex-col items-center gap-2 text-white">
            <div className="w-8 h-8 border-3 border-white/30 border-t-white rounded-full animate-spin" />
            <span className="text-sm">Generating preview...</span>
          </div>
        </div>
      </>
    ) : (
      <div className="flex flex-col items-center gap-2 text-muted-foreground">
        <div className="w-8 h-8 border-3 border-muted border-t-foreground rounded-full animate-spin" />
        <span className="text-sm">Processing...</span>
      </div>
    )}
  </div>
) : (
  // Existing preview rendering
  <img 
    src={photo.preview} 
    alt={photo.filename}
    className="max-w-full max-h-full object-contain"
  />
)}
```

---

## Task 7: Optional - Real-Time Updates with Broadcasting

For instant UI updates instead of polling, implement Laravel Broadcasting.

### 7.1 Create Event

**File:** `src/Events/PreviewReadyEvent.php`

```php
<?php

namespace prophoto\Ingest\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use prophoto\Ingest\Models\ProxyImage;

class PreviewReadyEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ProxyImage $proxy
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('ingest.' . $this->proxy->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'preview.ready';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->proxy->uuid,
            'thumbnail' => $this->proxy->thumbnail_path 
                ? Storage::disk(config('ingest.storage.temp_disk'))->url($this->proxy->thumbnail_path) 
                : null,
            'preview' => $this->proxy->preview_path 
                ? Storage::disk(config('ingest.storage.temp_disk'))->url($this->proxy->preview_path) 
                : null,
            'previewStatus' => $this->proxy->preview_status,
            'previewReady' => true,
        ];
    }
}
```

### 7.2 Dispatch Event in Job

In `ProcessPreviewJob.php`, after successful update:

```php
// After $proxy->update([...])
event(new PreviewReadyEvent($proxy));
```

### 7.3 Frontend WebSocket Listener

```typescript
// Using Laravel Echo
import Echo from 'laravel-echo'

useEffect(() => {
  const channel = window.Echo.private(`ingest.${userId}`)
  
  channel.listen('.preview.ready', (data: any) => {
    handlePreviewUpdates([{
      id: data.id,
      thumbnail: data.thumbnail,
      preview: data.preview,
      previewStatus: data.previewStatus,
      previewReady: data.previewReady,
    }])
  })

  return () => {
    channel.stopListening('.preview.ready')
  }
}, [userId, handlePreviewUpdates])
```

---

## Testing Checklist

After implementation, verify:

1. **Upload Speed**
   - [ ] Single RAW file uploads in <500ms (excluding network transfer)
   - [ ] Metadata appears immediately in UI
   - [ ] Tiny thumbnail displays (may be blurry, that's OK)

2. **Background Processing**
   - [ ] `ProcessPreviewJob` appears in queue
   - [ ] Job completes successfully
   - [ ] Preview status updates to 'ready'
   - [ ] High-quality thumbnail replaces tiny one

3. **Frontend Updates**
   - [ ] Polling detects ready previews
   - [ ] Thumbnails update without page refresh
   - [ ] Preview panel shows full preview when ready
   - [ ] Loading indicators display correctly

4. **Error Handling**
   - [ ] Failed preview generation marks status as 'failed'
   - [ ] Retries work correctly (3 attempts)
   - [ ] UI handles failed status gracefully

5. **Edge Cases**
   - [ ] JPEG files (may not have embedded thumbnail)
   - [ ] Files without EXIF data
   - [ ] Very large RAW files
   - [ ] Concurrent uploads (10+ files)

---

## Performance Expectations

| Metric | Before | After |
|--------|--------|-------|
| Upload response time (30MB RAW) | 3-5 seconds | <500ms |
| Time to see photo in UI | 3-5 seconds | <500ms |
| Time to see full preview | 3-5 seconds | 5-15 seconds (background) |
| Memory during upload | High | Low |
| Server CPU during upload | High | Low (deferred) |

---

## Files to Modify/Create Summary

### New Files
- `database/migrations/xxxx_add_preview_status_to_proxy_images.php`
- `src/Jobs/ProcessPreviewJob.php`
- `src/Events/PreviewReadyEvent.php` (optional)
- `resources/js/hooks/usePreviewPolling.ts`

### Modified Files
- `src/Models/ProxyImage.php` - Add new fields, helper methods
- `src/Services/MetadataExtractor.php` - Add fast extraction methods
- `src/Services/ExifToolService.php` - Add speed mode support
- `src/Http/Controllers/IngestController.php` - Update upload(), add previewStatus()
- `routes/ingest.php` - Add preview-status route
- `resources/js/types.ts` - Update Photo interface
- `resources/js/Pages/Ingest/Panel.tsx` - Add polling hook
- `resources/js/Components/ThumbnailBrowser.tsx` - Loading states
- `resources/js/Components/ImagePreview.tsx` - Loading states
