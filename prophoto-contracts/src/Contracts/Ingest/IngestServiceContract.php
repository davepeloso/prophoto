<?php

namespace ProPhoto\Contracts\Contracts\Ingest;

use ProPhoto\Contracts\DTOs\IngestRequest;
use ProPhoto\Contracts\DTOs\IngestResult;

interface IngestServiceContract
{
    /**
     * Queue an asset for ingestion.
     */
    public function queueIngest(IngestRequest $request): string;

    /**
     * Process an ingest job synchronously.
     */
    public function processIngest(IngestRequest $request): IngestResult;

    /**
     * Get the status of an ingest job.
     */
    public function getIngestStatus(string $jobId): string;
}
