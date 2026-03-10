<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ProPhoto\Ingest\Services\MetadataExtractor;
use ProPhoto\Ingest\Services\ExifToolService;

beforeEach(function () {
    skipIfDisabled('unit');
});

test('metadata extractor can be instantiated', function () {
    $extractor = app(MetadataExtractor::class);

    expect($extractor)->toBeInstanceOf(MetadataExtractor::class);
});

test('metadata extractor has extract method', function () {
    $extractor = app(MetadataExtractor::class);

    expect(method_exists($extractor, 'extract'))->toBeTrue();
});

test('metadata extractor has extractBatch method', function () {
    $extractor = app(MetadataExtractor::class);

    expect(method_exists($extractor, 'extractBatch'))->toBeTrue();
});

test('metadata extractor has generatePreview method', function () {
    $extractor = app(MetadataExtractor::class);

    expect(method_exists($extractor, 'generatePreview'))->toBeTrue();
});

test('metadata extractor has generateThumbnail method', function () {
    $extractor = app(MetadataExtractor::class);

    expect(method_exists($extractor, 'generateThumbnail'))->toBeTrue();
});

test('metadata extractor has extractEmbeddedPreview method', function () {
    $extractor = app(MetadataExtractor::class);

    expect(method_exists($extractor, 'extractEmbeddedPreview'))->toBeTrue();
});

test('metadata extractor provides exiftool availability check', function () {
    $extractor = app(MetadataExtractor::class);

    expect(method_exists($extractor, 'isExifToolAvailable'))->toBeTrue();
    expect($extractor->isExifToolAvailable())->toBeBool();
});

test('metadata extractor provides access to exiftool service', function () {
    $extractor = app(MetadataExtractor::class);

    $service = $extractor->getExifToolService();

    expect($service)->toBeInstanceOf(ExifToolService::class);
});

test('extract returns structured result array', function () {
    $extractor = app(MetadataExtractor::class);

    // Test with a non-existent file (will still return structure)
    $result = $extractor->extract('/tmp/nonexistent-test-file.jpg');

    expect($result)->toBeArray();
    expect($result)->toHaveKey('metadata');
    expect($result)->toHaveKey('metadata_raw');
    expect($result)->toHaveKey('extraction_method');
    expect($result)->toHaveKey('error');
});

test('extract sets extraction_method correctly', function () {
    $extractor = app(MetadataExtractor::class);

    // Test with a non-existent file
    $result = $extractor->extract('/tmp/nonexistent-test-file.jpg');

    expect($result['extraction_method'])->toBeIn(['exiftool', 'php_exif', 'none']);
});

test('extractBatch returns results keyed by filename', function () {
    $extractor = app(MetadataExtractor::class);

    $paths = [
        '/tmp/nonexistent1.jpg',
        '/tmp/nonexistent2.jpg',
    ];

    $results = $extractor->extractBatch($paths);

    expect($results)->toBeArray();
    expect($results)->toHaveKey('nonexistent1.jpg');
    expect($results)->toHaveKey('nonexistent2.jpg');
});

test('extractBatch returns empty array for empty input', function () {
    $extractor = app(MetadataExtractor::class);

    $results = $extractor->extractBatch([]);

    expect($results)->toBeArray();
    expect($results)->toBeEmpty();
});

test('parseGpsCoordinates handles normalized fields', function () {
    $extractor = app(MetadataExtractor::class);

    $metadata = [
        'gps_lat' => 49.2827,
        'gps_lng' => -123.1207,
    ];

    $result = $extractor->parseGpsCoordinates($metadata);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('lat');
    expect($result)->toHaveKey('lng');
    expect($result['lat'])->toBe(49.2827);
    expect($result['lng'])->toBe(-123.1207);
});

test('parseGpsCoordinates handles legacy GPS format', function () {
    $extractor = app(MetadataExtractor::class);

    $metadata = [
        'GPSLatitude' => 49.2827,
        'GPSLatitudeRef' => 'N',
        'GPSLongitude' => 123.1207,
        'GPSLongitudeRef' => 'W',
    ];

    $result = $extractor->parseGpsCoordinates($metadata);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('lat');
    expect($result)->toHaveKey('lng');
    expect($result['lat'])->toBeGreaterThan(0); // North
    expect($result['lng'])->toBeLessThan(0); // West
});

test('parseGpsCoordinates returns null for missing GPS data', function () {
    $extractor = app(MetadataExtractor::class);

    $result = $extractor->parseGpsCoordinates([]);

    expect($result)->toBeNull();
});

test('can upload image fixture and extract metadata', function () {
    $extractor = app(MetadataExtractor::class);

    Storage::fake('test-temp');

    $fixturePath = fixture('sample-images/8300 Mariposa Court 0009.jpg');

    $file = new UploadedFile(
        $fixturePath,
        '8300 Mariposa Court 0009.jpg',
        'image/jpeg',
        null,
        true
    );

    $storedPath = Storage::disk('test-temp')->putFile('uploads', $file);

    expect($storedPath)->toBeString();

    $absolutePath = Storage::disk('test-temp')->path($storedPath);

    $result = $extractor->extract($absolutePath);

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['metadata', 'metadata_raw', 'extraction_method', 'error']);

    expect($result['metadata'])->toBeArray();
    expect($result['metadata'])->toHaveKey('FileSize');
    expect($result['metadata'])->toHaveKey('FileName');

    expect($result['metadata']['FileSize'])->toBe(filesize($absolutePath));
    expect($result['metadata']['FileName'])->toBeString();

    if (!empty($result['metadata_raw'])) {
        expect($result['metadata_raw'])->toBeArray();
    }
});
