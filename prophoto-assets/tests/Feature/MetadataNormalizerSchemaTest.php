<?php

namespace ProPhoto\Assets\Tests\Feature;

use ProPhoto\Assets\Tests\TestCase;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataNormalizerContract;
use ProPhoto\Contracts\DTOs\RawMetadataBundle;

class MetadataNormalizerSchemaTest extends TestCase
{
    public function test_it_normalizes_exif_heavy_image_payload(): void
    {
        /** @var AssetMetadataNormalizerContract $normalizer */
        $normalizer = $this->app->make(AssetMetadataNormalizerContract::class);

        $normalized = $normalizer->normalize(new RawMetadataBundle(
            payload: [
                'MIMEType' => 'image/jpeg',
                'DateTimeOriginal' => '2026:03:09 10:11:12-07:00',
                'ImageWidth' => 6000,
                'ImageHeight' => 4000,
                'Make' => 'Canon',
                'Model' => 'EOS R6',
                'LensModel' => 'RF24-70mm F2.8 L IS USM',
                'ISO' => 250,
                'ExposureTime' => '1/200',
                'FNumber' => '2.8',
                'FocalLength' => '35.0 mm',
                'Keywords' => ['wedding', 'ceremony'],
                'GPSLatitude' => 37.1234,
                'GPSLongitude' => -122.2222,
            ],
            source: 'exiftool',
            toolVersion: '13.45',
            hash: 'hash-1'
        ));

        $this->assertSame('image', $normalized->payload['media_kind']);
        $this->assertSame('exif', $normalized->payload['captured_at_source']);
        $this->assertSame('embedded', $normalized->payload['timezone_source']);
        $this->assertSame(6000, $normalized->payload['dimensions']['width']);
        $this->assertSame(4000, $normalized->payload['dimensions']['height']);
        $this->assertSame('Canon', $normalized->payload['camera']['make']);
        $this->assertSame('EOS R6', $normalized->payload['camera']['model']);
        $this->assertSame(250, $normalized->payload['exposure']['iso']);
        $this->assertSame('1/200', $normalized->payload['exposure']['shutter_speed_display']);
        $this->assertEqualsWithDelta(0.005, (float) $normalized->payload['exposure']['shutter_speed_seconds'], 0.00001);
        $this->assertSame(['wedding', 'ceremony'], $normalized->payload['keywords']);
        $this->assertFalse($normalized->payload['gps']['is_redacted']);
        $this->assertSame('v1', $normalized->payload['normalization']['schema_version']);
        $this->assertSame('exiftool', $normalized->payload['source']['extractor']);
        $this->assertSame('exif', $normalized->payload['source']['source_record_kind']);
        $this->assertSame('Canon', $normalized->index['camera_make']);
        $this->assertSame(6000, $normalized->index['width']);
        $this->assertSame('image/jpeg', $normalized->index['mime_type']);
        $this->assertTrue($normalized->index['has_gps']);
    }

    public function test_it_handles_sparse_or_weird_payloads_without_throwing(): void
    {
        /** @var AssetMetadataNormalizerContract $normalizer */
        $normalizer = $this->app->make(AssetMetadataNormalizerContract::class);

        $normalized = $normalizer->normalize(new RawMetadataBundle(
            payload: [
                'unexpected_value' => ['nested' => true],
            ],
            source: 'unknown',
            hash: 'hash-2'
        ));

        $this->assertSame('other', $normalized->payload['media_kind']);
        $this->assertSame([], $normalized->payload['keywords']);
        $this->assertNull($normalized->payload['camera']['make']);
        $this->assertNull($normalized->payload['dimensions']['width']);
        $this->assertNull($normalized->payload['captured_at_source']);
        $this->assertNull($normalized->payload['timezone_source']);
        $this->assertSame('unknown', $normalized->payload['source']['source_record_kind']);
    }

    public function test_it_normalizes_pdf_payloads(): void
    {
        /** @var AssetMetadataNormalizerContract $normalizer */
        $normalizer = $this->app->make(AssetMetadataNormalizerContract::class);

        $normalized = $normalizer->normalize(new RawMetadataBundle(
            payload: [
                'MIMEType' => 'application/pdf',
                'PageCount' => '12',
                'Title' => 'Session Contract',
                'Author' => 'ProPhoto',
            ],
            source: 'pdfinfo',
            toolVersion: '1.0',
            hash: 'hash-3'
        ));

        $this->assertSame('pdf', $normalized->payload['media_kind']);
        $this->assertSame(12, $normalized->payload['document']['page_count']);
        $this->assertSame('Session Contract', $normalized->payload['document']['title']);
        $this->assertSame('ProPhoto', $normalized->payload['document']['author']);
        $this->assertSame('pdfinfo', $normalized->payload['source']['source_record_kind']);
        $this->assertSame(12, $normalized->index['page_count']);
    }

    public function test_it_normalizes_video_placeholder_payloads(): void
    {
        /** @var AssetMetadataNormalizerContract $normalizer */
        $normalizer = $this->app->make(AssetMetadataNormalizerContract::class);

        $normalized = $normalizer->normalize(new RawMetadataBundle(
            payload: [
                'MIMEType' => 'video/mp4',
                'Duration' => '12.5 s',
                'VideoFrameRate' => '29.97',
                'CompressorName' => 'H.264',
            ],
            source: 'ffprobe',
            toolVersion: '6.0',
            hash: 'hash-4'
        ));

        $this->assertSame('video', $normalized->payload['media_kind']);
        $this->assertSame(12.5, $normalized->payload['video']['duration_seconds']);
        $this->assertSame(29.97, $normalized->payload['video']['frame_rate']);
        $this->assertSame('H.264', $normalized->payload['video']['codec']);
        $this->assertSame(12.5, $normalized->index['duration_seconds']);
    }

    public function test_it_enforces_keyword_policy_rating_range_and_has_gps_semantics(): void
    {
        /** @var AssetMetadataNormalizerContract $normalizer */
        $normalizer = $this->app->make(AssetMetadataNormalizerContract::class);

        $normalized = $normalizer->normalize(new RawMetadataBundle(
            payload: [
                'MIMEType' => 'image/jpeg',
                'Keywords' => ['  Wedding ', 'wedding', 'CEREMONY', 'ceremony'],
                'Rating' => '9',
                'GPSLatitude' => 37.77,
                'GPSLongitude' => -122.41,
                'is_gps_redacted' => true,
            ],
            source: 'custom-extractor',
            hash: 'hash-5'
        ));

        // Keywords are trimmed, de-duplicated case-insensitively, and keep first-seen order/casing.
        $this->assertSame(['Wedding', 'CEREMONY'], $normalized->payload['keywords']);
        // Rating remains int|null and is constrained to 0..5.
        $this->assertSame(5, $normalized->payload['rating']);
        // has_gps tracks coordinate presence even when values are redacted for presentation.
        $this->assertTrue($normalized->payload['gps']['is_redacted']);
        $this->assertTrue($normalized->index['has_gps']);
        $this->assertSame('unknown', $normalized->payload['source']['source_record_kind']);
    }
}
