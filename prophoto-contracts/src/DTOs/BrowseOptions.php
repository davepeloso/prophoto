<?php

namespace ProPhoto\Contracts\DTOs;

readonly class BrowseOptions
{
    public function __construct(
        public int $limit = 200,
        public bool $includeFiles = true,
        public bool $includeFolders = true,
        public bool $recursive = false,
        public ?string $cursor = null
    ) {}
}
