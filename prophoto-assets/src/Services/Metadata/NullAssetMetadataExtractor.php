<?php

namespace ProPhoto\Assets\Services\Metadata;

use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataExtractorContract;
use ProPhoto\Contracts\DTOs\RawMetadataBundle;

class NullAssetMetadataExtractor implements AssetMetadataExtractorContract
{
    public function extract(string $sourcePath): RawMetadataBundle
    {
        return new RawMetadataBundle(
            payload: [
                'file_name' => basename($sourcePath),
                'file_size' => @filesize($sourcePath) ?: null,
                'source_path' => $sourcePath,
            ],
            source: 'null-extractor',
            toolVersion: 'v1',
            schemaVersion: 'v1',
            hash: hash_file('sha256', $sourcePath) ?: null,
        );
    }

    public function supports(string $mimeType): bool
    {
        return true;
    }
}
