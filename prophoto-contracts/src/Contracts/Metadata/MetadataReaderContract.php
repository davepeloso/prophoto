<?php

namespace ProPhoto\Contracts\Contracts\Metadata;

use ProPhoto\Contracts\DTOs\AssetMetadata;

interface MetadataReaderContract
{
    /**
     * Extract metadata from a file.
     */
    public function extractMetadata(string $filePath): AssetMetadata;

    /**
     * Check if the reader supports a given file type.
     */
    public function supports(string $mimeType): bool;
}
