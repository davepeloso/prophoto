<?php

namespace ProPhoto\Intelligence\Repositories;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\RunScope;
use ProPhoto\Contracts\Enums\RunStatus;

class IntelligenceRunRepository
{
    public function createPendingRun(
        AssetId $assetId,
        string $generatorType,
        string $generatorVersion,
        string $modelName,
        string $modelVersion,
        string $configurationHash,
        RunScope $runScope = RunScope::SINGLE_ASSET,
        ?string $triggerSource = null
    ): int {
        $assetValue = $assetId->toInt();
        $this->assertAssetExists($assetValue);

        $payload = [
            'asset_id' => $assetValue,
            'generator_type' => $generatorType,
            'generator_version' => $generatorVersion,
            'model_name' => $modelName,
            'model_version' => $modelVersion,
            'configuration_hash' => $configurationHash,
            'run_scope' => $runScope->value,
            'run_status' => RunStatus::PENDING->value,
            'retry_count' => 0,
            'trigger_source' => $triggerSource,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        try {
            return (int) DB::table('intelligence_runs')->insertGetId($payload);
        } catch (QueryException $exception) {
            $existingRunId = DB::table('intelligence_runs')
                ->where('asset_id', $assetValue)
                ->where('generator_type', $generatorType)
                ->where('generator_version', $generatorVersion)
                ->where('model_name', $modelName)
                ->where('model_version', $modelVersion)
                ->where('configuration_hash', $configurationHash)
                ->whereIn('run_status', [RunStatus::PENDING->value, RunStatus::RUNNING->value])
                ->orderByDesc('id')
                ->value('id');

            if ($existingRunId !== null) {
                return (int) $existingRunId;
            }

            throw $exception;
        }
    }

    public function markRunning(int|string $runId): bool
    {
        // Returns false when the run is missing or no longer in pending state.
        // This includes cases where another worker already claimed it.
        $updated = DB::table('intelligence_runs')
            ->where('id', $runId)
            ->where('run_status', RunStatus::PENDING->value)
            ->update([
                'run_status' => RunStatus::RUNNING->value,
                'started_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);

        return $updated > 0;
    }

    public function markCompleted(int|string $runId): void
    {
        DB::table('intelligence_runs')
            ->where('id', $runId)
            ->whereIn('run_status', [RunStatus::PENDING->value, RunStatus::RUNNING->value])
            ->update([
                'run_status' => RunStatus::COMPLETED->value,
                'completed_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    public function markFailed(int|string $runId, string $failureCode, string $failureMessage): void
    {
        DB::table('intelligence_runs')
            ->where('id', $runId)
            ->whereIn('run_status', [RunStatus::PENDING->value, RunStatus::RUNNING->value])
            ->update([
                'run_status' => RunStatus::FAILED->value,
                'failed_at' => now()->toDateTimeString(),
                'failure_code' => mb_substr($failureCode, 0, 100),
                'failure_message' => $failureMessage,
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    public function markCancelled(int|string $runId, ?string $reason = null): void
    {
        DB::table('intelligence_runs')
            ->where('id', $runId)
            ->whereIn('run_status', [RunStatus::PENDING->value, RunStatus::RUNNING->value])
            ->update([
                'run_status' => RunStatus::CANCELLED->value,
                'cancelled_at' => now()->toDateTimeString(),
                'cancellation_reason' => $reason,
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    public function find(int|string $runId): ?object
    {
        $row = DB::table('intelligence_runs')->where('id', $runId)->first();

        return $row ?: null;
    }

    /**
     * @return list<object>
     */
    public function plannerRunSummariesForAsset(AssetId $assetId): array
    {
        return DB::table('intelligence_runs')
            ->select([
                'asset_id',
                'generator_type',
                'generator_version',
                'model_name',
                'model_version',
                'configuration_hash',
                'run_status',
            ])
            ->where('asset_id', $assetId->toInt())
            ->whereIn('run_status', [
                RunStatus::PENDING->value,
                RunStatus::RUNNING->value,
                RunStatus::COMPLETED->value,
            ])
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    protected function assertAssetExists(int $assetId): void
    {
        if (! DB::table('assets')->where('id', $assetId)->exists()) {
            throw new InvalidArgumentException("Cannot create intelligence run: canonical asset {$assetId} was not found.");
        }
    }
}
