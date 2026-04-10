<?php

namespace ProPhoto\Ingest\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProPhoto\Ingest\Services\BatchUploadRecognitionService;
use ProPhoto\Ingest\Services\Matching\SessionMatchCandidateGenerator;
use ProPhoto\Ingest\Services\Matching\SessionMatchScoringService;

class BatchUploadRecognitionServiceTest extends TestCase
{
    protected BatchUploadRecognitionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BatchUploadRecognitionService(
            candidateGenerator: new SessionMatchCandidateGenerator(),
            scoringService: new SessionMatchScoringService()
        );
    }

    public function test_recognition_output_is_deterministic_for_identical_inputs(): void
    {
        $subject = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T18:05:00Z',
            'gps_lat' => 34.1000,
            'gps_lng' => -118.3000,
            'title_hint' => 'Smith Wedding Ceremony',
            'session_type_hint' => 'wedding',
            'job_type_hint' => 'ceremony',
        ]);

        $sessions = [
            $this->sessionContext(8801, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'title' => 'Smith Wedding Ceremony',
                'session_type' => 'wedding',
                'job_type' => 'ceremony',
                'location_lat' => 34.1001,
                'location_lng' => -118.3001,
            ]),
            $this->sessionContext(8802, '2026-03-13T21:00:00Z', '2026-03-13T22:00:00Z', [
                'title' => 'Evening Portraits',
                'session_type' => 'portrait',
                'job_type' => 'studio',
                'location_lat' => 34.2500,
                'location_lng' => -118.4500,
            ]),
        ];

        $first = $this->service->recognizeBatch($subject, $sessions);
        $second = $this->service->recognizeBatch($subject, $sessions);

        $this->assertSame($first, $second);
    }

    public function test_high_confidence_match_has_exactly_one_primary_candidate(): void
    {
        $result = $this->service->recognizeBatch(
            $this->subjectContext([
                'capture_at_utc' => '2026-03-13T18:05:00Z',
                'gps_lat' => 34.1000,
                'gps_lng' => -118.3000,
                'title_hint' => 'Smith Wedding Ceremony',
                'session_type_hint' => 'wedding',
                'job_type_hint' => 'ceremony',
            ]),
            [
                $this->sessionContext(8801, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                    'title' => 'Smith Wedding Ceremony',
                    'session_type' => 'wedding',
                    'job_type' => 'ceremony',
                    'location_lat' => 34.1001,
                    'location_lng' => -118.3001,
                ]),
                $this->sessionContext(8802, '2026-03-13T21:00:00Z', '2026-03-13T22:00:00Z', [
                    'title' => 'Other Session',
                    'location_lat' => 34.2500,
                    'location_lng' => -118.4500,
                ]),
            ]
        );

        $this->assertSame('high-confidence-match', $result['outcome_status']);
        $this->assertIsArray($result['primary_candidate']);
        $this->assertSame('high-confidence', $result['confidence']['tier']);
        $this->assertIsNumeric($result['confidence']['score']);
        $this->assertSame([], $result['low_confidence_candidates']);
    }

    public function test_low_confidence_candidates_has_no_primary_and_max_three_candidates(): void
    {
        $result = $this->service->recognizeBatch(
            $this->subjectContext([
                'capture_at_utc' => null,
                'gps_lat' => null,
                'gps_lng' => null,
                'title_hint' => null,
            ]),
            [
                $this->sessionContext(1001, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', ['title' => 'Session 1001']),
                $this->sessionContext(1002, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', ['title' => 'Session 1002']),
                $this->sessionContext(1003, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', ['title' => 'Session 1003']),
                $this->sessionContext(1004, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', ['title' => 'Session 1004']),
            ]
        );

        $this->assertSame('low-confidence-candidates', $result['outcome_status']);
        $this->assertNull($result['primary_candidate']);
        $this->assertSame('low-confidence', $result['confidence']['tier']);
        $this->assertLessThanOrEqual(3, count($result['low_confidence_candidates']));
        $this->assertCount(3, $result['low_confidence_candidates']);
    }

    public function test_top_ranked_candidate_is_included_in_low_confidence_candidates(): void
    {
        $generator = new class extends SessionMatchCandidateGenerator {
            /**
             * @param array<string, mixed> $subjectContext
             * @param list<array<string, mixed>> $sessionContexts
             * @param array<string, mixed> $options
             * @return list<array<string, mixed>>
             */
            public function generate(array $subjectContext, array $sessionContexts, array $options = []): array
            {
                return [
                    ['session_id' => '2001', 'title' => 'Session 2001'],
                    ['session_id' => '2002', 'title' => 'Session 2002'],
                    ['session_id' => '2003', 'title' => 'Session 2003'],
                ];
            }
        };

        $scoring = new class extends SessionMatchScoringService {
            /**
             * @param array<string, mixed> $subjectContext
             * @param list<array<string, mixed>> $candidates
             * @param array<string, mixed> $options
             * @return list<array<string, mixed>>
             */
            public function scoreCandidates(array $subjectContext, array $candidates, array $options = []): array
            {
                return [
                    [
                        'session_id' => '2002',
                        'score' => 0.51,
                        'title' => 'Session 2002',
                        'buffer_class' => 'unknown',
                        'minutes_from_planned_start' => null,
                        'distance_meters' => null,
                    ],
                    [
                        'session_id' => '2001',
                        'score' => 0.72,
                        'title' => 'Session 2001',
                        'buffer_class' => 'unknown',
                        'minutes_from_planned_start' => null,
                        'distance_meters' => null,
                    ],
                    [
                        'session_id' => '2003',
                        'score' => 0.63,
                        'title' => 'Session 2003',
                        'buffer_class' => 'unknown',
                        'minutes_from_planned_start' => null,
                        'distance_meters' => null,
                    ],
                ];
            }
        };

        $service = new BatchUploadRecognitionService(
            candidateGenerator: $generator,
            scoringService: $scoring
        );

        $result = $service->recognizeBatch(
            $this->subjectContext(),
            [],
            ['auto_assign_threshold' => 0.90]
        );

        $this->assertSame('low-confidence-candidates', $result['outcome_status']);
        $this->assertNull($result['primary_candidate']);
        $this->assertCount(3, $result['low_confidence_candidates']);
        $this->assertSame('2001', (string) $result['low_confidence_candidates'][0]['session_id']);
        $this->assertSame('low-confidence', (string) $result['low_confidence_candidates'][0]['confidence_tier']);
        $this->assertContains('2001', array_map(
            static fn (array $candidate): string => (string) ($candidate['session_id'] ?? ''),
            $result['low_confidence_candidates']
        ));
    }

    public function test_no_viable_candidates_has_no_primary_and_empty_candidates_list(): void
    {
        $result = $this->service->recognizeBatch(
            $this->subjectContext(['capture_at_utc' => '2026-03-13T18:05:00Z']),
            []
        );

        $this->assertSame('no-viable-candidates', $result['outcome_status']);
        $this->assertNull($result['primary_candidate']);
        $this->assertNull($result['confidence']['tier']);
        $this->assertNull($result['confidence']['score']);
        $this->assertSame([], $result['low_confidence_candidates']);
    }

    public function test_fixed_next_actions_are_always_present_and_ordered(): void
    {
        $result = $this->service->recognizeBatch(
            $this->subjectContext(['capture_at_utc' => '2026-03-13T18:05:00Z']),
            []
        );

        $this->assertSame([
            'Cull now',
            'Continue to delivery',
            'Review match / session context',
        ], $result['suggested_next_actions']);
    }

    public function test_recognition_evaluation_does_not_mutate_input_snapshots(): void
    {
        $subject = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T18:05:00Z',
            'title_hint' => 'Input should remain unchanged',
        ]);
        $sessions = [
            $this->sessionContext(8801, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z'),
        ];

        $subjectBefore = $subject;
        $sessionsBefore = $sessions;

        $this->service->recognizeBatch($subject, $sessions);

        $this->assertSame($subjectBefore, $subject);
        $this->assertSame($sessionsBefore, $sessions);
    }

    public function test_non_array_session_context_snapshot_entry_fails_fast(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('session_context_snapshots[1] must be an array.');

        $this->service->recognizeBatch(
            $this->subjectContext(),
            [
                $this->sessionContext(8801, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z'),
                'invalid-entry',
            ]
        );
    }

    public function test_associative_session_context_snapshots_fails_fast_when_list_contract_is_violated(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('session_context_snapshots must be a list with sequential integer keys.');

        $this->service->recognizeBatch(
            $this->subjectContext(),
            [
                'first' => $this->sessionContext(8801, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z'),
            ]
        );
    }

    public function test_unidentified_session_fallback_label_is_used_when_title_and_session_id_are_empty(): void
    {
        $generator = new class extends SessionMatchCandidateGenerator {
            /**
             * @param array<string, mixed> $subjectContext
             * @param list<array<string, mixed>> $sessionContexts
             * @param array<string, mixed> $options
             * @return list<array<string, mixed>>
             */
            public function generate(array $subjectContext, array $sessionContexts, array $options = []): array
            {
                return [[
                    'session_id' => '',
                    'title' => '',
                    'buffer_class' => 'unknown',
                    'minutes_from_planned_start' => null,
                    'distance_meters' => null,
                ]];
            }
        };

        $scoring = new class extends SessionMatchScoringService {
            /**
             * @param array<string, mixed> $subjectContext
             * @param list<array<string, mixed>> $candidates
             * @param array<string, mixed> $options
             * @return list<array<string, mixed>>
             */
            public function scoreCandidates(array $subjectContext, array $candidates, array $options = []): array
            {
                return [[
                    'session_id' => '',
                    'score' => 0.60,
                    'title' => '',
                    'buffer_class' => 'unknown',
                    'minutes_from_planned_start' => null,
                    'distance_meters' => null,
                ]];
            }
        };

        $service = new BatchUploadRecognitionService(
            candidateGenerator: $generator,
            scoringService: $scoring
        );

        $result = $service->recognizeBatch(
            $this->subjectContext(),
            [
                $this->sessionContext(8801, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z'),
            ]
        );

        $this->assertSame('low-confidence-candidates', $result['outcome_status']);
        $this->assertCount(1, $result['low_confidence_candidates']);
        $this->assertSame('Unidentified session', $result['low_confidence_candidates'][0]['display_label']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function subjectContext(array $overrides = []): array
    {
        $base = [
            'subject_type' => 'ingest_item',
            'subject_id' => 'ingest-unit-1',
            'ingest_item_id' => 'ingest-unit-1',
            'asset_id' => null,
            'capture_at_utc' => '2026-03-13T18:05:00Z',
            'gps_lat' => null,
            'gps_lng' => null,
            'session_type_hint' => null,
            'job_type_hint' => null,
            'title_hint' => null,
            'trigger_source' => 'ingest_batch',
        ];

        return array_merge($base, $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function sessionContext(
        int|string $sessionId,
        string $windowStartUtc,
        string $windowEndUtc,
        array $overrides = []
    ): array {
        $base = [
            'session_id' => $sessionId,
            'session_type' => null,
            'job_type' => null,
            'title' => null,
            'status' => 'confirmed',
            'calendar_context_state' => 'normal',
            'window_start_utc' => $windowStartUtc,
            'window_end_utc' => $windowEndUtc,
            'setup_buffer_minutes' => 10,
            'travel_buffer_before_minutes' => 10,
            'travel_buffer_after_minutes' => 10,
            'teardown_buffer_minutes' => 10,
            'location_lat' => null,
            'location_lng' => null,
        ];

        return array_merge($base, $overrides);
    }
}
