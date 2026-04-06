<?php

namespace ProPhoto\Debug\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class QueueStatusService
{
    /**
     * Get comprehensive queue status
     */
    public function getStatus(): array
    {
        return Cache::remember('debug:queue-status', 5, function () {
            return [
                'horizon' => $this->getHorizonStatus(),
                'jobs' => $this->getJobStats(),
                'worker' => $this->getWorkerStatus(),
            ];
        });
    }

    /**
     * Check if Horizon is available and get its status
     */
    protected function getHorizonStatus(): array
    {
        $installed = class_exists(\Laravel\Horizon\Horizon::class);

        if (!$installed) {
            return [
                'installed' => false,
                'status' => null,
            ];
        }

        try {
            // Check Horizon master supervisor status
            $status = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();
            $isRunning = !empty($status);

            return [
                'installed' => true,
                'status' => $isRunning ? 'running' : 'inactive',
                'supervisors' => count($status),
            ];
        } catch (\Exception $e) {
            return [
                'installed' => true,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get job statistics from the jobs table
     */
    protected function getJobStats(): array
    {
        try {
            $connection = config('queue.default');
            $driver = config("queue.connections.{$connection}.driver");

            if ($driver === 'database') {
                return $this->getDatabaseJobStats();
            }

            if ($driver === 'redis' && class_exists(\Laravel\Horizon\Horizon::class)) {
                return $this->getRedisJobStats();
            }

            return [
                'driver' => $driver,
                'pending' => null,
                'failed' => $this->getFailedJobCount(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'pending' => null,
                'failed' => null,
            ];
        }
    }

    /**
     * Get stats for database queue driver
     */
    protected function getDatabaseJobStats(): array
    {
        $table = config('queue.connections.database.table', 'jobs');

        $pending = DB::table($table)->count();
        $reserved = DB::table($table)->whereNotNull('reserved_at')->count();

        // Get ingest-specific jobs
        $ingestPending = DB::table($table)
            ->where(function ($query) {
                $query->where('queue', 'like', '%ingest%')
                    ->orWhere('payload', 'like', '%ProcessPreviewJob%')
                    ->orWhere('payload', 'like', '%ProcessImageIngestJob%')
                    ->orWhere('payload', 'like', '%EnhancePreviewJob%');
            })
            ->count();

        return [
            'driver' => 'database',
            'pending' => $pending,
            'reserved' => $reserved,
            'ingest_pending' => $ingestPending,
            'failed' => $this->getFailedJobCount(),
        ];
    }

    /**
     * Get stats for Redis queue (with Horizon)
     */
    protected function getRedisJobStats(): array
    {
        try {
            $metrics = app(\Laravel\Horizon\Contracts\MetricsRepository::class);

            return [
                'driver' => 'redis',
                'pending' => $metrics->jobsPending(),
                'recent_jobs' => $metrics->recentlyFailed(),
                'failed' => $this->getFailedJobCount(),
            ];
        } catch (\Exception $e) {
            return [
                'driver' => 'redis',
                'error' => $e->getMessage(),
                'failed' => $this->getFailedJobCount(),
            ];
        }
    }

    /**
     * Get failed job count
     */
    protected function getFailedJobCount(): int
    {
        try {
            $table = config('queue.failed.table', 'failed_jobs');
            return DB::table($table)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get worker status by checking for recent job processing
     *
     * Returns status: 'processing', 'idle', 'stalled', or 'unknown'
     * - processing: Jobs are actively being worked on
     * - idle: Queue is empty, worker may be waiting
     * - stalled: Jobs are pending but not being processed (worker likely stopped)
     * - unknown: Cannot determine status
     */
    protected function getWorkerStatus(): array
    {
        try {
            $connection = config('queue.default');
            $driver = config("queue.connections.{$connection}.driver");

            // For database driver
            if ($driver === 'database') {
                $table = config('queue.connections.database.table', 'jobs');

                $pending = DB::table($table)->count();
                $processing = DB::table($table)->whereNotNull('reserved_at')->exists();

                // Determine status based on queue state
                if ($processing) {
                    // Jobs are actively being processed
                    return [
                        'status' => 'processing',
                        'likely_running' => true,
                        'hint' => 'Jobs are being processed',
                    ];
                }

                if ($pending === 0) {
                    // Queue is empty - worker could be running and waiting
                    return [
                        'status' => 'idle',
                        'likely_running' => null, // Unknown - could be running or stopped
                        'hint' => 'Queue empty, worker may be idle',
                    ];
                }

                // Jobs are pending but not being processed
                // Check how long the oldest job has been waiting
                $oldestJob = DB::table($table)
                    ->whereNull('reserved_at')
                    ->orderBy('created_at', 'asc')
                    ->first();

                if ($oldestJob) {
                    $waitingSeconds = now()->timestamp - $oldestJob->created_at;
                    if ($waitingSeconds > 30) {
                        // Jobs waiting > 30 seconds, worker likely stopped
                        return [
                            'status' => 'stalled',
                            'likely_running' => false,
                            'hint' => "Jobs waiting {$waitingSeconds}s - worker may be stopped",
                        ];
                    }
                }

                // Jobs just arrived, give worker a moment
                return [
                    'status' => 'pending',
                    'likely_running' => null,
                    'hint' => 'Jobs pending, checking worker...',
                ];
            }

            // For Horizon/Redis
            if (class_exists(\Laravel\Horizon\Horizon::class)) {
                $supervisors = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();
                return [
                    'status' => !empty($supervisors) ? 'processing' : 'stalled',
                    'likely_running' => !empty($supervisors),
                    'supervisors' => count($supervisors),
                    'hint' => !empty($supervisors) ? 'Horizon is running' : 'Horizon is not running',
                ];
            }

            return [
                'status' => 'unknown',
                'likely_running' => null,
                'hint' => 'Cannot determine worker status for this queue driver',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unknown',
                'likely_running' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clear the cached status
     */
    public function clearCache(): void
    {
        Cache::forget('debug:queue-status');
    }
}