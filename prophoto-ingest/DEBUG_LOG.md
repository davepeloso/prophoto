# ProPhoto Ingest Debug Log

This log tracks debugging sessions, issues discovered, and fixes implemented.

---

## 2026-01-04: Thumbnail/Preview Race Condition Fix

### Issue Discovered
After uploading images, thumbnails would not display in the browser until a manual page refresh. The trace system (prophoto-debug package) revealed the timing issue.

### Root Cause Analysis
**Race Condition Timeline:**
1. Upload completes, `ProcessPreviewJob` dispatches to queue
2. Job generates preview and thumbnail files
3. Job updates database: `preview_status = 'ready'`
4. **Problem:** Database updated BEFORE filesystem sync completes
5. Frontend polling sees "ready" status, requests thumbnail URL
6. Browser requests file that isn't visible to web server yet → fails silently
7. User refreshes browser → files now exist → displays correctly

### Symptoms Observed
- Traces showed successful extraction (e.g., `ThumbnailImage` method succeeded)
- Browser showed placeholder or broken image
- Manual refresh fixed the display
- Both JPG and RAW files affected

### Fix Implemented
**File:** `src/Jobs/ProcessPreviewJob.php`

Added `verifyFilesExist()` method that:
1. Calls `clearstatcache()` to clear PHP's file stat cache
2. Verifies preview file exists and has content (size > 0)
3. Verifies thumbnail file exists and has content (size > 0)
4. If files not visible yet, releases job back to queue with 2-second delay
5. Only marks `preview_status = 'ready'` after files are verified

```php
protected function verifyFilesExist(?string $previewPath, ?string $thumbnailPath, string $disk): bool
{
    clearstatcache();
    $storage = Storage::disk($disk);

    if ($previewPath && !$storage->exists($previewPath)) return false;
    if ($thumbnailPath && !$storage->exists($thumbnailPath)) return false;
    if ($previewPath && $storage->size($previewPath) === 0) return false;
    if ($thumbnailPath && $storage->size($thumbnailPath) === 0) return false;

    return true;
}
```

### Testing Steps
1. Restart queue worker: `php artisan queue:restart`
2. Clear existing proxy images from database
3. Upload test images (both JPG and RAW)
4. Observe thumbnails appearing without browser refresh

### Related Files
- `prophoto-ingest/src/Jobs/ProcessPreviewJob.php` - Main fix location
- `prophoto-debug/src/Filament/Pages/IngestTracesPage.php` - Trace viewer
- `prophoto-debug/resources/views/filament/pages/ingest-traces.blade.php` - Trace UI

### Debug Tools Used
- **prophoto-debug package**: Ingest Traces page showed extraction method order and success/failure
- **PHP DebugBar**: Showed request timeline and Livewire updates
- **Browser DevTools**: Confirmed 404s on thumbnail URLs during race condition

---

## 2026-01-04: Embedded Preview Not Normalized to max_dimension

### Issue Discovered
Preview images extracted from camera RAW files could be full-resolution (6000x4000px) instead of the configured `max_dimension` (2048px). This caused inconsistent preview quality and larger-than-necessary file sizes.

### Root Cause Analysis
**The Problem:**
1. `extractEmbeddedPreview()` only checked FILE SIZE (8MB max via `max_preview_size` config)
2. The `max_dimension: 2048` config was only used in `generatePreviewFromSource()` (the fallback path)
3. Embedded previews from cameras could be full-resolution but small in file size (compressed JPEG)
4. These large-dimension previews passed the file size check and were used directly

**Code Path Issue:**
```
extractEmbeddedPreview()
  └── Checks: file_size < 8MB? ✓
  └── Does NOT check pixel dimensions
  └── Full-res preview passes through unchanged

generatePreviewFromSource() [fallback only]
  └── Respects max_dimension: 2048
  └── Only called when embedded extraction fails
```

### Symptoms Observed
- Initial upload preview appeared higher quality than job-complete preview in some cases
- Embedded previews from high-res cameras (e.g., 6000x4000) not being downscaled
- Inconsistent preview dimensions depending on whether embedded preview existed

### Fix Implemented
**File:** `src/Services/MetadataExtractor.php`

Created new `normalizePreview()` method that always checks pixel dimensions and downscales if needed:

```php
protected function normalizePreview(string $previewPath): bool
{
    $config = config('ingest.exif.preview', []);
    $maxDimension = $config['max_dimension'] ?? 2048;
    $quality = $config['quality'] ?? 85;

    try {
        $image = $this->imageManager->read($previewPath);
        $width = $image->width();
        $height = $image->height();
        $needsResize = ($width > $maxDimension || $height > $maxDimension);

        if ($needsResize) {
            $image->orient();
            if ($width > $height) {
                $image->scale(width: $maxDimension);
            } else {
                $image->scale(height: $maxDimension);
            }
            $encoded = $image->toJpeg($quality);
            file_put_contents($previewPath, $encoded);
        }
        return true;
    } catch (\Exception $e) {
        return false;
    }
}
```

Updated `extractEmbeddedPreview()` to always call `normalizePreview()` after successful extraction:

```php
if ($result !== false && file_exists($outputPath)) {
    // Always normalize preview to configured max_dimension and quality
    $this->normalizePreview($outputPath);
    return $tempPath . '/previews/' . $uuid . '.jpg';
}
```

Deprecated `downscalePreview()` to call `normalizePreview()` for backward compatibility.

### Testing Steps
1. Restart queue worker: `php artisan queue:restart`
2. Upload RAW images from high-resolution cameras (Sony, Canon, Nikon)
3. Verify previews are normalized to 2048px max dimension
4. Check file sizes are reasonable (typically under 1MB for 2048px JPEG at 85% quality)

### Related Files
- `prophoto-ingest/src/Services/MetadataExtractor.php` - Main fix location
- `prophoto-ingest/config/ingest.php` - Configuration for `exif.preview.max_dimension`

### Configuration Reference
```php
'exif' => [
    'preview' => [
        'enabled' => true,
        'max_dimension' => 2048, // Maximum width or height in pixels
        'quality' => 85,
    ],
],
```

---

## 2026-01-04: Tiny EXIF Thumbnail Used as Preview for JPG Files

### Issue Discovered
When uploading JPG files, the generated preview was worse quality than the initial upload thumbnail. The preview appeared blurry and zoomed-in compared to what was shown during upload.

### Root Cause Analysis
**The Problem:**
1. `extractEmbeddedPreview()` tries ExifTool tags in order: `PreviewImage` → `JpgFromRaw` → `ThumbnailImage`
2. JPG files don't have `PreviewImage` or `JpgFromRaw` (those are RAW-only tags)
3. Only `ThumbnailImage` exists - a tiny ~160x120px EXIF thumbnail (1793 bytes)
4. This tiny thumbnail was accepted as a valid "preview"
5. `normalizePreview()` tried to resize it UP, creating a blurry, pixelated mess
6. The fallback `generatePreviewFromSource()` (which reads the actual JPG) was never triggered

**Log Evidence:**
```
ExifTool preview extracted {"tag":"ThumbnailImage","size":1793}
Using embedded preview
Generated thumbnail from preview {"size":13505}
```

The 1793-byte ThumbnailImage was used as the preview source, then upscaled.

### Symptoms Observed
- Initial upload showed better quality preview (original file displayed)
- Job complete preview was blurry/pixelated (upscaled tiny thumbnail)
- Thumbnail appeared to "zoom in" after job completion
- Only affected JPG files (RAW files have proper embedded previews)

### Fix Implemented
**File:** `src/Services/MetadataExtractor.php`

**Solution:** Skip embedded preview extraction entirely for standard image formats. The key insight is that embedded preview extraction is only beneficial for RAW files (which have high-quality embedded JPEGs). Standard images like JPG/PNG should ALWAYS generate previews from the source file.

Modified `generatePreview()` to detect file type and route appropriately:

```php
public function generatePreview(string $sourcePath, string $uuid, ?string $sessionId = null): ?string
{
    $config = config('ingest.exif.preview', []);

    // Check if file is a standard image format (JPG, PNG, etc.)
    // These files don't have useful embedded previews - only tiny EXIF thumbnails
    // RAW files (CR2, NEF, ARW, etc.) have high-quality embedded previews worth extracting
    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $standardImageFormats = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif'];
    $isStandardImage = in_array($extension, $standardImageFormats);

    if ($isStandardImage) {
        // For standard images: generate directly from source (fast and high quality)
        Log::debug('Standard image detected, generating preview from source', [
            'file' => basename($sourcePath),
            'extension' => $extension,
        ]);
        return $this->generatePreviewFromSource($sourcePath, $uuid, $config, $sessionId);
    }

    // For RAW files: try to extract embedded preview first (much faster than decoding RAW)
    $embeddedPreview = $this->extractEmbeddedPreview($sourcePath, $uuid, $sessionId);
    if ($embeddedPreview !== null) {
        Log::debug('Using embedded preview from RAW file', ['file' => basename($sourcePath)]);
        return $embeddedPreview;
    }

    // Fall back to generating preview with ImageMagick/GD
    return $this->generatePreviewFromSource($sourcePath, $uuid, $config, $sessionId);
}
```

**Also added:** Minimum dimension check (800px) in `extractEmbeddedPreview()` as a safety net for RAW files with unusually small embedded previews.

**Result:**
- JPG/PNG files: Always use `generatePreviewFromSource()` → fast, high-quality 2048px preview
- RAW files: Try embedded preview first (PreviewImage/JpgFromRaw), fall back to source if needed

### Testing Steps

1. Restart queue worker: `php artisan queue:restart`
2. Clear existing proxy images
3. Upload JPG files
4. Verify logs show: "Standard image detected, generating preview from source"
5. Preview should be sharp at 2048px max dimension
6. Also test RAW files to confirm embedded preview extraction still works

### Related Files

- `prophoto-ingest/src/Services/MetadataExtractor.php` - Main fix location

### Expected Log Output After Fix

For JPG files:
```
Standard image detected, generating preview from source {"file":"photo.jpg","extension":"jpg"}
```

For RAW files:
```
Using embedded preview from RAW file {"file":"photo.arw"}
```

---

## 2026-01-04: Thumbnail Cropped to Wrong Area (Orientation Bug)

### Issue Discovered

Thumbnails were showing only a small portion of the image (e.g., just the ceiling) instead of a centered crop of the full image.

### Root Cause Analysis

**The Problem:**
The `orient()` call was happening AFTER `cover()` crop:

```php
// WRONG ORDER:
$image->cover($width, $height);  // Crops while image is rotated
$image->orient();                 // Fixes orientation too late
```

When a camera saves an image, it may store it rotated (e.g., 90° clockwise) with EXIF orientation metadata indicating how to display it correctly. The `cover()` crop was being applied to the raw rotated image, grabbing the wrong area. Then `orient()` fixed the rotation but the damage was already done.

### Symptoms Observed

- Thumbnail showed only a corner/edge of the image (e.g., ceiling)
- The cropped area didn't match what you'd expect from a center crop
- Affected images with non-standard EXIF orientation

### Fix Implemented

**File:** `src/Services/MetadataExtractor.php`

**Fix 1:** In `generateThumbnail()` - orient BEFORE cropping:
```php
$image->orient();                 // Fix orientation FIRST
$image->cover($width, $height);   // Then crop from the correctly oriented image
```

**Fix 2 (Critical):** In `generateThumbnailFromPreview()` - do NOT call orient():
```php
// DO NOT call orient() here - the preview is already visually correct
// (orientation was applied when preview was generated from source)
// Calling orient() again would double-rotate based on stale EXIF data
$image->cover($width, $height);
```

**Why Fix 2 was needed:**
1. When preview is generated, `orient()` visually rotates the pixels
2. But `toJpeg()` may preserve the original EXIF orientation tag
3. When thumbnail reads the preview and calls `orient()` again, it re-applies rotation to already-correct pixels
4. Result: double-rotated image → wrong crop area

The camera's embedded EXIF thumbnail works because it's already fully processed - no orientation transform needed. Our generated thumbnail from preview should work the same way.

### Testing Steps

1. Restart queue worker: `php artisan queue:restart`
2. Upload images (especially those shot in portrait mode or with rotation)
3. Verify thumbnails show a centered crop of the full image
4. Check logs for: `Generated thumbnail from preview {"dimensions":"400x400"}`

### Related Files

- `prophoto-ingest/src/Services/MetadataExtractor.php` - Both thumbnail generation methods

---

## 2026-01-05: ✅ JPG Ingest Working Correctly

### Status: SUCCESS

All fixes from 2026-01-04 are working as intended. JPG file ingest now follows the correct flow with proper preview and thumbnail generation.

### Successful Flow Observed

**Test File:** `Alma Mater Slide 006.jpg` (UUID: `8468c649-91b8-4794-a89a-f7089074c603`)

**Phase 1: Fast Upload (Synchronous HTTP Request)**
```
[07:09:42] ExifTool health check passed (v13.45)
[07:09:43] Metadata extraction completed - 142.19ms
[07:09:43] EXIF ThumbnailImage extracted - 1357 bytes (for immediate display)
[07:09:43] Fast upload completed - 274.19ms total
           - metadata_ms: 154.32ms
           - thumbnail_ms: 85.47ms
           - extraction_method: exiftool_fast
```

**Phase 2: Queue Job (Asynchronous)**
- Standard image detected → routes to `generatePreviewFromSource()`
- Bypasses embedded preview extraction (which would only find tiny EXIF thumbnail)
- Generates high-quality 2048px preview from source JPG
- Thumbnail generated from preview with correct center crop
- Files verified before marking status as 'ready'

### What's Working

| Component | Behavior | Status |
|-----------|----------|--------|
| Fast upload thumbnail | Extracts EXIF ThumbnailImage for immediate display | ✅ |
| JPG preview generation | Uses `generatePreviewFromSource()`, not embedded extraction | ✅ |
| Preview dimensions | Normalized to 2048px max | ✅ |
| Thumbnail orientation | `orient()` called before `cover()` crop | ✅ |
| Thumbnail center crop | `cover($w, $h, 'center')` explicit position | ✅ |
| Race condition | Files verified before DB status update | ✅ |

### Performance Summary

- **Total fast upload time:** ~274ms (user sees thumbnail immediately)
- **Metadata extraction:** ~142ms
- **Thumbnail extraction:** ~85ms
- **Queue job:** Runs async, doesn't block upload response

### Key Code Paths for JPG Files

```
Upload Request
  └── FastUploadService
        └── ExifTool: extract metadata (142ms)
        └── ExifTool: extract EXIF ThumbnailImage (85ms)
        └── Response with embedded thumbnail for immediate display
        └── Dispatch ProcessPreviewJob to queue

Queue Worker (async)
  └── ProcessPreviewJob
        └── MetadataExtractor::generatePreview()
              └── Detects JPG extension
              └── Routes to generatePreviewFromSource() (NOT embedded extraction)
              └── ImageManager reads source, scales to 2048px max
        └── MetadataExtractor::generateThumbnailFromPreview()
              └── Reads generated preview
              └── cover(400, 400, 'center') - no orient() needed
        └── verifyFilesExist() - ensures files visible before status update
        └── Update preview_status = 'ready'
```

---

## Template for Future Entries

```markdown
## YYYY-MM-DD: Brief Issue Title

### Issue Discovered
[Description of the problem observed]

### Root Cause Analysis
[Technical explanation of why this happened]

### Symptoms Observed
- [Symptom 1]
- [Symptom 2]

### Fix Implemented
**File:** `path/to/file.php`

[Description of the fix]

### Testing Steps
1. [Step 1]
2. [Step 2]

### Related Files
- `file1.php` - Description
- `file2.php` - Description
```