<?php

namespace ProPhoto\Assets\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use ProPhoto\Assets\Events\AssetSessionContextAttached;
use ProPhoto\Assets\Tests\TestCase;
use ProPhoto\Contracts\Enums\SessionAssignmentDecisionType;
use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Contracts\Events\Ingest\SessionAssociationResolved;

class HandleSessionAssociationResolvedTest extends TestCase
{
    public function test_auto_assign_writes_asset_session_context_row(): void
    {
        $assetId = $this->createAsset();
        Event::fakeExcept([SessionAssociationResolved::class]);

        $this->app['events']->dispatch($this->resolvedEvent([
            'decisionId' => 'decision-auto-1',
            'decisionType' => SessionAssignmentDecisionType::AUTO_ASSIGN,
            'assetId' => $assetId,
            'selectedSessionId' => 5001,
            'confidenceTier' => SessionMatchConfidenceTier::HIGH,
            'confidenceScore' => 0.96,
        ]));

        $row = DB::table('asset_session_contexts')
            ->where('source_decision_id', 'decision-auto-1')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($assetId, (int) $row->asset_id);
        $this->assertSame(5001, (int) $row->session_id);
        $this->assertSame('auto_assign', $row->decision_type);
        $this->assertSame('asset', $row->subject_type);
        $this->assertSame('high', $row->confidence_tier);

        Event::assertDispatched(AssetSessionContextAttached::class, function (AssetSessionContextAttached $event) use ($assetId): bool {
            return (int) $event->assetId === $assetId
                && (int) $event->sessionId === 5001
                && (string) $event->sourceDecisionId === 'decision-auto-1'
                && $event->triggerSource === 'asset_session_context';
        });
    }

    public function test_idempotency_duplicate_event_does_not_create_duplicate_rows(): void
    {
        $assetId = $this->createAsset();

        $event = $this->resolvedEvent([
            'decisionId' => 'decision-auto-2',
            'decisionType' => SessionAssignmentDecisionType::AUTO_ASSIGN,
            'assetId' => $assetId,
            'selectedSessionId' => 5002,
            'confidenceTier' => SessionMatchConfidenceTier::HIGH,
            'confidenceScore' => 0.95,
        ]);

        $this->app['events']->dispatch($event);
        $this->app['events']->dispatch($event);

        $this->assertSame(
            1,
            DB::table('asset_session_contexts')
                ->where('source_decision_id', 'decision-auto-2')
                ->count()
        );
    }

    public function test_no_match_is_ignored(): void
    {
        $assetId = $this->createAsset();
        Event::fakeExcept([SessionAssociationResolved::class]);

        $this->app['events']->dispatch($this->resolvedEvent([
            'decisionId' => 'decision-no-match-1',
            'decisionType' => SessionAssignmentDecisionType::NO_MATCH,
            'assetId' => $assetId,
            'selectedSessionId' => null,
            'confidenceTier' => SessionMatchConfidenceTier::LOW,
            'confidenceScore' => 0.21,
        ]));

        $this->assertSame(0, DB::table('asset_session_contexts')->count());
        Event::assertNotDispatched(AssetSessionContextAttached::class);
    }

    public function test_propose_is_explicit_no_op(): void
    {
        $assetId = $this->createAsset();
        Event::fakeExcept([SessionAssociationResolved::class]);

        $this->app['events']->dispatch($this->resolvedEvent([
            'decisionId' => 'decision-propose-1',
            'decisionType' => SessionAssignmentDecisionType::PROPOSE,
            'assetId' => $assetId,
            'selectedSessionId' => 5003,
            'confidenceTier' => SessionMatchConfidenceTier::MEDIUM,
            'confidenceScore' => 0.62,
        ]));

        $this->assertSame(0, DB::table('asset_session_contexts')->count());
        Event::assertNotDispatched(AssetSessionContextAttached::class);
    }

    public function test_auto_assign_with_missing_asset_id_is_ignored_without_exception(): void
    {
        $this->app['events']->dispatch($this->resolvedEvent([
            'decisionId' => 'decision-auto-missing-asset',
            'decisionType' => SessionAssignmentDecisionType::AUTO_ASSIGN,
            'assetId' => null,
            'selectedSessionId' => 5004,
            'confidenceTier' => SessionMatchConfidenceTier::HIGH,
            'confidenceScore' => 0.91,
        ]));

        $this->assertSame(0, DB::table('asset_session_contexts')->count());
    }

    protected function createAsset(): int
    {
        return (int) DB::table('assets')->insertGetId([
            'studio_id' => 'studio_test',
            'organization_id' => null,
            'type' => 'photo',
            'original_filename' => 'fixture.jpg',
            'mime_type' => 'image/jpeg',
            'bytes' => 1024,
            'checksum_sha256' => hash('sha256', uniqid('asset-', true)),
            'storage_driver' => 'local',
            'storage_key_original' => 'fixtures/original/fixture.jpg',
            'logical_path' => 'fixtures/tests',
            'captured_at' => null,
            'ingested_at' => null,
            'status' => 'ready',
            'metadata' => null,
            'created_at' => now('UTC')->toISOString(),
            'updated_at' => now('UTC')->toISOString(),
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function resolvedEvent(array $overrides = []): SessionAssociationResolved
    {
        return new SessionAssociationResolved(
            decisionId: $overrides['decisionId'] ?? 'decision-default',
            decisionType: $overrides['decisionType'] ?? SessionAssignmentDecisionType::AUTO_ASSIGN,
            subjectType: $overrides['subjectType'] ?? SessionAssociationSubjectType::ASSET,
            subjectId: $overrides['subjectId'] ?? (string) ($overrides['assetId'] ?? 1),
            ingestItemId: $overrides['ingestItemId'] ?? null,
            assetId: $overrides['assetId'] ?? null,
            selectedSessionId: $overrides['selectedSessionId'] ?? null,
            confidenceTier: $overrides['confidenceTier'] ?? null,
            confidenceScore: $overrides['confidenceScore'] ?? null,
            algorithmVersion: $overrides['algorithmVersion'] ?? 'v1',
            occurredAt: $overrides['occurredAt'] ?? '2026-04-05T12:00:00Z'
        );
    }
}
