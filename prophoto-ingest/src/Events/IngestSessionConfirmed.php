<?php

namespace ProPhoto\Ingest\Events;

use ProPhoto\Ingest\Models\UploadSession;

/**
 * IngestSessionConfirmed
 *
 * Fired by UploadSessionService::confirmSession() after the user clicks "Confirm"
 * in the gallery UI and the session status transitions to STATUS_CONFIRMED.
 *
 * This event is the bridge between the ingest workflow and the asset creation
 * pipeline in prophoto-assets. The listener (IngestSessionConfirmedListener)
 * reads all uploaded IngestFiles for this session and creates canonical Asset
 * records via AssetCreationService.
 *
 * Sprint 5 — Story 1c.1
 */
readonly class IngestSessionConfirmed
{
    public function __construct(
        public string     $sessionId,
        public int|string $studioId,
        public int|string $userId,
        public string     $occurredAt,
        public ?string    $calendarEventId = null,
        public ?string    $calendarProvider = null,
        public ?float     $calendarMatchConfidence = null,
        public ?int       $galleryId = null,
    ) {}
}
