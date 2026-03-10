<?php

namespace ProPhoto\Assets\Services\Storage;

use Illuminate\Support\Facades\Storage;
use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Models\AssetDerivative;
use ProPhoto\Contracts\Contracts\Asset\AssetPathResolverContract;
use ProPhoto\Contracts\Contracts\Asset\AssetStorageContract;
use ProPhoto\Contracts\Contracts\Asset\SignedUrlGeneratorContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\StoredObjectRef;
use ProPhoto\Contracts\Enums\DerivativeType;

class LaravelAssetStorage implements AssetStorageContract
{
    public function __construct(
        private readonly AssetPathResolverContract $pathResolver,
        private readonly SignedUrlGeneratorContract $signedUrlGenerator,
    ) {}

    public function putOriginal(string $sourcePath, AssetId $assetId, array $metadata = []): StoredObjectRef
    {
        $disk = (string) ($metadata['storage_driver'] ?? config('prophoto-assets.storage.disk', 'local'));
        $studioId = $metadata['studio_id'] ?? 'default';
        $originalFilename = (string) ($metadata['original_filename'] ?? basename($sourcePath));

        $key = $this->pathResolver->originalKey($assetId, $studioId, $originalFilename);
        $content = (string) file_get_contents($sourcePath);

        Storage::disk($disk)->put($key, $content);

        return new StoredObjectRef(
            storageDriver: $disk,
            storageKey: $key,
            mimeType: (string) ($metadata['mime_type'] ?? 'application/octet-stream'),
            bytes: strlen($content),
            metadata: $metadata
        );
    }

    public function getOriginalStream(AssetId $assetId): mixed
    {
        $asset = Asset::query()->find($assetId->value);

        if ($asset === null) {
            return null;
        }

        return Storage::disk((string) $asset->storage_driver)->readStream((string) $asset->storage_key_original);
    }

    public function putDerivative(
        AssetId $assetId,
        DerivativeType $derivativeType,
        string $sourcePath,
        array $metadata = []
    ): StoredObjectRef {
        $disk = (string) ($metadata['storage_driver'] ?? config('prophoto-assets.storage.disk', 'local'));
        $studioId = $metadata['studio_id'] ?? 'default';
        $extension = (string) ($metadata['extension'] ?? pathinfo($sourcePath, PATHINFO_EXTENSION));

        $key = $this->pathResolver->derivativeKey($assetId, $studioId, $derivativeType, $extension);
        $content = (string) file_get_contents($sourcePath);

        Storage::disk($disk)->put($key, $content);

        return new StoredObjectRef(
            storageDriver: $disk,
            storageKey: $key,
            mimeType: (string) ($metadata['mime_type'] ?? 'application/octet-stream'),
            bytes: strlen($content),
            metadata: $metadata
        );
    }

    public function getDerivativeUrl(AssetId $assetId, DerivativeType $derivativeType, array $options = []): string
    {
        $derivative = AssetDerivative::query()
            ->where('asset_id', $assetId->value)
            ->where('type', $derivativeType->value)
            ->latest('id')
            ->first();

        if ($derivative === null) {
            return '';
        }

        $storageDriver = (string) ($options['storage_driver'] ?? config('prophoto-assets.storage.disk', 'local'));

        $expiresAt = (new \DateTimeImmutable())->modify('+' . (int) config('prophoto-assets.storage.temporary_url_ttl_seconds', 3600) . ' seconds');

        return $this->signedUrlGenerator->forStorageKey(
            storageDriver: $storageDriver,
            storageKey: (string) $derivative->storage_key,
            expiresAt: $expiresAt,
            options: $options
        );
    }

    public function delete(AssetId $assetId): void
    {
        $asset = Asset::query()->find($assetId->value);

        if ($asset !== null) {
            Storage::disk((string) $asset->storage_driver)->delete((string) $asset->storage_key_original);
        }

        $derivatives = AssetDerivative::query()->where('asset_id', $assetId->value)->get();

        $driver = (string) ($asset?->storage_driver ?? config('prophoto-assets.storage.disk', 'local'));
        foreach ($derivatives as $derivative) {
            Storage::disk($driver)->delete((string) $derivative->storage_key);
        }
    }

    public function exists(AssetId $assetId, DerivativeType $type = DerivativeType::ORIGINAL): bool
    {
        $asset = Asset::query()->find($assetId->value);

        if ($asset === null) {
            return false;
        }

        $disk = Storage::disk((string) $asset->storage_driver);

        if ($type === DerivativeType::ORIGINAL) {
            return $disk->exists((string) $asset->storage_key_original);
        }

        $derivative = AssetDerivative::query()
            ->where('asset_id', $assetId->value)
            ->where('type', $type->value)
            ->latest('id')
            ->first();

        return $derivative ? $disk->exists((string) $derivative->storage_key) : false;
    }
}
