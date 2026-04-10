<?php

namespace ProPhoto\Ingest\Services;

use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Ingest\Domain\IngestItem;

/**
 * Pure input snapshot builder for ingest flow.
 *
 * No decision logic allowed.
 */
class IngestItemContextBuilder
{
    /**
     * @param list<array<string, mixed>>|null $sessionContextSnapshot
     * @return array{
     *     metadata_snapshot: array<string, mixed>,
     *     session_context_snapshot: list<array<string, mixed>>|null
     * }
     */
    public function buildInputSnapshots(
        IngestItem $ingestItem,
        ?array $sessionContextSnapshot = null
    ): array
    {
        return [
            'metadata_snapshot' => $this->buildMetadataSnapshot($ingestItem),
            // Pass-through only. No filtering, ranking, or interpretation at this layer.
            'session_context_snapshot' => $sessionContextSnapshot,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildMetadataSnapshot(IngestItem $ingestItem): array
    {
        $subjectId = (string) $ingestItem->ingestItemId;

        $snapshot = [
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
            $snapshot['created_at'] = $ingestItem->createdAt;
        }

        return $snapshot;
    }
}
