<?php

namespace ProPhoto\Ingest\Services\Matching;

use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;

class SessionMatchDecisionClassifier
{
    public function __construct(
        protected float $autoAssignThreshold = 0.85,
        protected float $proposalThreshold = 0.55,
        protected float $ambiguityDelta = 0.05,
        protected float $ambiguitySecondCandidateMinRatio = 0.90
    ) {}

    /**
     * Classifier policy (v1):
     * - Auto-assign only when top candidate is above auto threshold and is a clear winner.
     * - Propose when confidence is medium-or-better, or when ambiguous competition exists.
     * - No-match when no candidate is viable for proposal.
     *
     * Final confidence tier is derived from the final top score in this classifier.
     * Any candidate-provided confidence tier is treated as informational only.
     *
     * @param list<array<string, mixed>> $rankedCandidates
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function classify(array $rankedCandidates, array $options = []): array
    {
        $autoAssignThreshold = (float) ($options['auto_assign_threshold'] ?? $this->autoAssignThreshold);
        $proposalThreshold = (float) ($options['proposal_threshold'] ?? $this->proposalThreshold);
        $ambiguityDelta = (float) ($options['ambiguity_delta'] ?? $this->ambiguityDelta);
        $ambiguitySecondCandidateMinRatio = (float) ($options['ambiguity_second_candidate_min_ratio']
            ?? $this->ambiguitySecondCandidateMinRatio);

        $topCandidate = $rankedCandidates[0] ?? null;
        if (! is_array($topCandidate)) {
            return [
                'decision_type' => SessionAssignmentDecisionType::NO_MATCH,
                'selected_session_id' => null,
                'confidence_tier' => SessionMatchConfidenceTier::LOW,
                'confidence_score' => null,
                'reason_code' => 'no_viable_candidates',
                'ambiguity_detected' => false,
                'competing_session_id' => null,
            ];
        }

        $topScore = (float) ($topCandidate['score'] ?? 0.0);
        $topTier = $this->deriveConfidenceTier($topScore, $autoAssignThreshold, $proposalThreshold);

        $ambiguityResult = $this->ambiguityCheck(
            rankedCandidates: $rankedCandidates,
            proposalThreshold: $proposalThreshold,
            ambiguityDelta: $ambiguityDelta,
            ambiguitySecondCandidateMinRatio: $ambiguitySecondCandidateMinRatio
        );

        if ($topScore >= $autoAssignThreshold && ! $ambiguityResult['ambiguous']) {
            return [
                'decision_type' => SessionAssignmentDecisionType::AUTO_ASSIGN,
                'selected_session_id' => $topCandidate['session_id'],
                'confidence_tier' => $topTier,
                'confidence_score' => $topScore,
                'reason_code' => 'high_confidence_clear_winner',
                'ambiguity_detected' => false,
                'competing_session_id' => null,
            ];
        }

        if ($topScore >= $proposalThreshold) {
            return [
                'decision_type' => SessionAssignmentDecisionType::PROPOSE,
                'selected_session_id' => $topCandidate['session_id'],
                'confidence_tier' => $topTier,
                'confidence_score' => $topScore,
                'reason_code' => $ambiguityResult['ambiguous']
                    ? ($topScore >= $autoAssignThreshold
                        ? 'high_confidence_ambiguous_competition'
                        : 'medium_confidence_ambiguous_competition')
                    : 'medium_confidence_requires_review',
                'ambiguity_detected' => (bool) $ambiguityResult['ambiguous'],
                'competing_session_id' => $ambiguityResult['competing_session_id'],
            ];
        }

        return [
            'decision_type' => SessionAssignmentDecisionType::NO_MATCH,
            'selected_session_id' => null,
            'confidence_tier' => SessionMatchConfidenceTier::LOW,
            'confidence_score' => $topScore,
            'reason_code' => 'below_proposal_threshold',
            'ambiguity_detected' => false,
            'competing_session_id' => null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rankedCandidates
     * @return array{ambiguous: bool, competing_session_id: int|string|null}
     */
    protected function ambiguityCheck(
        array $rankedCandidates,
        float $proposalThreshold,
        float $ambiguityDelta,
        float $ambiguitySecondCandidateMinRatio
    ): array {
        $topCandidate = $rankedCandidates[0] ?? null;
        $secondCandidate = $rankedCandidates[1] ?? null;

        if (! is_array($topCandidate) || ! is_array($secondCandidate)) {
            return ['ambiguous' => false, 'competing_session_id' => null];
        }

        $topScore = (float) ($topCandidate['score'] ?? 0.0);
        $secondScore = (float) ($secondCandidate['score'] ?? 0.0);

        if ($secondScore < $proposalThreshold) {
            return ['ambiguous' => false, 'competing_session_id' => null];
        }

        if ($topScore <= 0.0) {
            return ['ambiguous' => false, 'competing_session_id' => null];
        }

        $relativeViability = $secondScore / $topScore;
        if ($relativeViability < $ambiguitySecondCandidateMinRatio) {
            return ['ambiguous' => false, 'competing_session_id' => null];
        }

        $delta = abs($topScore - $secondScore);
        if ($delta > $ambiguityDelta) {
            return ['ambiguous' => false, 'competing_session_id' => null];
        }

        return [
            'ambiguous' => true,
            'competing_session_id' => $secondCandidate['session_id'] ?? null,
        ];
    }

    /**
     */
    protected function deriveConfidenceTier(
        float $score,
        float $autoAssignThreshold,
        float $proposalThreshold
    ): SessionMatchConfidenceTier {
        if ($score >= $autoAssignThreshold) {
            return SessionMatchConfidenceTier::HIGH;
        }

        if ($score >= $proposalThreshold) {
            return SessionMatchConfidenceTier::MEDIUM;
        }

        return SessionMatchConfidenceTier::LOW;
    }
}
