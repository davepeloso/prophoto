<?php

namespace ProPhoto\Ingest\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssignmentLockEffect;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Ingest\Repositories\SessionAssignmentDecisionRepository;
use ProPhoto\Ingest\Repositories\SessionAssignmentRepository;
use ProPhoto\Ingest\Services\Matching\SessionMatchCandidateGenerator;
use ProPhoto\Ingest\Services\Matching\SessionMatchDecisionClassifier;
use ProPhoto\Ingest\Services\Matching\SessionMatchScoringService;
use ProPhoto\Ingest\Services\SessionAssociationWriteService;
use ProPhoto\Ingest\Services\SessionMatchingService;
use ProPhoto\Ingest\Tests\TestCase;

class SessionMatchingServiceTest extends TestCase
{
    protected SessionAssignmentDecisionRepository $decisionRepository;

    protected SessionAssignmentRepository $assignmentRepository;

    protected SessionAssociationWriteService $writeService;

    protected SessionMatchingService $matchingService;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection();

        $this->decisionRepository = new SessionAssignmentDecisionRepository($connection);
        $this->assignmentRepository = new SessionAssignmentRepository($connection);
        $this->writeService = new SessionAssociationWriteService(
            decisionRepository: $this->decisionRepository,
            assignmentRepository: $this->assignmentRepository,
            events: $this->app['events'],
            connection: $connection
        );

        $this->matchingService = new SessionMatchingService(
            candidateGenerator: new SessionMatchCandidateGenerator(),
            scoringService: new SessionMatchScoringService(),
            decisionClassifier: new SessionMatchDecisionClassifier(
                autoAssignThreshold: (float) config('prophoto-ingest.session_association.auto_assign_threshold', 0.85),
                proposalThreshold: (float) config('prophoto-ingest.session_association.proposal_threshold', 0.55),
                ambiguityDelta: 0.05
            ),
            writeService: $this->writeService
        );
    }

    public function test_clear_high_confidence_auto_assign(): void
    {
        $subjectContext = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T18:05:00Z',
            'gps_lat' => 34.1000,
            'gps_lng' => -118.3000,
            'session_type_hint' => 'wedding',
            'job_type_hint' => 'ceremony',
            'title_hint' => 'smith wedding ceremony',
            'idempotency_key' => 'match-auto-high-1',
        ]);

        $sessionContexts = [
            $this->sessionContext(7001, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'session_type' => 'wedding',
                'job_type' => 'ceremony',
                'title' => 'Smith Wedding Ceremony',
                'location_lat' => 34.1001,
                'location_lng' => -118.3001,
            ]),
            $this->sessionContext(7002, '2026-03-13T21:00:00Z', '2026-03-13T22:00:00Z', [
                'session_type' => 'portrait',
                'job_type' => 'couples',
                'title' => 'Evening Portraits',
                'location_lat' => 34.2222,
                'location_lng' => -118.4555,
            ]),
        ];

        $result = $this->matchingService->matchAndWrite($subjectContext, $sessionContexts);

        $this->assertSame(SessionAssignmentDecisionType::AUTO_ASSIGN, $result['classification']['decision_type']);
        $this->assertTrue($result['assignment_written']);
        $this->assertFalse($result['skipped_by_manual_lock']);
        $this->assertNotNull($result['assignment']);
        $this->assertSame(7001, (int) $result['assignment']['session_id']);
        $this->assertSame('assigned', $result['assignment']['effective_state']);
        $this->assertSame(SessionMatchConfidenceTier::HIGH->value, $result['decision']['confidence_tier']);
    }

    public function test_medium_confidence_proposal(): void
    {
        $subjectContext = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T17:40:00Z',
            'gps_lat' => null,
            'gps_lng' => null,
            'session_type_hint' => null,
            'job_type_hint' => null,
            'title_hint' => null,
            'idempotency_key' => 'match-proposal-medium-1',
        ]);

        $sessionContexts = [
            $this->sessionContext(7101, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'setup_buffer_minutes' => 30,
                'travel_buffer_minutes' => 0,
                'teardown_buffer_minutes' => 0,
                'location_lat' => null,
                'location_lng' => null,
            ]),
        ];

        $result = $this->matchingService->matchAndWrite($subjectContext, $sessionContexts);

        $this->assertSame(SessionAssignmentDecisionType::PROPOSE, $result['classification']['decision_type']);
        $this->assertFalse($result['assignment_written']);
        $this->assertNull($result['assignment']);
        $this->assertSame(7101, (int) $result['decision']['selected_session_id']);
        $this->assertSame(SessionMatchConfidenceTier::MEDIUM->value, $result['decision']['confidence_tier']);
    }

    public function test_low_confidence_no_match(): void
    {
        $subjectContext = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T06:00:00Z',
            'gps_lat' => null,
            'gps_lng' => null,
            'session_type_hint' => null,
            'job_type_hint' => null,
            'title_hint' => null,
            'idempotency_key' => 'match-no-match-low-1',
        ]);

        $sessionContexts = [
            $this->sessionContext(7201, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'setup_buffer_minutes' => 15,
                'travel_buffer_minutes' => 15,
                'teardown_buffer_minutes' => 15,
            ]),
        ];

        $result = $this->matchingService->matchAndWrite($subjectContext, $sessionContexts);

        $this->assertSame(SessionAssignmentDecisionType::NO_MATCH, $result['classification']['decision_type']);
        $this->assertFalse($result['assignment_written']);
        $this->assertNull($result['assignment']);
        $this->assertNull($result['decision']['selected_session_id']);
        $this->assertSame(1, DB::table('asset_session_assignment_decisions')->count());
        $this->assertSame(0, DB::table('asset_session_assignments')->count());
    }

    public function test_ambiguity_between_close_sessions_results_in_proposal(): void
    {
        $subjectContext = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T18:03:00Z',
            'gps_lat' => 34.1500,
            'gps_lng' => -118.3500,
            'session_type_hint' => 'wedding',
            'job_type_hint' => 'ceremony',
            'title_hint' => 'wedding ceremony',
            'idempotency_key' => 'match-ambiguous-1',
        ]);

        $sessionContexts = [
            $this->sessionContext(7301, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'session_type' => 'wedding',
                'job_type' => 'ceremony',
                'title' => 'Wedding Ceremony Main',
                'location_lat' => 34.1501,
                'location_lng' => -118.3501,
            ]),
            $this->sessionContext(7302, '2026-03-13T17:59:00Z', '2026-03-13T18:59:00Z', [
                'session_type' => 'wedding',
                'job_type' => 'ceremony',
                'title' => 'Wedding Ceremony Backup',
                'location_lat' => 34.1501,
                'location_lng' => -118.3501,
            ]),
        ];

        $result = $this->matchingService->matchAndWrite($subjectContext, $sessionContexts);

        $this->assertSame(SessionAssignmentDecisionType::PROPOSE, $result['classification']['decision_type']);
        $this->assertTrue((bool) $result['classification']['ambiguity_detected']);
        $this->assertSame('high_confidence_ambiguous_competition', $result['classification']['reason_code']);
        $this->assertFalse($result['assignment_written']);
        $this->assertNotNull($result['decision']['selected_session_id']);
    }

    public function test_evidence_payload_is_persisted_into_decision_history(): void
    {
        $subjectContext = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T18:05:00Z',
            'gps_lat' => 34.1000,
            'gps_lng' => -118.3000,
            'session_type_hint' => 'wedding',
            'job_type_hint' => 'ceremony',
            'title_hint' => 'smith wedding ceremony',
            'idempotency_key' => 'match-evidence-1',
        ]);

        $sessionContexts = [
            $this->sessionContext(7401, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'session_type' => 'wedding',
                'job_type' => 'ceremony',
                'title' => 'Smith Wedding Ceremony',
                'location_lat' => 34.1001,
                'location_lng' => -118.3001,
            ]),
            $this->sessionContext(7402, '2026-03-13T19:30:00Z', '2026-03-13T20:30:00Z'),
        ];

        $result = $this->matchingService->matchAndWrite($subjectContext, $sessionContexts);
        $decisionId = (int) $result['decision']['id'];

        $persisted = $this->decisionRepository->findById($decisionId);

        $this->assertNotNull($persisted);
        $this->assertIsArray($persisted['evidence_payload']);
        $this->assertIsArray($persisted['ranked_candidates_payload']);
        $this->assertArrayHasKey('matching_summary', $persisted['evidence_payload']);
        $this->assertArrayHasKey('top_candidate', $persisted['evidence_payload']);
        $this->assertSame('core', $persisted['evidence_payload']['top_candidate']['evidence']['time']['buffer_class']);
        $this->assertArrayHasKey('buffer_class', $persisted['evidence_payload']['top_candidate']);
        $this->assertArrayHasKey('minutes_from_planned_start', $persisted['evidence_payload']['top_candidate']);
        $this->assertArrayHasKey('distance_meters', $persisted['evidence_payload']['top_candidate']);
        $this->assertGreaterThan(0, count($persisted['ranked_candidates_payload']));
    }

    public function test_manual_lock_blocks_auto_assign_through_matching_service_flow(): void
    {
        $subjectContext = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T18:05:00Z',
            'gps_lat' => 34.1000,
            'gps_lng' => -118.3000,
            'session_type_hint' => 'wedding',
            'job_type_hint' => 'ceremony',
            'title_hint' => 'smith wedding ceremony',
            'idempotency_key' => 'match-blocked-by-lock-1',
        ]);

        $this->writeService->writeDecision([
            'decision_type' => SessionAssignmentDecisionType::MANUAL_UNASSIGN,
            'subject_type' => SessionAssociationSubjectType::ASSET,
            'subject_id' => '101',
            'ingest_item_id' => null,
            'asset_id' => 101,
            'selected_session_id' => null,
            'confidence_tier' => null,
            'confidence_score' => null,
            'algorithm_version' => 'v1',
            'trigger_source' => 'manual_override',
            'evidence_payload' => ['manual' => true],
            'ranked_candidates_payload' => [],
            'calendar_context_state' => null,
            'manual_override_reason_code' => 'operator_override',
            'manual_override_note' => null,
            // Decision-level lock_effect and assignment-level manual_lock_state intentionally use different vocabularies.
            'lock_effect' => SessionAssignmentLockEffect::LOCK_UNASSIGNED,
            'supersedes_decision_id' => null,
            'idempotency_key' => 'seed-manual-lock-1',
            'actor_type' => 'user',
            'actor_id' => 'user_1',
            'created_at' => now('UTC')->toISOString(),
        ]);

        $sessionContexts = [
            $this->sessionContext(7501, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'session_type' => 'wedding',
                'job_type' => 'ceremony',
                'title' => 'Smith Wedding Ceremony',
                'location_lat' => 34.1001,
                'location_lng' => -118.3001,
            ]),
        ];

        $result = $this->matchingService->matchAndWrite($subjectContext, $sessionContexts);

        $this->assertSame(SessionAssignmentDecisionType::AUTO_ASSIGN, $result['classification']['decision_type']);
        $this->assertFalse($result['assignment_written']);
        $this->assertTrue($result['skipped_by_manual_lock']);

        $current = $this->assignmentRepository->findCurrentBySubject(SessionAssociationSubjectType::ASSET, '101');
        $this->assertNotNull($current);
        $this->assertSame('manual_unassigned_lock', $current['manual_lock_state']);
        $this->assertSame('unassigned', $current['effective_state']);
    }

    public function test_subject_type_asset_requires_asset_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('asset_id is required when subject_type=asset.');

        $subjectContext = $this->subjectContext([
            'asset_id' => null,
            'idempotency_key' => 'subject-validation-asset',
        ]);

        $this->matchingService->matchAndWrite($subjectContext, [
            $this->sessionContext(7601, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z'),
        ]);
    }

    public function test_subject_type_ingest_item_requires_ingest_item_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ingest_item_id is required when subject_type=ingest_item.');

        $subjectContext = $this->subjectContext([
            'subject_type' => SessionAssociationSubjectType::INGEST_ITEM,
            'subject_id' => 'ingest-101',
            'asset_id' => null,
            'ingest_item_id' => null,
            'idempotency_key' => 'subject-validation-ingest',
        ]);

        $this->matchingService->matchAndWrite($subjectContext, [
            $this->sessionContext(7602, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z'),
        ]);
    }

    public function test_subject_id_must_match_asset_id_for_asset_subjects(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('subject_id must match asset_id string when subject_type=asset.');

        $subjectContext = $this->subjectContext([
            'subject_id' => 'asset-101',
            'asset_id' => 101,
            'idempotency_key' => 'subject-id-alignment-asset',
        ]);

        $this->matchingService->matchAndWrite($subjectContext, [
            $this->sessionContext(7605, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z'),
        ]);
    }

    public function test_subject_id_must_match_ingest_item_id_for_ingest_subjects(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('subject_id must match ingest_item_id string when subject_type=ingest_item.');

        $subjectContext = $this->subjectContext([
            'subject_type' => SessionAssociationSubjectType::INGEST_ITEM,
            'subject_id' => 'ingest-mismatch',
            'ingest_item_id' => 'ingest-101',
            'asset_id' => null,
            'idempotency_key' => 'subject-id-alignment-ingest',
        ]);

        $this->matchingService->matchAndWrite($subjectContext, [
            $this->sessionContext(7606, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z'),
        ]);
    }

    public function test_invalid_subject_type_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported subject_type [unknown_subject]');

        $subjectContext = $this->subjectContext([
            'subject_type' => 'unknown_subject',
            'idempotency_key' => 'invalid-subject-type',
        ]);

        $this->matchingService->matchAndWrite($subjectContext, [
            $this->sessionContext(7607, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z'),
        ]);
    }

    public function test_rank_tie_breaks_by_lexical_session_id_when_other_factors_equal(): void
    {
        $subjectContext = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T18:05:00Z',
            'gps_lat' => null,
            'gps_lng' => null,
            'session_type_hint' => null,
            'job_type_hint' => null,
            'title_hint' => null,
            'idempotency_key' => 'rank-tie-lexical-fallback',
        ]);

        $sessionContexts = [
            $this->sessionContext(9002, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'setup_buffer_minutes' => 10,
                'travel_buffer_minutes' => 10,
                'teardown_buffer_minutes' => 10,
                'location_lat' => null,
                'location_lng' => null,
            ]),
            $this->sessionContext(9001, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'setup_buffer_minutes' => 10,
                'travel_buffer_minutes' => 10,
                'teardown_buffer_minutes' => 10,
                'location_lat' => null,
                'location_lng' => null,
            ]),
        ];

        $result = $this->matchingService->matchAndWrite($subjectContext, $sessionContexts);

        $this->assertSame(2, count($result['ranked_candidates']));
        $this->assertSame('9001', (string) $result['ranked_candidates'][0]['session_id']);
        $this->assertSame('9002', (string) $result['ranked_candidates'][1]['session_id']);
    }

    public function test_created_at_override_is_opt_in_and_trigger_source_invalid_falls_back_to_ingest_batch(): void
    {
        $providedCreatedAt = '1999-01-02T03:04:05+02:00';
        $subjectContext = $this->subjectContext([
            'trigger_source' => 'unexpected_source',
            'created_at' => $providedCreatedAt,
            'idempotency_key' => 'created-at-default-path',
        ]);

        $before = now('UTC');
        $resultDefault = $this->matchingService->matchAndWrite($subjectContext, [
            $this->sessionContext(7603, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z'),
        ]);
        $after = now('UTC');

        $this->assertSame('ingest_batch', $resultDefault['decision']['trigger_source']);

        $persistedDefaultCreatedAt = Carbon::parse((string) $resultDefault['decision']['created_at'])->utc();
        $this->assertNotSame(
            Carbon::parse($providedCreatedAt)->utc()->toISOString(),
            $persistedDefaultCreatedAt->toISOString()
        );
        $this->assertTrue($persistedDefaultCreatedAt->greaterThanOrEqualTo($before->copy()->subSecond()));
        $this->assertTrue($persistedDefaultCreatedAt->lessThanOrEqualTo($after->copy()->addSecond()));

        $subjectContextOverride = $this->subjectContext([
            'trigger_source' => 'unexpected_source',
            'created_at' => $providedCreatedAt,
            'idempotency_key' => 'created-at-override-path',
        ]);

        $resultOverride = $this->matchingService->matchAndWrite(
            $subjectContextOverride,
            [$this->sessionContext(7604, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z')],
            ['allow_created_at_override' => true]
        );

        $this->assertSame('ingest_batch', $resultOverride['decision']['trigger_source']);
        $this->assertSame(
            Carbon::parse($providedCreatedAt)->utc()->toISOString(),
            Carbon::parse((string) $resultOverride['decision']['created_at'])->utc()->toISOString()
        );
    }

    public function test_invalid_created_at_override_falls_back_to_current_utc_time(): void
    {
        $subjectContext = $this->subjectContext([
            'created_at' => 'not-a-timestamp',
            'idempotency_key' => 'invalid-created-at-override',
        ]);

        $before = now('UTC');
        $result = $this->matchingService->matchAndWrite(
            $subjectContext,
            [$this->sessionContext(7608, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z')],
            ['allow_created_at_override' => true]
        );
        $after = now('UTC');

        $createdAt = Carbon::parse((string) $result['decision']['created_at'])->utc();
        $this->assertTrue($createdAt->greaterThanOrEqualTo($before->copy()->subSecond()));
        $this->assertTrue($createdAt->lessThanOrEqualTo($after->copy()->addSecond()));
    }

    public function test_matching_service_respects_idempotency_key_and_avoids_duplicate_effective_rows(): void
    {
        $subjectContext = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T18:05:00Z',
            'gps_lat' => 34.1000,
            'gps_lng' => -118.3000,
            'session_type_hint' => 'wedding',
            'job_type_hint' => 'ceremony',
            'title_hint' => 'smith wedding ceremony',
            'idempotency_key' => 'matching-idempotency-e2e-1',
        ]);

        $sessionContexts = [
            $this->sessionContext(7609, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'session_type' => 'wedding',
                'job_type' => 'ceremony',
                'title' => 'Smith Wedding Ceremony',
                'location_lat' => 34.1001,
                'location_lng' => -118.3001,
            ]),
        ];

        $first = $this->matchingService->matchAndWrite($subjectContext, $sessionContexts);
        $second = $this->matchingService->matchAndWrite($subjectContext, $sessionContexts);

        $this->assertFalse($first['idempotent']);
        $this->assertTrue($first['assignment_written']);
        $this->assertTrue($second['idempotent']);
        $this->assertFalse($second['assignment_written']);
        $this->assertSame($first['decision']['id'], $second['decision']['id']);
        $this->assertSame(1, DB::table('asset_session_assignment_decisions')->count());
        $this->assertSame(1, DB::table('asset_session_assignments')->count());
    }

    public function test_top_candidate_payload_persists_with_null_distance_when_gps_missing(): void
    {
        $subjectContext = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T18:05:00Z',
            'gps_lat' => null,
            'gps_lng' => null,
            'session_type_hint' => null,
            'job_type_hint' => null,
            'title_hint' => null,
            'idempotency_key' => 'no-gps-top-candidate-payload',
        ]);

        $result = $this->matchingService->matchAndWrite($subjectContext, [
            $this->sessionContext(7610, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'location_lat' => 34.1010,
                'location_lng' => -118.3010,
            ]),
        ]);

        $persisted = $this->decisionRepository->findById((int) $result['decision']['id']);

        $this->assertNotNull($persisted);
        $top = $persisted['evidence_payload']['top_candidate'] ?? null;
        $this->assertIsArray($top);
        $this->assertArrayHasKey('distance_meters', $top);
        $this->assertNull($top['distance_meters']);
        $this->assertArrayHasKey('buffer_class', $top);
        $this->assertArrayHasKey('minutes_from_planned_start', $top);
    }

    public function test_classifier_auto_assign_without_selected_session_is_rejected_before_write(): void
    {
        $service = $this->matchingServiceWithForcedClassification([
            'decision_type' => SessionAssignmentDecisionType::AUTO_ASSIGN,
            'selected_session_id' => null,
            'confidence_tier' => SessionMatchConfidenceTier::HIGH,
            'confidence_score' => 0.92,
            'reason_code' => 'forced_test_case',
            'ambiguity_detected' => false,
            'competing_session_id' => null,
        ]);

        $failed = false;
        try {
            $service->matchAndWrite(
                $this->subjectContext(['idempotency_key' => 'invalid-classifier-auto-missing-session']),
                [$this->sessionContext(7611, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z')]
            );
        } catch (InvalidArgumentException $exception) {
            $failed = true;
            $this->assertStringContainsString('selected_session_id is required', $exception->getMessage());
        }

        $this->assertTrue($failed);
        $this->assertSame(0, DB::table('asset_session_assignment_decisions')->count());
        $this->assertSame(0, DB::table('asset_session_assignments')->count());
    }

    public function test_classifier_no_match_with_selected_session_is_rejected_before_write(): void
    {
        $service = $this->matchingServiceWithForcedClassification([
            'decision_type' => SessionAssignmentDecisionType::NO_MATCH,
            'selected_session_id' => 7612,
            'confidence_tier' => SessionMatchConfidenceTier::LOW,
            'confidence_score' => 0.30,
            'reason_code' => 'forced_test_case',
            'ambiguity_detected' => false,
            'competing_session_id' => null,
        ]);

        $failed = false;
        try {
            $service->matchAndWrite(
                $this->subjectContext(['idempotency_key' => 'invalid-classifier-no-match-with-session']),
                [$this->sessionContext(7612, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z')]
            );
        } catch (InvalidArgumentException $exception) {
            $failed = true;
            $this->assertStringContainsString('selected_session_id must be null', $exception->getMessage());
        }

        $this->assertTrue($failed);
        $this->assertSame(0, DB::table('asset_session_assignment_decisions')->count());
        $this->assertSame(0, DB::table('asset_session_assignments')->count());
    }

    public function test_ranked_candidates_are_trimmed_consistently_in_response_and_persisted_payload(): void
    {
        $subjectContext = $this->subjectContext([
            'capture_at_utc' => '2026-03-13T18:05:00Z',
            'gps_lat' => 34.1000,
            'gps_lng' => -118.3000,
            'session_type_hint' => 'wedding',
            'job_type_hint' => 'ceremony',
            'title_hint' => 'smith wedding ceremony',
            'idempotency_key' => 'trim-ranked-candidates-1',
        ]);

        $sessionContexts = [
            $this->sessionContext(7613, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'session_type' => 'wedding',
                'job_type' => 'ceremony',
                'title' => 'Smith Wedding Ceremony',
                'location_lat' => 34.1001,
                'location_lng' => -118.3001,
            ]),
            $this->sessionContext(7614, '2026-03-13T18:10:00Z', '2026-03-13T19:10:00Z', [
                'session_type' => 'wedding',
                'job_type' => 'ceremony',
                'title' => 'Smith Wedding Portraits',
                'location_lat' => 34.1005,
                'location_lng' => -118.3005,
            ]),
            $this->sessionContext(7615, '2026-03-13T20:00:00Z', '2026-03-13T21:00:00Z', [
                'session_type' => 'wedding',
                'job_type' => 'reception',
                'title' => 'Smith Wedding Reception',
                'location_lat' => 34.2000,
                'location_lng' => -118.4000,
            ]),
        ];

        $result = $this->matchingService->matchAndWrite(
            $subjectContext,
            $sessionContexts,
            ['max_ranked_candidates' => 2]
        );

        $this->assertCount(2, $result['ranked_candidates']);
        $this->assertSame(2, $result['candidate_count']);

        $persisted = $this->decisionRepository->findById((int) $result['decision']['id']);
        $this->assertNotNull($persisted);
        $this->assertIsArray($persisted['ranked_candidates_payload']);
        $this->assertCount(2, $persisted['ranked_candidates_payload']);

        $responseSessionIds = array_map(
            static fn (array $candidate): string => (string) $candidate['session_id'],
            $result['ranked_candidates']
        );
        $persistedSessionIds = array_map(
            static fn (array $candidate): string => (string) ($candidate['session_id'] ?? ''),
            $persisted['ranked_candidates_payload']
        );

        $this->assertSame($responseSessionIds, $persistedSessionIds);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function subjectContext(array $overrides = []): array
    {
        $base = [
            'subject_type' => SessionAssociationSubjectType::ASSET,
            'subject_id' => '101',
            'ingest_item_id' => null,
            'asset_id' => 101,
            'capture_at_utc' => '2026-03-13T18:05:00Z',
            'gps_lat' => 34.1000,
            'gps_lng' => -118.3000,
            'session_type_hint' => null,
            'job_type_hint' => null,
            'title_hint' => null,
            'trigger_source' => 'ingest_batch',
            'idempotency_key' => null,
        ];

        return array_merge($base, $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function sessionContext(
        int $sessionId,
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
            'travel_buffer_minutes' => 10,
            'teardown_buffer_minutes' => 10,
            'location_lat' => null,
            'location_lng' => null,
            'location_label' => null,
        ];

        return array_merge($base, $overrides);
    }

    /**
     * @param array<string, mixed> $classification
     */
    protected function matchingServiceWithForcedClassification(array $classification): SessionMatchingService
    {
        $classifier = new class($classification) extends SessionMatchDecisionClassifier
        {
            /**
             * @param array<string, mixed> $forcedClassification
             */
            public function __construct(protected array $forcedClassification)
            {
                parent::__construct();
            }

            /**
             * @param list<array<string, mixed>> $rankedCandidates
             * @param array<string, mixed> $options
             * @return array<string, mixed>
             */
            public function classify(array $rankedCandidates, array $options = []): array
            {
                return $this->forcedClassification;
            }
        };

        return new SessionMatchingService(
            candidateGenerator: new SessionMatchCandidateGenerator(),
            scoringService: new SessionMatchScoringService(),
            decisionClassifier: $classifier,
            writeService: $this->writeService
        );
    }
}
