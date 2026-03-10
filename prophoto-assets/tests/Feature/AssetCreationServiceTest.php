<?php

namespace ProPhoto\Assets\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use ProPhoto\Assets\Services\Assets\AssetCreationService;
use ProPhoto\Assets\Tests\TestCase;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\MetadataScope;

class AssetCreationServiceTest extends TestCase
{
    public function test_it_creates_asset_and_persists_metadata_from_file(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'asset-create-');
        $this->assertNotFalse($tmp);

        file_put_contents($tmp, 'asset-creation-test');

        /** @var AssetCreationService $service */
        $service = $this->app->make(AssetCreationService::class);
        $asset = $service->createFromFile($tmp, [
            'studio_id' => 'fixture-studio',
            'original_filename' => 'fixture.txt',
            'mime_type' => 'text/plain',
            'logical_path' => 'fixtures/tests',
            'metadata_source' => 'phpunit',
            'raw_metadata' => [
                'source' => 'phpunit',
                'payload' => 'asset-creation-test',
            ],
        ]);

        $this->assertSame('ready', $asset->status);
        $this->assertNotEmpty($asset->storage_key_original);
        $this->assertTrue(Storage::disk($asset->storage_driver)->exists($asset->storage_key_original));

        /** @var AssetMetadataRepositoryContract $metadataRepository */
        $metadataRepository = $this->app->make(AssetMetadataRepositoryContract::class);
        $snapshot = $metadataRepository->get(AssetId::from($asset->id), MetadataScope::BOTH);

        $this->assertNotNull($snapshot->raw);
        $this->assertNotNull($snapshot->normalized);
        $this->assertSame('phpunit', $snapshot->raw->source);

        @unlink($tmp);
    }
}

