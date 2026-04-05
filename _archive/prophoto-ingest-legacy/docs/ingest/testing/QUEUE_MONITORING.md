# Queue Monitoring & Job Testing Guide

## Real-Time Queue Monitoring

### Command Line Monitoring
```bash
# Monitor all ingest queues in real-time
php artisan queue:monitor ingest ingest-preview ingest-enhance

# Monitor specific queue with verbose output
php artisan queue:monitor ingest-preview --verbose

# Check queue sizes
php artisan queue:monitor --format=json

# Watch worker processes
ps aux | grep "queue:work"

# Monitor logs for job activity
tail -f storage/logs/laravel.log | grep -i "ProcessPreviewJob\|ProcessImageIngestJob\|EnhancePreviewJob"
```

### Database Monitoring
```php
// In Tinker - Check current queue state
$totalJobs = DB::table('jobs')->count();
$previewJobs = DB::table('jobs')->where('queue', 'ingest-preview')->count();
$ingestJobs = DB::table('jobs')->where('queue', 'ingest')->count();
$enhanceJobs = DB::table('jobs')->where('queue', 'ingest-enhance')->count();

echo "Total pending: $totalJobs\n";
echo "Preview queue: $previewJobs\n";
echo "Ingest queue: $ingestJobs\n";
echo "Enhance queue: $enhanceJobs\n";

// Check failed jobs
$failedCount = DB::table('failed_jobs')->count();
echo "Failed jobs: $failedCount\n";

// Show recent failures
$recentFailures = DB::table('failed_jobs')
    ->latest('failed_at')
    ->limit(5)
    ->get(['queue', 'exception', 'failed_at']);
    
foreach ($recentFailures as $failure) {
    echo "Queue: {$failure->queue}, Failed: {$failure->failed_at}\n";
    echo "Error: " . substr($failure->exception, 0, 100) . "...\n\n";
}
```

## Job Testing Strategies

### 1. Test Individual Jobs
```bash
# Run a single preview job
php artisan tinker
>>> ProcessPreviewJob::dispatch('your-uuid-here');

# Process one job and stop
php artisan queue:work --once --queue=ingest-preview

# Run with memory limit for testing
php artisan queue:work --memory=128 --timeout=60 --queue=ingest-preview
```

### 2. Test Job Retry Logic
```php
// Create a job that will fail to test retry behavior
$job = new \prophoto\Ingest\Jobs\ProcessPreviewJob('nonexistent-uuid');

// Check retry configuration
echo "Tries: " . $job->tries . "\n";
echo "Backoff: " . implode(', ', $job->backoff) . "\n";
echo "Timeout: " . $job->timeout . " seconds\n";

// Manually test failure handling
try {
    $job->handle(app(\prophoto\Ingest\Services\MetadataExtractor::class));
} catch (Exception $e) {
    echo "Job failed as expected: " . $e->getMessage() . "\n";
}
```

### 3. Load Testing
```bash
# Dispatch multiple test jobs
php artisan tinker
>>> for ($i = 0; $i < 10; $i++) {
...     ProcessPreviewJob::dispatch('test-uuid-' . $i);
... }

# Monitor processing
php artisan queue:monitor ingest-preview

# Run multiple workers
php artisan queue:work --queue=ingest-preview &
php artisan queue:work --queue=ingest-preview &
php artisan queue:work --queue=ingest-preview &
```

## Performance Monitoring

### Track Job Performance
```php
// Add performance tracking to your testing
$startTime = microtime(true);

// Run the job or extraction
$result = $extractor->extractFast($imagePath);

$duration = round((microtime(true) - $startTime) * 1000, 2);
echo "Extraction took: {$duration}ms\n";

// Log performance data
Log::info('Performance test', [
    'operation' => 'extractFast',
    'duration_ms' => $duration,
    'file_size' => filesize($imagePath),
    'field_count' => count($result['metadata'])
]);
```

### Memory Usage Monitoring
```php
// Monitor memory usage during processing
$memoryBefore = memory_get_usage(true);

// Perform operation
$result = $extractor->extract($imagePath);

$memoryAfter = memory_get_usage(true);
$memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024;

echo "Memory used: " . round($memoryUsed, 2) . " MB\n";
echo "Peak memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
```

## Troubleshooting Queue Issues

### Check Queue Configuration
```bash
# Verify queue connection
php artisan tinker
>>> echo config('queue.default') . "\n";
>>> echo config('queue.connections.database.table') . "\n";

# Check if queue tables exist
>>> DB::table('jobs')->count();
>>> DB::table('failed_jobs')->count();
```

### Manual Job Recovery
```bash
# Retry specific failed job
php artisan queue:retry job-id-here

# Retry all failed jobs
php artisan queue:retry all

# Clear stuck jobs (use with caution)
php artisan queue:clear --queue=ingest-preview

# Restart workers
php artisan queue:restart
```

### Debug Job Execution
```php
// Create a test job with logging
class TestProcessPreviewJob extends ProcessPreviewJob
{
    public function handle(MetadataExtractor $extractor): void
    {
        Log::info('Test job starting', ['uuid' => $this->uuid]);
        
        try {
            parent::handle($extractor);
            Log::info('Test job completed', ['uuid' => $this->uuid]);
        } catch (Exception $e) {
            Log::error('Test job failed', [
                'uuid' => $this->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

// Dispatch test job
TestProcessPreviewJob::dispatch('test-uuid');
```

## Production Monitoring Setup

### Supervisor Configuration
```ini
# /etc/supervisor/conf.d/ingest-workers.conf
[program:ingest-preview-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work ingest-preview --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/preview-worker.log
stopwaitsecs=3600

[program:ingest-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work ingest --sleep=1 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
stdout_logfile=/path/to/your/project/storage/logs/ingest-worker.log
```

### Monitoring Commands
```bash
# Check supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart ingest-preview-worker:*

# View worker logs
tail -f storage/logs/preview-worker.log

# Monitor system resources
htop
iotop
```

### Health Check Endpoint
```php
// Add to routes/web.php or api.php
Route::get('/ingest/health', function () {
    return [
        'queue_status' => [
            'pending_jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'preview_queue' => DB::table('jobs')->where('queue', 'ingest-preview')->count(),
            'ingest_queue' => DB::table('jobs')->where('queue', 'ingest')->count(),
        ],
        'exiftool' => [
            'available' => app(\prophoto\Ingest\Services\ExifToolService::class)->healthCheck(),
            'version' => app(\prophoto\Ingest\Services\ExifToolService::class)->getVersion(),
        ],
        'storage' => [
            'temp_disk' => config('ingest.storage.temp_disk'),
            'temp_writable' => is_writable(storage_path('app/ingest-temp')),
        ],
        'timestamp' => now()->toISOString(),
    ];
});
```

## Testing Checklist

### Before Testing
- [ ] Queue worker running
- [ ] ExifTool installed and accessible
- [ ] Storage directories writable
- [ ] Database migrations run
- [ ] Test images available

### During Testing
- [ ] Monitor job logs
- [ ] Check memory usage
- [ ] Verify output files
- [ ] Test error scenarios
- [ ] Measure performance

### After Testing
- [ ] Clean up test jobs
- [ ] Clear temporary files
- [ ] Review logs for issues
- [ ] Document findings
- [ ] Reset queue if needed
