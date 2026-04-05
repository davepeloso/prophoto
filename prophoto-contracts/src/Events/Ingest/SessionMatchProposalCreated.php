<?php

namespace ProPhoto\Contracts\Events\Ingest;

use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;

readonly class SessionMatchProposalCreated
{
    public function __construct(
        public int|string $decisionId,
        public SessionAssociationSubjectType $subjectType,
        public string $subjectId,
        public int|string|null $ingestItemId,
        public int|string|null $assetId,
        public int|string|null $topCandidateSessionId,
        public int $candidateCount,
        public SessionMatchConfidenceTier $confidenceTier,
        public ?float $confidenceScore,
        public string $algorithmVersion,
        public string $occurredAt
    ) {}
}

