# Image Output Locations Guide

## Where to Find Generated Images

### Temporary Storage (During Processing)
```
storage/app/ingest-temp/
├── {uuid}.ext                  # Original uploaded file
├── thumbs/{uuid}.jpg           # 400x400 thumbnail (square)
└── previews/{uuid}.jpg         # 2048px preview (max dimension)
```

### Full Paths on Your System
```bash
# Base temporary directory
/Users/davepeloso/Herd/prophoto-ingest/storage/app/ingest-temp/

# Thumbnails
/Users/davepeloso/Herd/prophoto-ingest/storage/app/ingest-temp/thumbs/

# Previews  
/Users/davepeloso/Herd/prophoto-ingest/storage/app/ingest-temp/previews/
```

## How to View the Images

### Method 1: Direct File Access
```bash
# List all generated thumbnails
ls -la storage/app/ingest-temp/thumbs/

# List all generated previews
ls -la storage/app/ingest-temp/previews/

# Open a specific image (macOS)
open storage/app/ingest-temp/thumbs/your-uuid.jpg

# Quick preview (macOS)
ql storage/app/ingest-temp/previews/your-uuid.jpg
```

### Method 2: Laravel Tinker
```php
// Get the latest proxy image with its paths
$proxy = \prophoto\Ingest\Models\ProxyImage::latest()->first();
echo "Thumbnail: " . $proxy->thumbnail_path . "\n";
echo "Preview: " . $proxy->preview_path . "\n";

// Get full filesystem paths
$thumbPath = \Storage::disk('public')->path($proxy->thumbnail_path);
$previewPath = \Storage::disk('public')->path($proxy->preview_path);

echo "Full thumb path: " . $thumbPath . "\n";
echo "Full preview path: " . $previewPath . "\n";
```

### Method 3: Web Access (if using public disk)
```bash
# If your temp disk is 'public', images are accessible via URL:
# http://your-app.test/ingest-temp/thumbs/{uuid}.jpg
# http://your-app.test/ingest-temp/previews/{uuid}.jpg
```

## Testing Image Generation

### Test with a Specific Image
```bash
# Run the test script with a sample image
php test-extract-fast.php /path/to/your/test-image.jpg

# Or with a RAW file
php test-extract-fast.php /path/to/your/test-image.CR2
```

### Test via Laravel Tinker
```php
// Load the extractor
$extractor = app(\prophoto\Ingest\Services\MetadataExtractor::class);

// Test with an existing file
$imagePath = storage_path('app/ingest-temp/your-uuid.jpg');
$result = $extractor->extractFast($imagePath);

// Extract thumbnail
$uuid = 'test-' . uniqid();
$thumbPath = $extractor->extractEmbeddedThumbnail($imagePath, $uuid);
echo "Thumbnail saved to: " . $thumbPath . "\n";

// Generate preview
$previewPath = $extractor->generatePreview($imagePath, $uuid);
echo "Preview saved to: " . $previewPath . "\n";
```

## Debugging Missing Images

### Check If Files Exist
```php
// In Tinker, check for a specific proxy
$proxy = \prophoto\Ingest\Models\ProxyImage::where('uuid', 'your-uuid')->first();

if ($proxy) {
    echo "Thumbnail exists: " . \Storage::disk('public')->exists($proxy->thumbnail_path) . "\n";
    echo "Preview exists: " . \Storage::disk('public')->exists($proxy->preview_path) . "\n";
    echo "Preview status: " . $proxy->preview_status . "\n";
    
    if ($proxy->preview_error) {
        echo "Preview error: " . $proxy->preview_error . "\n";
    }
}
```

### Check Job Status
```bash
# Check if preview jobs are running
php artisan queue:monitor ingest-preview

# Check failed jobs
php artisan queue:failed

# Check recent logs
tail -f storage/logs/laravel.log | grep -i "preview\|thumbnail"
```

### Common Issues & Solutions

1. **Images not appearing in storage/app/ingest-temp/**
   - Check your `INGEST_TEMP_DISK` .env setting
   - Ensure the directory is writable: `chmod -R 755 storage/app/ingest-temp`

2. **Thumbnail extraction fails**
   - Not all images have embedded thumbnails
   - Check ExifTool availability: `php artisan exiftool:doctor`

3. **Preview generation fails**
   - Check ImageMagick/GD PHP extensions
   - Verify file format is supported

4. **Permission issues**
   ```bash
   # Fix storage permissions
   chmod -R 775 storage/
   chown -R www-data:www-data storage/
   ```
