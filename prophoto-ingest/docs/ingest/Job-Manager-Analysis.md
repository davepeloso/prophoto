# Queue & Job Management Guide

This guide covers running, stopping, and managing jobs in the prophoto-ingest package, including integration with the prophoto-debug package for monitoring.

---

## Queue Worker Commands

### Start Queue Workers

```bash
# Start worker for all queues (foreground)
php artisan queue:work

# Start worker for specific queue
php artisan queue:work --queue=ingest

# Start worker for multiple queues with priority
php artisan queue:work --queue=ingest,ingest-preview,ingest-enhance

# Start worker with memory limit (MB)
php artisan queue:work --memory=512

# Start worker with job timeout (seconds)
php artisan queue:work --timeout=300

# Process a single job then exit (useful for debugging)
php artisan queue:work --once

# Stop after processing current job if queue is empty
php artisan queue:work --stop-when-empty
```

### Stop Queue Workers

```bash
# Gracefully restart workers (finish current job, then restart)
# Use after deploying code changes
php artisan queue:restart

# Force stop all workers (immediate - may lose current job)
pkill -f "queue:work"

# Stop specific worker by PID
kill -SIGTERM <pid>
```

---

## Job Management

### Monitor Jobs

```bash
# List failed jobs
php artisan queue:failed

# Count pending jobs (database driver)
php artisan tinker --execute="echo DB::table('jobs')->count();"

# Count failed jobs
php artisan tinker --execute="echo DB::table('failed_jobs')->count();"

# Monitor ingest-specific jobs
php artisan tinker --execute="echo DB::table('jobs')->where('queue', 'like', '%ingest%')->count();"
```

### Retry & Clear Jobs

```bash
# Retry all failed jobs
php artisan queue:retry all

# Retry specific failed job by ID
php artisan queue:retry <job-id>

# Retry failed jobs from a specific queue
php artisan queue:retry --queue=ingest

# Clear all jobs from default queue
php artisan queue:clear

# Clear specific queue
php artisan queue:clear ingest

# Delete a specific failed job
php artisan queue:forget <job-id>

# Flush ALL failed jobs (⚠️ destructive)
php artisan queue:flush
```

---

## Ingest-Specific Job Types

### ProcessPreviewJob

- **Purpose**: Generates high-quality preview and thumbnail from uploaded file
- **Queue**: `ingest-preview`
- **Priority**: Medium - runs async after upload completes
- **Triggered By**: Automatically dispatched after file upload
- **Outputs**: 2048px preview + 400x400 thumbnail

### ProcessImageIngestJob

- **Purpose**: Final processing - moves file to permanent storage with metadata
- **Queue**: `ingest` (default)
- **Priority**: High - processes confirmed/selected images
- **Triggered By**: User confirms image selection
- **Outputs**: Permanent `Image` record, file in final storage location

### EnhancePreviewJob

- **Purpose**: On-demand preview quality upgrade (user-triggered)
- **Queue**: `ingest-enhance`
- **Priority**: Low - non-critical enhancement
- **Triggered By**: User clicks "Enhance" button
- **Outputs**: Higher resolution preview

---

## Queue Configuration

### Environment Setup

```bash
# Set queue driver in .env
QUEUE_CONNECTION=database    # Options: sync, database, redis, sqs

# For database driver, create the jobs table
php artisan queue:table
php artisan migrate

# Create failed_jobs table
php artisan queue:failed-table
php artisan migrate
```

### Queue Priority Order

When running a single worker for multiple queues, process in priority order:

```bash
# Higher priority queues listed first
php artisan queue:work --queue=ingest,ingest-preview,ingest-enhance
```

| Queue | Priority | Job Type |
|-------|----------|----------|
| `ingest` | High | Final image processing |
| `ingest-preview` | Medium | Preview generation |
| `ingest-enhance` | Low | Enhancement requests |

---

## Integration with prophoto-debug

The `prophoto-debug` package provides a Filament dashboard for monitoring queue health and job tracing.

### Filament Debug Dashboard

Access at: `/admin/ingest-traces-page`

**System Health Bar** displays real-time status:

| Badge | Color | Meaning |
|-------|-------|---------|
| Processing | Green | Jobs actively being processed |
| Idle | Blue | Queue empty, worker waiting |
| Stalled | Red | Jobs pending >30s, worker may be stopped |
| Pending | Yellow | Jobs just arrived, checking status |
| Unknown | Gray | Cannot determine status |

**Additional Indicators**:
- Pending job count
- Ingest-specific pending jobs
- Failed job count
- Queue driver type
- Auto-refresh every 10 seconds

### QueueStatusService

Programmatically check queue health:

```php
use ProPhoto\Debug\Services\QueueStatusService;

$status = app(QueueStatusService::class)->getStatus();

// Returns:
// [
//     'horizon' => ['installed' => bool, 'status' => 'running|inactive'],
//     'jobs' => [
//         'driver' => 'database',
//         'pending' => 5,
//         'reserved' => 1,
//         'ingest_pending' => 3,
//         'failed' => 0,
//     ],
//     'worker' => [
//         'status' => 'processing|idle|stalled|pending|unknown',
//         'likely_running' => true,
//         'hint' => 'Jobs are being processed',
//     ],
// ]

// Clear cached status (5-second cache)
app(QueueStatusService::class)->clearCache();
```

### Ingest Traces

Every job processing attempt is logged with detailed timing:

```php
// View traces for a specific upload
$traces = \ProPhoto\Debug\Models\IngestTrace::where('uuid', $uuid)->get();

// Each trace includes:
// - trace_type: preview_extraction, thumbnail_generation, metadata_extraction
// - method_tried: exiftool_preview, ImageManager_fallback, from_preview
// - method_order: 1, 2, 3 (attempt order)
// - success: true/false
// - failure_reason: error message if failed
// - result_info: ['duration_ms' => 142, 'size' => 34654]
```

---

## Worker Monitoring

### Check Running Workers

```bash
# List queue worker processes
ps aux | grep "queue:work"

# Watch queue activity in real-time
watch -n 2 'php artisan tinker --execute="echo \"Pending: \" . DB::table(\"jobs\")->count() . \" | Failed: \" . DB::table(\"failed_jobs\")->count();"'

# Monitor Laravel logs for job activity
tail -f storage/logs/laravel.log | grep -E "ProcessPreviewJob|ProcessImageIngestJob|EnhancePreviewJob|Job"
```

### Debug Single Job

```bash
# Process one job with verbose output
php artisan queue:work --once -v

# Process one job from specific queue
php artisan queue:work --queue=ingest-preview --once -v
```

---

## Troubleshooting

### Common Issues

**Jobs not processing:**
```bash
# 1. Check if worker is running
ps aux | grep "queue:work"

# 2. Check for pending jobs
php artisan tinker --execute="echo DB::table('jobs')->count();"

# 3. Start a worker
php artisan queue:work --queue=ingest,ingest-preview,ingest-enhance
```

**Jobs failing immediately:**
```bash
# Check failed jobs table
php artisan queue:failed

# View specific failure details
php artisan tinker --execute="DB::table('failed_jobs')->latest()->first();"

# Retry after fixing the issue
php artisan queue:retry all
```

**Worker not picking up code changes:**
```bash
# Always restart after deployment
php artisan queue:restart
```

**Memory issues:**
```bash
# Set memory limit and let worker restart periodically
php artisan queue:work --memory=256 --max-jobs=100
```

### Performance Tuning

```bash
# Run multiple workers in parallel (separate terminals or supervisor)
php artisan queue:work --queue=ingest &
php artisan queue:work --queue=ingest-preview &
php artisan queue:work --queue=ingest-enhance &

# Set appropriate timeouts for image processing
php artisan queue:work --timeout=120 --sleep=3

# Limit jobs before worker restart (prevents memory leaks)
php artisan queue:work --max-jobs=500 --max-time=3600
```

---

## Production Deployment

### Supervisor Configuration

```ini
# /etc/supervisor/conf.d/prophoto-ingest.conf

[program:prophoto-ingest]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --queue=ingest,ingest-preview,ingest-enhance --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

### Supervisor Commands

```bash
# Reload configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start/stop workers
sudo supervisorctl start prophoto-ingest:*
sudo supervisorctl stop prophoto-ingest:*
sudo supervisorctl restart prophoto-ingest:*

# Check status
sudo supervisorctl status
```

### Laravel Horizon (Optional)

If using Redis queue driver, Laravel Horizon provides a dashboard:

```bash
# Install Horizon
composer require laravel/horizon

# Publish configuration
php artisan horizon:install

# Start Horizon
php artisan horizon

# Access dashboard at /horizon
```

The prophoto-debug package automatically detects Horizon and displays its status in the System Health bar.

---

## Development vs Production

### Development

```bash
# Use sync driver for immediate feedback
QUEUE_CONNECTION=sync

# Or process jobs one at a time for debugging
php artisan queue:work --once -v

# Test specific job manually
php artisan tinker
>>> $proxy = \ProPhoto\Ingest\Models\ProxyImage::first();
>>> dispatch(new \ProPhoto\Ingest\Jobs\ProcessPreviewJob($proxy->uuid));
```

### Production

```bash
# Always use async driver
QUEUE_CONNECTION=database  # or redis

# Use supervisor for process management
sudo supervisorctl start prophoto-ingest:*

# Set appropriate limits
php artisan queue:work --memory=512 --timeout=300 --tries=3 --max-jobs=1000

# Monitor via debug dashboard
# /admin/ingest-traces-page
```

---

## Emergency Procedures

### Clear All Stuck Jobs (⚠️ Use with caution)

```bash
# Clear ALL pending jobs
php artisan queue:clear

# Clear ALL failed jobs
php artisan queue:flush

# Restart workers
php artisan queue:restart
```

### Reset Stuck Processing Jobs

```bash
# Jobs stuck in "reserved" state (worker died mid-processing)
php artisan tinker
>>> DB::table('jobs')->whereNotNull('reserved_at')->update(['reserved_at' => null, 'attempts' => 0]);
```

### Force Retry All Failed Jobs

```bash
php artisan queue:retry all
```

---

## Best Practices

1. **Always restart workers after deployment**: `php artisan queue:restart`
2. **Use supervisor in production**: Ensures workers restart on failure
3. **Set memory limits**: Prevents runaway memory usage
4. **Monitor failed jobs**: Check `/admin/ingest-traces-page` regularly
5. **Use job timeouts**: Prevent stuck jobs from blocking the queue
6. **Separate queues by priority**: Process critical jobs first
7. **Log job activity**: Enable debug logging during development

---

*Last updated: 2026-01-05*
