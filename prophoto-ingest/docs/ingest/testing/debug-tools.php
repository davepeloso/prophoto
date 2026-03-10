<?php

/**
 * Debugging tools for MetadataExtractor and job system
 * 
 * Usage in Laravel Tinker:
 * include_once 'debug-tools.php';
 * 
 * $debug = new IngestDebugger();
 * $debug->debugExifTool();
 * $debug->debugLatestProxy();
 */

use ProPhoto\Ingest\Services\MetadataExtractor;
use ProPhoto\Ingest\Services\ExifToolService;
use ProPhoto\Ingest\Models\ProxyImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class IngestDebugger
{
    protected MetadataExtractor $extractor;
    protected ExifToolService $exifToolService;
    
    public function __construct()
    {
        $this->extractor = app(MetadataExtractor::class);
        $this->exifToolService = app(ExifToolService::class);
    }
    
    /**
     * Debug ExifTool installation and configuration
     */
    public function debugExifTool(): void
    {
        echo "=== ExifTool Debug Information ===\n\n";
        
        // Check health
        echo "1. Health Check:\n";
        $isHealthy = $this->exifToolService->healthCheck();
        echo "   Status: " . ($isHealthy ? "✓ HEALTHY" : "✗ UNHEALTHY") . "\n";
        
        // Get version
        $version = $this->exifToolService->getVersion();
        echo "   Version: " . ($version ?? "Unknown") . "\n";
        
        // Check binary path
        $binaryPath = config('exiftool.bin', 'exiftool');
        echo "   Binary: $binaryPath\n";
        
        // Test with a simple command
        echo "\n2. Command Test:\n";
        try {
            $reflection = new ReflectionClass($this->exifToolService);
            $method = $reflection->getMethod('execute');
            $method->setAccessible(true);
            
            $result = $method->invoke($this->exifToolService, ['-ver'], 5);
            echo "   ✓ Command executes: " . trim($result) . "\n";
        } catch (Exception $e) {
            echo "   ✗ Command failed: " . $e->getMessage() . "\n";
        }
        
        // Check configuration
        echo "\n3. Configuration:\n";
        echo "   Timeout: " . config('ingest.exiftool.timeout', 30) . "s\n";
        echo "   Speed mode: " . config('ingest.exiftool.speed_mode', 'fast') . "\n";
        echo "   Fallback to PHP: " . (config('ingest.exiftool.fallback_to_php', true) ? "Yes" : "No") . "\n";
        echo "   Max preview size: " . (config('ingest.exiftool.max_preview_size', 8*1024*1024) / 1024 / 1024) . "MB\n";
    }
    
    /**
     * Debug latest proxy image and its processing status
     */
    public function debugLatestProxy(): void
    {
        echo "\n=== Latest Proxy Debug ===\n\n";
        
        $proxy = ProxyImage::latest()->first();
        
        if (!$proxy) {
            echo "No proxy images found in database.\n";
            return;
        }
        
        echo "Proxy UUID: " . $proxy->uuid . "\n";
        echo "Filename: " . $proxy->filename . "\n";
        echo "User ID: " . $proxy->user_id . "\n";
        echo "Created: " . $proxy->created_at . "\n";
        echo "Updated: " . $proxy->updated_at . "\n";
        
        echo "\nFile Paths:\n";
        echo "  Temp path: " . $proxy->temp_path . "\n";
        echo "  Thumbnail path: " . ($proxy->thumbnail_path ?? "None") . "\n";
        echo "  Preview path: " . ($proxy->preview_path ?? "None") . "\n";
        
        echo "\nStatus:\n";
        echo "  Preview status: " . ($proxy->preview_status ?? "None") . "\n";
        echo "  Enhancement status: " . ($proxy->enhancement_status ?? "None") . "\n";
        echo "  Preview attempted: " . ($proxy->preview_attempted_at ?? "Never") . "\n";
        
        if ($proxy->preview_error) {
            echo "  Preview error: " . $proxy->preview_error . "\n";
        }
        
        if ($proxy->metadata_error) {
            echo "  Metadata error: " . $proxy->metadata_error . "\n";
        }
        
        // Check if files actually exist
        $tempDisk = config('ingest.storage.temp_disk', 'local');
        echo "\nFile Existence:\n";
        echo "  Temp file exists: " . (Storage::disk($tempDisk)->exists($proxy->temp_path) ? "✓" : "✗") . "\n";
        
        if ($proxy->thumbnail_path) {
            echo "  Thumbnail exists: " . (Storage::disk($tempDisk)->exists($proxy->thumbnail_path) ? "✓" : "✗") . "\n";
        }
        
        if ($proxy->preview_path) {
            echo "  Preview exists: " . (Storage::disk($tempDisk)->exists($proxy->preview_path) ? "✓" : "✗") . "\n";
        }
        
        // Show metadata info
        echo "\nMetadata:\n";
        echo "  Extraction method: " . ($proxy->extraction_method ?? "Unknown") . "\n";
        echo "  Metadata fields: " . count($proxy->metadata ?? []) . "\n";
        echo "  Raw metadata fields: " . count($proxy->metadata_raw ?? []) . "\n";
        
        if ($proxy->metadata) {
            $keyFields = ['date_taken', 'camera_make', 'camera_model', 'f_stop', 'iso', 'shutter_speed'];
            echo "  Key metadata:\n";
            foreach ($keyFields as $field) {
                if (isset($proxy->metadata[$field])) {
                    echo "    $field: " . $proxy->metadata[$field] . "\n";
                }
            }
        }
    }
    
    /**
     * Test metadata extraction on a specific file
     */
    public function debugFileExtraction(string $filePath): void
    {
        echo "\n=== File Extraction Debug ===\n";
        echo "File: $filePath\n\n";
        
        if (!file_exists($filePath)) {
            echo "ERROR: File not found.\n";
            return;
        }
        
        echo "File size: " . filesize($filePath) . " bytes\n";
        echo "File type: " . mime_content_type($filePath) . "\n\n";
        
        // Test fast extraction
        echo "1. Fast Extraction:\n";
        $startTime = microtime(true);
        $fastResult = $this->extractor->extractFast($filePath);
        $fastDuration = round((microtime(true) - $startTime) * 1000, 2);
        
        echo "   Duration: {$fastDuration}ms\n";
        echo "   Method: {$fastResult['extraction_method']}\n";
        echo "   Fields: " . count($fastResult['metadata']) . "\n";
        
        if ($fastResult['error']) {
            echo "   Error: {$fastResult['error']}\n";
        }
        
        // Test full extraction
        echo "\n2. Full Extraction:\n";
        $startTime = microtime(true);
        $fullResult = $this->extractor->extract($filePath);
        $fullDuration = round((microtime(true) - $startTime) * 1000, 2);
        
        echo "   Duration: {$fullDuration}ms\n";
        echo "   Method: {$fullResult['extraction_method']}\n";
        echo "   Fields: " . count($fullResult['metadata']) . "\n";
        
        if ($fullResult['error']) {
            echo "   Error: {$fullResult['error']}\n";
        }
        
        // Compare results
        echo "\n3. Comparison:\n";
        $fastFields = array_keys($fastResult['metadata']);
        $fullFields = array_keys($fullResult['metadata']);
        $missingFields = array_diff($fullFields, $fastFields);
        
        if (!empty($missingFields)) {
            echo "   Fields missing in fast mode (" . count($missingFields) . "):\n";
            foreach (array_slice($missingFields, 0, 10) as $field) {
                echo "     • $field\n";
            }
            if (count($missingFields) > 10) {
                echo "     • ... and " . (count($missingFields) - 10) . " more\n";
            }
        } else {
            echo "   ✓ Fast mode extracted all available fields\n";
        }
        
        // Test thumbnail extraction
        echo "\n4. Thumbnail Extraction:\n";
        $uuid = 'debug-' . uniqid();
        $thumbPath = $this->extractor->extractEmbeddedThumbnail($filePath, $uuid);
        
        if ($thumbPath) {
            $fullPath = Storage::disk(config('ingest.storage.temp_disk', 'local'))->path($thumbPath);
            echo "   ✓ Embedded thumbnail extracted\n";
            echo "   Path: $thumbPath\n";
            echo "   Full path: $fullPath\n";
            echo "   Size: " . filesize($fullPath) . " bytes\n";
        } else {
            echo "   ✗ No embedded thumbnail found\n";
        }
        
        // Test preview extraction
        echo "\n5. Preview Extraction:\n";
        $previewPath = $this->extractor->generatePreview($filePath, $uuid);
        
        if ($previewPath) {
            $fullPath = Storage::disk(config('ingest.storage.temp_disk', 'local'))->path($previewPath);
            echo "   ✓ Preview generated\n";
            echo "   Path: $previewPath\n";
            echo "   Full path: $fullPath\n";
            echo "   Size: " . filesize($fullPath) . " bytes\n";
        } else {
            echo "   ✗ Preview generation failed\n";
        }
    }
    
    /**
     * Debug queue jobs
     */
    public function debugQueueJobs(): void
    {
        echo "\n=== Queue Job Debug ===\n\n";
        
        // Check pending jobs
        echo "1. Pending Jobs:\n";
        $pendingJobs = DB::table('jobs')->count();
        echo "   Total pending: $pendingJobs\n";
        
        $previewJobs = DB::table('jobs')->where('queue', 'ingest-preview')->count();
        echo "   Preview queue: $previewJobs\n";
        
        $ingestJobs = DB::table('jobs')->where('queue', 'ingest')->count();
        echo "   Ingest queue: $ingestJobs\n";
        
        $enhanceJobs = DB::table('jobs')->where('queue', 'ingest-enhance')->count();
        echo "   Enhance queue: $enhanceJobs\n";
        
        // Check failed jobs
        echo "\n2. Failed Jobs:\n";
        $failedJobs = DB::table('failed_jobs')->count();
        echo "   Total failed: $failedJobs\n";
        
        if ($failedJobs > 0) {
            $recentFailures = DB::table('failed_jobs')
                ->latest('failed_at')
                ->limit(3)
                ->get(['exception', 'failed_at', 'queue']);
                
            echo "   Recent failures:\n";
            foreach ($recentFailures as $failure) {
                echo "     • {$failure->queue}: " . substr($failure->exception, 0, 100) . "...\n";
            }
        }
        
        // Show recent job activity from logs
        echo "\n3. Recent Log Activity:\n";
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logs = tail_custom($logFile, 20);
            $jobLogs = array_filter($logs, function($line) {
                return str_contains($line, 'ProcessPreviewJob') || 
                       str_contains($line, 'ProcessImageIngestJob') || 
                       str_contains($line, 'EnhancePreviewJob');
            });
            
            if (!empty($jobLogs)) {
                foreach (array_slice($jobLogs, -5) as $log) {
                    echo "   " . trim($log) . "\n";
                }
            } else {
                echo "   No recent job activity in logs\n";
            }
        }
    }
    
    /**
     * Test specific ExifTool tags
     */
    public function debugExifToolTags(string $filePath, array $tags = []): void
    {
        echo "\n=== ExifTool Tags Debug ===\n";
        echo "File: $filePath\n\n";
        
        if (empty($tags)) {
            $tags = ['Make', 'Model', 'DateTimeOriginal', 'ISO', 'FNumber', 'ExposureTime', 'FocalLength', 'LensModel'];
        }
        
        echo "Testing tags: " . implode(', ', $tags) . "\n\n";
        
        try {
            $reflection = new ReflectionClass($this->exifToolService);
            $method = $reflection->getMethod('extractMetadata');
            $method->setAccessible(true);
            
            $startTime = microtime(true);
            $rawMetadata = $method->invoke($this->exifToolService, $filePath, [
                'args' => array_map(fn($tag) => "-$tag", $tags)
            ]);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            echo "Extraction time: {$duration}ms\n\n";
            
            if ($rawMetadata) {
                echo "Results:\n";
                foreach ($tags as $tag) {
                    $value = $rawMetadata[$tag] ?? 'Not found';
                    echo "  $tag: $value\n";
                }
            } else {
                echo "No metadata extracted\n";
            }
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}

/**
 * Custom tail function for log reading
 */
function tail_custom($filename, $lines = 10) {
    if (!file_exists($filename)) {
        return [];
    }
    
    $file = fopen($filename, "r");
    $linecounter = $lines;
    $pos = -2;
    $beginning = false;
    $text = [];
    
    while ($linecounter > 0) {
        $t = " ";
        while ($t != "\n") {
            if (fseek($file, $pos, SEEK_END) == -1) {
                $beginning = true;
                break;
            }
            $t = fgetc($file);
            $pos--;
        }
        
        $linecounter--;
        if ($beginning) {
            rewind($file);
        }
        
        $text[$lines - $linecounter - 1] = fgets($file);
        
        if ($beginning) {
            break;
        }
    }
    
    fclose($file);
    return array_reverse($text);
}

// Return instance for use in Tinker
return new IngestDebugger();
