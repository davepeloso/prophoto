<?php

namespace ProPhoto\Contracts\Contracts\Metadata;

use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\AssetMetadataSnapshot;
use ProPhoto\Contracts\DTOs\MetadataProvenance;
use ProPhoto\Contracts\DTOs\NormalizedAssetMetadata;
use ProPhoto\Contracts\DTOs\RawMetadataBundle;
use ProPhoto\Contracts\Enums\MetadataScope;

interface AssetMetadataRepositoryContract
{
    /**
     * Store source-truth raw metadata payload.
     */
    public function storeRaw(AssetId $assetId, RawMetadataBundle $bundle, MetadataProvenance $provenance): void;

    /**
     * Store canonical normalized metadata payload.
     */
    public function storeNormalized(
        AssetId $assetId,
        NormalizedAssetMetadata $metadata,
        MetadataProvenance $provenance
    ): void;

    /**
     * Read metadata snapshot for the requested scope.
     */
    public function get(AssetId $assetId, MetadataScope $scope = MetadataScope::BOTH): AssetMetadataSnapshot;
}
