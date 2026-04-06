# ProPhoto Downloads

Bulk download system for ProPhoto providing ZIP generation, progress tracking, and expiring artifacts.

## Purpose

Enables secure, trackable bulk downloads where:
- "Download all" creates async ZIP generation job
- Real-time progress tracking during ZIP creation
- Temporary artifacts with automatic expiration
- Include metadata CSV + README in ZIP
- Authorization checks per magic link token

## Key Responsibilities

### Async ZIP Generation
- Queue job to build ZIP (prevent timeout)
- Include selected images (or entire gallery)
- Add metadata CSV with EXIF data
- Add README with usage rights/attribution
- Progress updates via events

### Progress Tracking
- Real-time progress: "15 of 50 images processed"
- WebSocket or polling-based updates
- Estimated time remaining
- Retry support on failure

### Artifact Management
- Temporary storage for generated ZIPs
- Signed URL for secure download
- Auto-expiration after configured time (24 hours)
- Cleanup job removes old artifacts
- Storage quota tracking

### Metadata Inclusion
- CSV export of image metadata
- Include: filename, dimensions, capture date, camera, lens, ISO, aperture, shutter
- README with studio branding and usage terms
- Optional watermarking instructions

## Contracts Implemented

- `BulkDownloadContract` - Create bulk download jobs
- `ProgressTrackerContract` - Track download progress

## Database Tables

- `download_archives` - Generated ZIP files
  - `id`
  - `studio_id`
  - `organization_id`
  - `gallery_id`
  - `user_id` (who requested)
  - `magic_link_token_id` (if via magic link)
  - `status` (queued, building, ready, expired, failed)
  - `progress` (0-100)
  - `total_images`
  - `processed_images`
  - `file_size_mb`
  - `storage_path` (temporary ZIP location)
  - `signed_url` (time-limited)
  - `expires_at`
  - `completed_at`
  - `failed_reason` (if failed)
  - `metadata` (JSON: options, errors)

## Configuration

**config/downloads.php**
```php
return [
    'queue' => env('DOWNLOADS_QUEUE', 'downloads'), // Dedicated queue

    'zip' => [
        'compression' => 9, // 0-9, higher = smaller/slower
        'include_metadata_csv' => true,
        'include_readme' => true,
        'chunk_size' => 50, // Process 50 images at a time
    ],

    'artifacts' => [
        'storage_disk' => 'downloads', // Separate disk for ZIPs
        'expiry_hours' => 24, // ZIP available for 24 hours
        'cleanup_expired_days' => 7, // Delete expired after 7 days
        'max_size_mb' => 5000, // 5GB max ZIP size
    ],

    'progress' => [
        'broadcast' => true, // Use WebSockets for real-time
        'polling_interval_seconds' => 5, // Fallback polling
    ],

    'limits' => [
        'max_concurrent_per_studio' => 5,
        'max_images_per_download' => 1000,
        'rate_limit_per_hour' => 10,
    ],
];
```

## Usage Examples

### Request Bulk Download
```php
use ProPhoto\Contracts\Download\BulkDownloadContract;

$downloads = app(BulkDownloadContract::class);

$archive = $downloads->request([
    'gallery' => $gallery,
    'image_ids' => $imageIds, // Specific images, or null for all
    'include_originals' => false, // Use 'web' derivatives instead
    'include_metadata' => true,
    'include_readme' => true,
    'requested_by' => auth()->user(),
    'magic_link_token' => $token, // If via magic link
]);

// Returns: DownloadArchive DTO
// Status: queued
// Job dispatched to queue
```

### Check Progress
```php
use ProPhoto\Contracts\Download\ProgressTrackerContract;

$tracker = app(ProgressTrackerContract::class);

$progress = $tracker->getProgress($archive);

// Returns: DownloadProgress DTO
// {
//   status: 'building',
//   progress: 65,
//   total_images: 100,
//   processed_images: 65,
//   estimated_seconds_remaining: 45,
//   current_action: 'Compressing images...'
// }
```

### Download Ready Notification
```php
// User gets notified when ZIP is ready (via email or WebSocket)

// Email:
// "Your download is ready! Download now (expires in 24 hours)"

// WebSocket:
// Channel: download.{archive_id}
// Event: DownloadReady
// Payload: { signed_url, expires_at, file_size_mb }
```

### Download ZIP
```php
// User clicks download link (contains signed URL)
Route::get('/downloads/{archive}/download', function (DownloadArchive $archive) {
    // Verify not expired
    if ($archive->isExpired()) {
        abort(410, 'This download has expired. Please request a new one.');
    }

    // Verify authorization (magic link or user ownership)
    $this->authorize('download', $archive);

    // Return file download
    return response()->download(
        $archive->storage_path,
        "gallery-{$archive->gallery_id}.zip"
    );
});
```

### Track Downloaded ZIPs
```php
// Log to audit trail when ZIP downloaded
event(new ZipDownloaded($archive, $request->ip()));
```

## ZIP Structure

```
gallery-789.zip
  ├── README.txt                    // Studio info, usage rights
  ├── metadata.csv                  // Image metadata export
  └── images/
      ├── wedding-001.jpg
      ├── wedding-002.jpg
      ├── wedding-003.jpg
      └── ...
```

**metadata.csv**:
```csv
filename,width,height,capture_date,camera,lens,iso,aperture,shutter,file_size
wedding-001.jpg,4000,6000,2024-06-15 14:30:00,Canon EOS R5,RF 50mm f/1.2,400,f/1.8,1/200,12.5MB
wedding-002.jpg,4000,6000,2024-06-15 14:32:15,Canon EOS R5,RF 50mm f/1.2,400,f/2.0,1/250,11.8MB
...
```

**README.txt**:
```
ProPhoto Studio
===============

Gallery: Summer Wedding 2024
Downloaded: 2024-07-10 15:45:00

Images: 150 files
Total Size: 1.8 GB

USAGE RIGHTS
------------
These images are licensed for personal use only.
Commercial use requires written permission.

For questions, contact: dave@prophoto.com
Studio website: https://prophoto.davepeloso.com
```

## Job Processing

```php
// Job: GenerateDownloadArchiveJob

public function handle()
{
    $this->updateProgress(0, 'Starting...');

    // Step 1: Create temp directory
    $tempDir = storage_path("temp/download-{$this->archive->id}");
    mkdir($tempDir);

    // Step 2: Download images from storage
    $images = $this->archive->images;
    foreach ($images as $index => $image) {
        $localPath = $tempDir . "/images/{$image->filename}";
        Storage::copy($image->path, $localPath);

        $progress = (($index + 1) / count($images)) * 80; // 0-80%
        $this->updateProgress($progress, "Processing image {$index + 1} of " . count($images));
    }

    // Step 3: Generate metadata CSV
    $this->generateMetadataCsv($images, $tempDir . '/metadata.csv');
    $this->updateProgress(85, 'Generating metadata...');

    // Step 4: Generate README
    $this->generateReadme($tempDir . '/README.txt');
    $this->updateProgress(90, 'Adding README...');

    // Step 5: Create ZIP
    $zipPath = storage_path("downloads/archive-{$this->archive->id}.zip");
    $zip = Zipper::make($zipPath);
    $zip->add($tempDir);
    $zip->close();
    $this->updateProgress(95, 'Compressing...');

    // Step 6: Generate signed URL
    $signedUrl = $this->generateSignedUrl($zipPath);

    // Step 7: Mark ready
    $this->archive->update([
        'status' => 'ready',
        'progress' => 100,
        'storage_path' => $zipPath,
        'signed_url' => $signedUrl,
        'file_size_mb' => filesize($zipPath) / 1024 / 1024,
        'completed_at' => now(),
        'expires_at' => now()->addHours(24),
    ]);

    // Step 8: Cleanup temp
    File::deleteDirectory($tempDir);

    // Step 9: Notify user
    event(new DownloadReady($this->archive));
}
```

## Real-Time Progress (WebSockets)

```javascript
// Frontend: Listen for progress updates

Echo.private(`download.${archiveId}`)
    .listen('DownloadProgressUpdated', (e) => {
        updateProgressBar(e.progress); // 0-100
        updateStatus(e.current_action); // "Processing image 45 of 150"
    })
    .listen('DownloadReady', (e) => {
        showDownloadButton(e.signed_url);
        showExpiry(e.expires_at);
    })
    .listen('DownloadFailed', (e) => {
        showError(e.reason);
    });
```

## Cleanup Job

```php
// Scheduled daily: php artisan downloads:cleanup

public function handle()
{
    // Delete expired archives
    DownloadArchive::where('expires_at', '<', now())
        ->where('status', 'ready')
        ->chunk(100, function ($archives) {
            foreach ($archives as $archive) {
                Storage::delete($archive->storage_path);
                $archive->update(['status' => 'expired']);
            }
        });

    // Delete old expired records (after 7 days)
    DownloadArchive::where('status', 'expired')
        ->where('expires_at', '<', now()->subDays(7))
        ->delete();
}
```

## Events

- `DownloadRequested` - Bulk download requested
- `DownloadProgressUpdated` - Progress changed (0-100%)
- `DownloadReady` - ZIP ready for download
- `DownloadFailed` - ZIP generation failed
- `ZipDownloaded` - User downloaded ZIP
- `DownloadExpired` - ZIP expired and deleted

## Error Handling

- **Out of disk space**: Fail gracefully, notify admin
- **Image not found**: Skip and log warning
- **Timeout**: Job uses `tries = 3` with exponential backoff
- **Corruption**: Validate ZIP before marking ready

## Authorization

```php
// Policy: DownloadArchivePolicy

public function download(User $user, DownloadArchive $archive)
{
    // User owns this download
    if ($archive->user_id === $user->id) {
        return true;
    }

    // Or has valid magic link token
    if ($archive->magic_link_token_id) {
        return $user->hasValidToken($archive->magic_link_token_id);
    }

    return false;
}
```

## Future Enhancements

- [ ] Resume support (partial ZIPs)
- [ ] Format selection (ZIP, TAR, RAR)
- [ ] Custom folder structure in ZIP
- [ ] Split large ZIPs into parts
- [ ] Direct cloud storage upload (bypass server)
- [ ] Preview before download

## Dependencies

- `prophoto/contracts` - Download contracts and DTOs
- `prophoto/storage` - Access to images
- `prophoto/security` - Magic link authorization
- `prophoto/audit` - Log all downloads
- `prophoto/notifications` - Notify when ready

## Testing

```bash
cd prophoto-downloads
vendor/bin/pest
```

## Notes

- Downloads queue uses dedicated worker (high memory)
- Large ZIPs (>1GB) may take 10+ minutes
- Progress updates every 5% (not every image for performance)
- Signed URLs expire with archive (double security)
- Failed jobs retry 3 times before marking failed
