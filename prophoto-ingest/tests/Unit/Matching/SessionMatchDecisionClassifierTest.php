<?php

namespace ProPhoto\Ingest\Tests\Unit\Matching;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Ingest\Services\Matching\SessionMatchDecisionClassifier;

class SessionMatchDecisionClassifierTest extends TestCase
{
    protected SessionMatchDecisionClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new SessionMatchDecisionClassifier(
            autoAssignThreshold: 0.85,
            proposalThreshold: 0.55,
            ambiguityDelta: 0.05,
            ambiguitySecondCandidateMinRatio: 0.90
        );
    }

    public function test_top_score_exactly_at_auto_threshold_is_auto_assign_when_not_ambiguous(): void
    {
        $result = $this->classifier->classify([
            $this->candidate('s1', 0.85),
            $this->candidate('s2', 0.70),
        ]);

        $this->assertSame(SessionAssignmentDecisionType::AUTO_ASSIGN, $result['decision_type']);
        $this->assertSame('s1', $result['selected_session_id']);
        $this->assertSame(SessionMatchConfidenceTier::HIGH, $result['confidence_tier']);
        $this->assertSame('high_confidence_clear_winner', $result['reason_code']);
        $this->assertFalse($result['ambiguity_detected']);
    }

    public function test_top_score_exactly_at_proposal_threshold_is_propose(): void
    {
        $result = $this->classifier->classify([
            $this->candidate('s1', 0.55),
            $this->candidate('s2', 0.20),
        ]);

        $this->assertSame(SessionAssignmentDecisionType::PROPOSE, $result['decision_type']);
        $this->assertSame(SessionMatchConfidenceTier::MEDIUM, $result['confidence_tier']);
        $this->assertSame('medium_confidence_requires_review', $result['reason_code']);
        $this->assertFalse($result['ambiguity_detected']);
    }

    public function test_second_score_at_proposal_threshold_and_delta_at_ambiguity_delta_is_ambiguous(): void
    {
        $result = $this->classifier->classify([
            $this->candidate('s1', 0.60),
            $this->candidate('s2', 0.55), // delta=0.05, ratio=0.9166...
        ]);

        $this->assertSame(SessionAssignmentDecisionType::PROPOSE, $result['decision_type']);
        $this->assertTrue($result['ambiguity_detected']);
        $this->assertSame('medium_confidence_ambiguous_competition', $result['reason_code']);
        $this->assertSame('s2', $result['competing_session_id']);
    }

    public function test_second_score_just_below_proposal_threshold_is_not_ambiguous(): void
    {
        $result = $this->classifier->classify([
            $this->candidate('s1', 0.59),
            $this->candidate('s2', 0.549), // below proposal threshold
        ]);

        $this->assertSame(SessionAssignmentDecisionType::PROPOSE, $result['decision_type']);
        $this->assertFalse($result['ambiguity_detected']);
        $this->assertSame('medium_confidence_requires_review', $result['reason_code']);
    }

    public function test_delta_just_above_ambiguity_delta_is_not_ambiguous(): void
    {
        $result = $this->classifier->classify([
            $this->candidate('s1', 0.70),
            $this->candidate('s2', 0.64), // delta=0.06 > 0.05
        ]);

        $this->assertSame(SessionAssignmentDecisionType::PROPOSE, $result['decision_type']);
        $this->assertFalse($result['ambiguity_detected']);
        $this->assertSame('medium_confidence_requires_review', $result['reason_code']);
    }

    public function test_high_confidence_ambiguous_competition_blocks_auto_assign(): void
    {
        $result = $this->classifier->classify([
            $this->candidate('s1', 0.90),
            $this->candidate('s2', 0.86), // delta=0.04, ratio=0.955...
        ]);

        $this->assertSame(SessionAssignmentDecisionType::PROPOSE, $result['decision_type']);
        $this->assertTrue($result['ambiguity_detected']);
        $this->assertSame('high_confidence_ambiguous_competition', $result['reason_code']);
        $this->assertSame(SessionMatchConfidenceTier::HIGH, $result['confidence_tier']);
    }

    public function test_candidate_provided_confidence_tier_is_not_authoritative_for_final_classification(): void
    {
        $result = $this->classifier->classify([
            $this->candidate('s1', 0.70, SessionMatchConfidenceTier::HIGH), // provided HIGH, score is MEDIUM range
            $this->candidate('s2', 0.40),
        ]);

        $this->assertSame(SessionAssignmentDecisionType::PROPOSE, $result['decision_type']);
        $this->assertSame(SessionMatchConfidenceTier::MEDIUM, $result['confidence_tier']);
    }

    public function test_no_viable_candidates_returns_no_match_with_null_confidence_score(): void
    {
        $result = $this->classifier->classify([]);

        $this->assertSame(SessionAssignmentDecisionType::NO_MATCH, $result['decision_type']);
        $this->assertNull($result['confidence_score']);
        $this->assertSame('no_viable_candidates', $result['reason_code']);
    }

    public function test_below_proposal_threshold_returns_no_match_with_top_score_preserved(): void
    {
        $result = $this->classifier->classify([
            $this->candidate('s1', 0.54),
            $this->candidate('s2', 0.30),
        ]);

        $this->assertSame(SessionAssignmentDecisionType::NO_MATCH, $result['decision_type']);
        $this->assertSame(0.54, $result['confidence_score']);
        $this->assertSame('below_proposal_threshold', $result['reason_code']);
    }

    /**
     * @param int|string $sessionId
     * @param float $score
     */
    protected function candidate(
        int|string $sessionId,
        float $score,
        ?SessionMatchConfidenceTier $confidenceTier = null
    ): array {
        return [
            'session_id' => $sessionId,
            'score' => $score,
            'confidence_tier' => $confidenceTier,
        ];
    }
}

