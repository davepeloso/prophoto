<?php

namespace ProPhoto\Assets\Tests\Feature;

use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Tests\TestCase;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\MetadataProvenance;
use ProPhoto\Contracts\DTOs\NormalizedAssetMetadata;
use ProPhoto\Contracts\DTOs\RawMetadataBundle;
use ProPhoto\Contracts\Enums\AssetType;

class AssetMetadataRepositoryTest extends TestCase
{
    public function test_it_persists_raw_and_normalized_metadata(): void
    {
        $asset = Asset::query()->create([
            'studio_id' => '3',
            'type' => AssetType::JPEG->value,
            'original_filename' => 'meta.jpg',
            'mime_type' => 'image/jpeg',
            'bytes' => 1234,
            'checksum_sha256' => str_repeat('d', 64),
            'storage_driver' => 'local',
            'storage_key_original' => '3/assets/20/original/meta.jpg',
            'logical_path' => 'meta/demo',
            'status' => 'ready',
        ]);

        $repository = $this->app->make(AssetMetadataRepositoryContract::class);
        $assetId = AssetId::from($asset->id);

        $repository->storeRaw(
            $assetId,
            new RawMetadataBundle(payload: ['ISO' => 200], source: 'exiftool', toolVersion: '12.0', hash: 'abc123'),
            new MetadataProvenance(source: 'exiftool', toolVersion: '12.0', recordedAt: now()->toISOString())
        );

        $repository->storeNormalized(
            $assetId,
            new NormalizedAssetMetadata(schemaVersion: 'v1', payload: ['iso' => 200], index: ['iso' => 200]),
            new MetadataProvenance(source: 'normalizer', toolVersion: 'v1', recordedAt: now()->toISOString())
        );

        $snapshot = $repository->get($assetId);

        $this->assertNotNull($snapshot->raw);
        $this->assertNotNull($snapshot->normalized);
        $this->assertSame(200, $snapshot->normalized->index['iso']);
    }
}
