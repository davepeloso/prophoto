<?php

namespace ProPhoto\Ingest\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Contracts\Events\Ingest\SessionAutoAssignmentApplied;
use ProPhoto\Contracts\Events\Ingest\SessionManualAssignmentApplied;
use ProPhoto\Contracts\Events\Ingest\SessionManualUnassignmentApplied;
use ProPhoto\Contracts\Events\Ingest\SessionMatchProposalCreated;
use ProPhoto\Ingest\Repositories\SessionAssignmentDecisionRepository;
use ProPhoto\Ingest\Repositories\SessionAssignmentRepository;
use ProPhoto\Ingest\Services\SessionAssociationWriteService;
use ProPhoto\Ingest\Tests\TestCase;

class SessionAssociationWriteServiceTest extends TestCase
{
    protected SessionAssignmentDecisionRepository $decisionRepository;

    protected SessionAssignmentRepository $assignmentRepository;

    protected SessionAssociationWriteService $writeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decisionRepository = new SessionAssignmentDecisionRepository(DB::connection());
        $this->assignmentRepository = new SessionAssignmentRepository(DB::connection());
        $this->writeService = new SessionAssociationWriteService(
            decisionRepository: $this->decisionRepository,
            assignmentRepository: $this->assignmentRepository,
            events: $this->app['events'],
            connection: DB::connection()
        );
    }

    public function test_append_only_decision_persistence(): void
    {
        $first = $this->decisionRepository->append($this->decisionPayload([
            'idempotency_key' => 'decision-1',
            'selected_session_id' => 5001,
        ]));
        $second = $this->decisionRepository->append($this->decisionPayload([
            'idempotency_key' => 'decision-2',
            'selected_session_id' => 5002,
        ]));

        $this->assertNotSame($first['id'], $second['id']);

        $bySubject = $this->decisionRepository->findBySubject(
            SessionAssociationSubjectType::ASSET,
            '101'
        );
        $this->assertCount(2, $bySubject);
        $this->assertSame($second['id'], $bySubject[0]['id']);
        $this->assertSame($first['id'], $bySubject[1]['id']);

        $fetchedFirst = $this->decisionRepository->findById($first['id']);
        $this->assertNotNull($fetchedFirst);
        $this->assertSame('decision-1', $fetchedFirst['idempotency_key']);
        $this->assertSame(SessionAssignmentDecisionType::AUTO_ASSIGN->value, $fetchedFirst['decision_type']);
        $this->assertSame(5001, (int) $fetchedFirst['selected_session_id']);
    }

    public function test_superseding_current_assignment(): void
    {
        $firstDecision = $this->decisionRepository->append($this->decisionPayload([
            'idempotency_key' => 'supersede-decision-1',
            'selected_session_id' => 5001,
        ]));

        $firstAssignment = $this->assignmentRepository->appendEffective([
            'subject_type' => SessionAssociationSubjectType::ASSET,
            'subject_id' => '101',
            'asset_id' => 101,
            'session_id' => 5001,
            'effective_state' => 'assigned',
            'assignment_mode' => 'auto',
            'manual_lock_state' => 'none',
            'source_decision_id' => $firstDecision['id'],
            'confidence_tier' => SessionMatchConfidenceTier::HIGH,
            'confidence_score' => 0.97,
            'reason_code' => 'auto_assign',
            'became_effective_at' => now('UTC')->toISOString(),
        ]);

        $this->assignmentRepository->markSuperseded(
            assignmentId: $firstAssignment['id'],
            supersededAt: now('UTC')->toISOString()
        );

        $secondDecision = $this->decisionRepository->append($this->decisionPayload([
            'idempotency_key' => 'supersede-decision-2',
            'selected_session_id' => 5002,
        ]));

        $secondAssignment = $this->assignmentRepository->appendEffective([
            'subject_type' => SessionAssociationSubjectType::ASSET,
            'subject_id' => '101',
            'asset_id' => 101,
            'session_id' => 5002,
            'effective_state' => 'assigned',
            'assignment_mode' => 'manual',
            'manual_lock_state' => 'manual_assigned_lock',
            'source_decision_id' => $secondDecision['id'],
            'confidence_tier' => null,
            'confidence_score' => null,
            'reason_code' => 'manual_assign',
            'became_effective_at' => now('UTC')->toISOString(),
        ]);

        $this->assignmentRepository->setSupersededBy(
            assignmentId: $firstAssignment['id'],
            supersededByAssignmentId: $secondAssignment['id']
        );

        $firstRefreshed = $this->assignmentRepository->findById($firstAssignment['id']);
        $current = $this->assignmentRepository->findCurrentBySubject(SessionAssociationSubjectType::ASSET, '101');

        $this->assertNotNull($firstRefreshed);
        $this->assertNotNull($firstRefreshed['superseded_at']);
        $this->assertSame($secondAssignment['id'], (int) $firstRefreshed['superseded_by_assignment_id']);
        $this->assertNotNull($current);
        $this->assertSame($secondAssignment['id'], $current['id']);
    }

    public function test_manual_assign_creates_locked_effective_row(): void
    {
        $this->fakeEventsAndRebuildWriteService();

        $result = $this->writeService->writeDecision($this->decisionPayload([
            'decision_type' => SessionAssignmentDecisionType::MANUAL_ASSIGN,
            'idempotency_key' => 'manual-assign-1',
            'selected_session_id' => 5001,
            'confidence_tier' => null,
            'confidence_score' => null,
            'trigger_source' => 'manual_override',
            'manual_override_reason_code' => 'operator_verified',
            'lock_effect' => 'lock_assigned',
            'actor_type' => 'user',
            'actor_id' => 'user_1',
        ]));

        $this->assertTrue($result['assignment_written']);
        $this->assertFalse($result['skipped_by_manual_lock']);
        $this->assertNotNull($result['assignment']);
        $this->assertSame('assigned', $result['assignment']['effective_state']);
        $this->assertSame('manual', $result['assignment']['assignment_mode']);
        $this->assertSame('manual_assigned_lock', $result['assignment']['manual_lock_state']);

        Event::assertDispatched(SessionManualAssignmentApplied::class);
    }

    public function test_manual_unassign_creates_locked_unassigned_row(): void
    {
        $this->fakeEventsAndRebuildWriteService();

        $result = $this->writeService->writeDecision($this->decisionPayload([
            'decision_type' => SessionAssignmentDecisionType::MANUAL_UNASSIGN,
            'idempotency_key' => 'manual-unassign-1',
            'selected_session_id' => null,
            'confidence_tier' => null,
            'confidence_score' => null,
            'trigger_source' => 'manual_override',
            'manual_override_reason_code' => 'wrong_session',
            'lock_effect' => 'lock_unassigned',
            'actor_type' => 'user',
            'actor_id' => 'user_2',
        ]));

        $this->assertTrue($result['assignment_written']);
        $this->assertNotNull($result['assignment']);
        $this->assertSame('unassigned', $result['assignment']['effective_state']);
        $this->assertNull($result['assignment']['session_id']);
        $this->assertSame('manual_unassigned_lock', $result['assignment']['manual_lock_state']);

        Event::assertDispatched(SessionManualUnassignmentApplied::class);
    }

    public function test_automated_decisions_do_not_supersede_manual_locks(): void
    {
        $this->fakeEventsAndRebuildWriteService();

        $this->writeService->writeDecision($this->decisionPayload([
            'decision_type' => SessionAssignmentDecisionType::MANUAL_ASSIGN,
            'idempotency_key' => 'manual-lock-seed',
            'selected_session_id' => 5001,
            'confidence_tier' => null,
            'confidence_score' => null,
            'trigger_source' => 'manual_override',
            'lock_effect' => 'lock_assigned',
            'actor_type' => 'user',
            'actor_id' => 'user_3',
        ]));

        $result = $this->writeService->writeDecision($this->decisionPayload([
            'decision_type' => SessionAssignmentDecisionType::AUTO_ASSIGN,
            'idempotency_key' => 'auto-after-lock',
            'selected_session_id' => 5002,
            'confidence_tier' => SessionMatchConfidenceTier::HIGH,
            'confidence_score' => 0.95,
            'trigger_source' => 'ingest_batch',
            'lock_effect' => 'none',
            'actor_type' => 'system',
            'actor_id' => null,
        ]));

        $this->assertFalse($result['assignment_written']);
        $this->assertTrue($result['skipped_by_manual_lock']);

        $current = $this->assignmentRepository->findCurrentBySubject(SessionAssociationSubjectType::ASSET, '101');
        $this->assertNotNull($current);
        $this->assertSame('manual_assigned_lock', $current['manual_lock_state']);
        $this->assertSame(5001, (int) $current['session_id']);

        $decisions = $this->decisionRepository->findBySubject(SessionAssociationSubjectType::ASSET, '101');
        $this->assertCount(2, $decisions);
        Event::assertNotDispatched(SessionAutoAssignmentApplied::class);
    }

    public function test_proposal_and_no_match_do_not_create_effective_assigned_rows(): void
    {
        $this->fakeEventsAndRebuildWriteService();

        $this->writeService->writeDecision($this->decisionPayload([
            'decision_type' => SessionAssignmentDecisionType::PROPOSE,
            'idempotency_key' => 'proposal-1',
            'selected_session_id' => 5001,
            'confidence_tier' => SessionMatchConfidenceTier::MEDIUM,
            'confidence_score' => 0.66,
            'trigger_source' => 'ingest_batch',
            'ranked_candidates_payload' => [
                ['session_id' => 5001, 'score' => 0.66],
                ['session_id' => 5002, 'score' => 0.61],
            ],
        ]));

        $this->writeService->writeDecision($this->decisionPayload([
            'decision_type' => SessionAssignmentDecisionType::NO_MATCH,
            'idempotency_key' => 'no-match-1',
            'selected_session_id' => null,
            'confidence_tier' => SessionMatchConfidenceTier::LOW,
            'confidence_score' => 0.22,
            'trigger_source' => 'ingest_batch',
            'ranked_candidates_payload' => [],
        ]));

        $this->assertSame(2, DB::table('asset_session_assignment_decisions')->where('subject_id', '101')->count());
        $this->assertSame(0, DB::table('asset_session_assignments')->where('subject_id', '101')->count());
        $this->assertSame(0, DB::table('asset_session_assignments')->where('effective_state', 'assigned')->count());

        Event::assertDispatched(SessionMatchProposalCreated::class);
        Event::assertNotDispatched(SessionAutoAssignmentApplied::class);
        Event::assertNotDispatched(SessionManualAssignmentApplied::class);
        Event::assertNotDispatched(SessionManualUnassignmentApplied::class);
    }

    public function test_no_event_is_emitted_when_transaction_fails(): void
    {
        $this->fakeEventsAndRebuildWriteService();
        $failed = false;

        try {
            $this->writeService->writeDecision($this->decisionPayload([
                'decision_type' => SessionAssignmentDecisionType::AUTO_ASSIGN,
                'idempotency_key' => 'transaction-failure-1',
                'subject_type' => SessionAssociationSubjectType::ASSET,
                'subject_id' => 'asset-404',
                'asset_id' => null, // violates migration trigger/check for subject_type=asset
                'selected_session_id' => 5001,
                'confidence_tier' => SessionMatchConfidenceTier::HIGH,
                'confidence_score' => 0.95,
                'trigger_source' => 'ingest_batch',
            ]));
        } catch (QueryException) {
            $failed = true;
        }

        $this->assertTrue($failed, 'Expected writeDecision to fail when subject identity constraints are violated.');

        Event::assertNotDispatched(SessionAutoAssignmentApplied::class);
        Event::assertNotDispatched(SessionMatchProposalCreated::class);
        Event::assertNotDispatched(SessionManualAssignmentApplied::class);
        Event::assertNotDispatched(SessionManualUnassignmentApplied::class);
        $this->assertSame(0, DB::table('asset_session_assignment_decisions')->count());
        $this->assertSame(0, DB::table('asset_session_assignments')->count());
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function decisionPayload(array $overrides = []): array
    {
        $base = [
            'decision_type' => SessionAssignmentDecisionType::AUTO_ASSIGN,
            'subject_type' => SessionAssociationSubjectType::ASSET,
            'subject_id' => '101',
            'ingest_item_id' => null,
            'asset_id' => 101,
            'selected_session_id' => 5001,
            'confidence_tier' => SessionMatchConfidenceTier::HIGH,
            'confidence_score' => 0.91,
            'algorithm_version' => 'v1',
            'trigger_source' => 'ingest_batch',
            'evidence_payload' => ['signals' => ['time' => true]],
            'ranked_candidates_payload' => [['session_id' => 5001, 'score' => 0.91]],
            'calendar_context_state' => 'normal',
            'manual_override_reason_code' => null,
            'manual_override_note' => null,
            'lock_effect' => 'none',
            'supersedes_decision_id' => null,
            'idempotency_key' => null,
            'actor_type' => 'system',
            'actor_id' => null,
            'created_at' => now('UTC')->toISOString(),
        ];

        return array_merge($base, $overrides);
    }

    protected function fakeEventsAndRebuildWriteService(): void
    {
        Event::fake();

        $this->writeService = new SessionAssociationWriteService(
            decisionRepository: $this->decisionRepository,
            assignmentRepository: $this->assignmentRepository,
            events: $this->app['events'],
            connection: DB::connection()
        );
    }
}
