# Complete Testing & Troubleshooting Guide
Comprehensive testing suite for your job system. 

Files Created:
**test-extract-fast.php** - Complete testing script
* Tests extractFast with different ExifTool tags and speed modes
* Compares fast vs full extraction performance
* Tests thumbnail/preview extraction with output locations
* Shows exactly where images are saved and how to view them

***debug-tools.php** - Debugging utilities for Tinker*
1. debugExifTool() - Check ExifTool installation and configuration
2. debugLatestProxy() - Examine proxy images and their status
3. debugFileExtraction() - Test extraction on specific files
4. debugQueueJobs() - Monitor queue status and failures
5. debugExifToolTags() - Test specific ExifTool tags

***IMAGE_LOCATIONS.md** - Where to find output images*
* Shows exact file paths: storage/app/ingest-temp/thumbs/ and storage/app/ingest-temp/previews/
* Commands to view images: open, ql (QuickLook), web URLs
* Troubleshooting missing images and permission issues

**T*INKER_TESTING.md** - Laravel Tinker examples*
* Load testing tools and run specific tests
* Test job dispatch, retry logic, and error scenarios
* Performance testing and batch operations
* Real-world testing scenarios

***QUEUE_MONITORING.md** - Job system monitoring*
* Real-time queue monitoring commands
* Performance tracking and memory usage
* Production setup with Supervisor
* Health check endpoints

1. Test extractFast with different tags:
# Test with a specific image
```php test-extract-fast.php/path/to/your/image.jpg```

# Or find test images automatically
```php test-extract-fast.php```


2. See where your images appear:
# List generated thumbnails
```ls -la storage/app/ingest-temp/thumbs/```

# Open a thumbnail to verify
```open storage/app/ingest-temp/thumbs/your-uuid.jpg```


3. Debug with Tinker:
```php
php artisan tinker
»>> include_once 'debug-tools.php';
>>> $debug = new IngestDebugger();
>>> $debug->debugExifToolO);
>>> $debug->debugLatestProxyc;
```

1. Monitor job execution:
# Watch jobs process
```php artisan queue:monitor ingest-preview```
# Check logs
```tail -f storage/logs/laravel.log | grep -i "preview"```


### Key Testing Points extractFast Testing:
• Compare speed modes (`fast`, `fastz`, `full`)
• Test custom Exif Tool tag selection
• Measure extraction performance
• Verify metadata normalization

### Image Output Locations:
• Thumbnails: storage/app/ingest-temp/thumbs/{uuid}.jpg
(400x400px)
• Previews: storage/app/ingest-temp/previews/{uuid}.jpg
(2048px max)
• Originals: storage/app/ingest-temp/{uuid}.ext
Job System Testing:
• Manual job dispatch and monitoring
• Retry logic verification
• Error handling and recovery
• Performance under load
The testing suite gives you complete visibility into the job pipeline, from metadata extraction through image generation to final storage. You can now test every aspect of the system and see exactly where your output images appear.

### Job Types & Processing Pipeline
ProcessImageIngestJob (High Priority - ingest queue)
* Purpose: Final image processing and storage to permanent location
* Libraries: Uses IngestProcessor service with ExifTool metadata
  * Outputs:
  * Permanent Image record in database
  * Final file stored in configured final_disk location
  * Organized by schema patterns (e.g., shoots/2025/01/Canon-EOS-R5/001-IMG_1234.jpg)
  * Fallbacks:
  * 3 retries with exponential backoff [10, 30, 60 seconds]
  * Marks proxy with metadata_error on final failure
  * Preserves proxy for manual retry

⠀ProcessPreviewJob (Medium Priority - ingest-preview queue)
* Purpose: Generates preview images and enhanced thumbnails
* Libraries: MetadataExtractor with ExifTool + Intervention Image
  * Outputs:
  * Preview image (~2048px max dimension) at previews/{uuid}.jpg
  * Enhanced thumbnail (~400px square) at thumbs/{uuid}.jpg
  * Fallbacks:
  * Primary: Extract embedded preview using ExifTool (PreviewImage, JpgFromRaw, ThumbnailImage tags)
  * Secondary: Generate from source using Intervention Image
  * Tertiary: Keep existing tiny thumbnail if both fail
  * 3 retries with [10, 30, 60] second backoff

⠀EnhancePreviewJob (Low Priority - ingest-enhance queue)
* Purpose: User-triggered high-quality preview enhancement
* Libraries: Intervention Image v3 with ImageMagick/GD drivers
  * Outputs:
  * Enhanced preview at target width (configurable, typically larger)
  * Updates preview_width and enhancement_status fields
  * Fallbacks:
  * Driver selection: ImageMagick (preferred) → GD (fallback)
  * Source selection: Existing preview → Original file
  * 2 retries with [5, 15] second backoff

⠀Technology Stack & Libraries
ExifTool (Primary metadata engine)
* Binary: External Perl executable (configured via EXIFTOOL_BINARY)
* Speed modes: fast (default), fast2 (aggressive), full (complete)
  * Features:
  * Batch metadata extraction (JSON output)
  * Embedded preview extraction from RAW files
  * Support for 600+ file formats including RAW formats
  * Health checks and timeout handling (30s default)

⠀Intervention Image v3 (Image processing)
* Drivers: ImageMagick (preferred for RAW support) → GD (fallback)
* Operations: Resizing, orientation, JPEG encoding, cover cropping
* Configuration: Quality settings (80-95%), dimensions, auto-orientation

⠀Fallback Chain
1. ExifTool → PHP exif_read_data() functions
2. ImageMagick → GD extension
3. Embedded previews → Generated from source
4. Enhanced thumbnails → Basic thumbnails

⠀Output Files & Storage
Temporary Storage (ingest-temp/)



ingest-temp/
├── {uuid}.ext              # Original uploaded file
├── thumbs/{uuid}.jpg       # 400x400 thumbnail (square)
└── previews/{uuid}.jpg     # 2048px preview (max dimension)
Permanent Storage (configurable)



{final_path}/
└── {schema_pattern}/
    └── {final_filename}    # Original file moved to final location
Database Records
* ProxyImage: Temporary metadata, paths, status tracking
* Image: Permanent record with normalized metadata
* Status fields: preview_status, enhancement_status, error tracking

⠀Error Handling & Recovery
Retry Logic
* ProcessImageIngestJob: 3 retries, 30-minute timeout, exponential backoff
* ProcessPreviewJob: 3 retries, status tracking (pending → processing → ready/failed)
* EnhancePreviewJob: 2 retries, faster backoff for user-triggered operations

⠀Failure Modes
* Graceful degradation: Falls back to lower-quality outputs instead of failing
* Error preservation: Stores error messages in database for debugging
* Proxy preservation: Failed jobs keep proxy records for manual retry
* Cleanup: Failed temp files cleaned up after successful final processing

⠀Monitoring
* Job tags for filtering: ingest, proxy:{uuid}, user:{id}
* Comprehensive logging with timing metrics
* Status tracking in database for UI feedback

⠀Configuration Points
Performance Tuning
* ExifTool speed modes for batch vs. individual processing
* Preview size limits (8MB default, auto-downscaling)
* Quality settings: Thumbnail (80%), Preview (85%), Final (95%)
* Queue timeouts and memory limits

⠀Storage Flexibility
* Configurable disks for temporary vs. final storage
* Schema-based path/filename patterns
* Association support for linking to host app models

⠀This system provides robust, production-ready image processing with multiple fallback layers ensuring users always get usable output even when optimal processing fails.



