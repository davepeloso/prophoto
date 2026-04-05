<?php

namespace ProPhoto\Intelligence\Tests\Unit\Generators;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;
use ProPhoto\Contracts\DTOs\SessionContextSnapshot;
use ProPhoto\Contracts\Enums\RunScope;
use ProPhoto\Contracts\Enums\SessionAssociationLockState;
use ProPhoto\Contracts\Enums\SessionAssociationSource;
use ProPhoto\Contracts\Enums\SessionContextReliability;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Intelligence\Generators\EventSceneTaggingGenerator;

class EventSceneTaggingGeneratorTest extends TestCase
{
    public function test_generator_returns_context_aware_labels_when_session_context_exists(): void
    {
        $generator = new EventSceneTaggingGenerator();
        $runContext = $this->runContext(withSessionContext: true);

        $result = $generator->generate($runContext, ['mime_type' => 'image/jpeg']);
        $labels = array_map(static fn ($label): string => $label->label, $result->labels);

        $this->assertContains('event_scene_tagged', $labels);
        $this->assertContains('session_wedding_ceremony', $labels);
        $this->assertContains('job_wedding', $labels);
    }

    public function test_generator_falls_back_to_base_label_without_session_context(): void
    {
        $generator = new EventSceneTaggingGenerator();
        $runContext = $this->runContext(withSessionContext: false);

        $result = $generator->generate($runContext, ['mime_type' => 'image/jpeg']);
        $labels = array_map(static fn ($label): string => $label->label, $result->labels);

        $this->assertSame(['event_scene_tagged'], $labels);
    }

    protected function runContext(bool $withSessionContext): IntelligenceRunContext
    {
        $sessionContext = null;
        if ($withSessionContext) {
            $sessionContext = new SessionContextSnapshot(
                assetId: AssetId::from('asset_1'),
                sessionId: 'session_1',
                bookingId: 'booking_1',
                sessionStatus: 'confirmed',
                sessionType: 'Wedding Ceremony',
                jobType: 'Wedding',
                sessionTimezone: 'UTC',
                sessionWindowStart: '2026-04-04T10:00:00Z',
                sessionWindowEnd: '2026-04-04T12:00:00Z',
                locationHint: 'Venue',
                associationSource: SessionAssociationSource::MANUAL,
                associationConfidenceTier: SessionMatchConfidenceTier::HIGH,
                contextReliability: SessionContextReliability::HIGH,
                manualLockState: SessionAssociationLockState::MANUAL_ASSIGNED_LOCK,
                snapshotVersion: 1,
                snapshotCapturedAt: '2026-04-04T09:59:00Z'
            );
        }

        return new IntelligenceRunContext(
            assetId: AssetId::from('asset_1'),
            runId: 'run_1',
            generatorType: 'event_scene_tagging',
            generatorVersion: 'v1',
            modelName: 'event-scene-model',
            modelVersion: 'v1',
            runScope: RunScope::SINGLE_ASSET,
            configurationHash: 'cfg_hash',
            sessionContextSnapshot: $sessionContext
        );
    }
}
