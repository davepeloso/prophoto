<?php

namespace ProPhoto\Assets\Events;

/**
 * Emitted only when a new asset session-context attachment is persisted.
 * Not emitted for idempotent/replayed no-op attempts.
 */
readonly class AssetSessionContextAttached
{
    public function __construct(
        public int|string $assetId,
        public int|string $sessionId,
        public int|string $sourceDecisionId,
        public string $triggerSource,
        public string $occurredAt
    ) {}
}
