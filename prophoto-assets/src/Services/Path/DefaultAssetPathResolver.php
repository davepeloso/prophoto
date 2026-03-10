<?php

namespace ProPhoto\Assets\Services\Path;

use Illuminate\Support\Str;
use ProPhoto\Contracts\Contracts\Asset\AssetPathResolverContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\DerivativeType;

class DefaultAssetPathResolver implements AssetPathResolverContract
{
    public function originalKey(AssetId $assetId, int|string $studioId, string $originalFilename): string
    {
        $safeFilename = $this->safeFilename($originalFilename);

        return trim((string) $studioId, '/')
            . '/assets/' . $assetId->toString()
            . '/original/' . $safeFilename;
    }

    public function derivativeKey(
        AssetId $assetId,
        int|string $studioId,
        DerivativeType $derivativeType,
        string $extension
    ): string {
        $safeExt = ltrim(strtolower($extension), '.');

        return trim((string) $studioId, '/')
            . '/assets/' . $assetId->toString()
            . '/derivatives/' . $derivativeType->value
            . '.' . ($safeExt !== '' ? $safeExt : 'bin');
    }

    public function logicalPath(AssetId $assetId, int|string $studioId, ?string $prefix = null): string
    {
        $base = trim((string) $studioId, '/') . '/assets';

        if ($prefix !== null && trim($prefix) !== '') {
            return trim($base . '/' . trim($prefix, '/'), '/');
        }

        return trim($base . '/' . $assetId->toString(), '/');
    }

    private function safeFilename(string $filename): string
    {
        $filename = trim($filename);

        if ($filename === '') {
            return 'original.bin';
        }

        $name = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        $safeName = Str::slug($name !== '' ? $name : 'original', '_');

        return $safeName . ($ext !== '' ? '.' . strtolower($ext) : '.bin');
    }
}
