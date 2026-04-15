<?php

namespace ProPhoto\AI\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiModelTrained
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $galleryId,
        public readonly int $generationId,
        public readonly string $providerKey,
        public readonly string $modelStatus,
        public readonly ?string $trainedAt = null,
    ) {}
}
