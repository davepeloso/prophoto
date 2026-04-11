<?php

namespace ProPhoto\Ingest\Services\Calendar;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;

/**
 * CalendarMatcherService
 *
 * Accepts extracted image metadata (EXIF array from frontend) and
 * compares it against the user's calendar events to produce ranked
 * match suggestions with confidence scores and evidence breakdowns.
 *
 * This service fetches calendar events directly from the Google
 * Calendar API (using the user's OAuth token) and applies the same
 * weighted scoring algorithm used by the existing SessionMatchScoringService,
 * adapted for batch-level matching (many images → one event).
 *
 * Story 1a.3 — Sprint 2
 */
class CalendarMatcherService
{
    // Scoring weights — must sum to 1.0
    protected const WEIGHT_TIME      = 0.55;
    protected const WEIGHT_LOCATION  = 0.20;
    protected const WEIGHT_COHERENCE = 0.15;
    protected const WEIGHT_TYPE      = 0.10;

    // Confidence thresholds (matches existing SessionMatchConfidenceTier logic)
    protected const THRESHOLD_HIGH   = 0.85;
    protected const THRESHOLD_MEDIUM = 0.55;

    // Calendar API
    protected const CALENDAR_API_BASE = 'https://www.googleapis.com/calendar/v3';

    // How many hours before/after the image timestamp window to search
    protected const SEARCH_WINDOW_HOURS = 2;

    // Cache TTL for calendar event results (avoid hammering the API)
    protected const CACHE_TTL_MINUTES = 5;

    public function __construct(
        protected CalendarTokenService $tokenService
    ) {}

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Match a batch of image metadata against a user's calendar events.
     *
     * @param  list<array{filename: string, fileSize: int, fileType: string, exif: array}>  $metadata
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return array{
     *   matches: list<array>,
     *   no_match: bool,
     *   timestamp_range: array{earliest: string|null, latest: string|null},
     *   images_analyzed: int,
     * }
     */
    public function matchImages(array $metadata, mixed $user): array
    {
        $timestampRange = $this->extractTimestampRange($metadata);

        // If no timestamps extractable, skip calendar query
        if ($timestampRange['earliest'] === null) {
            Log::info('CalendarMatcher: no timestamps found in metadata, skipping calendar query', [
                'user_id'         => $user->id,
                'images_analyzed' => count($metadata),
            ]);

            return [
                'matches'          => [],
                'no_match'         => true,
                'timestamp_range'  => $timestampRange,
                'images_analyzed'  => count($metadata),
            ];
        }

        // Fetch calendar events (cached to avoid API hammering)
        $events = $this->fetchCalendarEvents($user, $timestampRange);

        if (empty($events)) {
            return [
                'matches'          => [],
                'no_match'         => true,
                'timestamp_range'  => $timestampRange,
                'images_analyzed'  => count($metadata),
            ];
        }

        // Score each event against the batch
        $scored = [];
        foreach ($events as $event) {
            $score = $this->scoreEvent($metadata, $event, $timestampRange);

            // Only surface matches above LOW threshold (avoid noise)
            if ($score['confidence'] >= self::THRESHOLD_MEDIUM) {
                $scored[] = $score;
            }
        }

        // Sort by confidence descending
        usort($scored, fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);

        return [
            'matches'          => array_values($scored),
            'no_match'         => empty($scored),
            'timestamp_range'  => $timestampRange,
            'images_analyzed'  => count($metadata),
        ];
    }

    // ─── Calendar API ──────────────────────────────────────────────────────────

    /**
     * Fetch calendar events for a user within the image timestamp search window.
     * Results cached for CACHE_TTL_MINUTES to reduce API calls.
     *
     * @return list<array>
     */
    protected function fetchCalendarEvents(mixed $user, array $timestampRange): array
    {
        $earliest = Carbon::parse($timestampRange['earliest'])
            ->subHours(self::SEARCH_WINDOW_HOURS);
        $latest = Carbon::parse($timestampRange['latest'])
            ->addHours(self::SEARCH_WINDOW_HOURS);

        $cacheKey = "calendar_events_{$user->id}_{$earliest->timestamp}_{$latest->timestamp}";

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($user, $earliest, $latest): array {
            try {
                $accessToken = $this->tokenService->getValidAccessToken($user);

                $response = Http::withToken($accessToken)
                    ->timeout(10)
                    ->get(self::CALENDAR_API_BASE . '/calendars/primary/events', [
                        'timeMin'      => $earliest->toRfc3339String(),
                        'timeMax'      => $latest->toRfc3339String(),
                        'singleEvents' => 'true',
                        'orderBy'      => 'startTime',
                        'maxResults'   => 20,
                        'fields'       => 'items(id,summary,start,end,location,description)',
                    ]);

                if (! $response->successful()) {
                    Log::warning('CalendarMatcher: Google Calendar API returned error', [
                        'user_id' => $user->id,
                        'status'  => $response->status(),
                    ]);
                    return [];
                }

                return $response->json('items', []);
            } catch (\Throwable $e) {
                Log::warning('CalendarMatcher: Failed to fetch calendar events', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    // ─── Scoring ──────────────────────────────────────────────────────────────

    /**
     * Score a single calendar event against the image batch.
     *
     * @param  list<array>  $metadata
     * @param  array  $event          Google Calendar event object
     * @param  array  $timestampRange
     * @return array{
     *   event_id: string,
     *   title: string,
     *   date: string,
     *   start_time: string,
     *   end_time: string,
     *   location: string|null,
     *   confidence: float,
     *   confidence_tier: string,
     *   image_count: int,
     *   evidence: array,
     * }
     */
    protected function scoreEvent(array $metadata, array $event, array $timestampRange): array
    {
        $eventStart = $this->parseEventDateTime($event['start'] ?? []);
        $eventEnd   = $this->parseEventDateTime($event['end'] ?? []);

        $timeScore      = $this->scoreTimeProximity($timestampRange, $eventStart, $eventEnd);
        $locationScore  = $this->scoreLocation($metadata, $event);
        $coherenceScore = $this->scoreBatchCoherence($metadata);
        $typeScore      = 0.5; // Neutral until we have session type hints

        $confidence = (
            ($timeScore      * self::WEIGHT_TIME) +
            ($locationScore  * self::WEIGHT_LOCATION) +
            ($coherenceScore * self::WEIGHT_COHERENCE) +
            ($typeScore      * self::WEIGHT_TYPE)
        );

        $confidence = round(min(1.0, max(0.0, $confidence)), 4);

        $imageCount = $this->countImagesWithinWindow($metadata, $eventStart, $eventEnd);

        return [
            'event_id'         => $event['id'] ?? '',
            'title'            => $event['summary'] ?? 'Untitled Event',
            'date'             => $eventStart?->toDateString() ?? '',
            'start_time'       => $eventStart?->toISOString() ?? '',
            'end_time'         => $eventEnd?->toISOString() ?? '',
            'location'         => $event['location'] ?? null,
            'confidence'       => $confidence,
            'confidence_tier'  => $this->confidenceTier($confidence)->value,
            'image_count'      => $imageCount,
            'evidence'         => [
                'time_proximity_score'     => round($timeScore, 4),
                'location_proximity_score' => round($locationScore, 4),
                'batch_coherence_score'    => round($coherenceScore, 4),
            ],
        ];
    }

    /**
     * Score how well the image timestamps overlap with the event window.
     * Returns 0.0 – 1.0 where 1.0 = all images are within the event window.
     */
    protected function scoreTimeProximity(
        array $timestampRange,
        ?Carbon $eventStart,
        ?Carbon $eventEnd
    ): float {
        if ($eventStart === null || $eventEnd === null) {
            return 0.40; // No event time — neutral/degraded
        }

        $earliest = Carbon::parse($timestampRange['earliest']);
        $latest   = Carbon::parse($timestampRange['latest']);

        // Expand event window by SEARCH_WINDOW_HOURS buffer
        $windowStart = $eventStart->copy()->subHours(self::SEARCH_WINDOW_HOURS / 4);
        $windowEnd   = $eventEnd->copy()->addHours(self::SEARCH_WINDOW_HOURS / 4);

        // Full overlap: image range is entirely within event window
        if ($earliest->greaterThanOrEqualTo($windowStart) && $latest->lessThanOrEqualTo($windowEnd)) {
            return 1.0;
        }

        // Partial overlap: calculate what percentage of time range overlaps
        $overlapStart  = $earliest->max($windowStart);
        $overlapEnd    = $latest->min($windowEnd);
        $overlapSeconds = max(0, $overlapStart->diffInSeconds($overlapEnd, false));
        $imageSpanSeconds = max(1, $earliest->diffInSeconds($latest));

        $ratio = min(1.0, $overlapSeconds / $imageSpanSeconds);

        // At minimum, score based on proximity even with no overlap
        if ($ratio === 0.0) {
            $minutesFromWindow = min(
                abs($earliest->diffInMinutes($windowEnd)),
                abs($latest->diffInMinutes($windowStart))
            );

            // Decay from 0.5 down to 0.0 over 360 minutes
            $decayRatio = min(1.0, $minutesFromWindow / 360);
            return max(0.0, 0.50 - (0.50 * $decayRatio));
        }

        // Scale partial overlap: 0.60 – 0.99
        return round(0.60 + ($ratio * 0.39), 4);
    }

    /**
     * Score location match between image GPS data and event location.
     * Returns 0.0 – 1.0 where 1.0 = images captured at event location.
     */
    protected function scoreLocation(array $metadata, array $event): float
    {
        if (empty($event['location'])) {
            return 0.50; // No event location — neutral
        }

        // Extract GPS from images that have it
        $gpsCoords = array_filter(
            array_map(fn (array $img) => $img['exif']['gps'] ?? null, $metadata)
        );

        if (empty($gpsCoords)) {
            return 0.50; // No GPS in images — neutral
        }

        // Attempt geocoding the event location string to get lat/lng
        $eventCoords = $this->geocodeEventLocation($event['location']);
        if ($eventCoords === null) {
            return 0.50; // Can't resolve event location
        }

        // Calculate median GPS from image batch
        $lats = array_map(fn (array $c) => $c['lat'], $gpsCoords);
        $lngs = array_map(fn (array $c) => $c['lng'], $gpsCoords);
        $medianLat = $this->median($lats);
        $medianLng = $this->median($lngs);

        $distanceMeters = $this->haversineMeters(
            $medianLat, $medianLng,
            $eventCoords['lat'], $eventCoords['lng']
        );

        // Same scoring buckets as SessionMatchScoringService
        return match (true) {
            $distanceMeters <= 100   => 1.00,
            $distanceMeters <= 500   => 0.85,
            $distanceMeters <= 1500  => 0.70,
            $distanceMeters <= 5000  => 0.45,
            $distanceMeters <= 20000 => 0.20,
            default                  => 0.05,
        };
    }

    /**
     * Score how tightly clustered the image timestamps are.
     * A tight cluster suggests a single coherent shoot session.
     * Returns 0.0 – 1.0 where 1.0 = all images within 1 hour.
     */
    protected function scoreBatchCoherence(array $metadata): float
    {
        $timestamps = array_filter(
            array_map(fn (array $img) => $img['exif']['timestamp'] ?? null, $metadata)
        );

        if (count($timestamps) < 2) {
            return 0.70; // Single image or no timestamps — moderate coherence
        }

        $times = array_map('strtotime', $timestamps);
        $spanSeconds = max($times) - min($times);

        return match (true) {
            $spanSeconds <= 3600   => 0.95, // < 1 hour: tight cluster
            $spanSeconds <= 7200   => 0.80, // 1-2 hours: coherent shoot
            $spanSeconds <= 14400  => 0.60, // 2-4 hours: moderate
            $spanSeconds <= 28800  => 0.40, // 4-8 hours: spread across day
            default                => 0.20, // > 8 hours: likely multiple sessions
        };
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Extract earliest and latest timestamps from image metadata.
     *
     * @param  list<array>  $metadata
     * @return array{earliest: string|null, latest: string|null}
     */
    protected function extractTimestampRange(array $metadata): array
    {
        $timestamps = array_filter(
            array_map(fn (array $img) => $img['exif']['timestamp'] ?? null, $metadata),
            fn (mixed $ts) => is_string($ts) && $ts !== ''
        );

        if (empty($timestamps)) {
            return ['earliest' => null, 'latest' => null];
        }

        $sorted = array_values($timestamps);
        sort($sorted);

        return [
            'earliest' => $sorted[0],
            'latest'   => $sorted[count($sorted) - 1],
        ];
    }

    /**
     * Count how many images have timestamps within the event window (± buffer).
     */
    protected function countImagesWithinWindow(
        array $metadata,
        ?Carbon $eventStart,
        ?Carbon $eventEnd
    ): int {
        if ($eventStart === null || $eventEnd === null) {
            return count($metadata);
        }

        $windowStart = $eventStart->copy()->subMinutes(30);
        $windowEnd   = $eventEnd->copy()->addMinutes(30);

        return count(array_filter(
            $metadata,
            function (array $img) use ($windowStart, $windowEnd): bool {
                $ts = $img['exif']['timestamp'] ?? null;
                if (! is_string($ts) || $ts === '') {
                    return false;
                }
                try {
                    $captureAt = Carbon::parse($ts);
                    return $captureAt->betweenIncluded($windowStart, $windowEnd);
                } catch (\Throwable) {
                    return false;
                }
            }
        ));
    }

    /**
     * Parse a Google Calendar event start/end datetime object.
     * Events can be all-day (date only) or timed (dateTime).
     */
    protected function parseEventDateTime(array $dateTimeObj): ?Carbon
    {
        if (! empty($dateTimeObj['dateTime'])) {
            try {
                return Carbon::parse($dateTimeObj['dateTime'])->utc();
            } catch (\Throwable) {
                return null;
            }
        }

        if (! empty($dateTimeObj['date'])) {
            try {
                return Carbon::parse($dateTimeObj['date'])->startOfDay()->utc();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Attempt to geocode a location string using a lightweight lookup.
     * In production this would use a geocoding API (Google Maps, Nominatim).
     * For Phase 1 MVP this is a stub that returns null (neutral location score).
     *
     * @return array{lat: float, lng: float}|null
     */
    protected function geocodeEventLocation(string $location): ?array
    {
        // Phase 1 MVP: No geocoding implemented yet.
        // Location matching will return neutral score (0.50) for all events.
        // Phase 1b will integrate geocoding API.
        return null;
    }

    /**
     * Map confidence score to tier.
     */
    protected function confidenceTier(float $confidence): SessionMatchConfidenceTier
    {
        if ($confidence >= self::THRESHOLD_HIGH) {
            return SessionMatchConfidenceTier::HIGH;
        }

        if ($confidence >= self::THRESHOLD_MEDIUM) {
            return SessionMatchConfidenceTier::MEDIUM;
        }

        return SessionMatchConfidenceTier::LOW;
    }

    /**
     * Haversine formula — distance between two GPS coordinates in meters.
     */
    protected function haversineMeters(
        float $latA,
        float $lngA,
        float $latB,
        float $lngB
    ): float {
        $R = 6371000.0;
        $dLat = deg2rad($latB - $latA);
        $dLng = deg2rad($lngB - $lngA);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Calculate the median of a numeric array.
     *
     * @param  list<float>  $values
     */
    protected function median(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $mid = (int) floor(count($values) / 2);

        if (count($values) % 2 === 0) {
            return ($values[$mid - 1] + $values[$mid]) / 2;
        }

        return $values[$mid];
    }
}
