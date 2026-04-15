<?php

namespace ProPhoto\AI\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ProPhoto\AI\Registry\AiProviderRegistry;
use ProPhoto\AI\Services\AiCostService;
use ProPhoto\Contracts\Contracts\AI\AiProviderContract;
use ProPhoto\Contracts\DTOs\AI\Money;

class AiCostServiceTest extends TestCase
{
    private function makeService(?AiProviderRegistry $registry = null): AiCostService
    {
        $registry ??= $this->createMock(AiProviderRegistry::class);

        return new AiCostService($registry);
    }

    // ── Estimate Training Cost ──────────────────────────────────────

    public function test_estimate_training_cost_delegates_to_provider(): void
    {
        $provider = $this->createMock(AiProviderContract::class);
        $provider->expects($this->once())
            ->method('estimateTrainingCost')
            ->with(10)
            ->willReturn(new Money(150));

        $registry = $this->createMock(AiProviderRegistry::class);
        $registry->expects($this->once())
            ->method('resolve')
            ->with('astria')
            ->willReturn($provider);

        $service = $this->makeService($registry);
        $cost = $service->estimateTrainingCost('astria', 10);

        $this->assertSame(150, $cost->amount);
    }

    // ── Estimate Generation Cost ────────────────────────────────────

    public function test_estimate_generation_cost_delegates_to_provider(): void
    {
        $provider = $this->createMock(AiProviderContract::class);
        $provider->expects($this->once())
            ->method('estimateGenerationCost')
            ->with(8)
            ->willReturn(new Money(23));

        $registry = $this->createMock(AiProviderRegistry::class);
        $registry->expects($this->once())
            ->method('resolve')
            ->with('astria')
            ->willReturn($provider);

        $service = $this->makeService($registry);
        $cost = $service->estimateGenerationCost('astria', 8);

        $this->assertSame(23, $cost->amount);
    }

    // ── Unknown Provider ────────────────────────────────────────────

    public function test_estimate_training_cost_throws_for_unknown_provider(): void
    {
        $registry = $this->createMock(AiProviderRegistry::class);
        $registry->expects($this->once())
            ->method('resolve')
            ->with('unknown')
            ->willThrowException(new \InvalidArgumentException('Provider not found'));

        $service = $this->makeService($registry);

        $this->expectException(\InvalidArgumentException::class);
        $service->estimateTrainingCost('unknown', 10);
    }
}
