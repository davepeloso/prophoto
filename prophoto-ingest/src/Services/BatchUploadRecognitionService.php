<?php

namespace ProPhoto\Ingest\Services;

use InvalidArgumentException;
use ProPhoto\Ingest\Services\Matching\SessionMatchCandidateGenerator;
use ProPhoto\Ingest\Services\Matching\SessionMatchScoringService;

/**
 * Ingest-local upload recognition service (v1).
 *
 * Boundary: detection-only, pre-mutation, and non-canonical. Output is guidance data for
 * ingest flow/UI and does not imply any persisted assignment or system state change.
 */
class BatchUploadRecognitionService
{
    /**
     * Fixed v1 suggested next actions.
     *
     * @var list<string>
     */
    protected const FIXED_NEXT_ACTIONS = [
        'Cull now',
        'Continue to delivery',
        'Review match / session context',
    ];
    protected const MAX_LOW_CONFIDENCE_CANDIDATES = 3;

    protected const OUTCOME_HIGH_CONFIDENCE_MATCH = 'high-confidence-match';
    protected const OUTCOME_LOW_CONFIDENCE_CANDIDATES = 'low-confidence-candidates';
    protected const OUTCOME_NO_VIABLE_CANDIDATES = 'no-viable-candidates';

    protected const TIER_HIGH_CONFIDENCE = 'high-confidence';
    protected const TIER_LOW_CONFIDENCE = 'low-confidence';

    public function __construct(
        protected SessionMatchCandidateGenerator $candidateGenerator,
        protected SessionMatchScoringService $scoringService
    ) {}

    /**
     * @param array<string, mixed> $normalizedMetadataSnapshot
     * @param list<array<string, mixed>> $sessionContextSnapshots
     * @param array<string, mixed> $options
     * @return array{
     *     outcome_status: string,
     *     primary_candidate: array<string, mixed>|null,
     *     confidence: array{tier: string|null, score: float|null},
     *     low_confidence_candidates: list<array<string, mixed>>,
     *     suggested_next_actions: list<string>
     * }
     */
    public function recognizeBatch(
        array $normalizedMetadataSnapshot,
        array $sessionContextSnapshots,
        array $options = []
    ): array {
        // Optional by design. Empty list is valid; malformed entries are not.
        $this->assertValidSessionContextSnapshots($sessionContextSnapshots);

        $autoAssignThreshold = (float) ($options['auto_assign_threshold']
            ?? $this->configFloat('prophoto-ingest.session_association.auto_assign_threshold', 0.85));

        $candidates = $this->candidateGenerator->generate(
            subjectContext: $normalizedMetadataSnapshot,
            sessionContexts: $sessionContextSnapshots,
            options: [
                'max_candidate_distance_minutes' => $options['max_candidate_distance_minutes']
                    ?? $this->configInt('prophoto-ingest.matching.max_candidate_distance_minutes', 720),
            ]
        );

        $scoredCandidates = $this->scoringService->scoreCandidates(
            subjectContext: $normalizedMetadataSnapshot,
            candidates: $candidates,
            options: [
                'high_threshold' => $autoAssignThreshold,
                'medium_threshold' => (float) ($options['proposal_threshold']
                    ?? $this->configFloat('prophoto-ingest.session_association.proposal_threshold', 0.55)),
            ]
        );

        $rankedCandidates = $this->rankCandidates($scoredCandidates);
        $topCandidate = $rankedCandidates[0] ?? null;
        $topScore = is_array($topCandidate) && isset($topCandidate['score'])
            ? (float) $topCandidate['score']
            : null;

        $isHighConfidence = is_array($topCandidate)
            && $topScore !== null
            && $topScore >= $autoAssignThreshold;

        $outcomeStatus = $rankedCandidates === []
            ? self::OUTCOME_NO_VIABLE_CANDIDATES
            : ($isHighConfidence
                ? self::OUTCOME_HIGH_CONFIDENCE_MATCH
                : self::OUTCOME_LOW_CONFIDENCE_CANDIDATES);

        $primaryCandidate = $isHighConfidence && is_array($topCandidate)
            ? $this->buildCandidateView($topCandidate, self::TIER_HIGH_CONFIDENCE)
            : null;

        $lowConfidenceCandidates = $outcomeStatus === self::OUTCOME_LOW_CONFIDENCE_CANDIDATES
            ? $this->buildLowConfidenceCandidates(
                rankedCandidates: $rankedCandidates,
                // Exclude only when a primary candidate exists; low-confidence outcomes have no primary.
                // This keeps the top-ranked low-confidence candidate visible in the candidate list.
                excludeSessionId: $primaryCandidate !== null
                    ? (string) ($topCandidate['session_id'] ?? '')
                    : ''
            )
            : [];

        $confidenceTier = match ($outcomeStatus) {
            self::OUTCOME_HIGH_CONFIDENCE_MATCH => self::TIER_HIGH_CONFIDENCE,
            self::OUTCOME_LOW_CONFIDENCE_CANDIDATES => self::TIER_LOW_CONFIDENCE,
            self::OUTCOME_NO_VIABLE_CANDIDATES => null,
        };
        $confidenceScore = $outcomeStatus === self::OUTCOME_NO_VIABLE_CANDIDATES
            ? null
            : $topScore;

        return [
            'outcome_status' => $outcomeStatus,
            'primary_candidate' => $primaryCandidate,
            'confidence' => [
                'tier' => $confidenceTier,
                'score' => $confidenceScore,
            ],
            'low_confidence_candidates' => $lowConfidenceCandidates,
            'suggested_next_actions' => self::FIXED_NEXT_ACTIONS,
        ];
    }

    /**
     * @param list<array<string, mixed>> $candidates
     * @return list<array<string, mixed>>
     */
    protected function rankCandidates(array $candidates): array
    {
        // Deterministic ordering guarantees identical output ordering for identical inputs:
        // score desc -> buffer priority -> planned-start delta -> distance -> session_id lexical.
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
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    protected function buildCandidateView(array $candidate, string $confidenceTier): array
    {
        $sessionId = (string) ($candidate['session_id'] ?? '');
        $displayLabel = trim((string) ($candidate['title'] ?? ''));
        if ($displayLabel === '') {
            $displayLabel = $sessionId === ''
                ? 'Unidentified session'
                : "Session {$sessionId}";
        }

        return [
            'session_id' => $sessionId,
            'display_label' => $displayLabel,
            // Candidate tier is retained for row-level rendering and intentionally mirrors
            // top-level confidence semantics in v1.
            'confidence_tier' => $confidenceTier,
            'score' => isset($candidate['score']) ? (float) $candidate['score'] : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rankedCandidates
     * @return list<array<string, mixed>>
     */
    protected function buildLowConfidenceCandidates(array $rankedCandidates, string $excludeSessionId = ''): array
    {
        if ($excludeSessionId !== '') {
            // Future-safe guard: if primary-candidate logic evolves, never duplicate it in low-confidence list.
            $rankedCandidates = array_values(array_filter(
                $rankedCandidates,
                static fn (array $candidate): bool => (string) ($candidate['session_id'] ?? '') !== $excludeSessionId
            ));
        }

        // Max 3 enforced here, do not move upstream.
        $trimmed = array_slice($rankedCandidates, 0, self::MAX_LOW_CONFIDENCE_CANDIDATES);

        return array_values(array_map(
            fn (array $candidate): array => $this->buildCandidateView($candidate, self::TIER_LOW_CONFIDENCE),
            $trimmed
        ));
    }

    protected function configFloat(string $key, float $default): float
    {
        if (! function_exists('config')) {
            return $default;
        }

        try {
            $value = config($key, $default);
        } catch (\Throwable) {
            return $default;
        }

        return is_numeric($value) ? (float) $value : $default;
    }

    protected function configInt(string $key, int $default): int
    {
        if (! function_exists('config')) {
            return $default;
        }

        try {
            $value = config($key, $default);
        } catch (\Throwable) {
            return $default;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param list<mixed> $sessionContextSnapshots
     */
    protected function assertValidSessionContextSnapshots(array $sessionContextSnapshots): void
    {
        if (! array_is_list($sessionContextSnapshots)) {
            throw new InvalidArgumentException(
                'session_context_snapshots must be a list with sequential integer keys.'
            );
        }

        foreach ($sessionContextSnapshots as $index => $snapshot) {
            if (! is_array($snapshot)) {
                throw new InvalidArgumentException(
                    "session_context_snapshots[{$index}] must be an array."
                );
            }
        }
    }
}
