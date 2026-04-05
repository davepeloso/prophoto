<?php

namespace ProPhoto\Ingest\Services;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssignmentLockEffect;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Ingest\Services\Matching\SessionMatchCandidateGenerator;
use ProPhoto\Ingest\Services\Matching\SessionMatchDecisionClassifier;
use ProPhoto\Ingest\Services\Matching\SessionMatchScoringService;

class SessionMatchingService
{
    public function __construct(
        protected SessionMatchCandidateGenerator $candidateGenerator,
        protected SessionMatchScoringService $scoringService,
        protected SessionMatchDecisionClassifier $decisionClassifier,
        protected SessionAssociationWriteService $writeService
    ) {}

    /**
     * @param array<string, mixed> $subjectContext
     * @param list<array<string, mixed>> $sessionContexts
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function matchAndWrite(array $subjectContext, array $sessionContexts, array $options = []): array
    {
        $this->assertValidSubjectContext($subjectContext);

        $autoAssignThreshold = (float) ($options['auto_assign_threshold']
            ?? config('prophoto-ingest.session_association.auto_assign_threshold', 0.85));
        $proposalThreshold = (float) ($options['proposal_threshold']
            ?? config('prophoto-ingest.session_association.proposal_threshold', 0.55));
        $ambiguityDelta = (float) ($options['ambiguity_delta']
            ?? config('prophoto-ingest.matching.ambiguity_delta', 0.05));
        $algorithmVersion = (string) ($options['algorithm_version']
            ?? config('prophoto-ingest.matching.algorithm_version', 'v1'));
        $maxRankedCandidates = (int) ($options['max_ranked_candidates']
            ?? config('prophoto-ingest.matching.max_ranked_candidates', 5));
        if ($maxRankedCandidates < 1) {
            $maxRankedCandidates = 1;
        }
        $allowCreatedAtOverride = (bool) ($options['allow_created_at_override'] ?? false);

        $candidates = $this->candidateGenerator->generate(
            subjectContext: $subjectContext,
            sessionContexts: $sessionContexts,
            options: [
                'max_candidate_distance_minutes' => $options['max_candidate_distance_minutes']
                    ?? config('prophoto-ingest.matching.max_candidate_distance_minutes', 720),
            ]
        );

        $scoredCandidates = $this->scoringService->scoreCandidates(
            subjectContext: $subjectContext,
            candidates: $candidates,
            options: [
                'high_threshold' => $autoAssignThreshold,
                'medium_threshold' => $proposalThreshold,
            ]
        );

        $rankedCandidates = $this->rankCandidates($scoredCandidates);

        $classification = $this->decisionClassifier->classify(
            rankedCandidates: $rankedCandidates,
            options: [
                'auto_assign_threshold' => $autoAssignThreshold,
                'proposal_threshold' => $proposalThreshold,
                'ambiguity_delta' => $ambiguityDelta,
            ]
        );

        $rankedCandidatesForPayload = $this->trimRankedCandidates($rankedCandidates, $maxRankedCandidates);

        $decisionAttributes = $this->buildDecisionAttributes(
            subjectContext: $subjectContext,
            rankedCandidates: $rankedCandidatesForPayload,
            classification: $classification,
            algorithmVersion: $algorithmVersion,
            maxRankedCandidates: $maxRankedCandidates,
            allowCreatedAtOverride: $allowCreatedAtOverride
        );

        $writeResult = $this->writeService->writeDecision($decisionAttributes);

        return array_merge(
            [
                'classification' => $classification,
                'candidate_count' => count($rankedCandidatesForPayload),
                'ranked_candidates' => $rankedCandidatesForPayload,
                'decision_attributes' => $decisionAttributes,
            ],
            $writeResult
        );
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @return list<array<string, mixed>>
     */
    protected function rankCandidates(array $candidates): array
    {
        // Deterministic ordering: score desc -> buffer priority -> planned-start delta -> distance -> session_id lexical.
        usort($candidates, function (array $left, array $right): int {
            $leftScore = (float) ($left['score'] ?? 0.0);
            $rightScore = (float) ($right['score'] ?? 0.0);
            if ($leftScore !== $rightScore) {
                return $leftScore < $rightScore ? 1 : -1;
            }

            $leftBufferPriority = $this->bufferPriority((string) ($left['buffer_class'] ?? 'unknown'));
            $rightBufferPriority = $this->bufferPriority((string) ($right['buffer_class'] ?? 'unknown'));
            if ($leftBufferPriority !== $rightBufferPriority) {
                return $leftBufferPriority < $rightBufferPriority ? 1 : -1;
            }

            $leftStartDelta = $this->sortableNullableNumber($left['minutes_from_planned_start'] ?? null);
            $rightStartDelta = $this->sortableNullableNumber($right['minutes_from_planned_start'] ?? null);
            if ($leftStartDelta !== $rightStartDelta) {
                return $leftStartDelta <=> $rightStartDelta;
            }

            $leftDistance = $this->sortableNullableNumber($left['distance_meters'] ?? null);
            $rightDistance = $this->sortableNullableNumber($right['distance_meters'] ?? null);
            if ($leftDistance !== $rightDistance) {
                return $leftDistance <=> $rightDistance;
            }

            return strcmp((string) ($left['session_id'] ?? ''), (string) ($right['session_id'] ?? ''));
        });

        return array_values($candidates);
    }

    protected function bufferPriority(string $bufferClass): int
    {
        return match ($bufferClass) {
            'core' => 3,
            'buffer' => 2,
            'outside' => 1,
            default => 0,
        };
    }

    /**
     * @param mixed $value
     */
    protected function sortableNullableNumber(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 9999999.0;
        }

        return (float) $value;
    }

    /**
     * @param array<string, mixed> $subjectContext
     */
    protected function assertValidSubjectContext(array $subjectContext): void
    {
        if (! array_key_exists('subject_type', $subjectContext)) {
            throw new InvalidArgumentException('subject_type is required for session matching.');
        }

        if (! array_key_exists('subject_id', $subjectContext)) {
            throw new InvalidArgumentException('subject_id is required for session matching.');
        }

        $subjectId = trim((string) $subjectContext['subject_id']);
        if ($subjectId === '') {
            throw new InvalidArgumentException('subject_id must be a non-empty string.');
        }

        $subjectType = $subjectContext['subject_type'];
        if ($subjectType instanceof SessionAssociationSubjectType) {
            $subjectType = $subjectType->value;
        }

        if (! is_string($subjectType) || $subjectType === '') {
            throw new InvalidArgumentException('subject_type must be a non-empty string or enum.');
        }

        if (! in_array($subjectType, array_column(SessionAssociationSubjectType::cases(), 'value'), true)) {
            throw new InvalidArgumentException("Unsupported subject_type [{$subjectType}] for session matching.");
        }

        if ($subjectType === SessionAssociationSubjectType::INGEST_ITEM->value
            && empty($subjectContext['ingest_item_id'])
        ) {
            throw new InvalidArgumentException('ingest_item_id is required when subject_type=ingest_item.');
        }

        if ($subjectType === SessionAssociationSubjectType::INGEST_ITEM->value
            && $subjectId !== (string) $subjectContext['ingest_item_id']
        ) {
            throw new InvalidArgumentException(
                'subject_id must match ingest_item_id string when subject_type=ingest_item.'
            );
        }

        if ($subjectType === SessionAssociationSubjectType::ASSET->value
            && empty($subjectContext['asset_id'])
        ) {
            throw new InvalidArgumentException('asset_id is required when subject_type=asset.');
        }

        if ($subjectType === SessionAssociationSubjectType::ASSET->value
            && $subjectId !== (string) $subjectContext['asset_id']
        ) {
            throw new InvalidArgumentException(
                'subject_id must match asset_id string when subject_type=asset.'
            );
        }
    }

    /**
     * @param array<string, mixed> $subjectContext
     * @param list<array<string, mixed>> $rankedCandidates
     * @param array<string, mixed> $classification
     * @return array<string, mixed>
     */
    protected function buildDecisionAttributes(
        array $subjectContext,
        array $rankedCandidates,
        array $classification,
        string $algorithmVersion,
        int $maxRankedCandidates,
        bool $allowCreatedAtOverride = false
    ): array {
        $subjectType = $subjectContext['subject_type'];
        if (! $subjectType instanceof SessionAssociationSubjectType) {
            $subjectType = SessionAssociationSubjectType::from((string) $subjectType);
        }

        $decisionType = $classification['decision_type'] ?? SessionAssignmentDecisionType::NO_MATCH;
        if (! $decisionType instanceof SessionAssignmentDecisionType) {
            $decisionType = SessionAssignmentDecisionType::from((string) $decisionType);
        }

        $confidenceTier = $classification['confidence_tier'] ?? SessionMatchConfidenceTier::LOW;
        if (! $confidenceTier instanceof SessionMatchConfidenceTier) {
            $confidenceTier = SessionMatchConfidenceTier::from((string) $confidenceTier);
        }

        $selectedSessionId = $classification['selected_session_id'] ?? null;
        $this->assertDecisionClassificationConsistency($decisionType, $selectedSessionId);

        $topCandidate = $rankedCandidates[0] ?? null;
        $triggerSource = $this->normalizeTriggerSource($subjectContext['trigger_source'] ?? 'ingest_batch');
        $actorType = $this->normalizeActorType($subjectContext['actor_type'] ?? 'system');
        $createdAt = $this->resolveCreatedAt($subjectContext, $allowCreatedAtOverride);

        return [
            'decision_type' => $decisionType,
            'subject_type' => $subjectType,
            'subject_id' => (string) $subjectContext['subject_id'],
            'ingest_item_id' => $subjectContext['ingest_item_id'] ?? null,
            'asset_id' => $subjectContext['asset_id'] ?? null,
            'selected_session_id' => $selectedSessionId,
            'confidence_tier' => $confidenceTier,
            'confidence_score' => $classification['confidence_score'] ?? null,
            'algorithm_version' => $algorithmVersion,
            'trigger_source' => $triggerSource,
            'evidence_payload' => $this->buildEvidencePayload($subjectContext, $classification, $topCandidate),
            'ranked_candidates_payload' => $this->buildRankedCandidatesPayload($rankedCandidates, $maxRankedCandidates),
            'calendar_context_state' => $this->normalizeCalendarContextState(
                $topCandidate['calendar_context_state'] ?? null
            ),
            'manual_override_reason_code' => null,
            'manual_override_note' => null,
            // Decision lock_effect captures lock intent at decision time; manual_lock_state is derived on effective assignment rows.
            'lock_effect' => SessionAssignmentLockEffect::NONE,
            'supersedes_decision_id' => null,
            'idempotency_key' => $subjectContext['idempotency_key'] ?? null,
            'actor_type' => $actorType,
            'actor_id' => $subjectContext['actor_id'] ?? null,
            'created_at' => $createdAt,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rankedCandidates
     * @return list<array<string, mixed>>
     */
    protected function trimRankedCandidates(array $rankedCandidates, int $maxRankedCandidates): array
    {
        return array_values(array_slice($rankedCandidates, 0, $maxRankedCandidates));
    }

    /**
     * @param int|string|null $selectedSessionId
     */
    protected function assertDecisionClassificationConsistency(
        SessionAssignmentDecisionType $decisionType,
        int|string|null $selectedSessionId
    ): void {
        if (in_array($decisionType, [
            SessionAssignmentDecisionType::AUTO_ASSIGN,
            SessionAssignmentDecisionType::PROPOSE,
        ], true) && $selectedSessionId === null) {
            throw new InvalidArgumentException(
                'Classifier output is inconsistent: selected_session_id is required for auto_assign/propose.'
            );
        }

        if ($decisionType === SessionAssignmentDecisionType::NO_MATCH && $selectedSessionId !== null) {
            throw new InvalidArgumentException(
                'Classifier output is inconsistent: selected_session_id must be null for no_match.'
            );
        }

        if (! in_array($decisionType, [
            SessionAssignmentDecisionType::AUTO_ASSIGN,
            SessionAssignmentDecisionType::PROPOSE,
            SessionAssignmentDecisionType::NO_MATCH,
        ], true)) {
            throw new InvalidArgumentException(
                "Classifier output is inconsistent: unsupported decision_type [{$decisionType->value}] for automated matching."
            );
        }
    }

    /**
     * @param array<string, mixed> $subjectContext
     * @param array<string, mixed> $classification
     * @param array<string, mixed>|null $topCandidate
     * @return array<string, mixed>
     */
    protected function buildEvidencePayload(
        array $subjectContext,
        array $classification,
        ?array $topCandidate
    ): array {
        return [
            'matching_summary' => [
                'decision_reason_code' => (string) ($classification['reason_code'] ?? 'unknown'),
                'ambiguity_detected' => (bool) ($classification['ambiguity_detected'] ?? false),
                'competing_session_id' => $classification['competing_session_id'] ?? null,
                'subject_type' => $subjectContext['subject_type'] instanceof SessionAssociationSubjectType
                    ? $subjectContext['subject_type']->value
                    : (string) $subjectContext['subject_type'],
                'subject_id' => (string) $subjectContext['subject_id'],
            ],
            'subject_signals' => [
                'capture_at_utc' => $subjectContext['capture_at_utc'] ?? null,
                'gps_present' => isset($subjectContext['gps'])
                    || (isset($subjectContext['gps_lat']) && isset($subjectContext['gps_lng'])),
                'session_type_hint' => $subjectContext['session_type_hint'] ?? null,
                'job_type_hint' => $subjectContext['job_type_hint'] ?? null,
                'title_hint' => $subjectContext['title_hint'] ?? null,
            ],
            'top_candidate' => $topCandidate === null
                ? null
                : [
                    'session_id' => $topCandidate['session_id'] ?? null,
                    'score' => $topCandidate['score'] ?? null,
                    'confidence_tier' => $topCandidate['confidence_tier'] instanceof SessionMatchConfidenceTier
                        ? $topCandidate['confidence_tier']->value
                        : (is_string($topCandidate['confidence_tier'] ?? null) ? $topCandidate['confidence_tier'] : null),
                    'buffer_class' => $topCandidate['buffer_class'] ?? null,
                    'minutes_from_planned_start' => $topCandidate['minutes_from_planned_start'] ?? null,
                    'distance_meters' => $topCandidate['distance_meters'] ?? null,
                    'evidence' => $topCandidate['evidence_payload'] ?? null,
                ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rankedCandidates
     * @return list<array<string, mixed>>
     */
    protected function buildRankedCandidatesPayload(array $rankedCandidates, int $maxRankedCandidates): array
    {
        $limited = array_slice($rankedCandidates, 0, $maxRankedCandidates);

        return array_values(array_map(
            static function (array $candidate, int $index): array {
                $tier = $candidate['confidence_tier'] ?? null;
                if ($tier instanceof SessionMatchConfidenceTier) {
                    $tier = $tier->value;
                }

                return [
                    'rank_position' => $index + 1,
                    'session_id' => $candidate['session_id'] ?? null,
                    'candidate_score' => $candidate['score'] ?? null,
                    'confidence_tier' => is_string($tier) ? $tier : null,
                    'buffer_class' => $candidate['buffer_class'] ?? null,
                    'time_delta_minutes' => $candidate['minutes_from_planned_start'] ?? null,
                    'distance_meters' => $candidate['distance_meters'] ?? null,
                ];
            },
            $limited,
            array_keys($limited)
        ));
    }

    /**
     * @param mixed $value
     */
    protected function normalizeTriggerSource(mixed $value): string
    {
        $triggerSource = is_string($value) ? trim($value) : '';
        $allowed = ['ingest_batch', 'post_canonicalization', 'manual_override', 'manual_reprocess', 'api'];

        return in_array($triggerSource, $allowed, true) ? $triggerSource : 'ingest_batch';
    }

    /**
     * @param mixed $value
     */
    protected function normalizeActorType(mixed $value): string
    {
        $actorType = is_string($value) ? trim($value) : '';

        return in_array($actorType, ['system', 'user'], true) ? $actorType : 'system';
    }

    /**
     * @param mixed $value
     */
    protected function normalizeCalendarContextState(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $state = strtolower(trim($value));
        $allowed = ['normal', 'stale', 'conflict', 'sync_error'];

        return in_array($state, $allowed, true) ? $state : null;
    }

    /**
     * @param array<string, mixed> $subjectContext
     */
    protected function resolveCreatedAt(array $subjectContext, bool $allowCreatedAtOverride): string
    {
        if (! $allowCreatedAtOverride) {
            return now('UTC')->toISOString();
        }

        $provided = $subjectContext['created_at'] ?? null;
        if (! is_string($provided) || trim($provided) === '') {
            return now('UTC')->toISOString();
        }

        try {
            return Carbon::parse($provided)->utc()->toISOString();
        } catch (\Throwable) {
            return now('UTC')->toISOString();
        }
    }
}
