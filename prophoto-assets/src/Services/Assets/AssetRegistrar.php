<?php

namespace ProPhoto\Assets\Services\Assets;

use ProPhoto\Assets\Models\Asset;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Events\Asset\AssetCreated;
use ProPhoto\Contracts\Events\Asset\AssetStored;
use ProPhoto\Contracts\Enums\AssetType;

class AssetRegistrar
{
    /**
     * Register a canonical asset and emit base lifecycle events.
     *
     * This is intentionally additive and does not alter ingest/gallery behavior.
     */
    public function register(array $attributes): Asset
    {
        $asset = Asset::query()->create([
            'studio_id' => (string) ($attributes['studio_id'] ?? 'default'),
            'organization_id' => $attributes['organization_id'] ?? null,
            'type' => (string) ($attributes['type'] ?? AssetType::UNKNOWN->value),
            'original_filename' => (string) ($attributes['original_filename'] ?? 'unknown.bin'),
            'mime_type' => (string) ($attributes['mime_type'] ?? 'application/octet-stream'),
            'bytes' => (int) ($attributes['bytes'] ?? 0),
            'checksum_sha256' => (string) ($attributes['checksum_sha256'] ?? ''),
            'storage_driver' => (string) ($attributes['storage_driver'] ?? config('prophoto-assets.storage.disk', 'local')),
            'storage_key_original' => (string) ($attributes['storage_key_original'] ?? ''),
            'logical_path' => (string) ($attributes['logical_path'] ?? ''),
            'status' => (string) ($attributes['status'] ?? 'pending'),
            'captured_at' => $attributes['captured_at'] ?? null,
            'ingested_at' => $attributes['ingested_at'] ?? now(),
            'metadata' => $attributes['metadata'] ?? [],
        ]);

        $assetId = AssetId::from($asset->id);
        $assetType = AssetType::tryFrom((string) $asset->type) ?? AssetType::UNKNOWN;

        event(new AssetCreated(
            assetId: $assetId,
            studioId: $asset->studio_id,
            type: $assetType,
            logicalPath: (string) $asset->logical_path,
            occurredAt: now()->toISOString(),
        ));

        event(new AssetStored(
            assetId: $assetId,
            storageDriver: (string) $asset->storage_driver,
            storageKeyOriginal: (string) $asset->storage_key_original,
            bytes: (int) ($asset->bytes ?? 0),
            checksumSha256: (string) $asset->checksum_sha256,
            occurredAt: now()->toISOString(),
        ));

        return $asset;
    }
}
