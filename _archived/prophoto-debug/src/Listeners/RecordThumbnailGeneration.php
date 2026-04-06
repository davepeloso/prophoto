<?php

namespace ProPhoto\Debug\Listeners;

use ProPhoto\Debug\Models\IngestTrace;
use ProPhoto\Debug\Services\IngestTracer;
use ProPhoto\Ingest\Events\ThumbnailGenerationCompleted;

class RecordThumbnailGeneration
{
    public function __construct(
        protected IngestTracer $tracer
    ) {}

    public function handle(ThumbnailGenerationCompleted $event): void
    {
        if (! $this->tracer->isEnabled()) {
            return;
        }

        if (! $this->tracer->isTypeEnabled(IngestTrace::TYPE_THUMBNAIL_GENERATION)) {
            return;
        }

        IngestTrace::create([
            'uuid' => $event->uuid,
            'session_id' => $event->sessionId,
            'trace_type' => IngestTrace::TYPE_THUMBNAIL_GENERATION,
            'method_tried' => $event->method,
            'method_order' => 1,
            'success' => $event->success,
            'failure_reason' => $event->failureReason,
            'result_info' => $event->resultInfo,
        ]);
    }
}
