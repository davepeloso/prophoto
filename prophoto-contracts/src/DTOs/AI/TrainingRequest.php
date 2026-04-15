<?php
namespace ProPhoto\Contracts\DTOs\AI;

readonly class TrainingRequest
{
    public function __construct(
        public string $providerKey,
        public array $imageUrls,         // list of publicly accessible URLs
        public string $subjectName,
        public ?string $callbackUrl = null,
        public array $metadata = [],
    ) {}
}
