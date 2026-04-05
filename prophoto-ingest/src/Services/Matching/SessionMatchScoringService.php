<?php

namespace ProPhoto\Ingest\Services\Matching;

use Illuminate\Support\Carbon;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;

class SessionMatchScoringService
{
    /**
     * @param array<string, mixed> $subjectContext
     * @param list<array<string, mixed>> $candidates
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     */
    public function scoreCandidates(array $subjectContext, array $candidates, array $options = []): array
    {
        $captureAt = $this->parseUtc($subjectContext['capture_at_utc'] ?? null);
        [$subjectLat, $subjectLng] = $this->resolveSubjectCoordinates($subjectContext);

        $highThreshold = (float) ($options['high_threshold'] ?? 0.85);
        $mediumThreshold = (float) ($options['medium_threshold'] ?? 0.55);

        $scored = [];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $timeEvidence = $this->buildTimeEvidence($captureAt, $candidate);
            $locationEvidence = $this->buildLocationEvidence($subjectLat, $subjectLng, $candidate);
            $semanticEvidence = $this->buildSemanticEvidence($subjectContext, $candidate);
            $operationalEvidence = $this->buildOperationalEvidence($candidate);

            $score = $this->calculateWeightedScore(
                timeScore: (float) $timeEvidence['score'],
                locationScore: (float) $locationEvidence['score'],
                semanticScore: (float) $semanticEvidence['score'],
                operationalScore: (float) $operationalEvidence['score']
            );

            $score = $this->applyHardDowngrades($score, $timeEvidence, $candidate);
            $score = $this->clampScore($score);

            $confidenceTier = $this->confidenceTierFromScore($score, $highThreshold, $mediumThreshold);

            $scored[] = [
                'session_id' => $candidate['session_id'],
                'score' => $score,
                'confidence_tier' => $confidenceTier,
                'session_type' => $candidate['session_type'] ?? null,
                'job_type' => $candidate['job_type'] ?? null,
                'calendar_context_state' => $candidate['calendar_context_state'] ?? null,
                'buffer_class' => $timeEvidence['buffer_class'] ?? 'unknown',
                'minutes_from_planned_start' => $timeEvidence['minutes_from_planned_start'] ?? null,
                'distance_meters' => $locationEvidence['distance_meters'] ?? null,
                'evidence_payload' => [
                    'time' => $timeEvidence,
                    'location' => $locationEvidence,
                    'semantic' => $semanticEvidence,
                    'operational' => $operationalEvidence,
                ],
            ];
        }

        return array_values($scored);
    }

    protected function calculateWeightedScore(
        float $timeScore,
        float $locationScore,
        float $semanticScore,
        float $operationalScore
    ): float {
        // v1 deterministic weighting: emphasize temporal fit.
        return ($timeScore * 0.55)
            + ($locationScore * 0.20)
            + ($semanticScore * 0.15)
            + ($operationalScore * 0.10);
    }

    /**
     * @param array<string, mixed> $timeEvidence
     * @param array<string, mixed> $candidate
     */
    protected function applyHardDowngrades(float $score, array $timeEvidence, array $candidate): float
    {
        // Terminal-status cap (cancelled, no_show) is intentionally NOT applied here.
        // The candidate generator is the correct gate for terminal statuses: it excludes
        // them before any candidate reaches this scorer. Capping again here would be
        // redundant and would obscure which layer owns the policy.
        // If the generator is ever bypassed, terminal candidates will score poorly on
        // operational evidence alone (status weight 0.15), which is an acceptable fallback.

        $minutesOutsideEffective = $timeEvidence['minutes_from_effective_window'] ?? null;
        if (is_int($minutesOutsideEffective) && $minutesOutsideEffective > 120) {
            $score = min($score, 0.54);
        }

        $calendarContextState = (string) ($candidate['calendar_context_state'] ?? '');
        if (in_array($calendarContextState, ['conflict', 'sync_error'], true)
            && ($timeEvidence['buffer_class'] ?? null) !== 'core'
        ) {
            $score *= 0.90;
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    protected function buildTimeEvidence(?Carbon $captureAt, array $candidate): array
    {
        $windowStart = $this->parseUtc($candidate['window_start_utc'] ?? null);
        $windowEnd = $this->parseUtc($candidate['window_end_utc'] ?? null);

        if (! $captureAt instanceof Carbon || ! $windowStart instanceof Carbon || ! $windowEnd instanceof Carbon) {
            // Neutral/limited-confidence default: 0.40.
            // Used when capture time or session window is unavailable, so no temporal fit
            // can be measured. Lower than location/semantic missing-signal defaults (0.50)
            // because time is the primary matching signal (weight 0.55). Do not raise this
            // without also reviewing how it interacts with the weighted score calculation.
            return [
                'capture_at_utc' => $captureAt?->toISOString(),
                'window_start_utc' => $windowStart?->toISOString(),
                'window_end_utc' => $windowEnd?->toISOString(),
                'effective_window_start_utc' => null,
                'effective_window_end_utc' => null,
                'buffer_class' => 'unknown',
                'minutes_from_planned_start' => null,
                'minutes_from_core_window' => null,
                'minutes_from_effective_window' => null,
                'score' => 0.40,
            ];
        }

        $setup = $this->asNonNegativeInt($candidate['setup_buffer_minutes'] ?? 0);
        $teardown = $this->asNonNegativeInt($candidate['teardown_buffer_minutes'] ?? 0);

        // Travel buffer is split before/after to match BOOKING-DATA-MODEL.md.
        // Temporary: legacy travel_buffer_minutes is accepted as a fallback for both
        // directions. Remove once all callers pass travel_buffer_before/after_minutes.
        $travelBefore = $this->asNonNegativeInt(
            $candidate['travel_buffer_before_minutes']
                ?? $candidate['travel_buffer_minutes']
                ?? 0
        );
        $travelAfter = $this->asNonNegativeInt(
            $candidate['travel_buffer_after_minutes']
                ?? $candidate['travel_buffer_minutes']
                ?? 0
        );

        $effectiveStart = $windowStart->copy()->subMinutes($setup + $travelBefore);
        $effectiveEnd = $windowEnd->copy()->addMinutes($teardown + $travelAfter);

        $minutesFromStart = $captureAt->diffInMinutes($windowStart, false);
        $absoluteMinutesFromStart = abs($minutesFromStart);

        $bufferClass = 'outside';
        $minutesFromCoreWindow = 0;
        $minutesFromEffectiveWindow = 0;
        $score = 0.0;

        if ($captureAt->betweenIncluded($windowStart, $windowEnd)) {
            $bufferClass = 'core';
            $score = 1.0;
        } elseif ($captureAt->betweenIncluded($effectiveStart, $effectiveEnd)) {
            $bufferClass = 'buffer';
            if ($captureAt->lessThan($windowStart)) {
                $minutesFromCoreWindow = $captureAt->diffInMinutes($windowStart);
                $sideBuffer = max(1, $setup + $travelBefore);
            } else {
                $minutesFromCoreWindow = $captureAt->diffInMinutes($windowEnd);
                $sideBuffer = max(1, $teardown + $travelAfter);
            }

            $distanceRatio = min(1.0, $minutesFromCoreWindow / $sideBuffer);
            $score = 0.85 - (0.25 * $distanceRatio);
            $score = max(0.60, $score);
        } else {
            if ($captureAt->lessThan($effectiveStart)) {
                $minutesFromEffectiveWindow = $captureAt->diffInMinutes($effectiveStart);
            } else {
                $minutesFromEffectiveWindow = $captureAt->diffInMinutes($effectiveEnd);
            }

            $distanceRatio = min(1.0, $minutesFromEffectiveWindow / 360);
            $score = 0.50 - (0.50 * $distanceRatio);
            $score = max(0.0, $score);
        }

        return [
            'capture_at_utc' => $captureAt->toISOString(),
            'window_start_utc' => $windowStart->toISOString(),
            'window_end_utc' => $windowEnd->toISOString(),
            'effective_window_start_utc' => $effectiveStart->toISOString(),
            'effective_window_end_utc' => $effectiveEnd->toISOString(),
            'buffer_class' => $bufferClass,
            'minutes_from_planned_start' => $absoluteMinutesFromStart,
            'minutes_from_core_window' => $minutesFromCoreWindow,
            'minutes_from_effective_window' => $minutesFromEffectiveWindow,
            'score' => round($score, 5),
        ];
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    protected function buildLocationEvidence(?float $subjectLat, ?float $subjectLng, array $candidate): array
    {
        $candidateLat = $this->asNullableFloat($candidate['location_lat'] ?? null);
        $candidateLng = $this->asNullableFloat($candidate['location_lng'] ?? null);

        if ($subjectLat === null || $subjectLng === null || $candidateLat === null || $candidateLng === null) {
            // Neutral/limited-confidence default: 0.50.
            // GPS coordinates absent for subject or session — no location signal available.
            // Neutral rather than penalizing: missing location is common for indoor/studio
            // sessions and should not suppress an otherwise strong temporal match.
            return [
                'distance_meters' => null,
                'distance_bucket' => 'unknown',
                'signal' => 'missing',
                'score' => 0.50,
            ];
        }

        $distanceMeters = $this->haversineMeters($subjectLat, $subjectLng, $candidateLat, $candidateLng);

        // v1 deterministic distance buckets and scores.
        // These thresholds are policy decisions, not measured empirically.
        // They may move to config in a future phase, but must not be changed
        // without updating the corresponding scoring tests.
        //   ≤ 100 m  → same_venue (1.00)
        //   ≤ 500 m  → very_near  (0.85)
        //   ≤ 1500 m → near       (0.70)
        //   ≤ 5000 m → nearby     (0.45)
        //   ≤ 20000 m→ far        (0.20)
        //   > 20000 m→ far        (0.05)
        $bucket = 'far';
        $score = 0.05;

        if ($distanceMeters <= 100) {
            $bucket = 'same_venue';
            $score = 1.00;
        } elseif ($distanceMeters <= 500) {
            $bucket = 'very_near';
            $score = 0.85;
        } elseif ($distanceMeters <= 1500) {
            $bucket = 'near';
            $score = 0.70;
        } elseif ($distanceMeters <= 5000) {
            $bucket = 'nearby';
            $score = 0.45;
        } elseif ($distanceMeters <= 20000) {
            $bucket = 'far';
            $score = 0.20;
        }

        return [
            'distance_meters' => round($distanceMeters, 2),
            'distance_bucket' => $bucket,
            'signal' => 'present',
            'score' => round($score, 5),
        ];
    }

    /**
     * @param array<string, mixed> $subjectContext
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    protected function buildSemanticEvidence(array $subjectContext, array $candidate): array
    {
        $availableSignals = 0;
        $matchedSignals = [];

        $sessionTypeHint = strtolower((string) ($subjectContext['session_type_hint'] ?? ''));
        $jobTypeHint = strtolower((string) ($subjectContext['job_type_hint'] ?? ''));
        $titleHint = strtolower((string) ($subjectContext['title_hint'] ?? ''));

        $candidateSessionType = strtolower((string) ($candidate['session_type'] ?? ''));
        $candidateJobType = strtolower((string) ($candidate['job_type'] ?? ''));
        $candidateTitle = strtolower((string) ($candidate['title'] ?? ''));

        if ($sessionTypeHint !== '') {
            $availableSignals++;
            if ($sessionTypeHint === $candidateSessionType) {
                $matchedSignals[] = 'session_type';
            }
        }

        if ($jobTypeHint !== '') {
            $availableSignals++;
            if ($jobTypeHint === $candidateJobType) {
                $matchedSignals[] = 'job_type';
            }
        }

        $hintTokens = $this->tokenize($titleHint);
        if ($hintTokens !== []) {
            $availableSignals++;
            $candidateTextTokens = array_unique(array_merge(
                $this->tokenize($candidateTitle),
                $this->tokenize((string) ($candidate['location_label'] ?? ''))
            ));
            $tokenOverlap = array_intersect($hintTokens, $candidateTextTokens);
            if (count($tokenOverlap) > 0) {
                $matchedSignals[] = 'title_or_location_hint';
            }
        }

        if ($availableSignals === 0) {
            // Neutral/limited-confidence default: 0.50.
            // No semantic hints present in the subject context — cannot match on
            // session_type, job_type, or title. Neutral rather than penalizing:
            // most automated ingest won't carry semantic hints.
            return [
                'matched_signals' => [],
                'available_signal_count' => 0,
                'score' => 0.50,
            ];
        }

        $score = count($matchedSignals) / $availableSignals;

        return [
            'matched_signals' => array_values($matchedSignals),
            'available_signal_count' => $availableSignals,
            'score' => round($score, 5),
        ];
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    protected function buildOperationalEvidence(array $candidate): array
    {
        $status = strtolower((string) ($candidate['status'] ?? 'confirmed'));
        $calendarContextState = (string) ($candidate['calendar_context_state'] ?? 'normal');

        $statusWeight = match ($status) {
            'in_progress', 'confirmed' => 1.00,
            'scheduled' => 0.90,
            'tentative', 'pending' => 0.75,
            // completed → 0.65: intentional policy for backfilled/delayed ingest.
            // Photos delivered or processed after the session window (late card imports,
            // culled batches, backup restores) should still match the originating session
            // with reasonable confidence. Lower than active statuses but not penalized.
            // Revisit if completed sessions should ever be excluded from matching.
            'completed' => 0.65,
            // Terminal statuses: candidate generator excludes these before scoring.
            // This branch exists as a safe defensive fallback only — it should never
            // be reached in normal operation. See applyHardDowngrades() for policy note.
            'cancelled', 'canceled', 'no_show' => 0.15,
            default => 0.80,
        };

        $calendarModifier = match ($calendarContextState) {
            'normal' => 1.00,
            'stale' => 0.85,
            'conflict' => 0.65,
            'sync_error' => 0.55,
            // Unknown/unrecognized calendar state → 0.75 (stale-ish uncertainty).
            // Not trusted but not assumed broken. More conservative than 'stale' (0.85)
            // because an unrecognized state indicates the calendar integration may not
            // be reporting correctly. Do not raise toward 1.0 without investigation.
            default => 0.75,
        };

        $score = $statusWeight * $calendarModifier;

        return [
            'session_status' => $status,
            'calendar_context_state' => $calendarContextState,
            'score' => round($score, 5),
        ];
    }

    protected function confidenceTierFromScore(
        float $score,
        float $highThreshold,
        float $mediumThreshold
    ): SessionMatchConfidenceTier {
        if ($score >= $highThreshold) {
            return SessionMatchConfidenceTier::HIGH;
        }

        if ($score >= $mediumThreshold) {
            return SessionMatchConfidenceTier::MEDIUM;
        }

        return SessionMatchConfidenceTier::LOW;
    }

    protected function clampScore(float $score): float
    {
        $score = max(0.0, min(1.0, $score));

        return round($score, 5);
    }

    /**
     * @param array<string, mixed> $subjectContext
     * @return array{0: float|null, 1: float|null}
     */
    protected function resolveSubjectCoordinates(array $subjectContext): array
    {
        $gps = $subjectContext['gps'] ?? null;
        if (is_array($gps)) {
            return [
                $this->asNullableFloat($gps['lat'] ?? $gps['latitude'] ?? null),
                $this->asNullableFloat($gps['lng'] ?? $gps['lon'] ?? $gps['longitude'] ?? null),
            ];
        }

        return [
            $this->asNullableFloat($subjectContext['capture_lat'] ?? $subjectContext['gps_lat'] ?? null),
            $this->asNullableFloat($subjectContext['capture_lng'] ?? $subjectContext['gps_lng'] ?? null),
        ];
    }

    protected function haversineMeters(
        float $latitudeA,
        float $longitudeA,
        float $latitudeB,
        float $longitudeB
    ): float {
        $earthRadiusMeters = 6371000.0;

        $latDelta = deg2rad($latitudeB - $latitudeA);
        $lngDelta = deg2rad($longitudeB - $longitudeA);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($latitudeA))
            * cos(deg2rad($latitudeB))
            * sin($lngDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMeters * $c;
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
    protected function asNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return list<string>
     */
    protected function tokenize(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', strtolower($text));
        if (! is_string($normalized) || trim($normalized) === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', trim($normalized));
        if (! is_array($tokens)) {
            return [];
        }

        return array_values(array_filter(
            array_unique($tokens),
            static fn (string $token): bool => strlen($token) >= 3
        ));
    }
}

