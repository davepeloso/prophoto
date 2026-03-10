<?php

namespace ProPhoto\Contracts\Events\Asset;

use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\DerivativeType;

readonly class AssetDerivativesGenerated
{
    /**
     * @param list<DerivativeType> $derivativeTypes
     */
    public function __construct(
        public AssetId $assetId,
        public array $derivativeTypes,
        public string $occurredAt
    ) {}
}
