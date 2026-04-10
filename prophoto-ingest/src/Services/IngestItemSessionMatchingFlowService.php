<?php

namespace ProPhoto\Ingest\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Contracts\Events\Ingest\SessionAssociationResolved;
use ProPhoto\Ingest\Domain\IngestItem;
use ProPhoto\Ingest\Events\IngestItemCreated;
use InvalidArgumentException;

class IngestItemSessionMatchingFlowService
{
    public function __construct(
        protected IngestItemContextBuilder $contextBuilder,
        protected BatchUploadRecognitionService $recognitionService,
        protected SessionMatchingService $matchingService,
        protected Dispatcher $events
    ) {}

    /**
     * @param list<array<string, mixed>> $sessionContexts
     * @param array<string, mixed> $options
     * @return array{
     *     subject_context: array<string, mixed>,
     *     recognition_result: array<string, mixed>,
     *     matching_result: array<string, mixed>
     * }
     */
    public function handleCreated(IngestItem $ingestItem, array $sessionContexts, array $options = []): array
    {
        $snapshots = $this->contextBuilder->buildInputSnapshots($ingestItem, $sessionContexts);
        $subjectContext = $snapshots['metadata_snapshot'];
        $sessionContextSnapshot = $snapshots['session_context_snapshot'] ?? [];
        $recognitionResult = $this->recognitionService->recognizeBatch(
            normalizedMetadataSnapshot: $subjectContext,
            sessionContextSnapshots: is_array($sessionContextSnapshot) ? $sessionContextSnapshot : [],
            options: $options
        );

        $this->events->dispatch(
            new IngestItemCreated(
                ingestItemId: $ingestItem->ingestItemId,
                captureAtUtc: $ingestItem->captureAtUtc,
                gpsLat: $ingestItem->gpsLat,
                gpsLng: $ingestItem->gpsLng,
                triggerSource: $ingestItem->triggerSource,
                occurredAt: $this->resolveOccurredAt($ingestItem->createdAt)
            )
        );

        $matchingResult = $this->matchingService->matchAndWrite(
            subjectContext: $subjectContext,
            sessionContexts: is_array($sessionContextSnapshot) ? $sessionContextSnapshot : [],
            options: $options
        );

        $this->dispatchResolvedEventIfApplicable($matchingResult);

        return [
            'subject_context' => $subjectContext,
            'recognition_result' => $recognitionResult,
            'matching_result' => $matchingResult,
        ];
    }

    protected function resolveOccurredAt(?string $createdAt): string
    {
        if (! is_string($createdAt) || $createdAt === '') {
            return Carbon::now('UTC')->toISOString();
        }

        try {
            return Carbon::parse($createdAt)->utc()->toISOString();
        } catch (\Throwable) {
            return Carbon::now('UTC')->toISOString();
        }
    }

    /**
     * @param array<string, mixed> $matchingResult
     */
    protected function dispatchResolvedEventIfApplicable(array $matchingResult): void
    {
        $decision = $matchingResult['decision'] ?? null;
        if (! is_array($decision)) {
            return;
        }

        $decisionTypeRaw = $decision['decision_type'] ?? null;
        if (! is_string($decisionTypeRaw) || $decisionTypeRaw === '') {
            return;
        }

        $decisionType = SessionAssignmentDecisionType::from($decisionTypeRaw);
        if (! in_array($decisionType, [
            SessionAssignmentDecisionType::AUTO_ASSIGN,
            SessionAssignmentDecisionType::PROPOSE,
        ], true)) {
            return;
        }

        $subjectTypeRaw = $decision['subject_type'] ?? null;
        if (! is_string($subjectTypeRaw) || $subjectTypeRaw === '') {
            throw new InvalidArgumentException('Decision payload is missing subject_type for resolved event emission.');
        }
        $subjectType = SessionAssociationSubjectType::from($subjectTypeRaw);

        $subjectId = $this->requiredDecisionString($decision, 'subject_id');
        $algorithmVersion = $this->requiredDecisionString($decision, 'algorithm_version');
        $occurredAt = $this->requiredDecisionString($decision, 'created_at');

        $confidenceTierRaw = $decision['confidence_tier'] ?? null;
        $confidenceTier = null;
        if (is_string($confidenceTierRaw) && $confidenceTierRaw !== '') {
            $confidenceTier = SessionMatchConfidenceTier::from($confidenceTierRaw);
        }

        $this->events->dispatch(
            new SessionAssociationResolved(
                decisionId: $decision['id'],
                decisionType: $decisionType,
                subjectType: $subjectType,
                subjectId: $subjectId,
                ingestItemId: $decision['ingest_item_id'] ?? null,
                assetId: $decision['asset_id'] ?? null,
                selectedSessionId: $decision['selected_session_id'] ?? null,
                confidenceTier: $confidenceTier,
                confidenceScore: isset($decision['confidence_score']) ? (float) $decision['confidence_score'] : null,
                algorithmVersion: $algorithmVersion,
                occurredAt: $occurredAt,
            )
        );
    }

    /**
     * @param array<string, mixed> $decision
     */
    protected function requiredDecisionString(array $decision, string $field): string
    {
        $value = $decision[$field] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("Decision payload is missing {$field} for resolved event emission.");
        }

        return $value;
    }
}
