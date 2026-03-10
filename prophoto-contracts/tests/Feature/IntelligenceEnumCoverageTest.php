<?php

namespace ProPhoto\Contracts\Tests\Feature;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\Enums\RunScope;
use ProPhoto\Contracts\Enums\RunStatus;

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
}
