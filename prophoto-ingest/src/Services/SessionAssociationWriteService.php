<?php

namespace ProPhoto\Ingest\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssignmentMode;
use ProPhoto\Contracts\Enums\SessionAssociationLockState;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Contracts\Events\Ingest\SessionAutoAssignmentApplied;
use ProPhoto\Contracts\Events\Ingest\SessionManualAssignmentApplied;
use ProPhoto\Contracts\Events\Ingest\SessionManualUnassignmentApplied;
use ProPhoto\Contracts\Events\Ingest\SessionMatchProposalCreated;
use ProPhoto\Ingest\Repositories\SessionAssignmentDecisionRepository;
use ProPhoto\Ingest\Repositories\SessionAssignmentRepository;

class SessionAssociationWriteService
{
    public function __construct(
        protected SessionAssignmentDecisionRepository $decisionRepository,
        protected SessionAssignmentRepository $assignmentRepository,
        protected Dispatcher $events,
        protected ?ConnectionInterface $connection = null
    ) {
        $this->connection = $this->connection ?? DB::connection();
    }

    /**
     * @param array<string, mixed> $decisionAttributes
     * @return array{
     *     decision: array<string, mixed>,
     *     assignment: array<string, mixed>|null,
     *     assignment_written: bool,
     *     skipped_by_manual_lock: bool,
     *     idempotent: bool
     * }
     */
    public function writeDecision(array $decisionAttributes): array
    {
        $eventToDispatch = null;

        $result = $this->connection->transaction(function () use ($decisionAttributes, &$eventToDispatch): array {
            $idempotencyKey = $decisionAttributes['idempotency_key'] ?? null;
            if (is_string($idempotencyKey) && $idempotencyKey !== '') {
                $existing = $this->decisionRepository->findByIdempotencyKey($idempotencyKey);
                if ($existing !== null) {
                    return [
                        'decision' => $existing,
                        'assignment' => $this->assignmentRepository->findCurrentBySubject(
                            $existing['subject_type'],
                            (string) $existing['subject_id']
                        ),
                        'assignment_written' => false,
                        'skipped_by_manual_lock' => false,
                        'idempotent' => true,
                    ];
                }
            }

            $decision = $this->decisionRepository->append($decisionAttributes);
            $decisionType = SessionAssignmentDecisionType::from((string) $decision['decision_type']);

            if ($decisionType === SessionAssignmentDecisionType::PROPOSE) {
                $eventToDispatch = $this->proposalEventFromDecision($decision);

                return [
                    'decision' => $decision,
                    'assignment' => null,
                    'assignment_written' => false,
                    'skipped_by_manual_lock' => false,
                    'idempotent' => false,
                ];
            }

            if ($decisionType === SessionAssignmentDecisionType::NO_MATCH) {
                return [
                    'decision' => $decision,
                    'assignment' => null,
                    'assignment_written' => false,
                    'skipped_by_manual_lock' => false,
                    'idempotent' => false,
                ];
            }

            $subjectType = (string) $decision['subject_type'];
            $subjectId = (string) $decision['subject_id'];
            $current = $this->assignmentRepository->findCurrentBySubject($subjectType, $subjectId);

            if ($decisionType === SessionAssignmentDecisionType::AUTO_ASSIGN
                && $this->isManualLockActive($current)
            ) {
                return [
                    'decision' => $decision,
                    'assignment' => null,
                    'assignment_written' => false,
                    'skipped_by_manual_lock' => true,
                    'idempotent' => false,
                ];
            }

            $assignmentPayload = $this->assignmentPayloadFromDecision($decision, $decisionType);

            if ($current !== null) {
                // Mark current row non-current before insert so one-current constraints can hold.
                $this->assignmentRepository->markSuperseded(
                    assignmentId: $current['id'],
                    supersededAt: (string) $decision['created_at']
                );
            }

            $assignment = $this->assignmentRepository->appendEffective($assignmentPayload);

            if ($current !== null) {
                $this->assignmentRepository->setSupersededBy(
                    assignmentId: $current['id'],
                    supersededByAssignmentId: $assignment['id']
                );
            }

            $eventToDispatch = match ($decisionType) {
                SessionAssignmentDecisionType::AUTO_ASSIGN => $this->autoAssignmentEventFrom($decision, $assignment),
                SessionAssignmentDecisionType::MANUAL_ASSIGN => $this->manualAssignmentEventFrom($decision, $assignment),
                SessionAssignmentDecisionType::MANUAL_UNASSIGN => $this->manualUnassignmentEventFrom($decision, $assignment),
                default => null,
            };

            return [
                'decision' => $decision,
                'assignment' => $assignment,
                'assignment_written' => true,
                'skipped_by_manual_lock' => false,
                'idempotent' => false,
            ];
        });

        if ($eventToDispatch !== null) {
            $this->events->dispatch($eventToDispatch);
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $current
     */
    protected function isManualLockActive(?array $current): bool
    {
        if ($current === null) {
            return false;
        }

        return in_array(
            (string) ($current['manual_lock_state'] ?? ''),
            [
                SessionAssociationLockState::MANUAL_ASSIGNED_LOCK->value,
                SessionAssociationLockState::MANUAL_UNASSIGNED_LOCK->value,
            ],
            true
        );
    }

    /**
     * @param array<string, mixed> $decision
     * @return array<string, mixed>
     */
    protected function assignmentPayloadFromDecision(array $decision, SessionAssignmentDecisionType $decisionType): array
    {
        $isAssigned = in_array(
            $decisionType,
            [SessionAssignmentDecisionType::AUTO_ASSIGN, SessionAssignmentDecisionType::MANUAL_ASSIGN],
            true
        );

        $manualLockState = match ($decisionType) {
            SessionAssignmentDecisionType::MANUAL_ASSIGN => SessionAssociationLockState::MANUAL_ASSIGNED_LOCK,
            SessionAssignmentDecisionType::MANUAL_UNASSIGN => SessionAssociationLockState::MANUAL_UNASSIGNED_LOCK,
            default => SessionAssociationLockState::NONE,
        };

        $assignmentMode = in_array(
            $decisionType,
            [SessionAssignmentDecisionType::MANUAL_ASSIGN, SessionAssignmentDecisionType::MANUAL_UNASSIGN],
            true
        )
            ? SessionAssignmentMode::MANUAL
            : SessionAssignmentMode::AUTO;

        if ($isAssigned && ($decision['selected_session_id'] ?? null) === null) {
            throw new InvalidArgumentException('selected_session_id is required for assigned outcomes.');
        }

        return [
            'subject_type' => SessionAssociationSubjectType::from((string) $decision['subject_type']),
            'subject_id' => (string) $decision['subject_id'],
            'ingest_item_id' => $decision['ingest_item_id'] ?? null,
            'asset_id' => $decision['asset_id'] ?? null,
            'session_id' => $isAssigned ? $decision['selected_session_id'] : null,
            'effective_state' => $isAssigned ? 'assigned' : 'unassigned',
            'assignment_mode' => $assignmentMode,
            'manual_lock_state' => $manualLockState,
            'source_decision_id' => $decision['id'],
            'confidence_tier' => $decisionType === SessionAssignmentDecisionType::AUTO_ASSIGN
                ? ($decision['confidence_tier'] ?? null)
                : null,
            'confidence_score' => $decisionType === SessionAssignmentDecisionType::AUTO_ASSIGN
                ? ($decision['confidence_score'] ?? null)
                : null,
            'reason_code' => $decisionType->value,
            'became_effective_at' => (string) $decision['created_at'],
        ];
    }

    /**
     * @param array<string, mixed> $decision
     * @param array<string, mixed> $assignment
     */
    protected function autoAssignmentEventFrom(array $decision, array $assignment): SessionAutoAssignmentApplied
    {
        $confidenceTierRaw = $decision['confidence_tier'] ?? null;
        if (! is_string($confidenceTierRaw)) {
            throw new InvalidArgumentException('Auto-assignment decisions require confidence_tier.');
        }

        return new SessionAutoAssignmentApplied(
            assignmentId: $assignment['id'],
            decisionId: $decision['id'],
            subjectType: SessionAssociationSubjectType::from((string) $decision['subject_type']),
            subjectId: (string) $decision['subject_id'],
            ingestItemId: $decision['ingest_item_id'] ?? null,
            assetId: $decision['asset_id'] ?? null,
            sessionId: $assignment['session_id'],
            confidenceTier: SessionMatchConfidenceTier::from($confidenceTierRaw),
            confidenceScore: isset($decision['confidence_score']) ? (float) $decision['confidence_score'] : null,
            algorithmVersion: (string) $decision['algorithm_version'],
            occurredAt: (string) $decision['created_at']
        );
    }

    /**
     * @param array<string, mixed> $decision
     * @param array<string, mixed> $assignment
     */
    protected function manualAssignmentEventFrom(array $decision, array $assignment): SessionManualAssignmentApplied
    {
        return new SessionManualAssignmentApplied(
            assignmentId: $assignment['id'],
            decisionId: $decision['id'],
            subjectType: SessionAssociationSubjectType::from((string) $decision['subject_type']),
            subjectId: (string) $decision['subject_id'],
            ingestItemId: $decision['ingest_item_id'] ?? null,
            assetId: $decision['asset_id'] ?? null,
            sessionId: $assignment['session_id'],
            lockState: SessionAssociationLockState::from((string) $assignment['manual_lock_state']),
            manualOverrideReasonCode: isset($decision['manual_override_reason_code'])
                ? (string) $decision['manual_override_reason_code']
                : null,
            actorId: (string) ($decision['actor_id'] ?? 'system'),
            occurredAt: (string) $decision['created_at']
        );
    }

    /**
     * @param array<string, mixed> $decision
     * @param array<string, mixed> $assignment
     */
    protected function manualUnassignmentEventFrom(array $decision, array $assignment): SessionManualUnassignmentApplied
    {
        return new SessionManualUnassignmentApplied(
            assignmentId: $assignment['id'],
            decisionId: $decision['id'],
            subjectType: SessionAssociationSubjectType::from((string) $decision['subject_type']),
            subjectId: (string) $decision['subject_id'],
            ingestItemId: $decision['ingest_item_id'] ?? null,
            assetId: $decision['asset_id'] ?? null,
            lockState: SessionAssociationLockState::from((string) $assignment['manual_lock_state']),
            manualOverrideReasonCode: isset($decision['manual_override_reason_code'])
                ? (string) $decision['manual_override_reason_code']
                : null,
            actorId: (string) ($decision['actor_id'] ?? 'system'),
            occurredAt: (string) $decision['created_at']
        );
    }

    /**
     * @param array<string, mixed> $decision
     */
    protected function proposalEventFromDecision(array $decision): SessionMatchProposalCreated
    {
        $confidenceTierRaw = $decision['confidence_tier'] ?? null;
        if (! is_string($confidenceTierRaw)) {
            throw new InvalidArgumentException('Proposal decisions require confidence_tier.');
        }

        $ranked = $decision['ranked_candidates_payload'] ?? [];
        $candidateCount = is_array($ranked) ? count($ranked) : 0;

        return new SessionMatchProposalCreated(
            decisionId: $decision['id'],
            subjectType: SessionAssociationSubjectType::from((string) $decision['subject_type']),
            subjectId: (string) $decision['subject_id'],
            ingestItemId: $decision['ingest_item_id'] ?? null,
            assetId: $decision['asset_id'] ?? null,
            topCandidateSessionId: $decision['selected_session_id'] ?? null,
            candidateCount: $candidateCount,
            confidenceTier: SessionMatchConfidenceTier::from($confidenceTierRaw),
            confidenceScore: isset($decision['confidence_score']) ? (float) $decision['confidence_score'] : null,
            algorithmVersion: (string) $decision['algorithm_version'],
            occurredAt: (string) $decision['created_at']
        );
    }
}

