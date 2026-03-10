<?php

namespace ProPhoto\Assets\Services\Assets;

use ProPhoto\Assets\Models\Asset;
use ProPhoto\Contracts\Contracts\Asset\AssetStorageContract;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataNormalizerContract;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\MetadataProvenance;
use ProPhoto\Contracts\DTOs\RawMetadataBundle;
use ProPhoto\Contracts\Enums\AssetType;
use ProPhoto\Contracts\Events\Asset\AssetCreated;
use ProPhoto\Contracts\Events\Asset\AssetMetadataExtracted;
use ProPhoto\Contracts\Events\Asset\AssetMetadataNormalized;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Contracts\Events\Asset\AssetStored;

class AssetCreationService
{
    public function __construct(
        private readonly AssetStorageContract $assetStorage,
        private readonly AssetMetadataRepositoryContract $metadataRepository,
        private readonly AssetMetadataNormalizerContract $metadataNormalizer,
    ) {}

    /**
     * Create a canonical asset record from a local file path and persist metadata.
     */
    public function createFromFile(string $sourcePath, array $attributes = []): Asset
    {
        if (!is_file($sourcePath)) {
            throw new \InvalidArgumentException("Source file does not exist: {$sourcePath}");
        }

        $originalFilename = (string) ($attributes['original_filename'] ?? basename($sourcePath));
        $mimeType = (string) ($attributes['mime_type'] ?? $this->detectMimeType($sourcePath));
        $studioId = (string) ($attributes['studio_id'] ?? 'default');
        $storageDriver = (string) ($attributes['storage_driver'] ?? config('prophoto-assets.storage.disk', 'local'));
        $bytes = (int) (@filesize($sourcePath) ?: 0);
        $checksum = hash_file('sha256', $sourcePath) ?: '';
        $capturedAt = $attributes['captured_at'] ?? null;
        $ingestedAt = $attributes['ingested_at'] ?? now();

        $asset = Asset::query()->create([
            'studio_id' => $studioId,
            'organization_id' => $attributes['organization_id'] ?? null,
            'type' => $this->resolveAssetType($originalFilename, $mimeType)->value,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'bytes' => $bytes,
            'checksum_sha256' => $checksum,
            'storage_driver' => $storageDriver,
            'storage_key_original' => '',
            'logical_path' => (string) ($attributes['logical_path'] ?? ''),
            'status' => 'pending',
            'captured_at' => $capturedAt,
            'ingested_at' => $ingestedAt,
            'metadata' => $attributes['metadata'] ?? [],
        ]);

        $assetId = AssetId::from($asset->id);
        $occurredAt = now()->toISOString();

        event(new AssetCreated(
            assetId: $assetId,
            studioId: $asset->studio_id,
            type: $this->resolveAssetType($originalFilename, $mimeType),
            logicalPath: (string) $asset->logical_path,
            occurredAt: $occurredAt,
        ));

        $stored = $this->assetStorage->putOriginal(
            sourcePath: $sourcePath,
            assetId: $assetId,
            metadata: [
                'storage_driver' => $storageDriver,
                'studio_id' => $studioId,
                'original_filename' => $originalFilename,
                'mime_type' => $mimeType,
            ],
        );

        $asset->forceFill([
            'storage_driver' => $stored->storageDriver,
            'storage_key_original' => $stored->storageKey,
            'bytes' => $stored->bytes ?? $bytes,
            'status' => 'ready',
        ])->save();

        event(new AssetStored(
            assetId: $assetId,
            storageDriver: (string) $asset->storage_driver,
            storageKeyOriginal: (string) $asset->storage_key_original,
            bytes: (int) ($asset->bytes ?? 0),
            checksumSha256: (string) $asset->checksum_sha256,
            occurredAt: $occurredAt,
        ));

        $rawPayload = is_array($attributes['raw_metadata'] ?? null)
            ? $attributes['raw_metadata']
            : [
                'file_name' => $originalFilename,
                'file_size' => $bytes,
                'mime_type' => $mimeType,
                'source_path' => $sourcePath,
            ];

        $rawBundle = new RawMetadataBundle(
            payload: $rawPayload,
            source: (string) ($attributes['metadata_source'] ?? 'asset-creation-service'),
            toolVersion: isset($attributes['metadata_tool_version'])
                ? (string) $attributes['metadata_tool_version']
                : null,
            schemaVersion: 'v1',
            hash: $this->hashPayload($rawPayload),
        );

        $provenance = new MetadataProvenance(
            source: $rawBundle->source,
            toolVersion: $rawBundle->toolVersion,
            recordedAt: $occurredAt,
            context: $attributes['metadata_context'] ?? [],
        );

        $this->metadataRepository->storeRaw($assetId, $rawBundle, $provenance);
        event(new AssetMetadataExtracted(
            assetId: $assetId,
            source: $rawBundle->source,
            extractedAt: $occurredAt,
            occurredAt: $occurredAt,
        ));

        $normalized = $this->metadataNormalizer->normalize($rawBundle);
        $this->metadataRepository->storeNormalized($assetId, $normalized, $provenance);
        event(new AssetMetadataNormalized(
            assetId: $assetId,
            schemaVersion: $normalized->schemaVersion,
            normalizedAt: $occurredAt,
            occurredAt: $occurredAt,
        ));

        event(new AssetReadyV1(
            assetId: $assetId,
            studioId: $asset->studio_id,
            status: (string) $asset->status,
            hasOriginal: true,
            hasNormalizedMetadata: true,
            hasDerivatives: false,
            occurredAt: $occurredAt,
        ));

        return $asset->fresh();
    }

    protected function resolveAssetType(string $filename, string $mimeType): AssetType
    {
        if (str_starts_with(strtolower($mimeType), 'video/')) {
            return AssetType::VIDEO;
        }

        return match (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => AssetType::JPEG,
            'heic', 'heif' => AssetType::HEIC,
            'png' => AssetType::PNG,
            'raw', 'dng', 'cr2', 'cr3', 'nef', 'arw', 'raf', 'orf', 'rw2' => AssetType::RAW,
            default => AssetType::UNKNOWN,
        };
    }

    protected function detectMimeType(string $sourcePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $sourcePath) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (is_string($mimeType) && trim($mimeType) !== '') {
            return $mimeType;
        }

        return 'application/octet-stream';
    }

    protected function hashPayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return hash('sha256', serialize($payload));
        }

        return hash('sha256', $json);
    }
}

