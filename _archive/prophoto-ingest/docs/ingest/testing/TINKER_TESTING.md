# Laravel Tinker Testing Guide

## Quick Start with Job System Testing

### Load the Debug Tools
```php
// In Laravel Tinker
include_once 'debug-tools.php';
$debug = new IngestDebugger();
```

### Test Metadata Extraction
```php
// Test extractFast with different configurations
$extractor = app(\prophoto\Ingest\Services\MetadataExtractor::class);

// Test with a sample image
$imagePath = storage_path('app/ingest-temp/your-uuid.jpg');
$result = $extractor->extractFast($imagePath);

// View results
echo "Method: " . $result['extraction_method'] . "\n";
echo "Fields: " . count($result['metadata']) . "\n";
print_r(array_keys($result['metadata']));
```

### Test Specific ExifTool Tags
```php
// Test custom tag extraction
$debug->debugExifToolTags('/path/to/image.jpg', [
    'Make', 
    'Model', 
    'DateTimeOriginal', 
    'ISO', 
    'FNumber'
]);
```

### Test Job Dispatch
```php
// Create a test proxy and dispatch jobs
use prophoto\Ingest\Models\ProxyImage;
use prophoto\Ingest\Jobs\ProcessPreviewJob;
use prophoto\Ingest\Jobs\ProcessImageIngestJob;

// Find an existing proxy to test with
$proxy = \prophoto\Ingest\Models\ProxyImage::where('preview_status', 'pending')->first();

if ($proxy) {
    // Dispatch preview job manually
    ProcessPreviewJob::dispatch($proxy->uuid);
    echo "Preview job dispatched for: " . $proxy->uuid . "\n";
    
    // Check job status
    $updatedProxy = \prophoto\Ingest\Models\ProxyImage::find($proxy->id);
    echo "Status: " . $updatedProxy->preview_status . "\n";
}
```

### Test Enhancement Job
```php
use prophoto\Ingest\Jobs\EnhancePreviewJob;

// Find a proxy with a ready preview
$proxy = \prophoto\Ingest\Models\ProxyImage::where('preview_status', 'ready')->first();

if ($proxy) {
    // Calculate enhanced dimensions (25% increase)
    $currentWidth = $proxy->preview_width ?? 2048;
    $newWidth = min(4096, (int) round($currentWidth * 1.25));
    
    // Dispatch enhancement job
    EnhancePreviewJob::dispatch($proxy->uuid, $newWidth);
    echo "Enhancement job dispatched for: " . $proxy->uuid . "\n";
    echo "Target width: $newWidth px\n";
}
```

### Test Image Processing Directly
```php
// Test thumbnail extraction
$uuid = 'test-' . uniqid();
$imagePath = storage_path('app/ingest-temp/your-image.jpg');

$thumbnailPath = $extractor->extractEmbeddedThumbnail($imagePath, $uuid);
if ($thumbnailPath) {
    echo "Thumbnail extracted: $thumbnailPath\n";
    
    // Get full path to view the image
    $fullPath = \Storage::disk('public')->path($thumbnailPath);
    echo "Full path: $fullPath\n";
    
    // You can now open this file: `open $fullPath`
}

// Test preview generation
$previewPath = $extractor->generatePreview($imagePath, $uuid);
if ($previewPath) {
    echo "Preview generated: $previewPath\n";
    
    $fullPath = \Storage::disk('public')->path($previewPath);
    echo "Full path: $fullPath\n";
}
```

### Monitor Job Execution
```php
// Check queue status
$debug->debugQueueJobs();

// Check specific proxy status
$proxy = \prophoto\Ingest\Models\ProxyImage::latest()->first();
echo "UUID: " . $proxy->uuid . "\n";
echo "Preview status: " . $proxy->preview_status . "\n";
echo "Enhancement status: " . $proxy->enhancement_status . "\n";

// Check if files exist
$tempDisk = config('ingest.storage.temp_disk', 'local');
echo "Temp file exists: " . \Storage::disk($tempDisk)->exists($proxy->temp_path) . "\n";
echo "Thumbnail exists: " . \Storage::disk($tempDisk)->exists($proxy->thumbnail_path) . "\n";
echo "Preview exists: " . \Storage::disk($tempDisk)->exists($proxy->preview_path) . "\n";
```

### Test Error Scenarios
```php
// Test with invalid file
$result = $extractor->extractFast('/nonexistent/file.jpg');
echo "Error handling: " . ($result['error'] ?? 'No error') . "\n";

// Test with unsupported file format
$result = $extractor->extractFast('/path/to/document.pdf');
echo "Unsupported format: " . $result['extraction_method'] . "\n";
```

### Test Batch Operations
```php
// Test batch metadata extraction
$imagePaths = [
    storage_path('app/ingest-temp/image1.jpg'),
    storage_path('app/ingest-temp/image2.jpg'),
    storage_path('app/ingest-temp/image3.jpg'),
];

$batchResults = $extractor->extractBatch($imagePaths);
foreach ($batchResults as $filename => $result) {
    echo "$filename: {$result['extraction_method']} (" . count($result['metadata']) . " fields)\n";
}
```

### Test Configuration Changes
```php
// Test different speed modes
$config = app('config');
$originalMode = $config->get('ingest.exiftool.speed_mode');

// Test fast2 mode
$config->set('ingest.exiftool.speed_mode', 'fast2');
$result1 = $extractor->extractFast($imagePath);
echo "Fast2 mode: " . (microtime(true) - $startTime) . "ms\n";

// Test full mode
$config->set('ingest.exiftool.speed_mode', 'full');
$result2 = $extractor->extractFast($imagePath);
echo "Full mode: " . (microtime(true) - $startTime) . "ms\n";

// Restore original
$config->set('ingest.exiftool.speed_mode', $originalMode);
```

## Real-World Testing Scenarios

### Scenario 1: Upload and Process Flow
```php
// Simulate the complete upload flow
use Illuminate\Http\UploadedFile;
use prophoto\Ingest\Http\Controllers\IngestController;

// Create a test file upload
$testFile = new UploadedFile(
    '/path/to/test/image.jpg',
    'test-image.jpg',
    'image/jpeg',
    null,
    true
);

// Test the upload endpoint (simplified)
$controller = new IngestController();
// Note: This would require proper request setup in real testing
```

### Scenario 2: Retry Logic Testing
```php
// Test job retry behavior
use prophoto\Ingest\Jobs\ProcessPreviewJob;

// Create a job that will fail
$job = new ProcessPreviewJob('invalid-uuid');

// Manually trigger the failure handling
$job->failed(new Exception('Test failure'));

// Check the error handling
$proxy = \prophoto\Ingest\Models\ProxyImage::where('uuid', 'invalid-uuid')->first();
if ($proxy) {
    echo "Error recorded: " . $proxy->preview_error . "\n";
}
```

### Scenario 3: Performance Testing
```php
// Test extraction performance
$imagePath = storage_path('app/ingest-temp/large-image.jpg');

$iterations = 10;
$totalTime = 0;

for ($i = 0; $i < $iterations; $i++) {
    $start = microtime(true);
    $result = $extractor->extractFast($imagePath);
    $totalTime += microtime(true) - $start;
}

$avgTime = ($totalTime / $iterations) * 1000;
echo "Average extraction time: {$avgTime}ms\n";
```

## Troubleshooting Common Issues

### Issue: Jobs Not Processing
```php
// Check if queue worker is running
$debug->debugQueueJobs();

// Manually run a job
php artisan queue:work --once --queue=ingest-preview
```

### Issue: Images Not Appearing
```php
// Check storage configuration
echo "Temp disk: " . config('ingest.storage.temp_disk') . "\n";
echo "Temp path: " . config('ingest.storage.temp_path') . "\n";

// Check permissions
$tempPath = storage_path('app/ingest-temp');
echo "Temp directory writable: " . (is_writable($tempPath) ? "Yes" : "No") . "\n";
```

### Issue: ExifTool Problems
```php
// Run ExifTool diagnostics
$debug->debugExifTool();

// Test ExifTool directly
exec('exiftool -ver 2>&1', $output, $returnCode);
echo "ExifTool command output: " . implode("\n", $output) . "\n";
echo "Return code: $returnCode\n";
```
