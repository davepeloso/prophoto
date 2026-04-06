# Ingest Pipeline Testing Guide

This guide walks through testing the prophoto-ingest pipeline using the debug tracing tools.

## Test Images Location

Test images are stored at:
```
/Herd-Profoto/test-images/
```

### Available Test Files

| File | Type | Notes |
|------|------|-------|
| `test-images-0008.dng` | Adobe DNG RAW | Good for testing RAW extraction |
| `test-images-0009.dng` | Adobe DNG RAW | |
| `test-images-0010.dng` | Adobe DNG RAW | |
| `AMB_5333.jpg` | JPEG | Standard JPEG with EXIF |
| `AMB_5336.jpg` | JPEG | |
| `AMB_5339.jpg` | JPEG | |
| `FLS_5412.jpg` | JPEG | |
| `FLS_5414.jpg` | JPEG | |
| `FLS_5415.jpg` | JPEG | |
| `1B92939C-*.heic` | HEIC | iPhone format |
| `8078BB34-*.heic` | HEIC | iPhone format |
| `Alma Mater Slide *.jpg` | JPEG | Files with spaces in names |

## Quick Start Testing

### 1. Enable Debug Mode

```bash
# In your sandbox .env
INGEST_DEBUG=true
```

### 2. Start Queue Workers

```bash
php artisan queue:work --queue=ingest-preview
```

### 3. Create Baseline Snapshot

```bash
php artisan debug:snapshot "Baseline" --description="Default settings before testing"
```

### 4. Upload a Test Image

Use the ingest UI or API to upload one of the test images.

### 5. Find the UUID

After upload, note the UUID from the response or check the `ingest_proxy_images` table.

### 6. View the Trace

```bash
php artisan debug:trace <uuid>
```

## Testing Scenarios

### Scenario 1: RAW File Preview Extraction

**Goal**: Verify which preview tags work for DNG files

**Steps**:
1. Upload `test-images-0008.dng`
2. Wait for preview job to complete
3. View traces:
   ```bash
   php artisan debug:trace <uuid> --summary
   ```

**Expected Traces**:
```
preview_extraction | PreviewImage    | 1 | Success or Fail
preview_extraction | JpgFromRaw      | 2 | (if #1 failed)
preview_extraction | ThumbnailImage  | 3 | (if #2 failed)
thumbnail_generation | from_preview  | 1 | Success
```

**What to Check**:
- Which preview tag succeeded?
- How long did extraction take?
- What's the preview size/dimensions?

---

### Scenario 2: JPEG Thumbnail Quality

**Goal**: Test thumbnail quality settings

**Steps**:
1. Create snapshot with current settings:
   ```bash
   php artisan debug:snapshot "Quality 80"
   ```
2. Upload `AMB_5333.jpg`
3. Note the thumbnail size from traces
4. Change `config/ingest.php`:
   ```php
   'thumbnail' => ['quality' => 90]
   ```
5. Create new snapshot:
   ```bash
   php artisan debug:snapshot "Quality 90"
   ```
6. Upload same image again
7. Compare trace results

---

### Scenario 3: HEIC File Handling

**Goal**: Test iPhone HEIC format support

**Steps**:
1. Upload `1B92939C-68AD-4CBB-8D7C-84E58C30926C.heic`
2. Check if preview extraction works
3. View traces for any failures

**Common Issues**:
- HEIC may require libheif for ImageMagick
- ExifTool should extract metadata fine
- Preview extraction may fall back to ImageManager

---

### Scenario 4: Files with Spaces

**Goal**: Verify path handling for files with spaces

**Steps**:
1. Upload `Alma Mater Slide 004.jpg`
2. Verify no path-related errors in traces
3. Check that preview and thumbnail were generated

---

### Scenario 5: ExifTool Speed Modes

**Goal**: Compare fast2 vs fast vs full extraction

**Test 1: fast2 mode (default)**
```bash
# Ensure .env has:
EXIFTOOL_SPEED_MODE=fast2

php artisan debug:snapshot "Speed: fast2"
# Upload test image
```

**Test 2: fast mode**
```bash
EXIFTOOL_SPEED_MODE=fast
php artisan debug:snapshot "Speed: fast"
# Upload same test image
```

**Test 3: full mode**
```bash
EXIFTOOL_SPEED_MODE=full
php artisan debug:snapshot "Speed: full"
# Upload same test image
```

**Compare**:
- Metadata extraction duration
- Number of fields extracted
- Any missing metadata in fast2 mode?

---

### Scenario 6: Queue Priority Testing

**Goal**: Test different queue configurations

**Setup**:
```bash
# Create snapshot before testing
php artisan debug:snapshot "Single worker"
```

**Test 1: Single Worker**
```bash
php artisan queue:work --queue=ingest-preview
```

**Test 2: Multiple Workers**
```bash
php artisan queue:work --queue=ingest-preview &
php artisan queue:work --queue=ingest-preview &
php artisan queue:work --queue=ingest-preview &
```

**Test 3: Priority Queues**
```bash
php artisan queue:work --queue=ingest,ingest-preview,ingest-enhance
```

**Measure**:
- Time from upload to preview ready
- Queue backlog during batch uploads

## Batch Testing

For testing multiple files at once:

```bash
# Upload all test images via curl or the UI
# Then view traces for all recent uploads:

php artisan tinker
>>> \ProPhoto\Debug\Models\IngestTrace::where('created_at', '>', now()->subHour())
    ->select('uuid', 'trace_type', 'method_tried', 'success')
    ->get()
    ->groupBy('uuid');
```

## Interpreting Results

### Success Rate by Method

Check which preview methods work best:

```bash
php artisan tinker
>>> \ProPhoto\Debug\Models\IngestTrace::where('trace_type', 'preview_extraction')
    ->selectRaw('method_tried, SUM(success) as successes, COUNT(*) as attempts')
    ->groupBy('method_tried')
    ->get();
```

### Average Duration by Trace Type

```bash
>>> \ProPhoto\Debug\Models\IngestTrace::selectRaw("trace_type, AVG(JSON_EXTRACT(result_info, '$.duration_ms')) as avg_ms")
    ->groupBy('trace_type')
    ->get();
```

### Failed Extractions

```bash
>>> \ProPhoto\Debug\Models\IngestTrace::where('success', false)
    ->select('uuid', 'method_tried', 'failure_reason')
    ->limit(20)
    ->get();
```

## Cleanup After Testing

```bash
# Remove traces older than 1 day
php artisan debug:cleanup --days=1

# Or clear all traces
php artisan tinker
>>> \ProPhoto\Debug\Models\IngestTrace::truncate();
```

## Checklist for Testing Sessions

- [ ] Debug mode enabled (`INGEST_DEBUG=true`)
- [ ] Queue workers running
- [ ] Baseline snapshot created
- [ ] Test images accessible
- [ ] Filament debug pages accessible (optional)
- [ ] Storage directories writable
