<?php

namespace ProPhoto\Ingest\Services\Matching;

use Illuminate\Support\Carbon;

class SessionMatchCandidateGenerator
{
    /**
     * Session statuses that are terminal — never viable candidates.
     * Confirmed and in_progress are preferred. Tentative is allowed by default.
     *
     * @var list<string>
     */
    protected const EXCLUDED_STATUSES = ['cancelled', 'no_show'];

    /**
     * Generate a sorted, filtered list of session candidates for a subject.
     *
     * Filtering applied in order:
     *   1. Normalization failure (missing session_id, invalid/reversed window) → dropped
     *   2. Terminal status (cancelled, no_show) → dropped
     *   3. Time window distance (if capture_at_utc is present and exceeds max_candidate_distance_minutes) → dropped
     *
     * If capture_at_utc is absent or unparseable the time-window filter is skipped entirely.
     * This is intentional: when capture time is unknown we surface all non-terminal candidates
     * and let the scorer weight them. Do not remove this bypass without adding a test.
     *
     * @param array<string, mixed> $subjectContext
     * @param list<array<string, mixed>> $sessionContexts
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     */
    public function generate(array $subjectContext, array $sessionContexts, array $options = []): array
    {
        $captureAt = $this->parseUtc($subjectContext['capture_at_utc'] ?? null);
        $maxDistanceMinutes = (int) ($options['max_candidate_distance_minutes'] ?? 720);
        if ($maxDistanceMinutes < 0) {
            $maxDistanceMinutes = 0;
        }

        $candidates = [];

        foreach ($sessionContexts as $sessionContext) {
            if (! is_array($sessionContext)) {
                continue;
            }

            $normalized = $this->normalizeSessionContext($sessionContext);
            if ($normalized === null) {
                // Dropped: normalization failure (missing session_id, invalid/reversed window).
                continue;
            }

            if ($this->isTerminalStatus($normalized['status'])) {
                // Dropped: terminal status — cancelled and no_show are never viable candidates.
                continue;
            }

            if ($captureAt instanceof Carbon) {
                $outsideMinutes = $this->minutesOutsideEffectiveWindow($captureAt, $normalized);
                if ($outsideMinutes > $maxDistanceMinutes) {
                    // Dropped: capture time too far from effective window.
                    continue;
                }

                $normalized['outside_window_minutes'] = $outsideMinutes;
                $normalized['candidate_generation_reason'] = $outsideMinutes === 0
                    ? 'within_effective_window'
                    : 'within_max_distance';
            } else {
                // No capture time — time-window filter skipped intentionally (see docblock).
                $normalized['outside_window_minutes'] = null;
                $normalized['candidate_generation_reason'] = 'no_capture_time';
            }

            $candidates[] = $normalized;
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => strcmp((string) $left['session_id'], (string) $right['session_id'])
        );

        return array_values($candidates);
    }

    /**
     * @param array<string, mixed> $sessionContext
     * @return array<string, mixed>|null Returns null when the context cannot be used as a candidate.
     */
    protected function normalizeSessionContext(array $sessionContext): ?array
    {
        $sessionId = $sessionContext['session_id'] ?? null;
        if ($sessionId === null || $sessionId === '') {
            return null;
        }

        $windowStart = $this->parseUtc(
            $sessionContext['window_start_utc']
                ?? $sessionContext['session_window_start_utc']
                ?? $sessionContext['start_at_utc']
                ?? null
        );
        $windowEnd = $this->parseUtc(
            $sessionContext['window_end_utc']
                ?? $sessionContext['session_window_end_utc']
                ?? $sessionContext['end_at_utc']
                ?? null
        );

        if (! $windowStart instanceof Carbon || ! $windowEnd instanceof Carbon) {
            return null;
        }

        if ($windowEnd->lessThan($windowStart)) {
            return null;
        }

        $setupBufferMinutes = $this->asNonNegativeInt($sessionContext['setup_buffer_minutes'] ?? 0);
        $teardownBufferMinutes = $this->asNonNegativeInt($sessionContext['teardown_buffer_minutes'] ?? 0);

        // Travel buffer is split into before/after to match BOOKING-DATA-MODEL.md.
        // Canonical keys: travel_buffer_before_minutes, travel_buffer_after_minutes.
        // The legacy key travel_buffer_minutes is a temporary fallback for callers not yet
        // updated to the split fields. Remove the fallback once all upstream callers pass
        // the canonical keys. Do not add new callers using the legacy key.
        $travelBufferBeforeMinutes = $this->asNonNegativeInt(
            $sessionContext['travel_buffer_before_minutes']
                ?? $sessionContext['travel_buffer_minutes']
                ?? 0
        );
        $travelBufferAfterMinutes = $this->asNonNegativeInt(
            $sessionContext['travel_buffer_after_minutes']
                ?? $sessionContext['travel_buffer_minutes']
                ?? 0
        );

        [$locationLat, $locationLng] = $this->resolveLocationCoordinates($sessionContext);

        return [
            'session_id' => $sessionId,
            'session_type' => $this->asNullableString($sessionContext['session_type'] ?? null),
            'job_type' => $this->asNullableString($sessionContext['job_type'] ?? null),
            'title' => $this->asNullableString($sessionContext['title'] ?? null),
            'status' => strtolower((string) ($sessionContext['status'] ?? 'confirmed')),
            'calendar_context_state' => $this->normalizeCalendarContextState($sessionContext['calendar_context_state'] ?? null),
            'window_start_utc' => $windowStart->copy()->utc()->toISOString(),
            'window_end_utc' => $windowEnd->copy()->utc()->toISOString(),
            'setup_buffer_minutes' => $setupBufferMinutes,
            'travel_buffer_before_minutes' => $travelBufferBeforeMinutes,
            'travel_buffer_after_minutes' => $travelBufferAfterMinutes,
            'teardown_buffer_minutes' => $teardownBufferMinutes,
            'location_lat' => $locationLat,
            'location_lng' => $locationLng,
            'location_label' => $this->asNullableString(
                $sessionContext['location_label']
                    ?? $sessionContext['location_name']
                    ?? $sessionContext['location_address']
                    ?? null
            ),
        ];
    }

    /**
     * Returns minutes the capture time falls outside the effective session window.
     * Returns 0 if capture time is within the window.
     *
     * Effective window:
     *   start: window_start - setup_buffer - travel_buffer_before
     *   end:   window_end   + teardown_buffer + travel_buffer_after
     *
     * @param array<string, mixed> $candidate Already-normalized candidate.
     */
    protected function minutesOutsideEffectiveWindow(Carbon $captureAt, array $candidate): int
    {
        $windowStart = $this->parseUtc($candidate['window_start_utc'] ?? null);
        $windowEnd = $this->parseUtc($candidate['window_end_utc'] ?? null);
        if (! $windowStart instanceof Carbon || ! $windowEnd instanceof Carbon) {
            return PHP_INT_MAX;
        }

        $effectiveStart = $windowStart->copy()->subMinutes(
            $this->asNonNegativeInt($candidate['setup_buffer_minutes'] ?? 0)
            + $this->asNonNegativeInt($candidate['travel_buffer_before_minutes'] ?? 0)
        );
        $effectiveEnd = $windowEnd->copy()->addMinutes(
            $this->asNonNegativeInt($candidate['teardown_buffer_minutes'] ?? 0)
            + $this->asNonNegativeInt($candidate['travel_buffer_after_minutes'] ?? 0)
        );

        if ($captureAt->betweenIncluded($effectiveStart, $effectiveEnd)) {
            return 0;
        }

        if ($captureAt->lessThan($effectiveStart)) {
            return $captureAt->diffInMinutes($effectiveStart, true);
        }

        return $captureAt->diffInMinutes($effectiveEnd, true);
    }

    /**
     * Returns true for statuses that should never reach scoring.
     */
    protected function isTerminalStatus(string $status): bool
    {
        return in_array($status, self::EXCLUDED_STATUSES, true);
    }

    /**
     * @param mixed $value
     */
    protected function parseUtc(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $sessionContext
     * @return array{0: float|null, 1: float|null}
     */
    protected function resolveLocationCoordinates(array $sessionContext): array
    {
        $location = $sessionContext['location'] ?? null;
        if (is_array($location)) {
            return [
                $this->asNullableFloat($location['lat'] ?? $location['latitude'] ?? null),
                $this->asNullableFloat($location['lng'] ?? $location['lon'] ?? $location['longitude'] ?? null),
            ];
        }

        return [
            $this->asNullableFloat($sessionContext['location_lat'] ?? $sessionContext['location_latitude'] ?? null),
            $this->asNullableFloat($sessionContext['location_lng'] ?? $sessionContext['location_longitude'] ?? null),
        ];
    }

    /**
     * @param mixed $value
     */
    protected function asNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @param mixed $value
     */
    protected function asNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param mixed $value
     */
    protected function asNonNegativeInt(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) $value);
    }

    /**
     * @param mixed $value
     */
    protected function normalizeCalendarContextState(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $state = strtolower(trim($value));
        $allowed = ['normal', 'stale', 'conflict', 'sync_error'];

        return in_array($state, $allowed, true) ? $state : null;
    }
}
