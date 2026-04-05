<?php

namespace ProPhoto\Ingest\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssignmentLockEffect;
use ProPhoto\Contracts\Enums\SessionAssociationLockState;
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
            'manual_lock_state' => SessionAssociationLockState::NONE,
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
            'manual_lock_state' => SessionAssociationLockState::MANUAL_ASSIGNED_LOCK,
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
            'lock_effect' => SessionAssignmentLockEffect::LOCK_ASSIGNED,
            'actor_type' => 'user',
            'actor_id' => 'user_1',
        ]));

        $this->assertTrue($result['assignment_written']);
        $this->assertFalse($result['skipped_by_manual_lock']);
        $this->assertNotNull($result['assignment']);
        $this->assertSame('assigned', $result['assignment']['effective_state']);
        $this->assertSame('manual', $result['assignment']['assignment_mode']);
        $this->assertSame(SessionAssociationLockState::MANUAL_ASSIGNED_LOCK->value, $result['assignment']['manual_lock_state']);

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
            'lock_effect' => SessionAssignmentLockEffect::LOCK_UNASSIGNED,
            'actor_type' => 'user',
            'actor_id' => 'user_2',
        ]));

        $this->assertTrue($result['assignment_written']);
        $this->assertNotNull($result['assignment']);
        $this->assertSame('unassigned', $result['assignment']['effective_state']);
        $this->assertNull($result['assignment']['session_id']);
        $this->assertSame(SessionAssociationLockState::MANUAL_UNASSIGNED_LOCK->value, $result['assignment']['manual_lock_state']);

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
            'lock_effect' => SessionAssignmentLockEffect::LOCK_ASSIGNED,
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
            'lock_effect' => SessionAssignmentLockEffect::NONE,
            'actor_type' => 'system',
            'actor_id' => null,
        ]));

        $this->assertFalse($result['assignment_written']);
        $this->assertTrue($result['skipped_by_manual_lock']);

        $current = $this->assignmentRepository->findCurrentBySubject(SessionAssociationSubjectType::ASSET, '101');
        $this->assertNotNull($current);
        $this->assertSame(SessionAssociationLockState::MANUAL_ASSIGNED_LOCK->value, $current['manual_lock_state']);
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

    public function test_auto_assign_supersedes_prior_auto_assignment_via_write_service(): void
    {
        $this->fakeEventsAndRebuildWriteService();

        $first = $this->writeService->writeDecision($this->decisionPayload([
            'idempotency_key' => 'supersede-e2e-1',
            'selected_session_id' => 5001,
        ]));

        $this->assertTrue($first['assignment_written']);
        $firstAssignment = $first['assignment'];

        $second = $this->writeService->writeDecision($this->decisionPayload([
            'idempotency_key' => 'supersede-e2e-2',
            'selected_session_id' => 5002,
        ]));

        $this->assertTrue($second['assignment_written']);
        $secondAssignment = $second['assignment'];

        // Old row must be superseded and linked forward.
        $oldRefreshed = $this->assignmentRepository->findById($firstAssignment['id']);
        $this->assertNotNull($oldRefreshed['superseded_at']);
        $this->assertSame($secondAssignment['id'], (int) $oldRefreshed['superseded_by_assignment_id']);

        // New row is current.
        $current = $this->assignmentRepository->findCurrentBySubject(SessionAssociationSubjectType::ASSET, '101');
        $this->assertNotNull($current);
        $this->assertSame($secondAssignment['id'], $current['id']);
        $this->assertNull($current['superseded_at']);
        $this->assertSame(5002, (int) $current['session_id']);
    }

    public function test_manual_unassigned_lock_blocks_auto_assign(): void
    {
        $this->fakeEventsAndRebuildWriteService();

        // Seed with manual_unassign lock.
        $this->writeService->writeDecision($this->decisionPayload([
            'decision_type' => SessionAssignmentDecisionType::MANUAL_UNASSIGN,
            'idempotency_key' => 'manual-unassign-lock-seed',
            'selected_session_id' => null,
            'confidence_tier' => null,
            'confidence_score' => null,
            'trigger_source' => 'manual_override',
            'manual_override_reason_code' => 'wrong_session',
            'lock_effect' => SessionAssignmentLockEffect::LOCK_UNASSIGNED,
            'actor_type' => 'user',
            'actor_id' => 'user_10',
        ]));

        // Attempt auto_assign — should be blocked.
        $result = $this->writeService->writeDecision($this->decisionPayload([
            'decision_type' => SessionAssignmentDecisionType::AUTO_ASSIGN,
            'idempotency_key' => 'auto-after-unassign-lock',
            'selected_session_id' => 5002,
            'confidence_tier' => SessionMatchConfidenceTier::HIGH,
            'confidence_score' => 0.95,
            'trigger_source' => 'ingest_batch',
            'lock_effect' => SessionAssignmentLockEffect::NONE,
            'actor_type' => 'system',
            'actor_id' => null,
        ]));

        $this->assertFalse($result['assignment_written']);
        $this->assertTrue($result['skipped_by_manual_lock']);

        // Current row still has the unassigned lock.
        $current = $this->assignmentRepository->findCurrentBySubject(SessionAssociationSubjectType::ASSET, '101');
        $this->assertNotNull($current);
        $this->assertSame(SessionAssociationLockState::MANUAL_UNASSIGNED_LOCK->value, $current['manual_lock_state']);
        $this->assertSame('unassigned', $current['effective_state']);

        // Decision was still recorded.
        $decisions = $this->decisionRepository->findBySubject(SessionAssociationSubjectType::ASSET, '101');
        $this->assertCount(2, $decisions);

        Event::assertNotDispatched(SessionAutoAssignmentApplied::class);
    }

    public function test_idempotency_key_prevents_duplicate_decisions(): void
    {
        $this->fakeEventsAndRebuildWriteService();

        $first = $this->writeService->writeDecision($this->decisionPayload([
            'idempotency_key' => 'idempotent-test-1',
            'selected_session_id' => 5001,
        ]));

        $this->assertFalse($first['idempotent']);
        $this->assertTrue($first['assignment_written']);

        // Same idempotency key — should return existing, no new rows.
        $second = $this->writeService->writeDecision($this->decisionPayload([
            'idempotency_key' => 'idempotent-test-1',
            'selected_session_id' => 9999, // different session — should be ignored
        ]));

        $this->assertTrue($second['idempotent']);
        $this->assertFalse($second['assignment_written']);
        $this->assertSame($first['decision']['id'], $second['decision']['id']);

        // Only 1 decision row exists.
        $decisions = $this->decisionRepository->findBySubject(SessionAssociationSubjectType::ASSET, '101');
        $this->assertCount(1, $decisions);

        // Only 1 assignment row exists.
        $this->assertSame(1, DB::table('asset_session_assignments')->where('subject_id', '101')->count());
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
            'lock_effect' => SessionAssignmentLockEffect::NONE,
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
