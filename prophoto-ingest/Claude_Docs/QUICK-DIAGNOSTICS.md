# Quick Diagnostics Guide

## Real-Time Monitoring Commands

### Queue Processing Status

```bash
# Watch queue in real-time
watch -n 1 'php artisan queue:monitor'

# Check failed jobs
php artisan queue:failed

# View detailed job
php artisan queue:failed --id=1

# Retry failed jobs
php artisan queue:retry 1
php artisan queue:retry all
```

### Log Monitoring

```bash
# Follow all logs
tail -f storage/logs/laravel.log

# Monitor ingest process
tail -f storage/logs/laravel.log | grep "Processing image ingest"

# Find errors
tail -f storage/logs/laravel.log | grep -i error

# Trace specific image (replace with actual UUID)
tail -f storage/logs/laravel.log | grep "550e8400-e29b-41d4-a716-446655440000"

# Watch file movements
tail -f storage/logs/laravel.log | grep "File moved successfully"

# Monitor failures
tail -f storage/logs/laravel.log | grep "Image ingest failed"

# Filter by severity
grep "\[ERROR\]" storage/logs/laravel.log
grep "\[WARNING\]" storage/logs/laravel.log
```

### Database Verification

```bash
# Check proxy images waiting to be ingested
mysql> SELECT uuid, filename, temp_path, is_culled, order_index
       FROM ingest_proxy_images
       WHERE user_id = 1
       ORDER BY order_index;

# Check final images created
mysql> SELECT id, file_name, file_path, disk, camera_make,
              camera_model, date_taken, iso, f_stop
       FROM ingest_images
       ORDER BY created_at DESC LIMIT 10;

# Check image tags
mysql> SELECT i.id, i.file_name, GROUP_CONCAT(t.name) as tags
       FROM ingest_images i
       LEFT JOIN ingest_image_tag it ON i.id = it.image_id
       LEFT JOIN ingest_tags t ON it.tag_id = t.id
       GROUP BY i.id LIMIT 10;

# Check failed queue jobs
mysql> SELECT id, payload, exception FROM failed_jobs
       ORDER BY failed_at DESC LIMIT 5;

# Count images at each stage
mysql> SELECT
         (SELECT COUNT(*) FROM ingest_proxy_images WHERE user_id=1) as proxy_count,
         (SELECT COUNT(*) FROM ingest_images) as final_count,
         (SELECT COUNT(*) FROM jobs WHERE queue='default') as queued_jobs,
         (SELECT COUNT(*) FROM failed_jobs) as failed_jobs;
```

### Storage Verification

```bash
# Check temp directory
ls -lah storage/app/public/ingest-temp/
ls -lah storage/app/public/ingest-temp/thumbs/
ls -lah storage/app/public/ingest-temp/previews/

# Check final directory structure
ls -lah storage/app/shoots/

# Check disk usage
du -sh storage/app/
df -h storage/

# Verify file ownership/permissions
ls -l storage/app/shoots/2025/01/

# Find large files
find storage/app -type f -size +50M -exec ls -lh {} \;

# Check for orphaned files (temp files older than 48 hours)
find storage/app/public/ingest-temp -type f -mtime +2
```

---

## Step-by-Step Ingest Test

### 1. Upload File

```bash
# Create test image
convert -size 640x480 xc:blue test-image.jpg

# Get auth token (adjust as needed)
TOKEN=$(curl -s -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}' | jq -r .token)

# Upload file
RESPONSE=$(curl -X POST http://localhost/ingest/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@test-image.jpg")

echo "$RESPONSE" | jq .

# Extract UUID
UUID=$(echo "$RESPONSE" | jq -r '.photo.uuid')
echo "UUID: $UUID"
```

### 2. Check Proxy Image

```bash
# Verify proxy created
mysql> SELECT uuid, filename, temp_path, metadata FROM ingest_proxy_images
       WHERE uuid = '$UUID';

# Check metadata extraction
mysql> SELECT COUNT(*) as metadata_keys FROM ingest_proxy_images
       WHERE uuid = '$UUID' AND metadata IS NOT NULL;
```

### 3. Verify Files

```bash
# Check temp file exists
ls -lh storage/app/public/ingest-temp/$UUID.*

# Check thumbnail exists
ls -lh storage/app/public/ingest-temp/thumbs/$UUID.jpg

# Check preview exists
ls -lh storage/app/public/ingest-temp/previews/$UUID.jpg
```

### 4. Trigger Ingest

```bash
# Start ingest
curl -X POST http://localhost/ingest/ingest \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{
    \"ids\": [\"$UUID\"],
    \"association\": {\"type\": \"Shoot\", \"id\": 1}
  }"
```

### 5. Check Queue

```bash
# See queued job
mysql> SELECT id, payload FROM jobs WHERE queue='default' LIMIT 1;

# Count pending jobs
mysql> SELECT COUNT(*) as pending_jobs FROM jobs;

# View failed jobs
mysql> SELECT * FROM failed_jobs;
```

### 6. Process Queue

```bash
# Run queue worker (single job mode for testing)
php artisan queue:work --once

# Or continuous processing
php artisan queue:work --timeout=300 --tries=3

# Check for output
# Should see: "Processing image ingest"
# Then: "File moved successfully"
# Then: "Image record created"
# Then: "Proxy cleanup completed"
```

### 7. Verify Final Result

```bash
# Check final image created
mysql> SELECT id, file_name, file_path, disk,
              camera_make, camera_model, date_taken, iso, f_stop, focal_length
       FROM ingest_images
       WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE);

# Check file moved
ls -lh storage/app/shoots/2025/01/*/

# Check temp cleaned up
ls -lah storage/app/public/ingest-temp/ | wc -l
# Should be very few files

# Check proxy deleted
mysql> SELECT COUNT(*) FROM ingest_proxy_images WHERE uuid = '$UUID';
# Should be 0
```

---

## Common Issues & Quick Fixes

### Issue: Files not moving to final location

**Diagnosis:**
```bash
# Check logs
grep "File moved successfully" storage/logs/laravel.log
grep "Source file not found" storage/logs/laravel.log
grep "Failed to move file" storage/logs/laravel.log

# Check disks configured
grep -A 5 "storage.*temp_disk\|final_disk" config/ingest.php
grep INGEST_DISK .env

# Check permissions
ls -ld storage/app/
ls -ld storage/app/public/
ls -ld storage/app/ingest-temp/
```

**Quick Fix:**
```bash
# Ensure directories are writable
chmod 755 storage/app/
chmod 755 storage/app/public/
mkdir -p storage/app/ingest-temp
mkdir -p storage/app/shoots/2025
chmod 755 storage/app/ingest-temp
chmod 755 storage/app/shoots

# Verify with test file
touch storage/app/shoots/test.txt
rm storage/app/shoots/test.txt
```

### Issue: Queue jobs not processing

**Diagnosis:**
```bash
# Check queue connection
grep QUEUE_CONNECTION .env

# Check if jobs exist
mysql> SELECT COUNT(*) FROM jobs;

# Check if worker is running
ps aux | grep "queue:work"

# Check for PHP errors
php -S localhost:8000
# Try uploading and ingesting through browser
```

**Quick Fix:**
```bash
# Start queue worker manually
php artisan queue:work --timeout=300 --tries=3

# Or in background
php artisan queue:work --timeout=300 --tries=3 &

# Or with supervisor (production)
cat /etc/supervisor/conf.d/ingest.conf
```

### Issue: Metadata not parsed

**Diagnosis:**
```bash
# Check EXIF was extracted
mysql> SELECT uuid, JSON_KEYS(metadata) as exif_keys
       FROM ingest_proxy_images LIMIT 1;

# Check metadata columns are populated
mysql> SELECT
         COUNT(*) as total_images,
         SUM(IF(camera_make IS NOT NULL, 1, 0)) as has_camera,
         SUM(IF(date_taken IS NOT NULL, 1, 0)) as has_date,
         SUM(IF(iso IS NOT NULL, 1, 0)) as has_iso
       FROM ingest_images;

# Check if file has EXIF
file test-image.jpg
exiftool test-image.jpg | head -20
```

**Quick Fix:**
```bash
# Ensure Imagick is installed
php -i | grep imagick

# If missing, install
sudo apt-get install imagemagick php-imagick

# Restart PHP-FPM or web server
sudo systemctl restart php8.2-fpm

# Retry ingest
php artisan queue:work --once
```

### Issue: GPS coordinates are NULL

**Diagnosis:**
```bash
# Check if GPS exists in metadata
mysql> SELECT uuid, JSON_EXTRACT(metadata, '$.GPSLatitude') as lat
       FROM ingest_proxy_images LIMIT 1;

# Check if parsing happens
grep "GPS parsing" storage/logs/laravel.log
```

**Quick Fix:**
```bash
# If GPS not in EXIF, it's not available in file
exiftool test-image.jpg | grep GPS

# For testing, use geotagged image
# Or add GPS via exiftool
exiftool -GPSLatitude="40 42 46" -GPSLatitudeRef=N test-image.jpg
```

### Issue: Queue timeouts

**Diagnosis:**
```bash
# Check job timeout setting
grep "timeout.*=" src/Jobs/ProcessImageIngestJob.php

# Check actual processing time
grep "Processing image ingest" storage/logs/laravel.log
grep "Proxy cleanup completed" storage/logs/laravel.log
# Calculate time difference between these logs

# Check image size
ls -lh storage/app/public/ingest-temp/
```

**Quick Fix:**
```bash
# Increase timeout in ProcessImageIngestJob
// From
public int $timeout = 300; // 5 minutes

// To
public int $timeout = 600; // 10 minutes

# Increase PHP timeout
php_value max_execution_time 600
```

---

## Performance Baseline

Before/After benchmarking:

```bash
# Measure ingest speed
time php artisan queue:work --once

# Or test multiple images
for i in {1..10}; do
  curl -X POST http://localhost/ingest/upload \
    -H "Authorization: Bearer $TOKEN" \
    -F "file=@test-image.jpg"
done

# Watch processing
watch -n 1 'mysql -e "SELECT COUNT(*) as queued FROM jobs; SELECT COUNT(*) as final FROM ingest_images;"'

# Monitor logs
tail -f storage/logs/laravel.log | grep "Image ingest completed"
```

---

## Health Check Script

Create a cron job to monitor health:

```bash
#!/bin/bash
# health-check.sh

echo "=== Ingest System Health Check ==="

# Check queue status
QUEUED=$(php artisan queue:monitor 2>/dev/null | grep 'queued' | awk '{print $1}')
echo "Queued jobs: $QUEUED"

if [ "$QUEUED" -gt 100 ]; then
  echo "⚠️  WARNING: Over 100 jobs queued!"
fi

# Check failed jobs
FAILED=$(mysql -se "SELECT COUNT(*) FROM failed_jobs;")
echo "Failed jobs: $FAILED"

if [ "$FAILED" -gt 0 ]; then
  echo "⚠️  WARNING: Failed jobs detected!"
fi

# Check disk space
USAGE=$(df storage/app | tail -1 | awk '{print $5}' | sed 's/%//')
echo "Disk usage: $USAGE%"

if [ "$USAGE" -gt 90 ]; then
  echo "🚨 CRITICAL: Disk over 90%!"
fi

# Check recent errors
ERRORS=$(grep "\[ERROR\]" storage/logs/laravel.log | tail -5)
if [ -n "$ERRORS" ]; then
  echo "Recent errors:"
  echo "$ERRORS"
fi
```

Run with cron:
```bash
*/15 * * * * /path/to/health-check.sh >> /var/log/ingest-health.log 2>&1
```

---

## Emergency Procedures

### Clear Stuck Jobs

```bash
# View job details
mysql> SELECT id, payload FROM failed_jobs LIMIT 1;

# Delete all failed jobs
php artisan queue:flush

# Or retry specific job
php artisan queue:retry 1
```

### Reset to Clean State

```bash
# Clear all queued jobs (BE CAREFUL!)
mysql> DELETE FROM jobs;

# Clear failed jobs
mysql> DELETE FROM failed_jobs;

# Clear temp proxy records (orphaned)
mysql> DELETE FROM ingest_proxy_images
       WHERE created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR);

# Clean temp files
rm -rf storage/app/public/ingest-temp/*
```

### Rollback Failed Ingest

```bash
# If image was partially created, delete it
mysql> DELETE FROM ingest_images WHERE created_at > '2025-01-15 10:00:00';

# Delete temp files if they weren't cleaned
rm -rf storage/app/shoots/2025/01/

# Restart queue worker
php artisan queue:restart
```

---

## Testing Commands Summary

```bash
# Quick test all stages
TEST_UUID=$(curl -s -X POST http://localhost/ingest/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@test.jpg" | jq -r '.photo.uuid') && \
echo "Uploaded: $TEST_UUID" && \
curl -s -X POST http://localhost/ingest/ingest \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"ids\":[\"$TEST_UUID\"]}" && \
php artisan queue:work --once && \
mysql -e "SELECT COUNT(*) as final_images FROM ingest_images; SELECT COUNT(*) as remaining_proxies FROM ingest_proxy_images;"
```

---

## Log Analysis

```bash
# Summary of today's ingests
grep "Image ingest completed successfully" storage/logs/laravel.log | wc -l

# Summary of today's failures
grep "Image ingest failed" storage/logs/laravel.log | wc -l

# Failed ingests by reason
grep "Image ingest failed" storage/logs/laravel.log | jq .error | sort | uniq -c

# Average processing time (rough estimate)
grep -E "Processing image ingest|Image ingest completed successfully" storage/logs/laravel.log | head -2
```

This guide provides quick diagnostics for all common issues and helps validate the ingest pipeline.
