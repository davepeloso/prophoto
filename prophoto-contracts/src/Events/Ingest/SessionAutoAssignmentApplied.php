<?php

namespace ProPhoto\Contracts\Events\Ingest;

use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;

readonly class SessionAutoAssignmentApplied
{
    public function __construct(
        public int|string $assignmentId,
        public int|string $decisionId,
        public SessionAssociationSubjectType $subjectType,
        public string $subjectId,
        public int|string|null $ingestItemId,
        public int|string|null $assetId,
        public int|string $sessionId,
        public SessionMatchConfidenceTier $confidenceTier,
        public ?float $confidenceScore,
        public string $algorithmVersion,
        public string $occurredAt
    ) {}
}

