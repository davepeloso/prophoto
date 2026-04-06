<?php

namespace ProPhoto\Ingest\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Contracts\Events\Ingest\SessionAssociationResolved;
use ProPhoto\Ingest\Domain\IngestItem;
use ProPhoto\Ingest\Events\IngestItemCreated;
use ProPhoto\Ingest\Repositories\SessionAssignmentDecisionRepository;
use ProPhoto\Ingest\Repositories\SessionAssignmentRepository;
use ProPhoto\Ingest\Services\IngestItemContextBuilder;
use ProPhoto\Ingest\Services\IngestItemSessionMatchingFlowService;
use ProPhoto\Ingest\Services\Matching\SessionMatchCandidateGenerator;
use ProPhoto\Ingest\Services\Matching\SessionMatchDecisionClassifier;
use ProPhoto\Ingest\Services\Matching\SessionMatchScoringService;
use ProPhoto\Ingest\Services\SessionAssociationWriteService;
use ProPhoto\Ingest\Services\SessionMatchingService;
use ProPhoto\Ingest\Tests\TestCase;

class IngestItemSessionMatchingFlowServiceTest extends TestCase
{
    protected SessionAssignmentDecisionRepository $decisionRepository;

    protected SessionAssignmentRepository $assignmentRepository;

    protected IngestItemSessionMatchingFlowService $flowService;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection();
        $this->decisionRepository = new SessionAssignmentDecisionRepository($connection);
        $this->assignmentRepository = new SessionAssignmentRepository($connection);

        $this->flowService = new IngestItemSessionMatchingFlowService(
            contextBuilder: new IngestItemContextBuilder(),
            matchingService: $this->buildMatchingService(),
            events: $this->app['events']
        );
    }

    public function test_ingest_item_created_flow_builds_context_and_writes_matching_decision(): void
    {
        Event::fake();
        $this->flowService = new IngestItemSessionMatchingFlowService(
            contextBuilder: new IngestItemContextBuilder(),
            matchingService: $this->buildMatchingService(),
            events: Event::getFacadeRoot()
        );

        $ingestItem = new IngestItem(
            ingestItemId: 'ingest-9001',
            captureAtUtc: '2026-03-13T18:05:00Z',
            gpsLat: 34.1000,
            gpsLng: -118.3000,
            sessionTypeHint: 'wedding',
            jobTypeHint: 'ceremony',
            titleHint: 'Smith Wedding Ceremony',
            triggerSource: 'ingest_batch',
            idempotencyKey: 'ingest-flow-9001',
            actorType: 'system',
            actorId: null,
            createdAt: '2026-03-13T18:06:00Z'
        );

        $result = $this->flowService->handleCreated($ingestItem, [
            $this->sessionContext(8801, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'session_type' => 'wedding',
                'job_type' => 'ceremony',
                'title' => 'Smith Wedding Ceremony',
                'location_lat' => 34.1001,
                'location_lng' => -118.3001,
            ]),
            $this->sessionContext(8802, '2026-03-13T21:00:00Z', '2026-03-13T22:00:00Z', [
                'session_type' => 'portrait',
                'job_type' => 'studio',
                'title' => 'Later Portrait Session',
                'location_lat' => 34.2000,
                'location_lng' => -118.4000,
            ]),
        ]);

        $subjectContext = $result['subject_context'];
        $matchingResult = $result['matching_result'];

        $this->assertSame(SessionAssociationSubjectType::INGEST_ITEM, $subjectContext['subject_type']);
        $this->assertSame('ingest-9001', $subjectContext['subject_id']);
        $this->assertSame('ingest-9001', $subjectContext['ingest_item_id']);
        $this->assertNull($subjectContext['asset_id']);

        $this->assertSame(SessionAssignmentDecisionType::AUTO_ASSIGN, $matchingResult['classification']['decision_type']);
        $this->assertTrue($matchingResult['assignment_written']);
        $this->assertNotNull($matchingResult['assignment']);
        $this->assertSame('ingest_item', $matchingResult['decision']['subject_type']);
        $this->assertSame('ingest-9001', $matchingResult['decision']['subject_id']);
        $this->assertSame('ingest-9001', $matchingResult['decision']['ingest_item_id']);
        $this->assertNull($matchingResult['decision']['asset_id']);

        $this->assertSame(1, DB::table('asset_session_assignment_decisions')->count());
        $this->assertSame(1, DB::table('asset_session_assignments')->count());

        Event::assertDispatched(IngestItemCreated::class, function (IngestItemCreated $event): bool {
            return (string) $event->ingestItemId === 'ingest-9001'
                && $event->captureAtUtc === '2026-03-13T18:05:00Z'
                && $event->triggerSource === 'ingest_batch';
        });

        Event::assertDispatched(SessionAssociationResolved::class, function (SessionAssociationResolved $event) use ($matchingResult): bool {
            return (string) $event->decisionId === (string) $matchingResult['decision']['id']
                && $event->decisionType === SessionAssignmentDecisionType::AUTO_ASSIGN
                && $event->subjectType === SessionAssociationSubjectType::INGEST_ITEM
                && $event->subjectId === (string) $matchingResult['decision']['subject_id']
                && (string) $event->ingestItemId === (string) $matchingResult['decision']['ingest_item_id']
                && $event->assetId === null
                && (string) $event->selectedSessionId === (string) $matchingResult['decision']['selected_session_id']
                && $event->confidenceTier === SessionMatchConfidenceTier::from((string) $matchingResult['decision']['confidence_tier'])
                && (float) $event->confidenceScore === (float) $matchingResult['decision']['confidence_score']
                && $event->algorithmVersion === (string) $matchingResult['decision']['algorithm_version']
                && $event->occurredAt === (string) $matchingResult['decision']['created_at'];
        });
    }

    public function test_propose_emits_session_association_resolved_event(): void
    {
        Event::fake();
        $this->flowService = new IngestItemSessionMatchingFlowService(
            contextBuilder: new IngestItemContextBuilder(),
            matchingService: $this->buildMatchingService(),
            events: Event::getFacadeRoot()
        );

        $ingestItem = new IngestItem(
            ingestItemId: 'ingest-9003',
            captureAtUtc: '2026-03-13T17:40:00Z',
            gpsLat: null,
            gpsLng: null,
            triggerSource: 'ingest_batch',
            idempotencyKey: 'ingest-flow-9003'
        );

        $result = $this->flowService->handleCreated($ingestItem, [
            $this->sessionContext(8803, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'setup_buffer_minutes' => 30,
                'travel_buffer_minutes' => 0,
                'teardown_buffer_minutes' => 0,
                'location_lat' => null,
                'location_lng' => null,
            ]),
        ]);

        $this->assertSame(SessionAssignmentDecisionType::PROPOSE, $result['matching_result']['classification']['decision_type']);
        Event::assertDispatched(SessionAssociationResolved::class, function (SessionAssociationResolved $event) use ($result): bool {
            return (string) $event->decisionId === (string) $result['matching_result']['decision']['id']
                && $event->decisionType === SessionAssignmentDecisionType::PROPOSE;
        });
    }

    public function test_no_match_does_not_emit_session_association_resolved_event(): void
    {
        Event::fake();
        $this->flowService = new IngestItemSessionMatchingFlowService(
            contextBuilder: new IngestItemContextBuilder(),
            matchingService: $this->buildMatchingService(),
            events: Event::getFacadeRoot()
        );

        $ingestItem = new IngestItem(
            ingestItemId: 'ingest-9004',
            captureAtUtc: '2026-03-13T06:00:00Z',
            gpsLat: null,
            gpsLng: null,
            triggerSource: 'ingest_batch',
            idempotencyKey: 'ingest-flow-9004'
        );

        $result = $this->flowService->handleCreated($ingestItem, [
            $this->sessionContext(8804, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z', [
                'setup_buffer_minutes' => 15,
                'travel_buffer_minutes' => 15,
                'teardown_buffer_minutes' => 15,
                'location_lat' => null,
                'location_lng' => null,
            ]),
        ]);

        $this->assertSame(SessionAssignmentDecisionType::NO_MATCH, $result['matching_result']['classification']['decision_type']);
        Event::assertNotDispatched(SessionAssociationResolved::class);
    }

    public function test_handle_created_requires_ingest_item_instance(): void
    {
        $this->expectException(\TypeError::class);

        /** @phpstan-ignore-next-line explicit runtime guard for typed API boundary */
        $this->flowService->handleCreated(['ingest_item_id' => 'array-input-not-allowed'], []);
    }

    public function test_event_dispatches_even_when_matching_throws_and_no_writes_occur(): void
    {
        Event::fake();

        $failingMatchingService = $this->getMockBuilder(SessionMatchingService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['matchAndWrite'])
            ->getMock();

        $failingMatchingService->expects($this->once())
            ->method('matchAndWrite')
            ->willThrowException(new RuntimeException('forced matching failure'));

        $flowService = new IngestItemSessionMatchingFlowService(
            contextBuilder: new IngestItemContextBuilder(),
            matchingService: $failingMatchingService,
            events: Event::getFacadeRoot()
        );

        $ingestItem = new IngestItem(
            ingestItemId: 'ingest-throw-9002',
            captureAtUtc: '2026-03-13T18:05:00Z',
            gpsLat: 34.1000,
            gpsLng: -118.3000,
            triggerSource: 'ingest_batch'
        );

        $thrown = false;
        try {
            $flowService->handleCreated($ingestItem, [
                $this->sessionContext(8810, '2026-03-13T18:00:00Z', '2026-03-13T19:00:00Z'),
            ]);
        } catch (RuntimeException $exception) {
            $thrown = true;
            $this->assertSame('forced matching failure', $exception->getMessage());
        }

        $this->assertTrue($thrown);
        Event::assertDispatched(IngestItemCreated::class, function (IngestItemCreated $event): bool {
            return (string) $event->ingestItemId === 'ingest-throw-9002';
        });
        Event::assertNotDispatched(SessionAssociationResolved::class);
        $this->assertSame(0, DB::table('asset_session_assignment_decisions')->count());
        $this->assertSame(0, DB::table('asset_session_assignments')->count());
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

    protected function buildMatchingService(): SessionMatchingService
    {
        $writeService = new SessionAssociationWriteService(
            decisionRepository: $this->decisionRepository,
            assignmentRepository: $this->assignmentRepository,
            events: $this->app['events'],
            connection: DB::connection()
        );

        return new SessionMatchingService(
            candidateGenerator: new SessionMatchCandidateGenerator(),
            scoringService: new SessionMatchScoringService(),
            decisionClassifier: new SessionMatchDecisionClassifier(
                autoAssignThreshold: (float) config('prophoto-ingest.session_association.auto_assign_threshold', 0.85),
                proposalThreshold: (float) config('prophoto-ingest.session_association.proposal_threshold', 0.55),
                ambiguityDelta: 0.05
            ),
            writeService: $writeService
        );
    }
}
