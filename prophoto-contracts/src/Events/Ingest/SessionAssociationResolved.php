<?php

namespace ProPhoto\Contracts\Events\Ingest;

use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;

readonly class SessionAssociationResolved
{
    public function __construct(
        public int|string $decisionId,
        public SessionAssignmentDecisionType $decisionType,
        public SessionAssociationSubjectType $subjectType,
        public string $subjectId,
        public int|string|null $ingestItemId,
        public int|string|null $assetId,
        public int|string|null $selectedSessionId,
        public ?SessionMatchConfidenceTier $confidenceTier,
        public ?float $confidenceScore,
        public string $algorithmVersion,
        public string $occurredAt
    ) {}
}
