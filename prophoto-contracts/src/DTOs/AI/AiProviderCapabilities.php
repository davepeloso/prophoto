<?php
namespace ProPhoto\Contracts\DTOs\AI;

readonly class AiProviderCapabilities
{
    public function __construct(
        public bool $supportsTraining,
        public bool $supportsGeneration,
        public bool $supportsVideo = false,
        public int $minTrainingImages = 0,
        public int $maxTrainingImages = 0,
        public ?int $maxGenerationsPerModel = null,  // null = unlimited
        public array $supportedOutputFormats = ['png', 'jpg'],
    ) {}
}
