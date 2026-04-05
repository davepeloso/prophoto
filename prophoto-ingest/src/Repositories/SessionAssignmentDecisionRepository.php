<?php

namespace ProPhoto\Ingest\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use RuntimeException;

class SessionAssignmentDecisionRepository
{
    public function __construct(
        protected ?ConnectionInterface $connection = null
    ) {
        $this->connection = $this->connection ?? DB::connection();
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function append(array $attributes): array
    {
        $payload = $this->normalizeForWrite($attributes);
        $id = (int) $this->connection
            ->table('asset_session_assignment_decisions')
            ->insertGetId($payload);

        $decision = $this->findById($id);
        if ($decision === null) {
            throw new RuntimeException('Failed to fetch persisted session assignment decision.');
        }

        return $decision;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int|string $id): ?array
    {
        $row = $this->connection
            ->table('asset_session_assignment_decisions')
            ->where('id', $id)
            ->first();

        return $row === null ? null : $this->hydrate((array) $row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdempotencyKey(string $idempotencyKey): ?array
    {
        if ($idempotencyKey === '') {
            return null;
        }

        $row = $this->connection
            ->table('asset_session_assignment_decisions')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $row === null ? null : $this->hydrate((array) $row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findBySubject(SessionAssociationSubjectType|string $subjectType, string $subjectId, int $limit = 50): array
    {
        $resolvedType = $subjectType instanceof SessionAssociationSubjectType
            ? $subjectType->value
            : $subjectType;

        $rows = $this->connection
            ->table('asset_session_assignment_decisions')
            ->where('subject_type', $resolvedType)
            ->where('subject_id', $subjectId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return array_values(
            array_map(
                fn (object $row): array => $this->hydrate((array) $row),
                $rows->all()
            )
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function normalizeForWrite(array $attributes): array
    {
        $decisionType = $attributes['decision_type'] ?? null;
        $subjectType = $attributes['subject_type'] ?? null;
        $confidenceTier = $attributes['confidence_tier'] ?? null;

        $evidencePayload = $attributes['evidence_payload'] ?? [];
        $rankedCandidatesPayload = $attributes['ranked_candidates_payload'] ?? null;

        return [
            'decision_type' => $decisionType instanceof SessionAssignmentDecisionType
                ? $decisionType->value
                : (string) $decisionType,
            'subject_type' => $subjectType instanceof SessionAssociationSubjectType
                ? $subjectType->value
                : (string) $subjectType,
            'subject_id' => (string) ($attributes['subject_id'] ?? ''),
            'ingest_item_id' => $attributes['ingest_item_id'] ?? null,
            'asset_id' => $attributes['asset_id'] ?? null,
            'selected_session_id' => $attributes['selected_session_id'] ?? null,
            'confidence_tier' => $confidenceTier instanceof SessionMatchConfidenceTier
                ? $confidenceTier->value
                : $confidenceTier,
            'confidence_score' => isset($attributes['confidence_score'])
                ? (float) $attributes['confidence_score']
                : null,
            'algorithm_version' => (string) ($attributes['algorithm_version'] ?? 'v1'),
            'trigger_source' => (string) ($attributes['trigger_source'] ?? 'api'),
            'evidence_payload' => $this->encodeJson($evidencePayload),
            'ranked_candidates_payload' => $rankedCandidatesPayload === null
                ? null
                : $this->encodeJson($rankedCandidatesPayload),
            'calendar_context_state' => $attributes['calendar_context_state'] ?? null,
            'manual_override_reason_code' => $attributes['manual_override_reason_code'] ?? null,
            'manual_override_note' => $attributes['manual_override_note'] ?? null,
            'lock_effect' => (string) ($attributes['lock_effect'] ?? 'none'),
            'supersedes_decision_id' => $attributes['supersedes_decision_id'] ?? null,
            'idempotency_key' => $attributes['idempotency_key'] ?? null,
            'actor_type' => (string) ($attributes['actor_type'] ?? 'system'),
            'actor_id' => $attributes['actor_id'] ?? null,
            'created_at' => (string) ($attributes['created_at'] ?? Carbon::now('UTC')->toISOString()),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function hydrate(array $row): array
    {
        $row['evidence_payload'] = $this->decodeJson($row['evidence_payload'] ?? null);
        $row['ranked_candidates_payload'] = $this->decodeJson($row['ranked_candidates_payload'] ?? null);
        $row['confidence_score'] = isset($row['confidence_score'])
            ? (float) $row['confidence_score']
            : null;

        return $row;
    }

    /**
     * @param mixed $value
     */
    protected function encodeJson(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected function decodeJson(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}

