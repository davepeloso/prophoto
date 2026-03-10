<?php

namespace ProPhoto\Assets\Tests\Feature;

use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Models\AssetMetadataNormalized;
use ProPhoto\Assets\Models\AssetMetadataRaw;
use ProPhoto\Assets\Tests\TestCase;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\MetadataProvenance;
use ProPhoto\Contracts\DTOs\NormalizedAssetMetadata;
use ProPhoto\Contracts\DTOs\RawMetadataBundle;

class AssetMetadataLifecycleTest extends TestCase
{
    public function test_raw_metadata_records_are_append_only_and_normalized_is_schema_versioned(): void
    {
        $asset = Asset::query()->create([
            'studio_id' => '10',
            'type' => 'jpeg',
            'original_filename' => 'lifecycle.jpg',
            'mime_type' => 'image/jpeg',
            'bytes' => 1000,
            'checksum_sha256' => str_repeat('f', 64),
            'storage_driver' => 'local',
            'storage_key_original' => '10/assets/lifecycle.jpg',
            'logical_path' => 'lifecycle',
            'status' => 'ready',
        ]);

        /** @var AssetMetadataRepositoryContract $repository */
        $repository = $this->app->make(AssetMetadataRepositoryContract::class);
        $assetId = AssetId::from($asset->id);

        $repository->storeRaw(
            $assetId,
            new RawMetadataBundle(payload: ['ISO' => 100], source: 'exiftool', hash: 'raw-1'),
            new MetadataProvenance(source: 'exiftool', toolVersion: '13.0', recordedAt: now()->toISOString())
        );

        $repository->storeRaw(
            $assetId,
            new RawMetadataBundle(payload: ['ISO' => 200], source: 'exiftool', hash: 'raw-2'),
            new MetadataProvenance(source: 'exiftool', toolVersion: '13.0', recordedAt: now()->toISOString())
        );

        $this->assertSame(2, AssetMetadataRaw::query()->where('asset_id', $asset->id)->count());

        $repository->storeNormalized(
            $assetId,
            new NormalizedAssetMetadata(
                schemaVersion: 'v1',
                payload: ['iso' => 100],
                index: ['iso' => 100, 'media_kind' => 'image', 'has_gps' => false]
            ),
            new MetadataProvenance(source: 'normalizer', toolVersion: 'v1', recordedAt: now()->toISOString())
        );

        $repository->storeNormalized(
            $assetId,
            new NormalizedAssetMetadata(
                schemaVersion: 'v2',
                payload: ['iso' => 200],
                index: ['iso' => 200, 'media_kind' => 'image', 'has_gps' => true]
            ),
            new MetadataProvenance(source: 'normalizer', toolVersion: 'v2', recordedAt: now()->toISOString())
        );

        $this->assertSame(2, AssetMetadataNormalized::query()->where('asset_id', $asset->id)->count());

        $repository->storeNormalized(
            $assetId,
            new NormalizedAssetMetadata(
                schemaVersion: 'v2',
                payload: ['iso' => 320],
                index: ['iso' => 320, 'media_kind' => 'image', 'has_gps' => true]
            ),
            new MetadataProvenance(source: 'normalizer', toolVersion: 'v2.1', recordedAt: now()->toISOString())
        );

        $this->assertSame(2, AssetMetadataNormalized::query()->where('asset_id', $asset->id)->count());

        $snapshot = $repository->get($assetId);
        $this->assertNotNull($snapshot->raw);
        $this->assertNotNull($snapshot->normalized);
        $this->assertSame('raw-2', $snapshot->raw->hash);
        $this->assertSame('v2', $snapshot->normalized->schemaVersion);
        $this->assertSame(320, $snapshot->normalized->index['iso']);
        $this->assertSame('image', $snapshot->normalized->index['media_kind']);
        $this->assertTrue((bool) $snapshot->normalized->index['has_gps']);
    }
}
