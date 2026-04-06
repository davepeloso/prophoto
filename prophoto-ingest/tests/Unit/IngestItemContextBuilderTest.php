<?php

namespace ProPhoto\Ingest\Tests\Unit;

use ProPhoto\Contracts\Enums\SessionAssociationSubjectType;
use ProPhoto\Ingest\Domain\IngestItem;
use ProPhoto\Ingest\Services\IngestItemContextBuilder;
use ProPhoto\Ingest\Tests\TestCase;

class IngestItemContextBuilderTest extends TestCase
{
    public function test_build_for_matching_creates_aligned_ingest_item_subject_context(): void
    {
        $ingestItem = new IngestItem(
            ingestItemId: 'ingest-5001',
            captureAtUtc: '2026-03-13T18:05:00Z',
            gpsLat: 34.1000,
            gpsLng: -118.3000,
            sessionTypeHint: 'wedding',
            jobTypeHint: 'ceremony',
            titleHint: 'Smith wedding ceremony',
            triggerSource: 'ingest_batch',
            idempotencyKey: 'ingest-context-test-1',
            actorType: 'system',
            actorId: null,
            createdAt: '2026-03-13T18:06:00Z'
        );

        $context = (new IngestItemContextBuilder())->buildForMatching($ingestItem);

        $this->assertSame(SessionAssociationSubjectType::INGEST_ITEM, $context['subject_type']);
        $this->assertSame('ingest-5001', $context['subject_id']);
        $this->assertSame('ingest-5001', $context['ingest_item_id']);
        $this->assertNull($context['asset_id']);
        $this->assertSame('2026-03-13T18:05:00Z', $context['capture_at_utc']);
        $this->assertSame(34.1000, $context['gps_lat']);
        $this->assertSame(-118.3000, $context['gps_lng']);
        $this->assertSame('wedding', $context['session_type_hint']);
        $this->assertSame('ceremony', $context['job_type_hint']);
        $this->assertSame('Smith wedding ceremony', $context['title_hint']);
        $this->assertSame('ingest_batch', $context['trigger_source']);
        $this->assertSame('ingest-context-test-1', $context['idempotency_key']);
        $this->assertSame('system', $context['actor_type']);
        $this->assertNull($context['actor_id']);
        $this->assertSame('2026-03-13T18:06:00Z', $context['created_at']);
    }

    public function test_build_for_matching_omits_created_at_when_not_provided(): void
    {
        $ingestItem = new IngestItem(
            ingestItemId: 'ingest-5002',
            captureAtUtc: null,
            gpsLat: null,
            gpsLng: null
        );

        $context = (new IngestItemContextBuilder())->buildForMatching($ingestItem);

        $this->assertArrayNotHasKey('created_at', $context);
        $this->assertSame('ingest-5002', $context['subject_id']);
        $this->assertSame('ingest-5002', $context['ingest_item_id']);
    }
}
