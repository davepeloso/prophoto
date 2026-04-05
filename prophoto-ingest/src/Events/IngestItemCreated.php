<?php

namespace ProPhoto\Ingest\Events;

readonly class IngestItemCreated
{
    public function __construct(
        public int|string $ingestItemId,
        public ?string $captureAtUtc,
        public ?float $gpsLat,
        public ?float $gpsLng,
        public string $triggerSource,
        public string $occurredAt
    ) {}
}
