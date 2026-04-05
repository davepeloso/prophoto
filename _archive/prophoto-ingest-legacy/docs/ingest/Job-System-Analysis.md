# Complete Job System Analysis

A comprehensive guide to the prophoto-ingest job system, image processing pipeline, fallback mechanisms, and integration with the prophoto-debug package.

---

## Job Types & Processing Pipeline

### ProcessPreviewJob (Medium Priority - `ingest-preview` queue)

**Purpose**: Generates high-quality preview images and enhanced thumbnails asynchronously after upload.

**Libraries**: `MetadataExtractor` with ExifTool + Intervention Image v3

**Outputs**:
- Preview image (~2048px max dimension) at `ingest-temp/previews/{uuid}.jpg`
- Enhanced thumbnail (400x400 square, center-cropped) at `ingest-temp/thumbs/{uuid}.jpg`

**Processing Logic** (Updated 2026-01-05):
```
generatePreview()
├── Is file JPG/PNG/GIF/WebP/BMP/TIFF?
│   └── YES → generatePreviewFromSource() [fast, high quality]
│   └── NO (RAW file) → extractEmbeddedPreview()
│       ├── Try PreviewImage tag
│       ├── Try JpgFromRaw tag
│       ├── Try ThumbnailImage tag (must be >800px)
│       └── Fallback → generatePreviewFromSource()
```

**Fallback Chain**:
1. **Standard images (JPG, PNG, etc.)**: Always generate from source using ImageManager
2. **RAW files**: Extract embedded preview → Generate from source if extraction fails
3. **Thumbnail**: Generate from preview → Use fast-upload EXIF thumbnail if both fail

**Retry Configuration**: 3 attempts with [10, 30, 60] second backoff

**Race Condition Prevention**: Files are verified with `verifyFilesExist()` before marking `preview_status = 'ready'`

---

### ProcessImageIngestJob (High Priority - `ingest` queue)

**Purpose**: Final image processing - moves files to permanent storage with normalized metadata.

**Libraries**: `IngestProcessor` service with ExifTool metadata

**Outputs**:
- Permanent `Image` record in database
- Final file stored in configured `final_disk` location
- Organized by schema patterns (e.g., `shoots/2025/01/Canon-EOS-R5/001-IMG_1234.jpg`)

**Retry Configuration**: 3 attempts with [10, 30, 60] second backoff, 30-minute retry window

**Failure Handling**:
- Marks proxy with `metadata_error` on final failure
- Preserves proxy for manual retry
- Logs detailed error information

---

### EnhancePreviewJob (Low Priority - `ingest-enhance` queue)

**Purpose**: User-triggered high-quality preview enhancement (on-demand upscaling).

**Libraries**: Intervention Image v3 with ImageMagick/GD drivers

**Outputs**:
- Enhanced preview at target width (typically 1.25x current size)
- Updates `preview_width` and `enhancement_status` fields

**Retry Configuration**: 2 attempts with [5, 15] second backoff

**Driver Selection**: ImageMagick (preferred for quality) → GD (fallback)

---

## Technology Stack & Libraries

### ExifTool (Primary metadata engine)

- **Binary**: External Perl executable (configured via `EXIFTOOL_BINARY` or auto-detected)
- **Speed modes**:
  - `fast` - Quick extraction with essential fields
  - `fast2` - Aggressive optimization, skips maker notes
  - `full` - Complete extraction including all metadata
- **Features**:
  - Batch metadata extraction (JSON output with `-j -n` flags)
  - Embedded preview extraction from RAW files (`-b` binary mode)
  - Support for 600+ file formats including Canon CR2/CR3, Nikon NEF, Sony ARW, etc.
  - Health checks and configurable timeout (30s default)

### Intervention Image v3 (Image processing)

- **Drivers**: ImageMagick (preferred for RAW support) → GD (fallback)
- **Key Operations**:
  - `orient()` - Auto-rotate based on EXIF orientation
  - `scale()` - Proportional resizing
  - `cover()` - Crop to exact dimensions with position control
  - `toJpeg()` - JPEG encoding with quality setting
- **Configuration**: Quality settings (80-95%), max dimensions, auto-orientation

### Fallback Chain Summary

| Component | Primary | Fallback |
|-----------|---------|----------|
| Metadata extraction | ExifTool | PHP `exif_read_data()` |
| Image processing | ImageMagick | GD extension |
| JPG/PNG previews | Generate from source | N/A (always works) |
| RAW previews | Embedded extraction | Generate from source |
| Thumbnails | From preview | From fast-upload EXIF thumbnail |

---

## Output Files & Storage

### Temporary Storage (`ingest-temp/`)

```
storage/app/public/ingest-temp/
├── {uuid}.ext                  # Original uploaded file
├── thumbs/{uuid}.jpg           # 400x400 thumbnail (square, center-cropped)
└── previews/{uuid}.jpg         # 2048px max dimension preview
```

### Permanent Storage (configurable)

```
{final_disk}/{schema_pattern}/
└── {sequence}-{original_name}.{ext}    # Final file with metadata
```

### Database Records

| Model | Purpose | Key Fields |
|-------|---------|------------|
| `ProxyImage` | Temporary upload record | `uuid`, `preview_status`, `thumbnail_path`, `preview_path` |
| `Image` | Permanent asset record | `path`, `metadata`, `tags`, `association` |

---

## Two-Phase Upload Architecture

### Phase 1: Fast Upload (Synchronous HTTP)

During the HTTP upload request (~250-300ms):

1. File stored to temp disk
2. ExifTool extracts metadata (fast2 mode)
3. ExifTool extracts EXIF ThumbnailImage (~160px) for immediate display
4. ProxyImage record created with `preview_status = 'pending'`
5. ProcessPreviewJob dispatched to queue
6. Response returns with thumbnail for instant UI feedback

### Phase 2: Queue Processing (Asynchronous)

Background worker processes the job:

1. Detect file type (standard image vs RAW)
2. Generate high-quality 2048px preview
3. Generate 400x400 center-cropped thumbnail
4. Verify files exist on disk (`clearstatcache()` + size check)
5. Update `preview_status = 'ready'`
6. Frontend polling detects status change and updates UI

---

## Integration with prophoto-debug Package

The `prophoto-debug` package provides real-time visibility into the job processing pipeline.

### Ingest Trace System

Every preview/thumbnail generation attempt is logged to the `ingest_traces` table:

```php
// Trace events captured automatically:
- preview_extraction (method, duration, success/failure)
- thumbnail_generation (method, duration, dimensions)
- metadata_extraction (fields extracted, timing)
```

### Filament Debug Dashboard

Access at `/admin/ingest-traces-page`:

**System Health Bar** - Real-time queue status:
- Worker status: Processing (green), Idle (blue), Stalled (red), Pending (yellow)
- Pending jobs count
- Ingest-specific jobs count
- Failed jobs count
- Queue driver info
- Auto-refresh every 10 seconds

**Trace Stats** - Aggregate metrics:
- Total traces
- Pass/Fail counts
- Filter by UUID, date range, trace type

**Trace Table** - Detailed records:
- UUID (copyable)
- Trace type (preview_extraction, thumbnail_generation, etc.)
- Method tried
- Success/failure with reason
- Duration in milliseconds
- Result size

### QueueStatusService

The debug package includes a `QueueStatusService` that provides queue health monitoring:

```php
$status = app(QueueStatusService::class)->getStatus();
// Returns:
// [
//     'horizon' => ['installed' => bool, 'status' => string],
//     'jobs' => ['pending' => int, 'failed' => int, 'ingest_pending' => int],
//     'worker' => ['status' => 'processing|idle|stalled|pending|unknown']
// ]
```

**Worker Status Detection** (database driver):
- `processing` - Jobs have `reserved_at` set (worker actively processing)
- `idle` - Queue empty, worker may be waiting for jobs
- `stalled` - Jobs pending >30 seconds without processing (worker likely stopped)
- `pending` - Jobs just arrived, checking worker status

### Debugging Workflow

1. Upload an image
2. Open `/admin/ingest-traces-page`
3. Filter by UUID to see all extraction attempts
4. Check method order and timing
5. View failure reasons if any step failed
6. Monitor queue health in the status bar

---

## Error Handling & Recovery

### Retry Logic

| Job | Attempts | Backoff (seconds) | Timeout |
|-----|----------|-------------------|---------|
| ProcessPreviewJob | 3 | [10, 30, 60] | 5 min |
| ProcessImageIngestJob | 3 | [10, 30, 60] | 30 min window |
| EnhancePreviewJob | 2 | [5, 15] | 5 min |

### Failure Modes

- **Graceful degradation**: Falls back to lower-quality outputs instead of complete failure
- **Error preservation**: Stores error messages in `preview_error` / `metadata_error` fields
- **Proxy preservation**: Failed jobs keep proxy records for manual retry
- **Cleanup**: Temp files only deleted after successful final processing

### Job Tags (for Horizon/monitoring)

```php
public function tags(): array
{
    return ['ingest', "proxy:{$this->uuid}", "user:{$this->userId}"];
}
```

---

## Configuration Points

### Performance Tuning (`config/ingest.php`)

```php
'exif' => [
    'preview' => [
        'enabled' => true,
        'max_dimension' => 2048,    // Max width/height for previews
        'quality' => 85,            // JPEG quality (0-100)
        'max_preview_size' => 8388608,  // 8MB max file size
    ],
    'thumbnail' => [
        'width' => 400,
        'height' => 400,
        'quality' => 80,
    ],
],
```

### Storage Configuration

```php
'storage' => [
    'temp_disk' => 'local',         // Disk for temporary files
    'temp_path' => 'ingest-temp',   // Path within temp disk
    'final_disk' => 'public',       // Disk for permanent storage
],
```

### Queue Configuration

```php
'queues' => [
    'default' => 'ingest',          // ProcessImageIngestJob
    'preview' => 'ingest-preview',  // ProcessPreviewJob
    'enhance' => 'ingest-enhance',  // EnhancePreviewJob
],
```

---

## Processing Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         HTTP UPLOAD REQUEST                          │
├─────────────────────────────────────────────────────────────────────┤
│  1. Store file to temp disk                                         │
│  2. ExifTool: extractFast() with -fast2 mode                       │
│  3. ExifTool: extractEmbeddedThumbnail() → tiny EXIF thumb         │
│  4. Create ProxyImage record (preview_status='pending')             │
│  5. Dispatch ProcessPreviewJob to queue                             │
│  6. Return response with embedded thumbnail                         │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    QUEUE: ProcessPreviewJob                          │
├─────────────────────────────────────────────────────────────────────┤
│  1. Load ProxyImage, mark preview_status='processing'               │
│  2. Detect file type (JPG/PNG vs RAW)                               │
│     ├── Standard image → generatePreviewFromSource()                │
│     └── RAW → extractEmbeddedPreview() OR generatePreviewFromSource │
│  3. Generate 2048px preview                                          │
│  4. Generate 400x400 thumbnail from preview                         │
│  5. verifyFilesExist() → ensure files visible                       │
│  6. Update preview_status='ready'                                   │
│  7. Log trace to prophoto-debug (if installed)                      │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    USER CONFIRMS INGEST                              │
├─────────────────────────────────────────────────────────────────────┤
│  Dispatch ProcessImageIngestJob for each selected proxy             │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                QUEUE: ProcessImageIngestJob                          │
├─────────────────────────────────────────────────────────────────────┤
│  1. Build final path using schema patterns                          │
│  2. Move file to permanent storage                                  │
│  3. Create Image record with normalized metadata                    │
│  4. Cleanup temp files (source, thumbnail, preview)                 │
│  5. Delete ProxyImage record                                        │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Key Code Locations

| Component | File |
|-----------|------|
| Fast upload controller | `src/Http/Controllers/IngestController.php` |
| Preview job | `src/Jobs/ProcessPreviewJob.php` |
| Final ingest job | `src/Jobs/ProcessImageIngestJob.php` |
| Enhancement job | `src/Jobs/EnhancePreviewJob.php` |
| Metadata extraction | `src/Services/MetadataExtractor.php` |
| ExifTool service | `src/Services/ExifToolService.php` |
| Ingest processor | `src/Services/IngestProcessor.php` |
| Debug traces | `prophoto-debug/src/Services/IngestTraceService.php` |
| Queue status | `prophoto-debug/src/Services/QueueStatusService.php` |

---

*Last updated: 2026-01-05*
