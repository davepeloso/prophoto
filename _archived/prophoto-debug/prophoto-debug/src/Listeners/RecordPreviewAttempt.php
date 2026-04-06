<?php

namespace ProPhoto\Debug\Listeners;

use ProPhoto\Debug\Models\IngestTrace;
use ProPhoto\Debug\Services\IngestTracer;
use ProPhoto\Ingest\Events\PreviewExtractionAttempted;

class RecordPreviewAttempt
{
    public function __construct(
        protected IngestTracer $tracer
    ) {}

    public function handle(PreviewExtractionAttempted $event): void
    {
        if (! $this->tracer->isEnabled()) {
            return;
        }

        if (! $this->tracer->isTypeEnabled(IngestTrace::TYPE_PREVIEW_EXTRACTION)) {
            return;
        }

        IngestTrace::create([
            'uuid' => $event->uuid,
            'session_id' => $event->sessionId,
            'trace_type' => IngestTrace::TYPE_PREVIEW_EXTRACTION,
            'method_tried' => $event->method,
            'method_order' => $event->order,
            'success' => $event->success,
            'failure_reason' => $event->failureReason,
            'result_info' => $event->resultInfo,
        ]);
    }
}
