<?php
namespace ProPhoto\Contracts\DTOs\AI;

use ProPhoto\Contracts\Enums\AI\ProviderRole;

readonly class AiProviderDescriptor
{
    public function __construct(
        public string $providerKey,
        public string $displayName,
        public ProviderRole $providerRole,
        public AiProviderCapabilities $capabilities,
        public array $defaultConfig = [],
    ) {}
}
