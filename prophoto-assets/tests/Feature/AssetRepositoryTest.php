<?php

namespace ProPhoto\Assets\Tests\Feature;

use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Tests\TestCase;
use ProPhoto\Contracts\Contracts\Asset\AssetRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\AssetQuery;
use ProPhoto\Contracts\Enums\AssetType;

class AssetRepositoryTest extends TestCase
{
    public function test_it_can_find_asset_record(): void
    {
        $asset = Asset::query()->create([
            'studio_id' => '1',
            'type' => AssetType::JPEG->value,
            'original_filename' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'bytes' => 1024,
            'checksum_sha256' => str_repeat('a', 64),
            'storage_driver' => 'local',
            'storage_key_original' => '1/assets/1/original/test.jpg',
            'logical_path' => '2026/01/test',
            'status' => 'ready',
        ]);

        $repository = $this->app->make(AssetRepositoryContract::class);
        $record = $repository->find(AssetId::from($asset->id));

        $this->assertNotNull($record);
        $this->assertSame('test.jpg', $record->originalFilename);
        $this->assertSame('image/jpeg', $record->mimeType);
        $this->assertSame('ready', $record->status);
    }

    public function test_it_can_list_and_browse_assets(): void
    {
        Asset::query()->create([
            'studio_id' => '2',
            'type' => AssetType::JPEG->value,
            'original_filename' => 'a.jpg',
            'mime_type' => 'image/jpeg',
            'bytes' => 2048,
            'checksum_sha256' => str_repeat('b', 64),
            'storage_driver' => 'local',
            'storage_key_original' => '2/assets/10/original/a.jpg',
            'logical_path' => 'projects/demo/a',
            'status' => 'ready',
        ]);

        Asset::query()->create([
            'studio_id' => '2',
            'type' => AssetType::PNG->value,
            'original_filename' => 'b.png',
            'mime_type' => 'image/png',
            'bytes' => 512,
            'checksum_sha256' => str_repeat('c', 64),
            'storage_driver' => 'local',
            'storage_key_original' => '2/assets/11/original/b.png',
            'logical_path' => 'projects/demo/b',
            'status' => 'pending',
        ]);

        $repository = $this->app->make(AssetRepositoryContract::class);

        $records = $repository->list(new AssetQuery(studioId: '2', status: 'ready'));
        $this->assertCount(1, $records);
        $this->assertSame('a.jpg', $records[0]->originalFilename);

        $browse = $repository->browse('projects/demo');
        $this->assertNotEmpty($browse->entries);
    }
}
