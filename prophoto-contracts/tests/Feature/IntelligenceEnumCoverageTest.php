<?php

namespace ProPhoto\Contracts\Tests\Feature;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\Enums\RunScope;
use ProPhoto\Contracts\Enums\RunStatus;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssignmentMode;
use ProPhoto\Contracts\Enums\SessionAssociationLockState;
use ProPhoto\Contracts\Enums\SessionAssociationSource;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionContextReliability;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;

class IntelligenceEnumCoverageTest extends TestCase
{
    public function test_run_status_enum_contains_expected_values(): void
    {
        $values = array_map(static fn (RunStatus $status): string => $status->value, RunStatus::cases());

        $this->assertSame([
            'pending',
            'running',
            'completed',
            'failed',
            'cancelled',
        ], $values);
    }

    public function test_run_scope_enum_contains_expected_values(): void
    {
        $values = array_map(static fn (RunScope $scope): string => $scope->value, RunScope::cases());

        $this->assertSame([
            'single_asset',
            'batch',
            'reindex',
            'migration',
        ], $values);
    }

    public function test_session_context_reliability_enum_contains_expected_values(): void
    {
        $values = array_map(
            static fn (SessionContextReliability $reliability): string => $reliability->value,
            SessionContextReliability::cases()
        );

        $this->assertSame([
            'high',
            'medium',
            'low',
            'none',
        ], $values);
    }

    public function test_session_association_source_enum_contains_expected_values(): void
    {
        $values = array_map(
            static fn (SessionAssociationSource $source): string => $source->value,
            SessionAssociationSource::cases()
        );

        $this->assertSame([
            'auto',
            'manual',
            'proposal',
            'none',
        ], $values);
    }

    public function test_session_association_lock_state_enum_contains_expected_values(): void
    {
        $values = array_map(
            static fn (SessionAssociationLockState $state): string => $state->value,
            SessionAssociationLockState::cases()
        );

        $this->assertSame([
            'none',
            'manual_assigned_lock',
            'manual_unassigned_lock',
        ], $values);
    }

    public function test_session_match_confidence_tier_enum_contains_expected_values(): void
    {
        $values = array_map(
            static fn (SessionMatchConfidenceTier $tier): string => $tier->value,
            SessionMatchConfidenceTier::cases()
        );

        $this->assertSame([
            'high',
            'medium',
            'low',
        ], $values);
    }

    public function test_session_assignment_decision_type_enum_contains_expected_values(): void
    {
        $values = array_map(
            static fn (SessionAssignmentDecisionType $type): string => $type->value,
            SessionAssignmentDecisionType::cases()
        );

        $this->assertSame([
            'auto_assign',
            'propose',
            'no_match',
            'manual_assign',
            'manual_unassign',
        ], $values);
    }

    public function test_session_association_subject_type_enum_contains_expected_values(): void
    {
        $values = array_map(
            static fn (SessionAssociationSubjectType $type): string => $type->value,
            SessionAssociationSubjectType::cases()
        );

        $this->assertSame([
            'ingest_item',
            'asset',
        ], $values);
    }

    public function test_session_assignment_mode_enum_contains_expected_values(): void
    {
        $values = array_map(
            static fn (SessionAssignmentMode $mode): string => $mode->value,
            SessionAssignmentMode::cases()
        );

        $this->assertSame([
            'auto',
            'manual',
        ], $values);
    }
}
