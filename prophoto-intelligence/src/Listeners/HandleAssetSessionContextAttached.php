<?php

namespace ProPhoto\Intelligence\Listeners;

use ProPhoto\Assets\Events\AssetSessionContextAttached;
use ProPhoto\Intelligence\Orchestration\IntelligenceEntryOrchestrator;

class HandleAssetSessionContextAttached
{
    public function __construct(
        protected IntelligenceEntryOrchestrator $entryOrchestrator
    ) {}

    public function handle(AssetSessionContextAttached $event): void
    {
        $this->entryOrchestrator->handleAssetSessionContextAttached(
            assetId: $event->assetId,
            sessionId: $event->sessionId,
            triggerSource: $event->triggerSource
        );
    }
}
