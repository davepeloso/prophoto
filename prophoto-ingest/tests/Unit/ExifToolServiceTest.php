<?php

use ProPhoto\Ingest\Services\ExifToolService;

beforeEach(function () {
    skipIfDisabled('unit');
});

test('exiftool service can be instantiated', function () {
    $service = new ExifToolService();

    expect($service)->toBeInstanceOf(ExifToolService::class);
});

test('health check returns boolean', function () {
    $service = new ExifToolService();
    $result = $service->healthCheck();

    expect($result)->toBeBool();
});

test('get version returns string or null', function () {
    $service = new ExifToolService();
    $version = $service->getVersion();

    expect(is_null($version) || is_string($version))->toBeTrue();
});

test('normalize metadata handles empty array', function () {
    $service = new ExifToolService();
    $result = $service->normalizeMetadata([]);

    expect($result)->toBeArray();
});

test('normalize metadata extracts date_taken from DateTimeOriginal', function () {
    $service = new ExifToolService();
    $raw = [
        'DateTimeOriginal' => '2025:10:23 12:21:28',
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('date_taken');
    expect($result['date_taken'])->toContain('2025-10-23');
});

test('normalize metadata extracts camera make and model', function () {
    $service = new ExifToolService();
    $raw = [
        'Make' => 'NIKON CORPORATION',
        'Model' => 'NIKON Z 6_2',
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('camera_make');
    expect($result)->toHaveKey('camera_model');
    expect($result)->toHaveKey('camera');
    expect($result['camera_make'])->toBe('NIKON CORPORATION');
    expect($result['camera_model'])->toBe('NIKON Z 6_2');
    expect($result['camera'])->toBe('nikon-corporation-nikon-z-6-2');
});

test('normalize metadata extracts f-stop as float', function () {
    $service = new ExifToolService();
    $raw = [
        'FNumber' => 7.1,
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('f_stop');
    expect($result['f_stop'])->toBe(7.1);
});

test('normalize metadata extracts ISO as integer', function () {
    $service = new ExifToolService();
    $raw = [
        'ISO' => 400,
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('iso');
    expect($result['iso'])->toBe(400);
});

test('normalize metadata extracts shutter speed', function () {
    $service = new ExifToolService();
    $raw = [
        'ExposureTime' => 0.05, // 1/20 second
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('shutter_speed');
    expect($result['shutter_speed'])->toBe(0.05);
});

test('normalize metadata formats shutter speed for display', function () {
    $service = new ExifToolService();
    $raw = [
        'ExposureTime' => 0.004, // ~1/250 second
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('shutter_speed_display');
    expect($result['shutter_speed_display'])->toContain('1/');
});

test('normalize metadata extracts focal length as integer', function () {
    $service = new ExifToolService();
    $raw = [
        'FocalLength' => 17.5,
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('focal_length');
    expect($result['focal_length'])->toBe(18); // Rounded
});

test('normalize metadata extracts GPS coordinates with numeric values', function () {
    $service = new ExifToolService();
    $raw = [
        'GPSLatitude' => 49.2827,
        'GPSLatitudeRef' => 'N',
        'GPSLongitude' => 123.1207,
        'GPSLongitudeRef' => 'W',
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('gps_lat');
    expect($result)->toHaveKey('gps_lng');
    expect($result['gps_lat'])->toBe(49.2827);
    expect($result['gps_lng'])->toBe(-123.1207); // W is negative
});

test('normalize metadata handles southern hemisphere GPS', function () {
    $service = new ExifToolService();
    $raw = [
        'GPSLatitude' => 33.8688,
        'GPSLatitudeRef' => 'S',
        'GPSLongitude' => 151.2093,
        'GPSLongitudeRef' => 'E',
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result['gps_lat'])->toBe(-33.8688); // S is negative
    expect($result['gps_lng'])->toBe(151.2093); // E is positive
});

test('normalize metadata extracts image dimensions', function () {
    $service = new ExifToolService();
    $raw = [
        'ImageWidth' => 4350,
        'ImageHeight' => 2894,
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('width');
    expect($result)->toHaveKey('height');
    expect($result['width'])->toBe(4350);
    expect($result['height'])->toBe(2894);
});

test('normalize metadata extracts file type info', function () {
    $service = new ExifToolService();
    $raw = [
        'FileType' => 'JPEG',
        'MIMEType' => 'image/jpeg',
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('file_type');
    expect($result)->toHaveKey('mime_type');
    expect($result['file_type'])->toBe('JPEG');
    expect($result['mime_type'])->toBe('image/jpeg');
});

test('normalize metadata handles missing fields gracefully', function () {
    $service = new ExifToolService();
    $raw = [
        'FileName' => 'test.jpg',
    ];

    $result = $service->normalizeMetadata($raw);

    // Should not throw, should return filtered result
    expect($result)->toBeArray();
    expect($result)->not->toHaveKey('date_taken');
    expect($result)->not->toHaveKey('f_stop');
});

test('normalize metadata handles shutter speed fraction string', function () {
    $service = new ExifToolService();
    $raw = [
        'ExposureTime' => '1/250',
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('shutter_speed');
    expect($result['shutter_speed'])->toBe(0.004);
});

test('normalize metadata handles date with timezone offset', function () {
    $service = new ExifToolService();
    $raw = [
        'DateTimeOriginal' => '2025:10:23 12:21:28',
        'OffsetTimeOriginal' => '-07:00',
    ];

    $result = $service->normalizeMetadata($raw);

    expect($result)->toHaveKey('date_taken');
    // Should include timezone info
    expect($result['date_taken'])->toContain('-07:00');
});

test('extract metadata returns empty array for invalid path', function () {
    $service = new ExifToolService();

    $result = $service->extractMetadata('/nonexistent/path/file.jpg');

    expect($result)->toBeArray();
    expect($result)->toBeEmpty();
});

test('extract metadata validates paths and blocks traversal', function () {
    $service = new ExifToolService();

    // Path with traversal should be rejected
    $result = $service->extractMetadata('/some/path/../../../etc/passwd');

    expect($result)->toBeArray();
    expect($result)->toBeEmpty();
});

test('extract preview returns false for invalid path', function () {
    $service = new ExifToolService();

    $result = $service->extractPreview('/nonexistent/path/file.jpg');

    expect($result)->toBeFalse();
});

test('complete metadata normalization example', function () {
    $service = new ExifToolService();

    // Simulated ExifTool output for a NIKON Z 6_2 image
    $raw = [
        'FileName' => 'AMB_0838.jpg',
        'FileSize' => 2306867,
        'FileType' => 'JPEG',
        'MIMEType' => 'image/jpeg',
        'Make' => 'NIKON CORPORATION',
        'Model' => 'NIKON Z 6_2',
        'ExposureTime' => 0.05,
        'FNumber' => 7.1,
        'ISO' => 400,
        'DateTimeOriginal' => '2025:10:23 12:21:28',
        'OffsetTimeOriginal' => '-07:00',
        'FocalLength' => 17.5,
        'LensModel' => 'NIKKOR Z 14-30mm f/4 S',
        'ExifImageWidth' => 4350,
        'ExifImageHeight' => 2894,
    ];

    $result = $service->normalizeMetadata($raw);

    // Verify all expected fields are present and correctly typed
    expect($result['camera_make'])->toBe('NIKON CORPORATION');
    expect($result['camera_model'])->toBe('NIKON Z 6_2');
    expect($result['camera'])->toBe('nikon-corporation-nikon-z-6-2');
    expect($result['f_stop'])->toBe(7.1);
    expect($result['iso'])->toBe(400);
    expect($result['shutter_speed'])->toBe(0.05);
    expect($result['focal_length'])->toBe(18);
    expect($result['lens'])->toBe('NIKKOR Z 14-30mm f/4 S');
    expect($result['width'])->toBe(4350);
    expect($result['height'])->toBe(2894);
    expect($result['file_type'])->toBe('JPEG');
    expect($result['mime_type'])->toBe('image/jpeg');
    expect($result['date_taken'])->toContain('2025-10-23');
});
