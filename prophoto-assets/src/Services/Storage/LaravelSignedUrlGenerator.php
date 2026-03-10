<?php

namespace ProPhoto\Assets\Services\Storage;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use ProPhoto\Assets\Models\AssetDerivative;
use ProPhoto\Contracts\Contracts\Asset\SignedUrlGeneratorContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\DerivativeType;

class LaravelSignedUrlGenerator implements SignedUrlGeneratorContract
{
    public function forStorageKey(
        string $storageDriver,
        string $storageKey,
        DateTimeInterface $expiresAt,
        array $options = []
    ): string {
        $disk = Storage::disk($storageDriver);

        if (method_exists($disk, 'temporaryUrl')) {
            return $disk->temporaryUrl($storageKey, $expiresAt, $options);
        }

        return $disk->url($storageKey);
    }

    public function forAssetDerivative(
        AssetId $assetId,
        DerivativeType $derivativeType,
        DateTimeInterface $expiresAt,
        array $options = []
    ): string {
        $derivative = AssetDerivative::query()
            ->where('asset_id', $assetId->value)
            ->where('type', $derivativeType->value)
            ->latest('id')
            ->first();

        if ($derivative === null) {
            return '';
        }

        $driver = (string) ($options['storage_driver'] ?? config('prophoto-assets.storage.disk', 'local'));

        return $this->forStorageKey($driver, (string) $derivative->storage_key, $expiresAt, $options);
    }

    public function defaultExpiryFromNow(): DateTimeImmutable
    {
        $ttl = (int) config('prophoto-assets.storage.temporary_url_ttl_seconds', 3600);

        return (new DateTimeImmutable())->add(new DateInterval('PT' . max(1, $ttl) . 'S'));
    }
}
