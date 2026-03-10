<?php

namespace ProPhoto\Assets\Tests\Feature;

use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Models\AssetMetadataNormalized;
use ProPhoto\Assets\Models\AssetMetadataRaw;
use ProPhoto\Assets\Tests\TestCase;

class RenormalizeAssetsMetadataCommandTest extends TestCase
{
    public function test_it_rebuilds_normalized_records_from_latest_raw_metadata(): void
    {
        $asset = Asset::query()->create([
            'studio_id' => '20',
            'type' => 'jpeg',
            'original_filename' => 'renorm.jpg',
            'mime_type' => 'image/jpeg',
            'bytes' => 1000,
            'checksum_sha256' => str_repeat('a', 64),
            'storage_driver' => 'local',
            'storage_key_original' => '20/assets/renorm.jpg',
            'logical_path' => 'renorm',
            'status' => 'ready',
        ]);

        AssetMetadataRaw::query()->create([
            'asset_id' => $asset->id,
            'source' => 'exiftool',
            'tool_version' => '13.45',
            'extracted_at' => now(),
            'payload' => [
                'MIMEType' => 'image/jpeg',
                'ImageWidth' => 3024,
                'ImageHeight' => 4032,
                'ISO' => 400,
                'Make' => 'Canon',
                'LensModel' => 'RF 28-70',
            ],
            'payload_hash' => 'raw-hash-1',
            'metadata' => [],
        ]);

        $this->assertSame(0, AssetMetadataNormalized::query()->count());

        $this->artisan('prophoto-assets:renormalize')
            ->assertExitCode(0);

        $record = AssetMetadataNormalized::query()->where('asset_id', $asset->id)->first();
        $this->assertNotNull($record);
        $this->assertSame(3024, $record->width);
        $this->assertSame(4032, $record->height);
        $this->assertSame(400, $record->iso);
        $this->assertSame('Canon', $record->camera_make);
        $this->assertSame('RF 28-70', $record->lens);
        $this->assertSame('image', $record->media_kind);
        $this->assertSame('image/jpeg', $record->mime_type);
        $this->assertFalse((bool) $record->has_gps);
    }
}
