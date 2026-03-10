<?php

namespace ProPhoto\Contracts\Contracts\Metadata;

use ProPhoto\Contracts\DTOs\NormalizedAssetMetadata;
use ProPhoto\Contracts\DTOs\RawMetadataBundle;

interface AssetMetadataNormalizerContract
{
    /**
     * Convert raw metadata into canonical normalized metadata.
     */
    public function normalize(RawMetadataBundle $rawBundle): NormalizedAssetMetadata;
}
