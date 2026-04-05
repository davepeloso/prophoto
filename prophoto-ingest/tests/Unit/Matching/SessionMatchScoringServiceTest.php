<?php

namespace ProPhoto\Ingest\Tests\Unit\Matching;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Ingest\Services\Matching\SessionMatchScoringService;

class SessionMatchScoringServiceTest extends TestCase
{
    protected SessionMatchScoringService $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new SessionMatchScoringService();
    }

    // -------------------------------------------------------------------------
    // Score ordering: core > buffer > outside
    // -------------------------------------------------------------------------

    public function test_core_window_candidate_scores_higher_than_buffer_candidate(): void
    {
        $subject = $this->subjectContext('2026-03-15T15:00:00Z'); // inside core

        $coreCandidate = $this->candidate([
            'session_id' => 'core',
            'window_start_utc' => '2026-03-15T14:00:00Z',
            'window_end_utc' => '2026-03-15T16:00:00Z',
        ]);

        // Capture at 13:30 — inside buffer (30-min travel before), outside core
        $bufferSubject = $this->subjectContext('2026-03-15T13:30:00Z');
        $bufferCandidate = $this->candidate([
            'session_id' => 'buffer',
            'window_start_utc' => '2026-03-15T14:00:00Z',
            'window_end_utc' => '2026-03-15T16:00:00Z',
            'travel_buffer_before_minutes' => 60,
        ]);

        $coreScored = $this->scorer->scoreCandidates($subject, [$coreCandidate]);
        $bufferScored = $this->scorer->scoreCandidates($bufferSubject, [$bufferCandidate]);

        $this->assertGreaterThan($bufferScored[0]['score'], $coreScored[0]['score']);
    }

    public function test_buffer_candidate_scores_higher_than_outside_window_candidate(): void
    {
        $windowStart = '2026-03-15T14:00:00Z';
        $windowEnd = '2026-03-15T16:00:00Z';

        // Buffer: 13:30 with 60-min travel_before → inside buffer
        $bufferScored = $this->scorer->scoreCandidates(
            $this->subjectContext('2026-03-15T13:30:00Z'),
            [$this->candidate([
                'session_id' => 'buffer',
                'window_start_utc' => $windowStart,
                'window_end_utc' => $windowEnd,
                'travel_buffer_before_minutes' => 60,
            ])]
        );

        // Outside: 10:00 with no buffer → well outside effective window
        $outsideScored = $this->scorer->scoreCandidates(
            $this->subjectContext('2026-03-15T10:00:00Z'),
            [$this->candidate([
                'session_id' => 'outside',
                'window_start_utc' => $windowStart,
                'window_end_utc' => $windowEnd,
            ])]
        );

        $this->assertGreaterThan($outsideScored[0]['score'], $bufferScored[0]['score']);
    }

    // -------------------------------------------------------------------------
    // Location scoring
    // -------------------------------------------------------------------------

    public function test_nearby_location_scores_higher_than_missing_location(): void
    {
        $subject = $this->subjectContext('2026-03-15T15:00:00Z');
        $candidate = $this->candidate();

        // Same capture time — only difference is location presence
        $withLocation = array_merge($subject, [
            'capture_lat' => 37.7749,
            'capture_lng' => -122.4194,
        ]);

        $nearCandidate = array_merge($candidate, [
            'location_lat' => 37.7750, // ~10 m away → same_venue
            'location_lng' => -122.4194,
        ]);

        $scoredWithLocation = $this->scorer->scoreCandidates($withLocation, [$nearCandidate]);
        $scoredMissingLocation = $this->scorer->scoreCandidates($subject, [$candidate]);

        $this->assertGreaterThan(
            $scoredMissingLocation[0]['score'],
            $scoredWithLocation[0]['score']
        );
    }

    public function test_same_venue_location_scores_1_point_0(): void
    {
        $subject = array_merge($this->subjectContext('2026-03-15T15:00:00Z'), [
            'capture_lat' => 37.7749,
            'capture_lng' => -122.4194,
        ]);
        $candidate = array_merge($this->candidate(), [
            'location_lat' => 37.7749,
            'location_lng' => -122.4194,
        ]);

        $scored = $this->scorer->scoreCandidates($subject, [$candidate]);
        $locationEvidence = $scored[0]['evidence_payload']['location'];

        $this->assertSame('same_venue', $locationEvidence['distance_bucket']);
        $this->assertEqualsWithDelta(1.00, $locationEvidence['score'], 0.0001);
    }

    // -------------------------------------------------------------------------
    // Semantic scoring
    // -------------------------------------------------------------------------

    public function test_exact_session_type_match_improves_score(): void
    {
        $baseSubject = $this->subjectContext('2026-03-15T15:00:00Z');
        $candidate = $this->candidate();

        $withHint = array_merge($baseSubject, ['session_type_hint' => 'portrait']);
        $matchingCandidate = array_merge($candidate, ['session_type' => 'portrait']);
        $nonMatchingCandidate = array_merge($candidate, ['session_type' => 'wedding']);

        $matchScored = $this->scorer->scoreCandidates($withHint, [$matchingCandidate]);
        $noMatchScored = $this->scorer->scoreCandidates($withHint, [$nonMatchingCandidate]);

        $this->assertGreaterThan($noMatchScored[0]['score'], $matchScored[0]['score']);
    }

    public function test_multiple_semantic_matches_score_higher_than_single_match(): void
    {
        $subject = array_merge($this->subjectContext('2026-03-15T15:00:00Z'), [
            'session_type_hint' => 'portrait',
            'job_type_hint' => 'family',
        ]);
        $candidate = $this->candidate();

        $bothMatch = array_merge($candidate, ['session_type' => 'portrait', 'job_type' => 'family']);
        $oneMatch = array_merge($candidate, ['session_type' => 'portrait', 'job_type' => 'corporate']);

        $bothScored = $this->scorer->scoreCandidates($subject, [$bothMatch]);
        $oneScored = $this->scorer->scoreCandidates($subject, [$oneMatch]);

        $this->assertGreaterThan($oneScored[0]['score'], $bothScored[0]['score']);
    }

    public function test_no_semantic_hints_yields_neutral_semantic_score(): void
    {
        $subject = $this->subjectContext('2026-03-15T15:00:00Z'); // no hints
        $scored = $this->scorer->scoreCandidates($subject, [$this->candidate()]);

        $semanticEvidence = $scored[0]['evidence_payload']['semantic'];
        $this->assertEqualsWithDelta(0.50, $semanticEvidence['score'], 0.0001);
        $this->assertSame(0, $semanticEvidence['available_signal_count']);
    }

    // -------------------------------------------------------------------------
    // Ambiguity: close scores are preserved, not collapsed
    // -------------------------------------------------------------------------

    public function test_similar_candidates_preserve_distinct_scores(): void
    {
        $subject = $this->subjectContext('2026-03-15T15:30:00Z'); // 30 min after start

        // Slightly closer in time
        $closer = $this->candidate([
            'session_id' => 'closer',
            'window_start_utc' => '2026-03-15T15:00:00Z',
            'window_end_utc' => '2026-03-15T17:00:00Z',
        ]);
        // Slightly further
        $further = $this->candidate([
            'session_id' => 'further',
            'window_start_utc' => '2026-03-15T16:00:00Z',
            'window_end_utc' => '2026-03-15T18:00:00Z',
        ]);

        $results = $this->scorer->scoreCandidates($subject, [$closer, $further]);
        $scores = array_column($results, 'score', 'session_id');

        $this->assertGreaterThan($scores['further'], $scores['closer']);
        // Scores are distinct — not collapsed to the same value
        $this->assertNotEquals($scores['closer'], $scores['further']);
    }

    // -------------------------------------------------------------------------
    // Hard downgrade: conflict/sync_error outside core window
    // -------------------------------------------------------------------------

    public function test_conflict_calendar_state_outside_core_window_applies_downgrade(): void
    {
        $subject = $this->subjectContext('2026-03-15T13:30:00Z'); // in buffer, not core

        $conflictCandidate = $this->candidate([
            'window_start_utc' => '2026-03-15T14:00:00Z',
            'window_end_utc' => '2026-03-15T16:00:00Z',
            'travel_buffer_before_minutes' => 60,
            'calendar_context_state' => 'conflict',
        ]);
        $normalCandidate = $this->candidate([
            'window_start_utc' => '2026-03-15T14:00:00Z',
            'window_end_utc' => '2026-03-15T16:00:00Z',
            'travel_buffer_before_minutes' => 60,
            'calendar_context_state' => 'normal',
        ]);

        $conflictScored = $this->scorer->scoreCandidates($subject, [$conflictCandidate]);
        $normalScored = $this->scorer->scoreCandidates($subject, [$normalCandidate]);

        $this->assertLessThan($normalScored[0]['score'], $conflictScored[0]['score']);
    }

    public function test_conflict_calendar_state_inside_core_window_does_not_apply_downgrade(): void
    {
        $subject = $this->subjectContext('2026-03-15T15:00:00Z'); // inside core

        $conflictCandidate = $this->candidate([
            'window_start_utc' => '2026-03-15T14:00:00Z',
            'window_end_utc' => '2026-03-15T16:00:00Z',
            'calendar_context_state' => 'conflict',
        ]);
        $normalCandidate = $this->candidate([
            'window_start_utc' => '2026-03-15T14:00:00Z',
            'window_end_utc' => '2026-03-15T16:00:00Z',
            'calendar_context_state' => 'normal',
        ]);

        $conflictScored = $this->scorer->scoreCandidates($subject, [$conflictCandidate]);
        $normalScored = $this->scorer->scoreCandidates($subject, [$normalCandidate]);

        // Downgrade not applied in core window — scores should differ only by
        // the operational evidence calendar modifier, not the hard 0.90 multiplier.
        $operationalConflict = $conflictScored[0]['evidence_payload']['operational']['score'];
        $operationalNormal = $normalScored[0]['evidence_payload']['operational']['score'];
        $this->assertLessThan($operationalNormal, $operationalConflict);
    }

    // -------------------------------------------------------------------------
    // Score clamping: always 0.0–1.0
    // -------------------------------------------------------------------------

    public function test_score_is_always_clamped_between_0_and_1(): void
    {
        $cases = [
            // Core window hit
            [$this->subjectContext('2026-03-15T15:00:00Z'), $this->candidate()],
            // Far outside window
            [$this->subjectContext('2026-03-10T00:00:00Z'), $this->candidate()],
            // Missing capture time
            [[], $this->candidate()],
            // All signals present and matching
            [
                array_merge($this->subjectContext('2026-03-15T15:00:00Z'), [
                    'capture_lat' => 37.7749,
                    'capture_lng' => -122.4194,
                    'session_type_hint' => 'portrait',
                ]),
                array_merge($this->candidate(), [
                    'location_lat' => 37.7749,
                    'location_lng' => -122.4194,
                    'session_type' => 'portrait',
                ]),
            ],
        ];

        foreach ($cases as [$subject, $candidate]) {
            $scored = $this->scorer->scoreCandidates($subject, [$candidate]);
            $score = $scored[0]['score'];
            $this->assertGreaterThanOrEqual(0.0, $score, "Score below 0.0: $score");
            $this->assertLessThanOrEqual(1.0, $score, "Score above 1.0: $score");
        }
    }

    // -------------------------------------------------------------------------
    // Confidence tier boundaries
    // -------------------------------------------------------------------------

    public function test_score_at_high_threshold_yields_high_tier(): void
    {
        $scored = $this->scorer->scoreCandidates(
            $this->subjectContext('2026-03-15T15:00:00Z'),
            [$this->candidate()],
            ['high_threshold' => 0.85, 'medium_threshold' => 0.55]
        );

        // Core window → time score 1.0 → weighted ≈ 0.55 + 0.10 + 0.075 + 0.10 = 0.825
        // That's below HIGH. Force a subject with matching location and session_type.
        $richSubject = array_merge($this->subjectContext('2026-03-15T15:00:00Z'), [
            'capture_lat' => 37.7749,
            'capture_lng' => -122.4194,
            'session_type_hint' => 'portrait',
        ]);
        $richCandidate = array_merge($this->candidate(), [
            'location_lat' => 37.7749,
            'location_lng' => -122.4194,
            'session_type' => 'portrait',
        ]);

        $richScored = $this->scorer->scoreCandidates($richSubject, [$richCandidate], [
            'high_threshold' => 0.85,
            'medium_threshold' => 0.55,
        ]);

        $this->assertGreaterThanOrEqual(0.85, $richScored[0]['score']);
        $this->assertSame(SessionMatchConfidenceTier::HIGH, $richScored[0]['confidence_tier']);
    }

    public function test_score_just_below_high_threshold_yields_medium_tier(): void
    {
        // Score a buffer candidate — will be below HIGH but above MEDIUM.
        $scored = $this->scorer->scoreCandidates(
            $this->subjectContext('2026-03-15T13:30:00Z'), // in buffer
            [$this->candidate([
                'window_start_utc' => '2026-03-15T14:00:00Z',
                'window_end_utc' => '2026-03-15T16:00:00Z',
                'travel_buffer_before_minutes' => 60,
            ])],
            ['high_threshold' => 0.85, 'medium_threshold' => 0.55]
        );

        $score = $scored[0]['score'];
        $this->assertGreaterThanOrEqual(0.55, $score);
        $this->assertLessThan(0.85, $score);
        $this->assertSame(SessionMatchConfidenceTier::MEDIUM, $scored[0]['confidence_tier']);
    }

    public function test_score_below_medium_threshold_yields_low_tier(): void
    {
        // Far outside window → LOW
        $scored = $this->scorer->scoreCandidates(
            $this->subjectContext('2026-03-10T00:00:00Z'), // days away
            [$this->candidate()],
            ['high_threshold' => 0.85, 'medium_threshold' => 0.55]
        );

        $this->assertSame(SessionMatchConfidenceTier::LOW, $scored[0]['confidence_tier']);
    }

    // -------------------------------------------------------------------------
    // Travel buffer: canonical before/after vs legacy
    // -------------------------------------------------------------------------

    public function test_canonical_travel_buffer_before_extends_effective_start_for_scoring(): void
    {
        // Capture at 13:30 — 30 min before window start (14:00).
        // travel_buffer_before=60 → effective start 13:00 → capture is in buffer.
        $scored = $this->scorer->scoreCandidates(
            $this->subjectContext('2026-03-15T13:30:00Z'),
            [$this->candidate([
                'window_start_utc' => '2026-03-15T14:00:00Z',
                'window_end_utc' => '2026-03-15T16:00:00Z',
                'travel_buffer_before_minutes' => 60,
                'travel_buffer_after_minutes' => 0,
            ])]
        );

        $this->assertSame('buffer', $scored[0]['buffer_class']);
    }

    public function test_canonical_travel_buffer_after_extends_effective_end_for_scoring(): void
    {
        // Capture at 16:30 — 30 min after window end (16:00).
        // travel_buffer_after=60 → effective end 17:00 → capture is in buffer.
        $scored = $this->scorer->scoreCandidates(
            $this->subjectContext('2026-03-15T16:30:00Z'),
            [$this->candidate([
                'window_start_utc' => '2026-03-15T14:00:00Z',
                'window_end_utc' => '2026-03-15T16:00:00Z',
                'travel_buffer_before_minutes' => 0,
                'travel_buffer_after_minutes' => 60,
            ])]
        );

        $this->assertSame('buffer', $scored[0]['buffer_class']);
    }

    public function test_legacy_travel_buffer_minutes_still_works_when_canonical_absent(): void
    {
        // Capture at 13:30 — uses legacy key only, no canonical keys present.
        $legacyOnlyCandidate = $this->candidate([
            'window_start_utc' => '2026-03-15T14:00:00Z',
            'window_end_utc' => '2026-03-15T16:00:00Z',
            'travel_buffer_minutes' => 60, // legacy only
        ]);
        // Explicitly remove canonical keys so legacy fallback is exercised.
        unset($legacyOnlyCandidate['travel_buffer_before_minutes'], $legacyOnlyCandidate['travel_buffer_after_minutes']);

        $scored = $this->scorer->scoreCandidates(
            $this->subjectContext('2026-03-15T13:30:00Z'),
            [$legacyOnlyCandidate]
        );

        $this->assertSame('buffer', $scored[0]['buffer_class']);
    }

    public function test_canonical_travel_buffer_wins_over_legacy_in_scoring(): void
    {
        // canonical before=30, after=10, legacy=0.
        // Capture at 13:40 → 20 min before window start.
        // With canonical before=30 → effective start 13:30 → capture inside buffer.
        // With legacy=0 → effective start 14:00 → capture outside.
        $scored = $this->scorer->scoreCandidates(
            $this->subjectContext('2026-03-15T13:40:00Z'),
            [$this->candidate([
                'window_start_utc' => '2026-03-15T14:00:00Z',
                'window_end_utc' => '2026-03-15T16:00:00Z',
                'travel_buffer_before_minutes' => 30,
                'travel_buffer_after_minutes' => 10,
                'travel_buffer_minutes' => 0, // legacy — must be ignored
            ])]
        );

        $this->assertSame('buffer', $scored[0]['buffer_class']);
    }

    // -------------------------------------------------------------------------
    // Missing capture time: neutral default
    // -------------------------------------------------------------------------

    public function test_missing_capture_time_yields_neutral_time_score(): void
    {
        $scored = $this->scorer->scoreCandidates([], [$this->candidate()]);

        $timeEvidence = $scored[0]['evidence_payload']['time'];
        $this->assertEqualsWithDelta(0.40, $timeEvidence['score'], 0.0001);
        $this->assertSame('unknown', $timeEvidence['buffer_class']);
    }

    // -------------------------------------------------------------------------
    // Operational evidence policy
    // -------------------------------------------------------------------------

    public function test_unknown_calendar_state_uses_conservative_default(): void
    {
        $scored = $this->scorer->scoreCandidates(
            $this->subjectContext('2026-03-15T15:00:00Z'),
            [$this->candidate(['calendar_context_state' => 'totally_unknown_state'])]
        );

        $operationalEvidence = $scored[0]['evidence_payload']['operational'];
        // Unknown calendar state → 0.75 × status_weight (1.00 for confirmed) = 0.75
        $this->assertEqualsWithDelta(0.75, $operationalEvidence['score'], 0.0001);
    }

    public function test_completed_session_still_scores_reasonably_for_backfilled_ingest(): void
    {
        $scored = $this->scorer->scoreCandidates(
            $this->subjectContext('2026-03-15T15:00:00Z'),
            [$this->candidate(['status' => 'completed', 'calendar_context_state' => 'normal'])]
        );

        $operationalEvidence = $scored[0]['evidence_payload']['operational'];
        // completed (0.65) × normal (1.00) = 0.65
        $this->assertEqualsWithDelta(0.65, $operationalEvidence['score'], 0.0001);
        // Overall score should still be meaningful (not penalized to near-zero)
        $this->assertGreaterThan(0.40, $scored[0]['score']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function candidate(array $overrides = []): array
    {
        $base = [
            'session_id' => 'session-1',
            'status' => 'confirmed',
            'calendar_context_state' => 'normal',
            'window_start_utc' => '2026-03-15T14:00:00Z',
            'window_end_utc' => '2026-03-15T16:00:00Z',
            'setup_buffer_minutes' => 0,
            'travel_buffer_before_minutes' => 0,
            'travel_buffer_after_minutes' => 0,
            'teardown_buffer_minutes' => 0,
            'session_type' => null,
            'job_type' => null,
            'title' => null,
            'location_lat' => null,
            'location_lng' => null,
        ];

        return array_merge($base, $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function subjectContext(string $captureAtUtc): array
    {
        return ['capture_at_utc' => $captureAtUtc];
    }
}
