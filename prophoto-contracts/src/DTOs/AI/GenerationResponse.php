<?php
namespace ProPhoto\Contracts\DTOs\AI;

readonly class GenerationResponse
{
    public function __construct(
        public string $externalRequestId,
        public ?int $estimatedDurationSeconds = null,
        public ?Money $cost = null,
        public array $metadata = [],
    ) {}
}
