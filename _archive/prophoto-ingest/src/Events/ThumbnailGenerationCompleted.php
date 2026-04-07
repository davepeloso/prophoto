<?php

namespace ProPhoto\Ingest\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ThumbnailGenerationCompleted
{
    use Dispatchable;

    public function __construct(
        public string $uuid,
        public string $sessionId,
        public string $method,
        public bool $success,
        public ?string $failureReason = null,
        public array $resultInfo = [],
    ) {}
}
