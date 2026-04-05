<?php

namespace ProPhoto\Contracts\DTOs;

use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\SessionAssociationLockState;
use ProPhoto\Contracts\Enums\SessionAssociationSource;
use ProPhoto\Contracts\Enums\SessionContextReliability;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;

readonly class SessionContextSnapshot
{
    public function __construct(
        public AssetId $assetId,
        public int|string|null $sessionId = null,
        public int|string|null $bookingId = null,
        public ?string $sessionStatus = null,
        public ?string $sessionType = null,
        public ?string $jobType = null,
        public ?string $sessionTimezone = null,
        // ISO8601 UTC (e.g. 2026-03-13T18:00:00Z)
        public ?string $sessionWindowStart = null,
        // ISO8601 UTC (e.g. 2026-03-13T20:00:00Z)
        public ?string $sessionWindowEnd = null,
        public ?string $locationHint = null,
        public SessionAssociationSource $associationSource = SessionAssociationSource::NONE,
        public ?SessionMatchConfidenceTier $associationConfidenceTier = null,
        public SessionContextReliability $contextReliability = SessionContextReliability::NONE,
        public SessionAssociationLockState $manualLockState = SessionAssociationLockState::NONE,
        public int $snapshotVersion = 1,
        public ?string $snapshotCapturedAt = null
    ) {}
}
