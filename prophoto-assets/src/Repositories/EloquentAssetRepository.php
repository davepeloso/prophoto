<?php

namespace ProPhoto\Assets\Repositories;

use Illuminate\Database\Eloquent\Builder;
use ProPhoto\Assets\Models\Asset;
use ProPhoto\Contracts\Contracts\Asset\AssetRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\AssetQuery;
use ProPhoto\Contracts\DTOs\AssetRecord;
use ProPhoto\Contracts\DTOs\BrowseEntry;
use ProPhoto\Contracts\DTOs\BrowseOptions;
use ProPhoto\Contracts\DTOs\BrowseResult;
use ProPhoto\Contracts\Enums\AssetType;

class EloquentAssetRepository implements AssetRepositoryContract
{
    public function find(AssetId $assetId): ?AssetRecord
    {
        $asset = Asset::query()->find($assetId->value);

        return $asset ? $this->mapRecord($asset) : null;
    }

    public function list(AssetQuery $query): array
    {
        $builder = Asset::query();

        if ($query->studioId !== null) {
            $builder->where('studio_id', (string) $query->studioId);
        }

        if ($query->type !== null) {
            $builder->where('type', $query->type->value);
        }

        if ($query->logicalPathPrefix !== null && $query->logicalPathPrefix !== '') {
            $builder->where('logical_path', 'like', rtrim($query->logicalPathPrefix, '/') . '%');
        }

        if ($query->status !== null) {
            $builder->where('status', $query->status);
        }

        $this->applyExtraFilters($builder, $query->filters);

        return $builder
            ->orderByDesc('id')
            ->offset(max(0, $query->offset))
            ->limit(max(1, $query->limit))
            ->get()
            ->map(fn (Asset $asset): AssetRecord => $this->mapRecord($asset))
            ->all();
    }

    public function browse(string $prefixPath, ?BrowseOptions $options = null): BrowseResult
    {
        $options ??= new BrowseOptions();
        $normalizedPrefix = trim($prefixPath, '/');

        $query = Asset::query();
        if ($normalizedPrefix !== '') {
            $query->where('logical_path', 'like', $normalizedPrefix . '%');
        }

        $assets = $query
            ->orderBy('logical_path')
            ->limit(max(1, $options->limit))
            ->get();

        $entries = [];
        $folders = [];

        foreach ($assets as $asset) {
            $path = trim((string) $asset->logical_path, '/');

            if ($options->includeFolders && !$options->recursive) {
                $relative = $normalizedPrefix === '' ? $path : ltrim(substr($path, strlen($normalizedPrefix)), '/');
                if ($relative !== '' && str_contains($relative, '/')) {
                    $folder = explode('/', $relative)[0];
                    $folderPath = trim(($normalizedPrefix !== '' ? $normalizedPrefix . '/' : '') . $folder, '/');
                    $folders[$folderPath] = true;
                }
            }

            if ($options->includeFiles) {
                $entries[] = new BrowseEntry(
                    path: $path,
                    isDirectory: false,
                    assetId: AssetId::from($asset->id),
                    mimeType: $asset->mime_type,
                    bytes: $asset->bytes,
                    metadata: $asset->metadata ?? []
                );
            }
        }

        if ($options->includeFolders) {
            foreach (array_keys($folders) as $folderPath) {
                $entries[] = new BrowseEntry(
                    path: $folderPath,
                    isDirectory: true
                );
            }
        }

        return new BrowseResult(
            prefixPath: $normalizedPrefix,
            entries: $entries,
            nextCursor: null
        );
    }

    private function applyExtraFilters(Builder $builder, array $filters): void
    {
        foreach ($filters as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (in_array($key, ['mime_type', 'checksum_sha256', 'storage_driver', 'status'], true)) {
                $builder->where($key, $value);
            }
        }
    }

    private function mapRecord(Asset $asset): AssetRecord
    {
        $type = AssetType::tryFrom((string) $asset->type) ?? AssetType::UNKNOWN;

        return new AssetRecord(
            id: AssetId::from($asset->id),
            studioId: $asset->studio_id,
            type: $type,
            originalFilename: (string) $asset->original_filename,
            mimeType: (string) $asset->mime_type,
            bytes: (int) ($asset->bytes ?? 0),
            checksumSha256: (string) $asset->checksum_sha256,
            storageDriver: (string) $asset->storage_driver,
            storageKeyOriginal: (string) $asset->storage_key_original,
            logicalPath: (string) $asset->logical_path,
            status: (string) $asset->status,
            capturedAt: $asset->captured_at?->toISOString(),
            ingestedAt: $asset->ingested_at?->toISOString(),
            metadata: $asset->metadata ?? []
        );
    }
}
