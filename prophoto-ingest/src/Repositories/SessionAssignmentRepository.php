<?php

namespace ProPhoto\Ingest\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ProPhoto\Contracts\Enums\SessionAssignmentMode;
use ProPhoto\Contracts\Enums\SessionAssociationLockState;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use RuntimeException;

class SessionAssignmentRepository
{
    public function __construct(
        protected ?ConnectionInterface $connection = null
    ) {
        $this->connection = $this->connection ?? DB::connection();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int|string $id): ?array
    {
        $row = $this->connection
            ->table('asset_session_assignments')
            ->where('id', $id)
            ->first();

        return $row === null ? null : $this->hydrate((array) $row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCurrentBySubject(SessionAssociationSubjectType|string $subjectType, string $subjectId): ?array
    {
        $resolvedType = $subjectType instanceof SessionAssociationSubjectType
            ? $subjectType->value
            : $subjectType;

        $row = $this->connection
            ->table('asset_session_assignments')
            ->where('subject_type', $resolvedType)
            ->where('subject_id', $subjectId)
            ->whereNull('superseded_at')
            ->orderByDesc('id')
            ->first();

        return $row === null ? null : $this->hydrate((array) $row);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function appendEffective(array $attributes): array
    {
        $payload = $this->normalizeForWrite($attributes);
        $id = (int) $this->connection
            ->table('asset_session_assignments')
            ->insertGetId($payload);

        $assignment = $this->findById($id);
        if ($assignment === null) {
            throw new RuntimeException('Failed to fetch persisted effective session assignment.');
        }

        return $assignment;
    }

    public function markSuperseded(int|string $assignmentId, ?string $supersededAt = null): void
    {
        $timestamp = $supersededAt ?? Carbon::now('UTC')->toISOString();

        $this->connection
            ->table('asset_session_assignments')
            ->where('id', $assignmentId)
            ->update([
                'superseded_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
    }

    public function setSupersededBy(int|string $assignmentId, int|string $supersededByAssignmentId): void
    {
        $this->connection
            ->table('asset_session_assignments')
            ->where('id', $assignmentId)
            ->update([
                'superseded_by_assignment_id' => $supersededByAssignmentId,
                'updated_at' => Carbon::now('UTC')->toISOString(),
            ]);
    }

    public function supersedeCurrent(int|string $assignmentId, int|string $supersededByAssignmentId, ?string $supersededAt = null): void
    {
        $this->markSuperseded($assignmentId, $supersededAt);
        $this->setSupersededBy($assignmentId, $supersededByAssignmentId);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function normalizeForWrite(array $attributes): array
    {
        $subjectType = $attributes['subject_type'] ?? null;
        $assignmentMode = $attributes['assignment_mode'] ?? null;
        $manualLockState = $attributes['manual_lock_state'] ?? null;
        $confidenceTier = $attributes['confidence_tier'] ?? null;

        $createdAt = (string) ($attributes['created_at'] ?? Carbon::now('UTC')->toISOString());
        $updatedAt = (string) ($attributes['updated_at'] ?? $createdAt);

        return [
            'subject_type' => $subjectType instanceof SessionAssociationSubjectType
                ? $subjectType->value
                : (string) $subjectType,
            'subject_id' => (string) ($attributes['subject_id'] ?? ''),
            'ingest_item_id' => $attributes['ingest_item_id'] ?? null,
            'asset_id' => $attributes['asset_id'] ?? null,
            'session_id' => $attributes['session_id'] ?? null,
            'effective_state' => (string) ($attributes['effective_state'] ?? 'unassigned'),
            'assignment_mode' => $assignmentMode instanceof SessionAssignmentMode
                ? $assignmentMode->value
                : (string) $assignmentMode,
            'manual_lock_state' => $manualLockState instanceof SessionAssociationLockState
                ? $manualLockState->value
                : (string) $manualLockState,
            'source_decision_id' => $attributes['source_decision_id'] ?? null,
            'confidence_tier' => $confidenceTier instanceof SessionMatchConfidenceTier
                ? $confidenceTier->value
                : $confidenceTier,
            'confidence_score' => isset($attributes['confidence_score'])
                ? (float) $attributes['confidence_score']
                : null,
            'reason_code' => $attributes['reason_code'] ?? null,
            'became_effective_at' => (string) ($attributes['became_effective_at'] ?? $createdAt),
            'superseded_at' => $attributes['superseded_at'] ?? null,
            'superseded_by_assignment_id' => $attributes['superseded_by_assignment_id'] ?? null,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function hydrate(array $row): array
    {
        $row['confidence_score'] = isset($row['confidence_score'])
            ? (float) $row['confidence_score']
            : null;

        return $row;
    }
}

