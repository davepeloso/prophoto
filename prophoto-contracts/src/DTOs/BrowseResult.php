<?php

namespace ProPhoto\Contracts\DTOs;

readonly class BrowseResult
{
    /**
     * @param list<BrowseEntry> $entries
     */
    public function __construct(
        public string $prefixPath,
        public array $entries = [],
        public ?string $nextCursor = null
    ) {}
}
