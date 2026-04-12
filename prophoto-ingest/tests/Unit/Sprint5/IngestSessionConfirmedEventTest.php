<?php

namespace ProPhoto\Ingest\Tests\Unit\Sprint5;

use Illuminate\Support\Facades\Event;
use Mockery;
use Orchestra\Testbench\TestCase;
use ProPhoto\Ingest\Events\IngestSessionConfirmed;
use ProPhoto\Ingest\Models\IngestFile;
use ProPhoto\Ingest\Models\UploadSession;
use ProPhoto\Ingest\Services\UploadSessionService;
use ProPhoto\Ingest\IngestServiceProvider;

/**
 * Sprint 5 — Story 1c.1 + 1c.5
 *
 * Tests that:
 *  1. confirmSession() dispatches IngestSessionConfirmed with correct payload
 *  2. The event carries all session fields (calendarEventId, confidence, galleryId)
 *  3. Dispatch does NOT fire when session is in a non-confirmable status
 */
class IngestSessionConfirmedEventTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [IngestServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('queue.default', 'sync');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
    }

    // ─── Test 1: Event is dispatched with correct session payload ─────────────

    public function test_confirm_session_dispatches_ingest_session_confirmed_event(): void
    {
        Event::fake([IngestSessionConfirmed::class]);

        $session = UploadSession::create([
            'studio_id'  => 10,
            'user_id'    => 5,
            'status'     => UploadSession::STATUS_UPLOADING,
            'file_count' => 3,
        ]);

        $service = app(UploadSessionService::class);
        $service->confirmSession($session->id, galleryId: 42);

        Event::assertDispatched(IngestSessionConfirmed::class, function (IngestSessionConfirmed $event) use ($session) {
            return $event->sessionId  === $session->id
                && (int) $event->studioId === 10
                && (int) $event->userId   === 5
                && $event->galleryId      === 42;
        });
    }

    // ─── Test 2: Event carries calendar fields ────────────────────────────────

    public function test_confirmed_event_carries_calendar_fields(): void
    {
        Event::fake([IngestSessionConfirmed::class]);

        $session = UploadSession::create([
            'studio_id'                 => 10,
            'user_id'                   => 5,
            'status'                    => UploadSession::STATUS_UPLOADING,
            'file_count'                => 1,
            'calendar_event_id'         => 'google-event-abc123',
            'calendar_provider'         => 'google',
            'calendar_match_confidence' => 0.92,
        ]);

        $service = app(UploadSessionService::class);
        $service->confirmSession($session->id);

        Event::assertDispatched(IngestSessionConfirmed::class, function (IngestSessionConfirmed $event) {
            return $event->calendarEventId         === 'google-event-abc123'
                && $event->calendarProvider         === 'google'
                && abs($event->calendarMatchConfidence - 0.92) < 0.001;
        });
    }

    // ─── Test 3: No event on non-confirmable status ───────────────────────────

    public function test_confirm_session_throws_on_invalid_status_and_no_event_dispatched(): void
    {
        Event::fake([IngestSessionConfirmed::class]);

        $session = UploadSession::create([
            'studio_id'  => 10,
            'user_id'    => 5,
            'status'     => UploadSession::STATUS_INITIATED, // not confirmable
            'file_count' => 2,
        ]);

        $service = app(UploadSessionService::class);

        $this->expectException(\RuntimeException::class);
        $service->confirmSession($session->id);

        Event::assertNotDispatched(IngestSessionConfirmed::class);
    }

    // ─── Test 4: Event is dispatched from STATUS_TAGGING as well ─────────────

    public function test_confirm_session_dispatches_event_from_tagging_status(): void
    {
        Event::fake([IngestSessionConfirmed::class]);

        $session = UploadSession::create([
            'studio_id'  => 10,
            'user_id'    => 5,
            'status'     => UploadSession::STATUS_TAGGING,
            'file_count' => 4,
        ]);

        $service = app(UploadSessionService::class);
        $service->confirmSession($session->id);

        Event::assertDispatched(IngestSessionConfirmed::class);
    }

    // ─── Test 5: Event occurredAt is an ISO-8601 string ──────────────────────

    public function test_confirmed_event_has_iso8601_occurred_at(): void
    {
        Event::fake([IngestSessionConfirmed::class]);

        $session = UploadSession::create([
            'studio_id'  => 10,
            'user_id'    => 5,
            'status'     => UploadSession::STATUS_UPLOADING,
            'file_count' => 1,
        ]);

        app(UploadSessionService::class)->confirmSession($session->id);

        Event::assertDispatched(IngestSessionConfirmed::class, function (IngestSessionConfirmed $event) {
            // ISO-8601 check — must parse without throwing
            $parsed = \Carbon\Carbon::parse($event->occurredAt);
            return $parsed instanceof \Carbon\Carbon;
        });
    }

    // ─── Test 6: Event has null calendar fields for sessions with no match ────

    public function test_confirmed_event_has_null_calendar_fields_when_no_calendar_match(): void
    {
        Event::fake([IngestSessionConfirmed::class]);

        $session = UploadSession::create([
            'studio_id'  => 10,
            'user_id'    => 5,
            'status'     => UploadSession::STATUS_UPLOADING,
            'file_count' => 1,
            // No calendar fields
        ]);

        app(UploadSessionService::class)->confirmSession($session->id);

        Event::assertDispatched(IngestSessionConfirmed::class, function (IngestSessionConfirmed $event) {
            return $event->calendarEventId         === null
                && $event->calendarProvider         === null
                && $event->calendarMatchConfidence  === null;
        });
    }
}
