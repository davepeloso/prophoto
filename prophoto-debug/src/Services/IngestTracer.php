<?php

namespace ProPhoto\Debug\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ProPhoto\Debug\Models\IngestTrace;

class IngestTracer
{
    /**
     * In-memory method order counters per session/type.
     *
     * @var array<string, int>
     */
    protected array $methodOrders = [];

    /**
     * Check if debug tracing is enabled.
     */
    public function isEnabled(): bool
    {
        return config('debug.enabled', false);
    }

    /**
     * Check if a specific trace type is enabled.
     */
    public function isTypeEnabled(string $traceType): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return config("debug.trace_types.{$traceType}", true);
    }

    /**
     * Start a new trace session for an upload.
     *
     * @param  string  $uuid  The ProxyImage UUID
     * @return string The session ID
     */
    public function startSession(string $uuid): string
    {
        $sessionId = Str::uuid()->toString();

        // Reset method orders for this session
        foreach (IngestTrace::traceTypes() as $type) {
            $this->methodOrders["{$sessionId}:{$type}"] = 0;
        }

        return $sessionId;
    }

    /**
     * Record an attempt at a specific method.
     */
    public function recordAttempt(string $sessionId, array $data): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $traceType = $data['trace_type'] ?? 'unknown';

        if (! $this->isTypeEnabled($traceType)) {
            return;
        }

        // Increment and get the method order for this session/type
        $orderKey = "{$sessionId}:{$traceType}";
        $this->methodOrders[$orderKey] = ($this->methodOrders[$orderKey] ?? 0) + 1;

        IngestTrace::create([
            'uuid' => $data['uuid'] ?? '',
            'session_id' => $sessionId,
            'trace_type' => $traceType,
            'method_tried' => $data['method_tried'] ?? 'unknown',
            'method_order' => $data['method_order'] ?? $this->methodOrders[$orderKey],
            'success' => $data['success'] ?? false,
            'failure_reason' => $data['failure_reason'] ?? null,
            'result_info' => $data['result_info'] ?? null,
        ]);
    }

    /**
     * Record a successful method execution.
     */
    public function recordSuccess(string $sessionId, string $uuid, string $traceType, string $method, array $info = []): void
    {
        $this->recordAttempt($sessionId, [
            'uuid' => $uuid,
            'trace_type' => $traceType,
            'method_tried' => $method,
            'success' => true,
            'result_info' => $info,
        ]);
    }

    /**
     * Record a failed method execution.
     */
    public function recordFailure(string $sessionId, string $uuid, string $traceType, string $method, string $reason): void
    {
        $this->recordAttempt($sessionId, [
            'uuid' => $uuid,
            'trace_type' => $traceType,
            'method_tried' => $method,
            'success' => false,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Get all traces for a specific UUID.
     */
    public function getTrace(string $uuid): Collection
    {
        return IngestTrace::forUuid($uuid)
            ->orderBy('created_at')
            ->orderBy('method_order')
            ->get();
    }

    /**
     * Get all traces for a specific session.
     */
    public function getSessionTrace(string $sessionId): Collection
    {
        return IngestTrace::forSession($sessionId)
            ->orderBy('created_at')
            ->orderBy('method_order')
            ->get();
    }

    /**
     * Get traces grouped by trace type for a UUID.
     */
    public function getTraceGrouped(string $uuid): Collection
    {
        return $this->getTrace($uuid)->groupBy('trace_type');
    }

    /**
     * Get a summary of trace results for a UUID.
     */
    public function getTraceSummary(string $uuid): array
    {
        $traces = $this->getTrace($uuid);

        return [
            'uuid' => $uuid,
            'total_attempts' => $traces->count(),
            'successful' => $traces->where('success', true)->count(),
            'failed' => $traces->where('success', false)->count(),
            'by_type' => $traces->groupBy('trace_type')->map(function ($group) {
                $successful = $group->firstWhere('success', true);

                return [
                    'attempts' => $group->count(),
                    'successful_method' => $successful?->method_tried,
                    'duration_ms' => $successful?->duration_ms,
                ];
            }),
        ];
    }

    /**
     * Get the winning method for each trace type for a UUID.
     */
    public function getWinningMethods(string $uuid): array
    {
        $traces = $this->getTrace($uuid);

        return $traces
            ->where('success', true)
            ->groupBy('trace_type')
            ->map(function ($group) {
                $winner = $group->first();

                return [
                    'method' => $winner->method_tried,
                    'order' => $winner->method_order,
                    'duration_ms' => $winner->duration_ms,
                    'size' => $winner->size,
                    'dimensions' => $winner->dimensions,
                ];
            })
            ->toArray();
    }

    /**
     * Delete traces older than retention period.
     */
    public function cleanup(?int $days = null): int
    {
        return IngestTrace::expired($days)->delete();
    }

    /**
     * Get statistics about stored traces.
     */
    public function getStats(): array
    {
        return [
            'total_traces' => IngestTrace::count(),
            'unique_uploads' => IngestTrace::distinct('uuid')->count('uuid'),
            'unique_sessions' => IngestTrace::distinct('session_id')->count('session_id'),
            'by_type' => IngestTrace::selectRaw('trace_type, COUNT(*) as count')
                ->groupBy('trace_type')
                ->pluck('count', 'trace_type')
                ->toArray(),
            'success_rate' => $this->calculateSuccessRate(),
            'oldest_trace' => IngestTrace::min('created_at'),
            'newest_trace' => IngestTrace::max('created_at'),
        ];
    }

    /**
     * Calculate the overall success rate.
     */
    protected function calculateSuccessRate(): ?float
    {
        $total = IngestTrace::count();

        if ($total === 0) {
            return null;
        }

        $successful = IngestTrace::where('success', true)->count();

        return round(($successful / $total) * 100, 2);
    }
}
