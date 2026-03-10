<?php

namespace ProPhoto\Intelligence\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\RunScope;
use ProPhoto\Contracts\Enums\RunStatus;
use ProPhoto\Intelligence\Repositories\IntelligenceRunRepository;
use ProPhoto\Intelligence\Tests\TestCase;

class IntelligenceRunRepositoryTest extends TestCase
{
    public function test_create_pending_run_returns_existing_active_run_for_same_configuration_hash(): void
    {
        $assetId = $this->createAsset();
        $existingRunId = $this->insertRun(
            assetId: $assetId,
            configurationHash: 'config-a',
            runStatus: RunStatus::PENDING->value
        );

        /** @var IntelligenceRunRepository $repository */
        $repository = $this->app->make(IntelligenceRunRepository::class);

        $returnedRunId = $repository->createPendingRun(
            assetId: AssetId::from($assetId),
            generatorType: 'demo_tagging',
            generatorVersion: 'v1',
            modelName: 'demo-tag-model',
            modelVersion: 'v1',
            configurationHash: 'config-a',
            runScope: RunScope::SINGLE_ASSET
        );

        $this->assertSame($existingRunId, $returnedRunId);
    }

    public function test_create_pending_run_throws_for_active_tuple_with_different_configuration_hash(): void
    {
        $assetId = $this->createAsset();
        $this->insertRun(
            assetId: $assetId,
            configurationHash: 'config-a',
            runStatus: RunStatus::PENDING->value
        );

        /** @var IntelligenceRunRepository $repository */
        $repository = $this->app->make(IntelligenceRunRepository::class);

        $this->expectException(QueryException::class);

        $repository->createPendingRun(
            assetId: AssetId::from($assetId),
            generatorType: 'demo_tagging',
            generatorVersion: 'v1',
            modelName: 'demo-tag-model',
            modelVersion: 'v1',
            configurationHash: 'config-b',
            runScope: RunScope::SINGLE_ASSET
        );
    }

    protected function createAsset(): int
    {
        return (int) DB::table('assets')->insertGetId([
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    protected function insertRun(int $assetId, string $configurationHash, string $runStatus): int
    {
        return (int) DB::table('intelligence_runs')->insertGetId([
            'asset_id' => $assetId,
            'generator_type' => 'demo_tagging',
            'generator_version' => 'v1',
            'model_name' => 'demo-tag-model',
            'model_version' => 'v1',
            'configuration_hash' => $configurationHash,
            'run_scope' => RunScope::SINGLE_ASSET->value,
            'run_status' => $runStatus,
            'retry_count' => 0,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
