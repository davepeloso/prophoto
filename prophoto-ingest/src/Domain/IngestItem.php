<?php

namespace ProPhoto\Ingest\Domain;

readonly class IngestItem
{
    public function __construct(
        public int|string $ingestItemId,
        public ?string $captureAtUtc = null,
        public ?float $gpsLat = null,
        public ?float $gpsLng = null,
        public ?string $sessionTypeHint = null,
        public ?string $jobTypeHint = null,
        public ?string $titleHint = null,
        public string $triggerSource = 'ingest_batch',
        public ?string $idempotencyKey = null,
        public string $actorType = 'system',
        public ?string $actorId = null,
        public ?string $createdAt = null
    ) {}
}
