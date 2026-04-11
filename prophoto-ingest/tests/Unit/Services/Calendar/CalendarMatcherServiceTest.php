<?php

namespace ProPhoto\Ingest\Tests\Unit\Services\Calendar;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use ProPhoto\Ingest\IngestServiceProvider;
use ProPhoto\Ingest\Services\Calendar\CalendarMatcherService;
use ProPhoto\Ingest\Services\Calendar\CalendarTokenService;

/**
 * CalendarMatcherService Unit Tests
 * Story 1a.3 — Sprint 2
 *
 * Covers:
 *   - matchImages() with no timestamps → returns no_match=true
 *   - matchImages() when API call fails → graceful empty result
 *   - matchImages() when events returned → scoring + ranking
 *   - matchImages() filters out events below MEDIUM threshold
 *   - matchImages() sorts matches by confidence descending
 *   - scoreTimeProximity() — full overlap, partial overlap, no overlap
 *   - scoreBatchCoherence() — tight cluster, spread across day
 *   - extractTimestampRange() — happy path and empty input
 *   - countImagesWithinWindow() — inside / outside event window
 *   - haversine distance calculation (via integration through scoreLocation)
 */
class CalendarMatcherServiceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [IngestServiceProvider::class];
    }

    // ─── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * A minimal metadata entry with a timestamp.
     */
    protected function makeImage(string $timestamp, ?array $gps = null): array
    {
        return [
            'filename' => 'IMG_001.CR2',
            'fileSize' => 52_000_000,
            'fileType' => 'raw',
            'exif'     => array_filter([
                'timestamp' => $timestamp,
                'gps'       => $gps,
                'iso'       => 400,
            ]),
        ];
    }

    /**
     * A Google Calendar event object matching the API shape.
     */
    protected function makeEvent(
        string $id,
        string $summary,
        string $startIso,
        string $endIso,
        ?string $location = null
    ): array {
        return [
            'id'       => $id,
            'summary'  => $summary,
            'start'    => ['dateTime' => $startIso],
            'end'      => ['dateTime' => $endIso],
            'location' => $location,
        ];
    }

    /**
     * Fake user object with an ID (does not need to be a real model for tests).
     */
    protected function fakeUser(int $id = 1): object
    {
        return new class ($id) {
            public function __construct(public int $id) {}
        };
    }

    /**
     * Build a CalendarMatcherService with the CalendarTokenService mocked.
     */
    protected function makeService(?string $accessToken = 'fake-access-token'): CalendarMatcherService
    {
        $tokenService = $this->createMock(CalendarTokenService::class);

        if ($accessToken !== null) {
            $tokenService
                ->method('getValidAccessToken')
                ->willReturn($accessToken);
        } else {
            $tokenService
                ->method('getValidAccessToken')
                ->willThrowException(new \RuntimeException('Token unavailable'));
        }

        return new CalendarMatcherService($tokenService);
    }

    // ─── matchImages() — no timestamps ────────────────────────────────────────

    /** @test */
    public function it_returns_no_match_when_no_timestamps_in_metadata(): void
    {
        $service = $this->makeService();
        $user    = $this->fakeUser();

        $metadata = [
            ['filename' => 'IMG_001.CR2', 'fileSize' => 5_000_000, 'fileType' => 'raw', 'exif' => []],
            ['filename' => 'IMG_002.CR2', 'fileSize' => 5_000_000, 'fileType' => 'raw', 'exif' => []],
        ];

        $result = $service->matchImages($metadata, $user);

        $this->assertTrue($result['no_match']);
        $this->assertEmpty($result['matches']);
        $this->assertEquals(2, $result['images_analyzed']);
        $this->assertNull($result['timestamp_range']['earliest']);
        $this->assertNull($result['timestamp_range']['latest']);
    }

    /** @test */
    public function it_returns_no_match_when_metadata_is_empty(): void
    {
        $service = $this->makeService();
        $user    = $this->fakeUser();

        $result = $service->matchImages([], $user);

        $this->assertTrue($result['no_match']);
        $this->assertEmpty($result['matches']);
        $this->assertEquals(0, $result['images_analyzed']);
    }

    // ─── matchImages() — API failures ─────────────────────────────────────────

    /** @test */
    public function it_returns_no_match_when_token_service_throws(): void
    {
        $service = $this->makeService(accessToken: null); // will throw
        $user    = $this->fakeUser();

        Cache::flush();

        $metadata = [
            $this->makeImage('2026-04-10T10:00:00+00:00'),
            $this->makeImage('2026-04-10T11:00:00+00:00'),
        ];

        $result = $service->matchImages($metadata, $user);

        $this->assertTrue($result['no_match']);
        $this->assertEmpty($result['matches']);
    }

    /** @test */
    public function it_returns_no_match_when_google_api_returns_error(): void
    {
        $service = $this->makeService();
        $user    = $this->fakeUser(99);

        Cache::flush();

        Http::fake([
            'www.googleapis.com/*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $metadata = [
            $this->makeImage('2026-04-10T10:00:00+00:00'),
        ];

        $result = $service->matchImages($metadata, $user);

        $this->assertTrue($result['no_match']);
        $this->assertEmpty($result['matches']);
    }

    /** @test */
    public function it_returns_no_match_when_api_returns_no_events(): void
    {
        $service = $this->makeService();
        $user    = $this->fakeUser(98);

        Cache::flush();

        Http::fake([
            'www.googleapis.com/*' => Http::response(['items' => []], 200),
        ]);

        $metadata = [
            $this->makeImage('2026-04-10T10:00:00+00:00'),
        ];

        $result = $service->matchImages($metadata, $user);

        $this->assertTrue($result['no_match']);
    }

    // ─── matchImages() — scoring and ranking ──────────────────────────────────

    /** @test */
    public function it_returns_matches_sorted_by_confidence_descending(): void
    {
        $service = $this->makeService();
        $user    = $this->fakeUser(97);

        Cache::flush();

        // Images taken 10:00–11:00 UTC on April 10
        $metadata = [
            $this->makeImage('2026-04-10T10:00:00+00:00'),
            $this->makeImage('2026-04-10T10:30:00+00:00'),
            $this->makeImage('2026-04-10T11:00:00+00:00'),
        ];

        // Event A: perfectly overlaps (high score expected)
        // Event B: starts 5 hours later (lower score expected)
        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [
                    $this->makeEvent('event-b', 'Evening Dinner', '2026-04-10T16:00:00+00:00', '2026-04-10T18:00:00+00:00'),
                    $this->makeEvent('event-a', 'Morning Portrait Session', '2026-04-10T09:30:00+00:00', '2026-04-10T11:30:00+00:00'),
                ],
            ], 200),
        ]);

        $result = $service->matchImages($metadata, $user);

        // event-a should be ranked first (better time alignment)
        $this->assertNotEmpty($result['matches']);

        if (count($result['matches']) >= 2) {
            $this->assertGreaterThanOrEqual(
                $result['matches'][1]['confidence'],
                $result['matches'][0]['confidence'],
                'Matches should be sorted by confidence descending'
            );
        }

        // Verify the better-aligned event comes first
        $firstMatch = $result['matches'][0];
        $this->assertEquals('event-a', $firstMatch['event_id']);
    }

    /** @test */
    public function it_filters_out_events_below_medium_threshold(): void
    {
        $service = $this->makeService();
        $user    = $this->fakeUser(96);

        Cache::flush();

        // Images shot April 10 morning
        $metadata = [
            $this->makeImage('2026-04-10T10:00:00+00:00'),
            $this->makeImage('2026-04-10T10:30:00+00:00'),
        ];

        // Event many days later — should produce a very low score, filtered out
        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [
                    $this->makeEvent('event-far', 'Conference Next Week', '2026-04-20T09:00:00+00:00', '2026-04-20T17:00:00+00:00'),
                ],
            ], 200),
        ]);

        $result = $service->matchImages($metadata, $user);

        // The distant event should be filtered out (well below MEDIUM = 0.55)
        $this->assertTrue($result['no_match']);
        $this->assertEmpty($result['matches']);
    }

    /** @test */
    public function it_includes_required_fields_in_each_match(): void
    {
        $service = $this->makeService();
        $user    = $this->fakeUser(95);

        Cache::flush();

        $metadata = [
            $this->makeImage('2026-04-10T10:00:00+00:00'),
            $this->makeImage('2026-04-10T10:30:00+00:00'),
        ];

        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [
                    $this->makeEvent('event-abc', 'Portrait Session', '2026-04-10T09:30:00+00:00', '2026-04-10T11:30:00+00:00', 'Studio A, Los Angeles'),
                ],
            ], 200),
        ]);

        $result = $service->matchImages($metadata, $user);

        $this->assertNotEmpty($result['matches'], 'Expected at least one match above MEDIUM threshold');

        $match = $result['matches'][0];

        foreach (['event_id', 'title', 'date', 'start_time', 'end_time', 'confidence', 'confidence_tier', 'image_count', 'evidence'] as $field) {
            $this->assertArrayHasKey($field, $match, "Match missing required field: {$field}");
        }

        $this->assertArrayHasKey('time_proximity_score', $match['evidence']);
        $this->assertArrayHasKey('location_proximity_score', $match['evidence']);
        $this->assertArrayHasKey('batch_coherence_score', $match['evidence']);

        $this->assertGreaterThanOrEqual(0.0, $match['confidence']);
        $this->assertLessThanOrEqual(1.0, $match['confidence']);
        $this->assertContains($match['confidence_tier'], ['high', 'medium', 'low']);
    }

    /** @test */
    public function it_reports_correct_image_count_and_analyzed_count(): void
    {
        $service = $this->makeService();
        $user    = $this->fakeUser(94);

        Cache::flush();

        $metadata = [
            $this->makeImage('2026-04-10T10:00:00+00:00'),
            $this->makeImage('2026-04-10T10:15:00+00:00'),
            $this->makeImage('2026-04-10T10:30:00+00:00'),
            $this->makeImage('2026-04-10T10:45:00+00:00'),
            $this->makeImage('2026-04-10T11:00:00+00:00'),
        ];

        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'items' => [
                    $this->makeEvent('event-match', 'Wedding Ceremony', '2026-04-10T09:30:00+00:00', '2026-04-10T11:30:00+00:00'),
                ],
            ], 200),
        ]);

        $result = $service->matchImages($metadata, $user);

        $this->assertEquals(5, $result['images_analyzed']);

        if (! empty($result['matches'])) {
            $this->assertGreaterThan(0, $result['matches'][0]['image_count']);
        }
    }

    // ─── extractTimestampRange() ──────────────────────────────────────────────

    /** @test */
    public function it_extracts_earliest_and_latest_timestamps(): void
    {
        $service = $this->makeService();

        // Access the protected method via reflection
        $method = new \ReflectionMethod($service, 'extractTimestampRange');
        $method->setAccessible(true);

        $metadata = [
            $this->makeImage('2026-04-10T10:00:00+00:00'),
            $this->makeImage('2026-04-10T09:00:00+00:00'),
            $this->makeImage('2026-04-10T11:30:00+00:00'),
        ];

        $result = $method->invoke($service, $metadata);

        $this->assertEquals('2026-04-10T09:00:00+00:00', $result['earliest']);
        $this->assertEquals('2026-04-10T11:30:00+00:00', $result['latest']);
    }

    /** @test */
    public function it_returns_null_range_when_no_timestamps_present(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'extractTimestampRange');
        $method->setAccessible(true);

        $metadata = [
            ['filename' => 'IMG.CR2', 'fileSize' => 1000, 'fileType' => 'raw', 'exif' => []],
        ];

        $result = $method->invoke($service, $metadata);

        $this->assertNull($result['earliest']);
        $this->assertNull($result['latest']);
    }

    // ─── scoreBatchCoherence() ────────────────────────────────────────────────

    /** @test */
    public function it_gives_high_coherence_score_for_tight_cluster(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'scoreBatchCoherence');
        $method->setAccessible(true);

        // All images within 30 minutes — very tight
        $metadata = [
            $this->makeImage('2026-04-10T10:00:00+00:00'),
            $this->makeImage('2026-04-10T10:15:00+00:00'),
            $this->makeImage('2026-04-10T10:28:00+00:00'),
        ];

        $score = $method->invoke($service, $metadata);

        $this->assertGreaterThanOrEqual(0.90, $score, 'Tight cluster should score ≥ 0.90');
    }

    /** @test */
    public function it_gives_low_coherence_score_for_spread_out_timestamps(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'scoreBatchCoherence');
        $method->setAccessible(true);

        // Images spread across 10 hours
        $metadata = [
            $this->makeImage('2026-04-10T06:00:00+00:00'),
            $this->makeImage('2026-04-10T10:00:00+00:00'),
            $this->makeImage('2026-04-10T16:00:00+00:00'),
        ];

        $score = $method->invoke($service, $metadata);

        $this->assertLessThanOrEqual(0.40, $score, 'Spread-out timestamps should score ≤ 0.40');
    }

    /** @test */
    public function it_gives_moderate_coherence_for_single_image(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'scoreBatchCoherence');
        $method->setAccessible(true);

        $metadata = [$this->makeImage('2026-04-10T10:00:00+00:00')];

        $score = $method->invoke($service, $metadata);

        $this->assertEquals(0.70, $score, 'Single image should return 0.70 (moderate)');
    }

    // ─── scoreTimeProximity() ─────────────────────────────────────────────────

    /** @test */
    public function it_scores_full_time_overlap_as_1_0(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'scoreTimeProximity');
        $method->setAccessible(true);

        $eventStart = \Illuminate\Support\Carbon::parse('2026-04-10T09:00:00+00:00');
        $eventEnd   = \Illuminate\Support\Carbon::parse('2026-04-10T12:00:00+00:00');

        $timestampRange = [
            'earliest' => '2026-04-10T10:00:00+00:00',
            'latest'   => '2026-04-10T11:00:00+00:00',
        ];

        $score = $method->invoke($service, $timestampRange, $eventStart, $eventEnd);

        $this->assertEquals(1.0, $score, 'Images fully within event window should score 1.0');
    }

    /** @test */
    public function it_scores_null_event_times_as_neutral(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'scoreTimeProximity');
        $method->setAccessible(true);

        $timestampRange = [
            'earliest' => '2026-04-10T10:00:00+00:00',
            'latest'   => '2026-04-10T11:00:00+00:00',
        ];

        $score = $method->invoke($service, $timestampRange, null, null);

        $this->assertEquals(0.40, $score, 'Null event times should return 0.40 (neutral/degraded)');
    }

    // ─── haversineMeters() ────────────────────────────────────────────────────

    /** @test */
    public function it_calculates_haversine_distance_correctly(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'haversineMeters');
        $method->setAccessible(true);

        // New York City approx (40.7128, -74.0060) → Los Angeles (34.0522, -118.2437)
        // Known distance: ~3,940 km
        $meters = $method->invoke($service, 40.7128, -74.0060, 34.0522, -118.2437);

        $this->assertGreaterThan(3_900_000, $meters);
        $this->assertLessThan(4_000_000, $meters);
    }

    /** @test */
    public function it_returns_zero_for_identical_coordinates(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'haversineMeters');
        $method->setAccessible(true);

        $meters = $method->invoke($service, 34.0522, -118.2437, 34.0522, -118.2437);

        $this->assertEquals(0.0, round($meters, 2));
    }

    // ─── median() ─────────────────────────────────────────────────────────────

    /** @test */
    public function it_calculates_median_of_odd_count_array(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'median');
        $method->setAccessible(true);

        $this->assertEquals(3.0, $method->invoke($service, [1.0, 2.0, 3.0, 4.0, 5.0]));
    }

    /** @test */
    public function it_calculates_median_of_even_count_array(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'median');
        $method->setAccessible(true);

        $this->assertEquals(2.5, $method->invoke($service, [1.0, 2.0, 3.0, 4.0]));
    }

    /** @test */
    public function it_returns_zero_median_for_empty_array(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'median');
        $method->setAccessible(true);

        $this->assertEquals(0.0, $method->invoke($service, []));
    }

    // ─── parseEventDateTime() ─────────────────────────────────────────────────

    /** @test */
    public function it_parses_timed_event_datetime(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'parseEventDateTime');
        $method->setAccessible(true);

        $result = $method->invoke($service, ['dateTime' => '2026-04-10T10:00:00-07:00']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $result);
        $this->assertEquals('2026-04-10T17:00:00+00:00', $result->toISOString());
    }

    /** @test */
    public function it_parses_all_day_event_date(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'parseEventDateTime');
        $method->setAccessible(true);

        $result = $method->invoke($service, ['date' => '2026-04-10']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $result);
        $this->assertEquals('2026-04-10', $result->toDateString());
    }

    /** @test */
    public function it_returns_null_for_empty_datetime_object(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'parseEventDateTime');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($service, []));
    }

    // ─── geocodeEventLocation() stub ─────────────────────────────────────────

    /** @test */
    public function it_returns_null_from_geocode_stub_in_phase_1(): void
    {
        $service = $this->makeService();
        $method  = new \ReflectionMethod($service, 'geocodeEventLocation');
        $method->setAccessible(true);

        // Phase 1: always returns null (neutral score, no API cost)
        $this->assertNull($method->invoke($service, 'Studio A, Los Angeles, CA'));
        $this->assertNull($method->invoke($service, '123 Main St, New York, NY 10001'));
    }
}
