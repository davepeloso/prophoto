<?php

namespace ProPhoto\Contracts\Contracts\Asset;

use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\AssetQuery;
use ProPhoto\Contracts\DTOs\AssetRecord;
use ProPhoto\Contracts\DTOs\BrowseOptions;
use ProPhoto\Contracts\DTOs\BrowseResult;

interface AssetRepositoryContract
{
    /**
     * Find one asset by canonical identifier.
     */
    public function find(AssetId $assetId): ?AssetRecord;

    /**
     * List assets using filter/query criteria.
     *
     * @return list<AssetRecord>
     */
    public function list(AssetQuery $query): array;

    /**
     * Browse assets using drive-like path semantics.
     */
    public function browse(string $prefixPath, ?BrowseOptions $options = null): BrowseResult;
}
