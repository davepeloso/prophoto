<?php

namespace ProPhoto\Ingest\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TraceSessionEnded
{
    use Dispatchable;

    public function __construct(
        public string $uuid,
        public string $sessionId,
        public bool $success,
        public ?string $error = null,
    ) {}
}
