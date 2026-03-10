<?php

namespace ProPhoto\Contracts\DTOs;

readonly class IngestResult
{
    public function __construct(
        public AssetId $assetId,
        public array $derivativePaths,
        public array $metadata,
        public bool $success = true,
        public ?string $errorMessage = null
    ) {}

    public static function success(
        AssetId $assetId,
        array $derivativePaths,
        array $metadata
    ): self {
        return new self($assetId, $derivativePaths, $metadata, true);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            AssetId::from(0),
            [],
            [],
            false,
            $errorMessage
        );
    }
}
