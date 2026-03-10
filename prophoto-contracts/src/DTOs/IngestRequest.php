<?php

namespace ProPhoto\Contracts\DTOs;

readonly class IngestRequest
{
    public function __construct(
        public string $filePath,
        public string $source,
        public ?int $userId = null,
        public array $options = []
    ) {}

    public static function make(
        string $filePath,
        string $source,
        ?int $userId = null,
        array $options = []
    ): self {
        return new self($filePath, $source, $userId, $options);
    }
}
