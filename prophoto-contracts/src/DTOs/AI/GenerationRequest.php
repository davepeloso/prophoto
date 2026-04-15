<?php
namespace ProPhoto\Contracts\DTOs\AI;

readonly class GenerationRequest
{
    public function __construct(
        public string $externalModelId,
        public string $prompt,
        public int $numImages = 8,
        public array $metadata = [],
    ) {}
}
