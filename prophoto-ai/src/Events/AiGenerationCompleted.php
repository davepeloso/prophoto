<?php

namespace ProPhoto\AI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiGenerationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $galleryId,
        public readonly int $generationId,
        public readonly int $requestId,
        public readonly int $portraitCount,
        public readonly string $providerKey,
    ) {}
}
