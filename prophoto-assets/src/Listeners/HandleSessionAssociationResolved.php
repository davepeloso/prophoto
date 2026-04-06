<?php

namespace ProPhoto\Assets\Listeners;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use ProPhoto\Assets\Events\AssetSessionContextAttached;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Events\Ingest\SessionAssociationResolved;

class HandleSessionAssociationResolved
{
    public function handle(SessionAssociationResolved $event): void
    {
        if ($event->decisionType !== SessionAssignmentDecisionType::AUTO_ASSIGN) {
            return;
        }

        if ($event->assetId === null) {
            return;
        }

        if ($event->selectedSessionId === null) {
            return;
        }

        $inserted = DB::table('asset_session_contexts')->insertOrIgnore([
            'asset_id' => $event->assetId,
            'session_id' => $event->selectedSessionId,
            'source_decision_id' => (string) $event->decisionId,
            'decision_type' => $event->decisionType->value,
            'subject_type' => $event->subjectType->value,
            'subject_id' => $event->subjectId,
            'ingest_item_id' => $event->ingestItemId === null ? null : (string) $event->ingestItemId,
            'confidence_tier' => $event->confidenceTier?->value,
            'confidence_score' => $event->confidenceScore,
            'algorithm_version' => $event->algorithmVersion,
            'occurred_at' => $event->occurredAt,
            'created_at' => now('UTC')->toISOString(),
            'updated_at' => now('UTC')->toISOString(),
        ]);

        if ((int) $inserted < 1) {
            return;
        }

        Event::dispatch(new AssetSessionContextAttached(
            assetId: $event->assetId,
            sessionId: $event->selectedSessionId,
            sourceDecisionId: $event->decisionId,
            triggerSource: 'asset_session_context',
            occurredAt: $event->occurredAt
        ));
    }
}
