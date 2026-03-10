<?php

namespace ProPhoto\Assets\Services\Metadata;

use ProPhoto\Assets\Models\AssetMetadataNormalized;
use ProPhoto\Assets\Models\AssetMetadataRaw;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\AssetMetadataSnapshot;
use ProPhoto\Contracts\DTOs\MetadataProvenance;
use ProPhoto\Contracts\DTOs\NormalizedAssetMetadata;
use ProPhoto\Contracts\DTOs\RawMetadataBundle;
use ProPhoto\Contracts\Enums\MetadataScope;

class EloquentAssetMetadataRepository implements AssetMetadataRepositoryContract
{
    public function storeRaw(AssetId $assetId, RawMetadataBundle $bundle, MetadataProvenance $provenance): void
    {
        AssetMetadataRaw::query()->create([
            'asset_id' => $assetId->value,
            'source' => $provenance->source,
            'tool_version' => $provenance->toolVersion,
            'extracted_at' => $provenance->recordedAt,
            'payload' => $bundle->payload,
            'payload_hash' => $bundle->hash,
            'metadata' => $provenance->context,
        ]);
    }

    public function storeNormalized(
        AssetId $assetId,
        NormalizedAssetMetadata $metadata,
        MetadataProvenance $provenance
    ): void {
        AssetMetadataNormalized::query()->updateOrCreate(
            [
                'asset_id' => $assetId->value,
                'schema_version' => $metadata->schemaVersion,
            ],
            [
                'normalized_at' => $provenance->recordedAt,
                'payload' => $metadata->payload,
                'media_kind' => $metadata->index['media_kind'] ?? null,
                'captured_at' => $metadata->index['captured_at'] ?? null,
                'camera_make' => $metadata->index['camera_make'] ?? null,
                'camera_model' => $metadata->index['camera_model'] ?? null,
                'mime_type' => $metadata->index['mime_type'] ?? null,
                'file_size' => $metadata->index['file_size'] ?? null,
                'lens' => $metadata->index['lens'] ?? null,
                'color_profile' => $metadata->index['color_profile'] ?? null,
                'rating' => $metadata->index['rating'] ?? null,
                'page_count' => $metadata->index['page_count'] ?? null,
                'duration_seconds' => $metadata->index['duration_seconds'] ?? null,
                'has_gps' => (bool) ($metadata->index['has_gps'] ?? false),
                'iso' => $metadata->index['iso'] ?? null,
                'width' => $metadata->index['width'] ?? null,
                'height' => $metadata->index['height'] ?? null,
                'exif_orientation' => $metadata->index['exif_orientation'] ?? null,
                'metadata' => $provenance->context,
            ]
        );
    }

    public function get(AssetId $assetId, MetadataScope $scope = MetadataScope::BOTH): AssetMetadataSnapshot
    {
        $raw = null;
        $normalized = null;

        if (in_array($scope, [MetadataScope::RAW, MetadataScope::BOTH], true)) {
            $rawRecord = AssetMetadataRaw::query()
                ->where('asset_id', $assetId->value)
                ->latest('id')
                ->first();

            if ($rawRecord !== null) {
                $raw = new RawMetadataBundle(
                    payload: $rawRecord->payload ?? [],
                    source: (string) $rawRecord->source,
                    toolVersion: $rawRecord->tool_version,
                    schemaVersion: null,
                    hash: $rawRecord->payload_hash,
                );
            }
        }

        if (in_array($scope, [MetadataScope::NORMALIZED, MetadataScope::BOTH], true)) {
            $normalizedRecord = AssetMetadataNormalized::query()
                ->where('asset_id', $assetId->value)
                ->latest('id')
                ->first();

            if ($normalizedRecord !== null) {
                $normalized = new NormalizedAssetMetadata(
                    schemaVersion: (string) $normalizedRecord->schema_version,
                    payload: $normalizedRecord->payload ?? [],
                    index: [
                        'media_kind' => $normalizedRecord->media_kind,
                        'captured_at' => $normalizedRecord->captured_at?->toISOString(),
                        'mime_type' => $normalizedRecord->mime_type,
                        'file_size' => $normalizedRecord->file_size,
                        'camera_make' => $normalizedRecord->camera_make,
                        'camera_model' => $normalizedRecord->camera_model,
                        'lens' => $normalizedRecord->lens,
                        'color_profile' => $normalizedRecord->color_profile,
                        'rating' => $normalizedRecord->rating,
                        'page_count' => $normalizedRecord->page_count,
                        'duration_seconds' => $normalizedRecord->duration_seconds,
                        'has_gps' => $normalizedRecord->has_gps,
                        'iso' => $normalizedRecord->iso,
                        'width' => $normalizedRecord->width,
                        'height' => $normalizedRecord->height,
                        'exif_orientation' => $normalizedRecord->exif_orientation,
                    ],
                );
            }
        }

        return new AssetMetadataSnapshot($raw, $normalized);
    }
}
