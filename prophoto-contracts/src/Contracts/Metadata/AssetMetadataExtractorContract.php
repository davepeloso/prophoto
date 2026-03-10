<?php

namespace ProPhoto\Contracts\Contracts\Metadata;

use ProPhoto\Contracts\DTOs\RawMetadataBundle;

interface AssetMetadataExtractorContract
{
    /**
     * Extract raw metadata from the provided source path.
     */
    public function extract(string $sourcePath): RawMetadataBundle;

    /**
     * Determine whether this extractor supports the mime type.
     */
    public function supports(string $mimeType): bool;
}
