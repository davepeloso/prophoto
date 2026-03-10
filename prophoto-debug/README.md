# ProPhoto Debug Package

A debugging and tracing toolkit for the ProPhoto ingest pipeline. This package provides visibility into the decision-making process during image upload, preview generation, and thumbnail extraction.

## Features

- **Decision Tracing**: Records every method attempted during preview/thumbnail extraction
- **Configuration Snapshots**: Capture and compare test configurations
- **Filament Integration**: View traces and snapshots in the admin panel
- **CLI Tools**: Artisan commands for debugging and maintenance

## Installation

### 1. Add to your application's composer.json

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../prophoto-debug",
            "options": {"symlink": true}
        }
    ],
    "require": {
        "prophoto/debug": "@dev"
    }
}
```

### 2. Install the package

```bash
composer update prophoto/debug
```

### 3. Run migrations

```bash
php artisan migrate
```

### 4. Enable debug mode

Add to your `.env` file:

```env
INGEST_DEBUG=true
```

### 5. (Optional) Add Filament plugin

In your Filament admin panel provider:

```php
use ProPhoto\Debug\Filament\DebugPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            DebugPlugin::make(),
        ]);
}
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=debug-config
```

### Configuration Options

```php
// config/debug.php

return [
    // Enable/disable debug tracing (default: false)
    'enabled' => env('INGEST_DEBUG', false),

    // Days to retain traces before cleanup (default: 7)
    'retention_days' => 7,

    // Toggle specific trace types
    'trace_types' => [
        'preview_extraction' => true,
        'metadata_extraction' => true,
        'thumbnail_generation' => true,
        'enhancement' => true,
    ],

    // Environment variables to capture in snapshots
    'capture_environment' => [
        'EXIFTOOL_BINARY',
        'EXIFTOOL_SPEED_MODE',
        'QUEUE_CONNECTION',
        // ...
    ],

    // Filament integration settings
    'filament' => [
        'enabled' => true,
        'navigation_group' => 'Debug',
    ],
];
```

## Usage

### Understanding Decision Traces

When you upload an image with `INGEST_DEBUG=true`, the system records:

1. **Preview Extraction Attempts**
   - Which ExifTool preview tags were tried (PreviewImage, JpgFromRaw, ThumbnailImage)
   - Order of attempts (1, 2, 3...)
   - Success/failure for each
   - Duration in milliseconds
   - Output size and dimensions

2. **Metadata Extraction**
   - Which extraction method was used (exiftool_fast, exiftool, php_exif)
   - Success/failure status
   - Processing time

3. **Thumbnail Generation**
   - Source used (from_preview, from_source)
   - Processing time

### Example Trace Output

After uploading a Canon CR2 RAW file, you might see:

| Type | Method | Order | Success | Reason | Duration |
|------|--------|-------|---------|--------|----------|
| preview_extraction | PreviewImage | 1 | No | Tag not found | 12ms |
| preview_extraction | JpgFromRaw | 2 | Yes | - | 45ms |
| thumbnail_generation | from_preview | 1 | Yes | - | 23ms |

This tells you that `PreviewImage` wasn't available in the CR2 file, but `JpgFromRaw` worked on the second attempt.

### CLI Commands

#### View traces for a specific upload

```bash
# Show all traces for a UUID
php artisan debug:trace abc12345-uuid-here

# Show summary only
php artisan debug:trace abc12345-uuid-here --summary
```

#### Create a configuration snapshot

```bash
# Create a named snapshot
php artisan debug:snapshot "Testing RAW extraction"

# With description
php artisan debug:snapshot "Canon CR2 test" --description="Testing with Canon 5D Mark IV files"
```

#### Cleanup old traces

```bash
# Delete traces older than 7 days (default)
php artisan debug:cleanup

# Delete traces older than 3 days
php artisan debug:cleanup --days=3

# Preview what would be deleted
php artisan debug:cleanup --dry-run
```

### Filament Admin Pages

With the Filament plugin enabled, you'll see two new pages under the "Debug" navigation group:

#### Ingest Traces Page
- Filter by UUID, trace type, date range, success/failure
- View all decision attempts in a sortable table
- Stats showing total/successful/failed traces
- Auto-refreshes every 10 seconds

#### Config Snapshots Page
- View all saved configuration snapshots
- Create new snapshots with name and description
- View detailed snapshot data including:
  - Thumbnail/preview quality settings
  - ExifTool configuration
  - Queue settings
  - Environment variables

## Testing Workflow

### Recommended Test Process

1. **Before testing**: Create a configuration snapshot
   ```bash
   php artisan debug:snapshot "Baseline config"
   ```

2. **Upload test images**: Use your test image directory

3. **Review traces**: Check which methods succeeded/failed
   ```bash
   php artisan debug:trace <uuid> --summary
   ```

4. **Make config changes**: Adjust settings in `config/ingest.php`

5. **Create another snapshot**: Document the change
   ```bash
   php artisan debug:snapshot "Increased preview quality"
   ```

6. **Compare results**: Upload same test images and compare traces

### Test Images Location

Store test images in a consistent location for reproducible testing:

```
/Herd-Profoto/test-images/
├── RAW files
│   ├── test-images-0008.dng
│   ├── test-images-0009.dng
│   └── test-images-0010.dng
├── JPEG files
│   ├── AMB_5333.jpg
│   ├── FLS_5412.jpg
│   └── ...
└── HEIC files
    ├── 1B92939C-68AD-4CBB-8D7C-84E58C30926C.heic
    └── ...
```

## Troubleshooting

### Traces not being recorded

1. Ensure `INGEST_DEBUG=true` in your `.env`
2. Check that the `debug_ingest_traces` table exists
3. Verify queue workers are running (traces are recorded during job execution)

### Preview extraction failing

Check the traces to see which methods were attempted:

```bash
php artisan debug:trace <uuid>
```

Common issues:
- **All tags fail**: File may not have embedded previews (generate from source)
- **PreviewImage fails, ThumbnailImage works**: File has only small thumbnail embedded
- **ImageManager_fallback**: Fell back to Intervention Image (slower but works)

### Thumbnail too small

If thumbnails are coming out too small:
1. Check `config('ingest.exif.thumbnail')` settings
2. Look at trace `result_info.dimensions` to see what was generated
3. Verify preview extraction succeeded (thumbnails are generated from previews)

## Database Schema

### debug_ingest_traces

| Column | Type | Description |
|--------|------|-------------|
| uuid | string | Links to ProxyImage |
| session_id | string | Groups traces from same job |
| trace_type | enum | preview_extraction, metadata_extraction, etc. |
| method_tried | string | Method attempted |
| method_order | int | Order in fallback chain |
| success | boolean | Whether method succeeded |
| failure_reason | text | Why it failed |
| result_info | json | Duration, size, dimensions |

### debug_config_snapshots

| Column | Type | Description |
|--------|------|-------------|
| name | string | User-defined name |
| description | text | What's being tested |
| config_data | json | Ingest configuration |
| queue_config | json | Queue settings |
| supervisor_config | json | Worker settings |
| environment | json | Captured env vars |

## License

MIT
