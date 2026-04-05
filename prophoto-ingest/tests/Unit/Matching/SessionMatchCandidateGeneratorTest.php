<?php

namespace ProPhoto\Ingest\Tests\Unit\Matching;

use PHPUnit\Framework\TestCase;
use ProPhoto\Ingest\Services\Matching\SessionMatchCandidateGenerator;

class SessionMatchCandidateGeneratorTest extends TestCase
{
    protected SessionMatchCandidateGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SessionMatchCandidateGenerator();
    }

    // -------------------------------------------------------------------------
    // Normalization failure — dropped candidates
    // -------------------------------------------------------------------------

    public function test_session_with_missing_session_id_is_dropped(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$this->sessionContext(['session_id' => null])]
        );

        $this->assertCount(0, $candidates);
    }

    public function test_session_with_empty_session_id_is_dropped(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$this->sessionContext(['session_id' => ''])]
        );

        $this->assertCount(0, $candidates);
    }

    public function test_session_with_invalid_window_start_is_dropped(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$this->sessionContext(['window_start_utc' => 'not-a-date'])]
        );

        $this->assertCount(0, $candidates);
    }

    public function test_session_with_invalid_window_end_is_dropped(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$this->sessionContext(['window_end_utc' => 'not-a-date'])]
        );

        $this->assertCount(0, $candidates);
    }

    public function test_session_with_missing_window_start_is_dropped(): void
    {
        $session = $this->sessionContext();
        unset($session['window_start_utc']);

        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$session]
        );

        $this->assertCount(0, $candidates);
    }

    public function test_session_with_missing_window_end_is_dropped(): void
    {
        $session = $this->sessionContext();
        unset($session['window_end_utc']);

        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$session]
        );

        $this->assertCount(0, $candidates);
    }

    public function test_session_where_window_end_is_before_window_start_is_dropped(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$this->sessionContext([
                'window_start_utc' => '2026-03-15T16:00:00Z',
                'window_end_utc' => '2026-03-15T14:00:00Z', // reversed
            ])]
        );

        $this->assertCount(0, $candidates);
    }

    // -------------------------------------------------------------------------
    // Status filtering
    // -------------------------------------------------------------------------

    public function test_confirmed_session_is_included(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$this->sessionContext(['status' => 'confirmed'])]
        );

        $this->assertCount(1, $candidates);
        $this->assertSame('confirmed', $candidates[0]['status']);
    }

    public function test_tentative_session_is_included(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$this->sessionContext(['status' => 'tentative'])]
        );

        $this->assertCount(1, $candidates);
    }

    public function test_in_progress_session_is_included(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$this->sessionContext(['status' => 'in_progress'])]
        );

        $this->assertCount(1, $candidates);
    }

    public function test_cancelled_session_is_excluded(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$this->sessionContext(['status' => 'cancelled'])]
        );

        $this->assertCount(0, $candidates);
    }

    public function test_no_show_session_is_excluded(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$this->sessionContext(['status' => 'no_show'])]
        );

        $this->assertCount(0, $candidates);
    }

    public function test_status_comparison_is_case_insensitive(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T14:00:00Z'),
            [$this->sessionContext(['status' => 'Cancelled'])]
        );

        $this->assertCount(0, $candidates);
    }

    // -------------------------------------------------------------------------
    // Max distance filtering
    // -------------------------------------------------------------------------

    public function test_candidate_inside_effective_window_is_included(): void
    {
        // Session: 14:00–16:00, capture at 15:00 → inside window
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T15:00:00Z'),
            [$this->sessionContext([
                'window_start_utc' => '2026-03-15T14:00:00Z',
                'window_end_utc' => '2026-03-15T16:00:00Z',
            ])]
        );

        $this->assertCount(1, $candidates);
        $this->assertSame(0, $candidates[0]['outside_window_minutes']);
        $this->assertSame('within_effective_window', $candidates[0]['candidate_generation_reason']);
    }

    public function test_candidate_outside_window_but_within_max_distance_is_included(): void
    {
        // Session: 14:00–16:00, capture at 17:00 → 60 min outside, max=120
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T17:00:00Z'),
            [$this->sessionContext([
                'window_start_utc' => '2026-03-15T14:00:00Z',
                'window_end_utc' => '2026-03-15T16:00:00Z',
            ])],
            ['max_candidate_distance_minutes' => 120]
        );

        $this->assertCount(1, $candidates);
        $this->assertSame(60, $candidates[0]['outside_window_minutes']);
        $this->assertSame('within_max_distance', $candidates[0]['candidate_generation_reason']);
    }

    public function test_candidate_outside_max_distance_is_excluded(): void
    {
        // Session: 14:00–16:00, capture at 20:00 → 240 min outside, max=120
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T20:00:00Z'),
            [$this->sessionContext([
                'window_start_utc' => '2026-03-15T14:00:00Z',
                'window_end_utc' => '2026-03-15T16:00:00Z',
            ])],
            ['max_candidate_distance_minutes' => 120]
        );

        $this->assertCount(0, $candidates);
    }

    // -------------------------------------------------------------------------
    // Travel buffer before/after
    // -------------------------------------------------------------------------

    public function test_travel_buffer_before_extends_effective_start(): void
    {
        // Session: 14:00–16:00, travel_before=60 → effective start 13:00
        // Capture at 13:30 → inside effective window
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T13:30:00Z'),
            [$this->sessionContext([
                'window_start_utc' => '2026-03-15T14:00:00Z',
                'window_end_utc' => '2026-03-15T16:00:00Z',
                'travel_buffer_before_minutes' => 60,
                'travel_buffer_after_minutes' => 0,
            ])]
        );

        $this->assertCount(1, $candidates);
        $this->assertSame(0, $candidates[0]['outside_window_minutes']);
    }

    public function test_travel_buffer_after_extends_effective_end(): void
    {
        // Session: 14:00–16:00, travel_after=60 → effective end 17:00
        // Capture at 16:30 → inside effective window
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T16:30:00Z'),
            [$this->sessionContext([
                'window_start_utc' => '2026-03-15T14:00:00Z',
                'window_end_utc' => '2026-03-15T16:00:00Z',
                'travel_buffer_before_minutes' => 0,
                'travel_buffer_after_minutes' => 60,
            ])]
        );

        $this->assertCount(1, $candidates);
        $this->assertSame(0, $candidates[0]['outside_window_minutes']);
    }

    public function test_legacy_travel_buffer_minutes_applied_to_both_directions(): void
    {
        // Legacy callers pass travel_buffer_minutes — it should apply before and after.
        // Session: 14:00–16:00, legacy travel=60 → effective 13:00–17:00
        // Capture at 13:30 → inside
        $legacyOnlySession = $this->sessionContext([
            'window_start_utc' => '2026-03-15T14:00:00Z',
            'window_end_utc' => '2026-03-15T16:00:00Z',
            'travel_buffer_minutes' => 60, // legacy key
        ]);
        // Explicitly remove canonical keys so legacy fallback is exercised.
        unset($legacyOnlySession['travel_buffer_before_minutes'], $legacyOnlySession['travel_buffer_after_minutes']);

        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T13:30:00Z'),
            [$legacyOnlySession]
        );

        $this->assertCount(1, $candidates);
        $this->assertSame(60, $candidates[0]['travel_buffer_before_minutes']);
        $this->assertSame(60, $candidates[0]['travel_buffer_after_minutes']);
        $this->assertSame(0, $candidates[0]['outside_window_minutes']);
    }

    public function test_canonical_travel_buffer_keys_take_precedence_over_legacy(): void
    {
        // When canonical before/after keys are present alongside the legacy key,
        // canonical values must win for BOTH directions and the legacy value must be ignored.
        //
        // Setup: canonical before=30, after=10, legacy=60
        //   → effective start: 14:00 - 30 = 13:30
        //   → effective end:   16:00 + 10 = 16:10
        //   → legacy value of 60 must NOT be used for either direction.
        //
        // Capture at 13:45: inside canonical effective start (13:30), proves before=30 wins.
        // Also directly assert both output fields equal the canonical values, not the legacy value.
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T13:45:00Z'),
            [$this->sessionContext([
                'window_start_utc' => '2026-03-15T14:00:00Z',
                'window_end_utc' => '2026-03-15T16:00:00Z',
                'travel_buffer_before_minutes' => 30,
                'travel_buffer_after_minutes' => 10,
                'travel_buffer_minutes' => 60, // legacy — must be ignored when canonicals present
            ])]
        );

        $this->assertCount(1, $candidates);

        // Both output fields must reflect canonical values, not the legacy 60.
        $this->assertSame(30, $candidates[0]['travel_buffer_before_minutes']);
        $this->assertSame(10, $candidates[0]['travel_buffer_after_minutes']);

        // Capture is inside the canonical window → outside_window_minutes must be 0.
        $this->assertSame(0, $candidates[0]['outside_window_minutes']);
    }

    // -------------------------------------------------------------------------
    // Missing capture_at behavior (intentional bypass)
    // -------------------------------------------------------------------------

    public function test_missing_capture_time_surfaces_all_non_terminal_candidates(): void
    {
        // No capture_at_utc → time-window filter skipped.
        // All non-terminal sessions should come through regardless of timing.
        $candidates = $this->generator->generate(
            [], // no subject context at all
            [
                $this->sessionContext(['session_id' => 'S1', 'status' => 'confirmed']),
                $this->sessionContext(['session_id' => 'S2', 'status' => 'tentative']),
                $this->sessionContext(['session_id' => 'S3', 'status' => 'cancelled']), // excluded
            ]
        );

        $this->assertCount(2, $candidates);
        $ids = array_column($candidates, 'session_id');
        $this->assertContains('S1', $ids);
        $this->assertContains('S2', $ids);
        $this->assertNotContains('S3', $ids);
    }

    public function test_missing_capture_time_sets_no_capture_time_reason(): void
    {
        $candidates = $this->generator->generate(
            [],
            [$this->sessionContext()]
        );

        $this->assertCount(1, $candidates);
        $this->assertNull($candidates[0]['outside_window_minutes']);
        $this->assertSame('no_capture_time', $candidates[0]['candidate_generation_reason']);
    }

    public function test_unparseable_capture_time_treated_same_as_missing(): void
    {
        // Invalid timestamp → parseUtc returns null → filter skipped.
        $candidates = $this->generator->generate(
            ['capture_at_utc' => 'not-a-timestamp'],
            [$this->sessionContext()]
        );

        $this->assertCount(1, $candidates);
        $this->assertSame('no_capture_time', $candidates[0]['candidate_generation_reason']);
    }

    // -------------------------------------------------------------------------
    // Output metadata on the normalized candidate
    // -------------------------------------------------------------------------

    public function test_normalized_candidate_contains_expected_fields(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T15:00:00Z'),
            [$this->sessionContext([
                'session_id' => 'S100',
                'session_type' => 'portrait',
                'job_type' => 'family',
                'title' => 'Smith Family Session',
                'status' => 'confirmed',
                'calendar_context_state' => 'normal',
                'setup_buffer_minutes' => 15,
                'travel_buffer_before_minutes' => 30,
                'travel_buffer_after_minutes' => 20,
                'teardown_buffer_minutes' => 10,
            ])]
        );

        $this->assertCount(1, $candidates);
        $c = $candidates[0];

        $this->assertSame('S100', $c['session_id']);
        $this->assertSame('portrait', $c['session_type']);
        $this->assertSame('family', $c['job_type']);
        $this->assertSame('Smith Family Session', $c['title']);
        $this->assertSame('confirmed', $c['status']);
        $this->assertSame('normal', $c['calendar_context_state']);
        $this->assertSame(15, $c['setup_buffer_minutes']);
        $this->assertSame(30, $c['travel_buffer_before_minutes']);
        $this->assertSame(20, $c['travel_buffer_after_minutes']);
        $this->assertSame(10, $c['teardown_buffer_minutes']);
        $this->assertArrayHasKey('window_start_utc', $c);
        $this->assertArrayHasKey('window_end_utc', $c);

        // Candidate contract fields — these are part of the internal shape contract
        // between the generator and downstream consumers (scorer, debug tooling).
        // Capture time present and inside window → int 0 and 'within_effective_window'.
        $this->assertIsInt($c['outside_window_minutes']);
        $this->assertSame(0, $c['outside_window_minutes']);
        $this->assertIsString($c['candidate_generation_reason']);
        $this->assertSame('within_effective_window', $c['candidate_generation_reason']);
    }

    // -------------------------------------------------------------------------
    // Sorting
    // -------------------------------------------------------------------------

    public function test_candidates_are_sorted_by_session_id(): void
    {
        $candidates = $this->generator->generate(
            $this->subjectContext('2026-03-15T15:00:00Z'),
            [
                $this->sessionContext(['session_id' => 'C']),
                $this->sessionContext(['session_id' => 'A']),
                $this->sessionContext(['session_id' => 'B']),
            ]
        );

        $this->assertSame(['A', 'B', 'C'], array_column($candidates, 'session_id'));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function sessionContext(array $overrides = []): array
    {
        $base = [
            'session_id' => 'session-1',
            'status' => 'confirmed',
            'window_start_utc' => '2026-03-15T14:00:00Z',
            'window_end_utc' => '2026-03-15T16:00:00Z',
            'setup_buffer_minutes' => 0,
            'travel_buffer_before_minutes' => 0,
            'travel_buffer_after_minutes' => 0,
            'teardown_buffer_minutes' => 0,
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
