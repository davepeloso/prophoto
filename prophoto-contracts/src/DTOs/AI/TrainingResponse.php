<?php
namespace ProPhoto\Contracts\DTOs\AI;

readonly class TrainingResponse
{
    public function __construct(
        public string $externalModelId,
        public ?int $estimatedDurationSeconds = null,
        public ?Money $cost = null,
        public array $metadata = [],
    ) {}
}
