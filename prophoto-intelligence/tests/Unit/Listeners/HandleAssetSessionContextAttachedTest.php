<?php

namespace ProPhoto\Intelligence\Tests\Unit\Listeners;

use PHPUnit\Framework\TestCase;
use ProPhoto\Assets\Events\AssetSessionContextAttached;
use ProPhoto\Intelligence\Listeners\HandleAssetSessionContextAttached;
use ProPhoto\Intelligence\Orchestration\IntelligenceEntryOrchestrator;

class HandleAssetSessionContextAttachedTest extends TestCase
{
    public function test_listener_forwards_event_to_entry_orchestrator(): void
    {
        $orchestrator = $this->createMock(IntelligenceEntryOrchestrator::class);
        $orchestrator->expects($this->once())
            ->method('handleAssetSessionContextAttached')
            ->with(42, 9001, 'asset_session_context');

        $listener = new HandleAssetSessionContextAttached($orchestrator);

        $listener->handle(new AssetSessionContextAttached(
            assetId: 42,
            sessionId: 9001,
            sourceDecisionId: 'decision_1',
            triggerSource: 'asset_session_context',
            occurredAt: '2026-04-05T15:00:00Z'
        ));
    }
}
