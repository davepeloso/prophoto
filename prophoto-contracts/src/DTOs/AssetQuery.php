<?php

namespace ProPhoto\Contracts\DTOs;

use ProPhoto\Contracts\Enums\AssetType;

readonly class AssetQuery
{
    public function __construct(
        public int|string|null $studioId = null,
        public ?AssetType $type = null,
        public ?string $logicalPathPrefix = null,
        public ?string $status = null,
        public int $limit = 50,
        public int $offset = 0,
        public array $filters = []
    ) {}
}
