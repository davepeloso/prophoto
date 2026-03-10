<?php

namespace ProPhoto\Debug\Listeners;

use Illuminate\Support\Facades\Log;
use ProPhoto\Debug\Services\IngestTracer;
use ProPhoto\Ingest\Events\TraceSessionEnded;

class RecordSessionEnd
{
    public function __construct(
        protected IngestTracer $tracer
    ) {}

    public function handle(TraceSessionEnded $event): void
    {
        if (! $this->tracer->isEnabled()) {
            return;
        }

        Log::debug('Debug trace session ended', [
            'uuid' => $event->uuid,
            'session_id' => $event->sessionId,
            'success' => $event->success,
            'error' => $event->error,
        ]);

        // Log a summary of the session
        $summary = $this->tracer->getTraceSummary($event->uuid);

        Log::info('Ingest trace summary', [
            'uuid' => $event->uuid,
            'total_attempts' => $summary['total_attempts'],
            'successful' => $summary['successful'],
            'failed' => $summary['failed'],
            'by_type' => $summary['by_type'],
        ]);
    }
}
