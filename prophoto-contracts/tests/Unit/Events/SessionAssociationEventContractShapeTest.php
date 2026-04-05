<?php

namespace ProPhoto\Contracts\Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\Enums\SessionAssociationLockState;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Contracts\Events\Ingest\SessionAutoAssignmentApplied;
use ProPhoto\Contracts\Events\Ingest\SessionManualAssignmentApplied;
use ProPhoto\Contracts\Events\Ingest\SessionManualUnassignmentApplied;
use ProPhoto\Contracts\Events\Ingest\SessionMatchProposalCreated;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

class SessionAssociationEventContractShapeTest extends TestCase
{
    public function test_session_association_event_constructor_signatures_are_stable(): void
    {
        $autoAssignmentExpected = [
            ['assignmentId', 'int|string'],
            ['decisionId', 'int|string'],
            ['subjectType', 'ProPhoto\Contracts\Enums\SessionAssociationSubjectType'],
            ['subjectId', 'string'],
            ['ingestItemId', 'int|null|string'],
            ['assetId', 'int|null|string'],
            ['sessionId', 'int|string'],
            ['confidenceTier', 'ProPhoto\Contracts\Enums\SessionMatchConfidenceTier'],
            ['confidenceScore', 'float|null'],
            ['algorithmVersion', 'string'],
            ['occurredAt', 'string'],
        ];

        $proposalExpected = [
            ['decisionId', 'int|string'],
            ['subjectType', 'ProPhoto\Contracts\Enums\SessionAssociationSubjectType'],
            ['subjectId', 'string'],
            ['ingestItemId', 'int|null|string'],
            ['assetId', 'int|null|string'],
            ['topCandidateSessionId', 'int|null|string'],
            ['candidateCount', 'int'],
            ['confidenceTier', 'ProPhoto\Contracts\Enums\SessionMatchConfidenceTier'],
            ['confidenceScore', 'float|null'],
            ['algorithmVersion', 'string'],
            ['occurredAt', 'string'],
        ];

        $manualAssignmentExpected = [
            ['assignmentId', 'int|string'],
            ['decisionId', 'int|string'],
            ['subjectType', 'ProPhoto\Contracts\Enums\SessionAssociationSubjectType'],
            ['subjectId', 'string'],
            ['ingestItemId', 'int|null|string'],
            ['assetId', 'int|null|string'],
            ['sessionId', 'int|string'],
            ['lockState', 'ProPhoto\Contracts\Enums\SessionAssociationLockState'],
            ['manualOverrideReasonCode', 'null|string'],
            ['actorId', 'string'],
            ['occurredAt', 'string'],
        ];

        $manualUnassignmentExpected = [
            ['assignmentId', 'int|string'],
            ['decisionId', 'int|string'],
            ['subjectType', 'ProPhoto\Contracts\Enums\SessionAssociationSubjectType'],
            ['subjectId', 'string'],
            ['ingestItemId', 'int|null|string'],
            ['assetId', 'int|null|string'],
            ['lockState', 'ProPhoto\Contracts\Enums\SessionAssociationLockState'],
            ['manualOverrideReasonCode', 'null|string'],
            ['actorId', 'string'],
            ['occurredAt', 'string'],
        ];

        $this->assertEventSignature(SessionAutoAssignmentApplied::class, $autoAssignmentExpected);
        $this->assertEventSignature(SessionMatchProposalCreated::class, $proposalExpected);
        $this->assertEventSignature(SessionManualAssignmentApplied::class, $manualAssignmentExpected);
        $this->assertEventSignature(SessionManualUnassignmentApplied::class, $manualUnassignmentExpected);
    }

    public function test_session_association_events_are_json_serializable_with_scalar_and_enum_payloads(): void
    {
        $events = [
            new SessionAutoAssignmentApplied(
                assignmentId: 'assign_1',
                decisionId: 'decision_1',
                subjectType: SessionAssociationSubjectType::ASSET,
                subjectId: '123',
                ingestItemId: null,
                assetId: 123,
                sessionId: 'session_1',
                confidenceTier: SessionMatchConfidenceTier::HIGH,
                confidenceScore: 0.97,
                algorithmVersion: 'v1',
                occurredAt: '2026-04-04T18:00:00Z'
            ),
            new SessionMatchProposalCreated(
                decisionId: 'decision_2',
                subjectType: SessionAssociationSubjectType::INGEST_ITEM,
                subjectId: 'ing_456',
                ingestItemId: 'ing_456',
                assetId: null,
                topCandidateSessionId: 'session_2',
                candidateCount: 3,
                confidenceTier: SessionMatchConfidenceTier::MEDIUM,
                confidenceScore: 0.61,
                algorithmVersion: 'v1',
                occurredAt: '2026-04-04T18:00:00Z'
            ),
            new SessionManualAssignmentApplied(
                assignmentId: 'assign_3',
                decisionId: 'decision_3',
                subjectType: SessionAssociationSubjectType::ASSET,
                subjectId: '789',
                ingestItemId: 'ing_789',
                assetId: 789,
                sessionId: 'session_3',
                lockState: SessionAssociationLockState::MANUAL_ASSIGNED_LOCK,
                manualOverrideReasonCode: 'operator_verified',
                actorId: 'user_22',
                occurredAt: '2026-04-04T18:00:00Z'
            ),
            new SessionManualUnassignmentApplied(
                assignmentId: 'assign_4',
                decisionId: 'decision_4',
                subjectType: SessionAssociationSubjectType::INGEST_ITEM,
                subjectId: 'ing_999',
                ingestItemId: 'ing_999',
                assetId: null,
                lockState: SessionAssociationLockState::MANUAL_UNASSIGNED_LOCK,
                manualOverrideReasonCode: 'wrong_session',
                actorId: 'user_23',
                occurredAt: '2026-04-04T18:00:00Z'
            ),
        ];

        foreach ($events as $event) {
            $json = json_encode($event, JSON_THROW_ON_ERROR);
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            $this->assertIsArray($decoded);
            $this->assertNotSame([], $decoded);
            $this->assertScalarOrNullPayload($decoded);
        }
    }

    /**
     * @param list<array{0: string, 1: string}> $expectedSignature
     */
    protected function assertEventSignature(string $eventClass, array $expectedSignature): void
    {
        $reflection = new \ReflectionClass($eventClass);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor, "{$eventClass} constructor is required.");

        $actual = [];
        foreach ($constructor->getParameters() as $parameter) {
            $actual[] = [$parameter->getName(), $this->renderType($parameter->getType())];
        }

        $this->assertSame($expectedSignature, $actual, "{$eventClass} signature drifted.");
    }

    protected function renderType(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            if ($type->allowsNull() && $name !== 'mixed' && $name !== 'null') {
                $union = [$name, 'null'];
                sort($union);

                return implode('|', $union);
            }

            return $name;
        }

        if ($type instanceof ReflectionUnionType) {
            $union = array_map(
                static fn (ReflectionNamedType $named): string => $named->getName(),
                $type->getTypes()
            );
            sort($union);

            return implode('|', $union);
        }

        return (string) $type;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function assertScalarOrNullPayload(array $payload): void
    {
        foreach ($payload as $key => $value) {
            $this->assertTrue(
                is_scalar($value) || $value === null,
                "Event payload key {$key} must be scalar or null."
            );
        }
    }
}
