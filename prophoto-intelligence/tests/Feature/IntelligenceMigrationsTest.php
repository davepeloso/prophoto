<?php

namespace ProPhoto\Intelligence\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ProPhoto\Intelligence\Tests\TestCase;

class IntelligenceMigrationsTest extends TestCase
{
    public function test_tables_and_required_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('intelligence_runs'));
        $this->assertTrue(Schema::hasTable('asset_labels'));
        $this->assertTrue(Schema::hasTable('asset_embeddings'));

        foreach ([
            'id',
            'asset_id',
            'generator_type',
            'generator_version',
            'model_name',
            'model_version',
            'configuration_hash',
            'run_scope',
            'run_status',
            'started_at',
            'completed_at',
            'failed_at',
            'failure_code',
            'failure_message',
            'cancelled_at',
            'cancellation_reason',
            'retry_count',
            'trigger_source',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('intelligence_runs', $column), "Missing intelligence_runs.{$column}");
        }

        foreach (['id', 'asset_id', 'run_id', 'label', 'confidence', 'created_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('asset_labels', $column), "Missing asset_labels.{$column}");
        }

        foreach (['id', 'asset_id', 'run_id', 'embedding_vector', 'vector_dimensions', 'created_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('asset_embeddings', $column), "Missing asset_embeddings.{$column}");
        }
    }

    public function test_active_run_uniqueness_is_enforced_for_pending_and_running_states(): void
    {
        $assetId = $this->createAsset();
        $tuple = [
            'asset_id' => $assetId,
            'generator_type' => 'embedding_generator',
            'generator_version' => 'v1',
            'model_name' => 'text-embedding-3-large',
            'model_version' => '2025-02',
            'configuration_hash' => 'hash-1',
        ];

        $this->createRun($tuple + ['run_status' => 'pending']);

        $this->expectException(QueryException::class);
        $this->createRun($tuple + ['run_status' => 'running']);
    }

    public function test_completed_runs_can_repeat_same_tuple_without_mutating_history(): void
    {
        $assetId = $this->createAsset();
        $tuple = [
            'asset_id' => $assetId,
            'generator_type' => 'embedding_generator',
            'generator_version' => 'v1',
            'model_name' => 'text-embedding-3-large',
            'model_version' => '2025-02',
            'configuration_hash' => 'hash-1',
            'run_status' => 'completed',
        ];

        $this->createRun($tuple);
        $this->createRun($tuple);

        $count = DB::table('intelligence_runs')
            ->where('asset_id', $assetId)
            ->where('generator_type', 'embedding_generator')
            ->where('run_status', 'completed')
            ->count();

        $this->assertSame(2, $count);
    }

    public function test_label_uniqueness_is_enforced_per_run(): void
    {
        $assetId = $this->createAsset();
        $runId = $this->createRun([
            'asset_id' => $assetId,
            'generator_type' => 'tagging_generator',
            'generator_version' => 'v1',
            'model_name' => 'clip-vit-l',
            'model_version' => '2025-01',
            'configuration_hash' => 'hash-labels',
            'run_status' => 'completed',
        ]);

        DB::table('asset_labels')->insert([
            'asset_id' => $assetId,
            'run_id' => $runId,
            'label' => 'portrait',
            'confidence' => 0.99,
            'created_at' => now()->toDateTimeString(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('asset_labels')->insert([
            'asset_id' => $assetId,
            'run_id' => $runId,
            'label' => 'portrait',
            'confidence' => 0.70,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_embedding_uniqueness_is_enforced_per_asset_per_run(): void
    {
        $assetId = $this->createAsset();
        $runId = $this->createRun([
            'asset_id' => $assetId,
            'generator_type' => 'embedding_generator',
            'generator_version' => 'v1',
            'model_name' => 'text-embedding-3-large',
            'model_version' => '2025-02',
            'configuration_hash' => 'hash-embeddings',
            'run_status' => 'completed',
        ]);

        DB::table('asset_embeddings')->insert([
            'asset_id' => $assetId,
            'run_id' => $runId,
            'embedding_vector' => json_encode([0.1, -0.2, 0.3], JSON_THROW_ON_ERROR),
            'vector_dimensions' => 3,
            'created_at' => now()->toDateTimeString(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('asset_embeddings')->insert([
            'asset_id' => $assetId,
            'run_id' => $runId,
            'embedding_vector' => json_encode([0.7, 0.8, 0.9], JSON_THROW_ON_ERROR),
            'vector_dimensions' => 3,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_foreign_keys_are_enforced(): void
    {
        $this->expectException(QueryException::class);

        $this->createRun([
            'asset_id' => 999999,
            'generator_type' => 'embedding_generator',
            'generator_version' => 'v1',
            'model_name' => 'text-embedding-3-large',
            'model_version' => '2025-02',
            'configuration_hash' => 'hash-missing-asset',
            'run_status' => 'pending',
        ]);
    }

    public function test_foreign_keys_are_enforced_for_labels_and_embeddings(): void
    {
        $assetId = $this->createAsset();
        $runId = $this->createRun([
            'asset_id' => $assetId,
            'generator_type' => 'tagging_generator',
            'generator_version' => 'v1',
            'model_name' => 'clip-vit-l',
            'model_version' => '2025-01',
            'configuration_hash' => 'hash-fk',
            'run_status' => 'completed',
        ]);

        try {
            DB::table('asset_labels')->insert([
                'asset_id' => $assetId,
                'run_id' => $runId + 999,
                'label' => 'wedding',
                'confidence' => 0.88,
                'created_at' => now()->toDateTimeString(),
            ]);
            $this->fail('Expected FK violation for asset_labels.run_id');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);

        DB::table('asset_embeddings')->insert([
            'asset_id' => $assetId,
            'run_id' => $runId + 999,
            'embedding_vector' => json_encode([0.1, 0.2], JSON_THROW_ON_ERROR),
            'vector_dimensions' => 2,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    protected function createAsset(): int
    {
        return (int) DB::table('assets')->insertGetId([
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function createRun(array $attributes): int
    {
        $now = now()->toDateTimeString();

        return (int) DB::table('intelligence_runs')->insertGetId(array_merge([
            'asset_id' => $this->createAsset(),
            'generator_type' => 'embedding_generator',
            'generator_version' => 'v1',
            'model_name' => 'text-embedding-3-large',
            'model_version' => '2025-02',
            'configuration_hash' => 'hash-default',
            'run_scope' => 'single_asset',
            'run_status' => 'pending',
            'retry_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
    }
}
