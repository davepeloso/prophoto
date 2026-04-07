<?php

namespace ProPhoto\Debug\Listeners;

use Illuminate\Support\Facades\Log;
use ProPhoto\Debug\Services\IngestTracer;
use ProPhoto\Ingest\Events\TraceSessionStarted;

class RecordSessionStart
{
    public function __construct(
        protected IngestTracer $tracer
    ) {}

    public function handle(TraceSessionStarted $event): void
    {
        if (! $this->tracer->isEnabled()) {
            return;
        }

        Log::debug('Debug trace session started', [
            'uuid' => $event->uuid,
            'session_id' => $event->sessionId,
            'filename' => $event->filename,
        ]);
    }
}
