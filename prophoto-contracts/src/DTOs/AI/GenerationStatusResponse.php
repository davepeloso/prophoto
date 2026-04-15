<?php
namespace ProPhoto\Contracts\DTOs\AI;

use ProPhoto\Contracts\Enums\AI\GenerationStatus;

readonly class GenerationStatusResponse
{
    public function __construct(
        public GenerationStatus $status,
        public array $imageUrls = [],     // list of URL strings from provider (transient)
        public ?string $errorMessage = null,
        public array $metadata = [],
    ) {}
}
