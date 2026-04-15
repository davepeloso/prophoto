<?php
namespace ProPhoto\Contracts\DTOs\AI;

use ProPhoto\Contracts\Enums\AI\TrainingStatus;

readonly class TrainingStatusResponse
{
    public function __construct(
        public TrainingStatus $status,
        public string $externalModelId,
        public ?string $errorMessage = null,
        public ?string $completedAt = null,
        public ?string $expiresAt = null,
        public array $metadata = [],
    ) {}
}
