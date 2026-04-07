# ExifTool Integration

This document describes the ExifTool-based metadata and preview extraction system used in the prophoto-ingest package.

## Overview

The ingest system uses [ExifTool](https://exiftool.org/) as the primary metadata extraction engine, providing:

- **Robust metadata extraction** from all image formats (RAW, HEIC, JPEG, TIFF, etc.)
- **Embedded preview extraction** from RAW files for fast thumbnail generation
- **Normalized JSON output** with consistent field names across camera manufacturers
- **Batch processing** for efficient handling of multiple files

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     Upload Request                               │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    IngestController                              │
│    - Stores temp file                                            │
│    - Calls MetadataExtractor::extract()                          │
│    - Generates preview/thumbnail                                 │
│    - Creates ProxyImage record                                   │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   MetadataExtractor                              │
│    - Coordinates extraction                                      │
│    - Uses ExifToolService (primary)                              │
│    - Falls back to PHP exif (secondary)                          │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    ExifToolService                               │
│    - Runs ExifTool binary                                        │
│    - Returns JSON metadata                                       │
│    - Extracts embedded previews                                  │
│    - Normalizes output to app schema                             │
└─────────────────────────────────────────────────────────────────┘
```

## Configuration

Add these settings to your `.env` file:

```env
# Path to exiftool binary (use absolute path in production)
# This avoids PATH-related issues in PHP-FPM/Horizon/queue workers
EXIFTOOL_BIN=/opt/local/bin/exiftool

# Optional: Directory to prepend to PATH when spawning exiftool processes
# Use this if the binary is not in the PHP runtime's PATH
EXIFTOOL_PATH_PREFIX=/opt/local/bin

# Timeout for exiftool operations in seconds (default: 30)
EXIFTOOL_TIMEOUT=30

# Speed mode: fast, fast2, or full (default: fast)
EXIFTOOL_SPEED_MODE=fast
```

### Important: PATH Configuration

The PHP runtime PATH (in PHP-FPM/Horizon/queue workers) often differs from your shell PATH. To avoid "exiftool: not found" errors:

1. **Use absolute path** (recommended):
   ```env
   EXIFTOOL_BIN=/usr/local/bin/exiftool
   ```

2. **Or set PATH prefix**:
   ```env
   EXIFTOOL_PATH_PREFIX=/usr/local/bin
   ```

3. **After updating `.env`, clear config cache**:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

4. **Restart services**:
   ```bash
   # If using Horizon
   php artisan horizon:terminate

   # Restart PHP-FPM (varies by system)
   # macOS with Herd: restart via Herd UI
   # Ubuntu: sudo systemctl restart php8.2-fpm
   ```

### Speed Modes

| Mode | Description | Use Case |
|------|-------------|----------|
| `fast` | Skips non-essential processing | Recommended for most uploads |
| `fast2` | More aggressive optimization | Quick scans, may miss some fields |
| `full` | Complete extraction | When you need every field |

## Metadata Schema

### Normalized Fields (Application Schema)

The ExifTool output is normalized to these consistent field names:

| Normalized Field | ExifTool Source | Type | Description |
|-----------------|-----------------|------|-------------|
| `date_taken` | DateTimeOriginal | ISO 8601 string | When photo was captured |
| `camera_make` | Make | string | Camera manufacturer |
| `camera_model` | Model | string | Camera model name |
| `camera` | Make + Model | slug | Slugified camera identifier |
| `lens` | LensModel | string | Lens model name |
| `f_stop` | FNumber | float | Aperture value |
| `shutter_speed` | ExposureTime | float | Exposure time in seconds |
| `shutter_speed_display` | ExposureTime | string | Human-readable (e.g., "1/250s") |
| `iso` | ISO | int | ISO sensitivity |
| `focal_length` | FocalLength | int | Focal length in mm (rounded) |
| `gps_lat` | GPSLatitude | float | GPS latitude (decimal degrees) |
| `gps_lng` | GPSLongitude | float | GPS longitude (decimal degrees) |
| `width` | ImageWidth | int | Image width in pixels |
| `height` | ImageHeight | int | Image height in pixels |
| `file_type` | FileType | string | File format (JPEG, NEF, etc.) |
| `mime_type` | MIMEType | string | MIME type |
| `file_size` | FileSize | int | File size in bytes |
| `orientation` | Orientation | int | EXIF orientation value |
| `software` | Software | string | Processing software |

### Sample ExifTool Output

```json
{
  "FileName": "AMB_0838.jpg",
  "FileSize": 2306867,
  "FileType": "JPEG",
  "MIMEType": "image/jpeg",
  "Make": "NIKON CORPORATION",
  "Model": "NIKON Z 6_2",
  "ExposureTime": 0.05,
  "FNumber": 7.1,
  "ISO": 400,
  "DateTimeOriginal": "2025:10:23 12:21:28",
  "OffsetTimeOriginal": "-07:00",
  "FocalLength": 17.5,
  "LensModel": "NIKKOR Z 14-30mm f/4 S",
  "ExifImageWidth": 4350,
  "ExifImageHeight": 2894
}
```

### Normalized Output

```json
{
  "date_taken": "2025-10-23T12:21:28-07:00",
  "camera_make": "NIKON CORPORATION",
  "camera_model": "NIKON Z 6_2",
  "camera": "nikon-corporation-nikon-z-6-2",
  "lens": "NIKKOR Z 14-30mm f/4 S",
  "f_stop": 7.1,
  "shutter_speed": 0.05,
  "shutter_speed_display": "1/20s",
  "iso": 400,
  "focal_length": 18,
  "width": 4350,
  "height": 2894,
  "file_type": "JPEG",
  "mime_type": "image/jpeg"
}
```

## Preview Extraction

### Embedded Preview Tags

ExifTool extracts embedded previews using these tags (tried in order):

1. **PreviewImage** - High-quality preview (Sony ARW, Nikon NEF)
2. **JpgFromRaw** - Embedded JPEG from RAW (Canon CR2)
3. **ThumbnailImage** - Smaller thumbnail fallback

### Performance Benefits

Extracting embedded previews is significantly faster than rendering from RAW:

| Method | Typical Time | CPU Usage |
|--------|-------------|-----------|
| Embedded preview (ExifTool) | 50-100ms | Low |
| Render from RAW (ImageMagick) | 2-5s | High |

## Database Schema

The upgrade adds these columns to `ingest_proxy_images`:

```php
$table->json('metadata_raw')->nullable();       // Raw ExifTool JSON
$table->string('metadata_error')->nullable();   // Error message if failed
$table->string('extraction_method', 20)->nullable(); // 'exiftool', 'php_exif', 'none'
```

## Fallback Behavior

If ExifTool is unavailable, the system falls back to PHP's `exif_read_data()`:

1. Check `exiftool` binary availability on startup
2. If unavailable, log warning and set `fallback_to_php` mode
3. Use PHP exif functions for JPEG/TIFF files
4. Mark `extraction_method` as `'php_exif'` on proxy records

## Error Handling

### Metadata Extraction Errors

When extraction fails:

1. Error stored in `ProxyImage.metadata_error`
2. `extraction_method` set to `'none'`
3. Upload continues with basic file info only
4. User can still ingest (with limited metadata)

### Job Processing Errors

The `ProcessImageIngestJob` handles failures with:

- 3 retry attempts with exponential backoff (10s, 30s, 60s)
- Proxy marked with error on final failure
- Worker process not crashed by individual failures

## Performance Metrics

### Expected Latency

| Operation | Single File | Batch (10 files) |
|-----------|-------------|------------------|
| Metadata extraction | 50-150ms | 200-400ms |
| Preview extraction | 30-100ms | N/A |
| Total upload time | 100-300ms | - |

### Batch Optimization

For multiple files, use batch extraction to reduce process overhead:

```php
$extractor = app(MetadataExtractor::class);
$results = $extractor->extractBatch($filePaths);
```

## Security Considerations

1. **Path Validation**: All file paths are validated and sanitized
2. **Traversal Prevention**: Paths with `..` or null bytes are rejected
3. **Symfony Process**: Uses Symfony Process for safe shell execution
4. **Timeout Limits**: Operations timeout after configurable duration

## Troubleshooting

### Diagnostic Command

Run the diagnostic command to check ExifTool configuration:

```bash
php artisan exiftool:doctor
```

This command checks:
- PHP environment (SAPI, user, PHP version)
- Configuration values (EXIFTOOL_BIN, EXIFTOOL_PATH_PREFIX)
- Current PATH and effective PATH with prefix
- Binary existence and permissions
- ExifTool execution and version

**Example output**:

```
╔════════════════════════════════════════════════════════════════╗
║                  ExifTool Configuration Doctor                 ║
╚════════════════════════════════════════════════════════════════╝

┌─ PHP Environment ──────────────────────────────────────────────
  SAPI:           fpm-fcgi
  User:           www-data (UID: 33)
  PHP Version:    8.2.14

┌─ ExifTool Configuration ───────────────────────────────────────
  Binary (EXIFTOOL_BIN):        /opt/local/bin/exiftool
  Path Prefix (PATH_PREFIX):    /opt/local/bin

┌─ PATH Environment ─────────────────────────────────────────────
  Current PATH:
    /usr/local/bin:/usr/bin:/bin

  Effective PATH (with prefix):
    /opt/local/bin:/usr/local/bin:/usr/bin:/bin

┌─ ExifTool Execution Test ──────────────────────────────────────
  Running: /opt/local/bin/exiftool -ver

  Exit Code:  0
  Version:    12.70

✓ ExifTool is working correctly!
```

### ExifTool Not Found

**Symptoms**:
```
ExifTool not available, falling back to PHP exif functions
```

Or error in logs:
```
ExifTool failed (exit code 127): exiftool: not found
```

**Solutions**:

1. **Install ExifTool**:
   ```bash
   # macOS with Homebrew
   brew install exiftool

   # macOS with MacPorts
   sudo port install p5-image-exiftool

   # Ubuntu/Debian
   sudo apt install libimage-exiftool-perl

   # Docker
   RUN apt-get update && apt-get install -y libimage-exiftool-perl
   ```

2. **Find ExifTool location**:
   ```bash
   which exiftool
   # Output: /opt/local/bin/exiftool
   ```

3. **Configure absolute path in `.env`**:
   ```env
   EXIFTOOL_BIN=/opt/local/bin/exiftool
   EXIFTOOL_PATH_PREFIX=/opt/local/bin
   ```

4. **Clear config and restart**:
   ```bash
   php artisan config:clear
   php artisan horizon:terminate
   # Restart PHP-FPM
   ```

5. **Run diagnostic**:
   ```bash
   php artisan exiftool:doctor
   ```

### Health Check (Programmatic)

Verify ExifTool is working programmatically:

```php
$service = app(\prophoto\Ingest\Services\ExifToolService::class);
$healthy = $service->healthCheck();
$version = $service->getVersion();
```

### Slow Extractions

If extraction is slow:

1. Use `speed_mode: 'fast'` in config
2. Check disk I/O (especially for network storage)
3. Consider batch extraction for multiple files
4. Review timeout settings

## Migration Guide

### From PHP exif_read_data

The new system is backwards compatible:

1. Run migration: `php artisan migrate`
2. ExifTool extractions will populate normalized fields
3. Legacy parsers still work as fallback
4. `ProxyImage.exif` accessor handles both formats

### Rolling Back

If issues occur:

1. Set `EXIFTOOL_FALLBACK_TO_PHP=true`
2. System will use PHP exif functions
3. Existing data remains accessible
