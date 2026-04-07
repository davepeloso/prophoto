<?php

/**
 * Testing script for MetadataExtractor extractFast with different ExifTool configurations
 * 
 * Run this with: php test-extract-fast.php
 * Or in Tinker: include_once 'test-extract-fast.php';
 */

require_once __DIR__ . '/vendor/autoload.php';

use ProPhoto\Ingest\Services\MetadataExtractor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ExtractFastTester
{
    protected MetadataExtractor $extractor;
    
    public function __construct()
    {
        // Bootstrap Laravel if needed
        if (!function_exists('app')) {
            $app = require_once __DIR__ . '/bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }
        
        $this->extractor = app(MetadataExtractor::class);
    }
    
    /**
     * Test extractFast with different ExifTool speed modes and options
     */
    public function testExtractFastVariations(string $imagePath): void
    {
        echo "=== Testing extractFast variations ===\n";
        echo "Image: $imagePath\n\n";
        
        if (!file_exists($imagePath)) {
            echo "ERROR: File not found: $imagePath\n";
            return;
        }
        
        // Test 1: Default fast2 mode
        echo "1. Testing default fast2 mode:\n";
        $result1 = $this->extractor->extractFast($imagePath);
        $this->printResult($result1);
        
        // Test 2: Test with custom ExifTool options
        echo "\n2. Testing with specific tags only:\n";
        $result2 = $this->testCustomTags($imagePath);
        $this->printResult($result2);
        
        // Test 3: Test with different speed modes
        echo "\n3. Testing different speed modes:\n";
        $this->testSpeedModes($imagePath);
        
        // Test 4: Test thumbnail extraction
        echo "\n4. Testing thumbnail extraction:\n";
        $this->testThumbnailExtraction($imagePath);
    }
    
    /**
     * Test extractFast with specific ExifTool tags
     */
    protected function testCustomTags(string $imagePath): array
    {
        // Temporarily modify ExifTool service to use specific tags
        $exifToolService = $this->extractor->getExifToolService();
        
        // Test with limited tags for faster extraction
        try {
            $startTime = microtime(true);
            
            // Use reflection to access protected method for testing
            $reflection = new ReflectionClass($exifToolService);
            $method = $reflection->getMethod('extractMetadata');
            $method->setAccessible(true);
            
            $rawMetadata = $method->invoke($exifToolService, $imagePath, [
                'args' => ['-Make', '-Model', '-DateTimeOriginal', '-ISO', '-FNumber']
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($rawMetadata) {
                $normalized = $exifToolService->normalizeMetadata($rawMetadata);
                
                return [
                    'metadata' => $normalized,
                    'metadata_raw' => $rawMetadata,
                    'extraction_method' => 'exiftool_custom',
                    'error' => null,
                    'duration_ms' => $duration,
                ];
            }
        } catch (Exception $e) {
            return [
                'metadata' => [],
                'metadata_raw' => null,
                'extraction_method' => 'none',
                'error' => $e->getMessage(),
            ];
        }
        
        return ['metadata' => [], 'extraction_method' => 'none'];
    }
    
    /**
     * Test different ExifTool speed modes
     */
    protected function testSpeedModes(string $imagePath): void
    {
        $exifToolService = $this->extractor->getExifToolService();
        
        $speedModes = ['fast', 'fast2', 'full'];
        
        foreach ($speedModes as $mode) {
            echo "   Testing $mode mode: ";
            
            try {
                $startTime = microtime(true);
                
                $reflection = new ReflectionClass($exifToolService);
                $method = $reflection->getMethod('extractMetadata');
                $method->setAccessible(true);
                
                $rawMetadata = $method->invoke($exifToolService, $imagePath, [
                    'speed_mode' => $mode
                ]);
                
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $fieldCount = is_array($rawMetadata) ? count($rawMetadata) : 0;
                
                echo "{$duration}ms, {$fieldCount} fields\n";
                
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Test thumbnail extraction and output locations
     */
    protected function testThumbnailExtraction(string $imagePath): void
    {
        $uuid = 'test-' . uniqid();
        
        echo "   Testing embedded thumbnail extraction:\n";
        
        // Test embedded thumbnail extraction
        $thumbnailPath = $this->extractor->extractEmbeddedThumbnail($imagePath, $uuid);
        
        if ($thumbnailPath) {
            $fullPath = Storage::disk(config('ingest.storage.temp_disk', 'local'))->path($thumbnailPath);
            echo "     ✓ Thumbnail extracted to: $thumbnailPath\n";
            echo "     ✓ Full path: $fullPath\n";
            echo "     ✓ File size: " . filesize($fullPath) . " bytes\n";
            
            // Show where to find it
            $this->showImageLocation($fullPath, 'thumbnail');
        } else {
            echo "     ✗ No embedded thumbnail found\n";
        }
        
        // Test generated thumbnail
        echo "   Testing generated thumbnail:\n";
        $generatedThumb = $this->extractor->generateThumbnail($imagePath, $uuid . '-gen');
        
        if ($generatedThumb) {
            $fullPath = Storage::disk(config('ingest.storage.temp_disk', 'local'))->path($generatedThumb);
            echo "     ✓ Generated thumbnail: $generatedThumb\n";
            echo "     ✓ Full path: $fullPath\n";
            echo "     ✓ File size: " . filesize($fullPath) . " bytes\n";
            
            $this->showImageLocation($fullPath, 'generated thumbnail');
        } else {
            echo "     ✗ Failed to generate thumbnail\n";
        }
    }
    
    /**
     * Show where images are stored and how to view them
     */
    protected function showImageLocation(string $fullPath, string $type): void
    {
        echo "\n     📁 How to view this $type:\n";
        echo "        • File path: $fullPath\n";
        echo "        • URL (if public): " . $this->getPublicUrl($fullPath) . "\n";
        echo "        • Open with: open \"$fullPath\"\n";
        echo "        • Preview with: ql \"$fullPath\" (macOS QuickLook)\n";
    }
    
    /**
     * Get public URL for file if accessible
     */
    protected function getPublicUrl(string $fullPath): string
    {
        $tempDisk = config('ingest.storage.temp_disk', 'local');
        $tempPath = config('ingest.storage.temp_path', 'ingest-temp');
        
        // Convert full path to relative path
        if (str_contains($fullPath, $tempPath)) {
            $relativePath = str_replace(storage_path(), '', $fullPath);
            return url($relativePath);
        }
        
        return 'Not publicly accessible';
    }
    
    /**
     * Print extraction result details
     */
    protected function printResult(array $result): void
    {
        echo "   Method: {$result['extraction_method']}\n";
        echo "   Fields extracted: " . count($result['metadata']) . "\n";
        echo "   Error: " . ($result['error'] ?? 'None') . "\n";
        
        if (!empty($result['metadata'])) {
            echo "   Key fields:\n";
            $keyFields = ['date_taken', 'camera_make', 'camera_model', 'f_stop', 'iso', 'shutter_speed'];
            
            foreach ($keyFields as $field) {
                if (isset($result['metadata'][$field])) {
                    echo "     • $field: {$result['metadata'][$field]}\n";
                }
            }
        }
        
        if (isset($result['duration_ms'])) {
            echo "   Duration: {$result['duration_ms']}ms\n";
        }
    }
    
    /**
     * Test with sample images
     */
    public function testWithSamples(): void
    {
        echo "=== Testing with sample images ===\n\n";
        
        // Look for test images in common locations
        $samplePaths = [
            __DIR__ . '/storage/app/ingest-temp',
            __DIR__ . '/tests/fixtures',
            __DIR__ . '/sample-images',
        ];
        
        $foundImages = [];
        foreach ($samplePaths as $path) {
            if (is_dir($path)) {
                $files = glob($path . '/*.{jpg,jpeg,png,tiff,cr2,nef,arw}', GLOB_BRACE);
                $foundImages = array_merge($foundImages, $files);
            }
        }
        
        if (empty($foundImages)) {
            echo "No sample images found. Please place a test image in one of:\n";
            foreach ($samplePaths as $path) {
                echo "  • $path\n";
            }
            return;
        }
        
        // Test first few images
        $testImages = array_slice($foundImages, 0, 3);
        
        foreach ($testImages as $imagePath) {
            echo "\n--- Testing: " . basename($imagePath) . " ---\n";
            $this->testExtractFastVariations($imagePath);
        }
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $tester = new ExtractFastTester();
    
    if (isset($argv[1])) {
        // Test specific file
        $tester->testExtractFastVariations($argv[1]);
    } else {
        // Test with sample images
        $tester->testWithSamples();
    }
}

return new ExtractFastTester();
