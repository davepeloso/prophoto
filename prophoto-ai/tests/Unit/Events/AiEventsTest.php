<?php

namespace ProPhoto\AI\Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use ProPhoto\AI\Events\AiGenerationCompleted;
use ProPhoto\AI\Events\AiModelTrained;

class AiEventsTest extends TestCase
{
    public function test_ai_model_trained_stores_all_properties(): void
    {
        $event = new AiModelTrained(
            galleryId: 1,
            generationId: 2,
            providerKey: 'astria',
            modelStatus: 'trained',
            trainedAt: '2026-04-15T12:00:00+00:00',
        );

        $this->assertSame(1, $event->galleryId);
        $this->assertSame(2, $event->generationId);
        $this->assertSame('astria', $event->providerKey);
        $this->assertSame('trained', $event->modelStatus);
        $this->assertSame('2026-04-15T12:00:00+00:00', $event->trainedAt);
    }

    public function test_ai_model_trained_allows_null_trained_at(): void
    {
        $event = new AiModelTrained(
            galleryId: 1,
            generationId: 2,
            providerKey: 'astria',
            modelStatus: 'failed',
        );

        $this->assertNull($event->trainedAt);
    }

    public function test_ai_generation_completed_stores_all_properties(): void
    {
        $event = new AiGenerationCompleted(
            galleryId: 1,
            generationId: 2,
            requestId: 3,
            portraitCount: 8,
            providerKey: 'astria',
        );

        $this->assertSame(1, $event->galleryId);
        $this->assertSame(2, $event->generationId);
        $this->assertSame(3, $event->requestId);
        $this->assertSame(8, $event->portraitCount);
        $this->assertSame('astria', $event->providerKey);
    }
}
