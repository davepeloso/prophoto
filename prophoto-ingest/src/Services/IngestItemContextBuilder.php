<?php

namespace ProPhoto\Ingest\Services;

use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Ingest\Domain\IngestItem;

class IngestItemContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function buildForMatching(IngestItem $ingestItem): array
    {
        $subjectId = (string) $ingestItem->ingestItemId;

        $context = [
            'subject_type' => SessionAssociationSubjectType::INGEST_ITEM,
            'subject_id' => $subjectId,
            'ingest_item_id' => $subjectId,
            'asset_id' => null,
            'capture_at_utc' => $ingestItem->captureAtUtc,
            'gps_lat' => $ingestItem->gpsLat,
            'gps_lng' => $ingestItem->gpsLng,
            'session_type_hint' => $ingestItem->sessionTypeHint,
            'job_type_hint' => $ingestItem->jobTypeHint,
            'title_hint' => $ingestItem->titleHint,
            'trigger_source' => $ingestItem->triggerSource,
            'idempotency_key' => $ingestItem->idempotencyKey,
            'actor_type' => $ingestItem->actorType,
            'actor_id' => $ingestItem->actorId,
        ];

        if ($ingestItem->createdAt !== null && $ingestItem->createdAt !== '') {
            $context['created_at'] = $ingestItem->createdAt;
        }

        return $context;
    }
}
