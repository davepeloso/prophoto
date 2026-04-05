<?php

namespace ProPhoto\Contracts\Events\Ingest;

use ProPhoto\Contracts\Enums\SessionAssociationLockState;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;

readonly class SessionManualUnassignmentApplied
{
    public function __construct(
        public int|string $assignmentId,
        public int|string $decisionId,
        public SessionAssociationSubjectType $subjectType,
        public string $subjectId,
        public int|string|null $ingestItemId,
        public int|string|null $assetId,
        public SessionAssociationLockState $lockState,
        public ?string $manualOverrideReasonCode,
        public string $actorId,
        public string $occurredAt
    ) {}
}

