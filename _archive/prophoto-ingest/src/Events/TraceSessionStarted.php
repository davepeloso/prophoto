<?php

namespace ProPhoto\Ingest\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TraceSessionStarted
{
    use Dispatchable;

    public function __construct(
        public string $uuid,
        public string $sessionId,
        public string $filename,
    ) {}
}
