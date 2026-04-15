<?php

namespace ProPhoto\AI\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use ProPhoto\AI\Jobs\GeneratePortraitsJob;
use ProPhoto\AI\Jobs\PollGenerationStatusJob;
use ProPhoto\AI\Jobs\PollTrainingStatusJob;
use ProPhoto\AI\Jobs\TrainModelJob;
use Carbon\Carbon;

class JobConfigurationTest extends TestCase
{
    // ── TrainModelJob ───────────────────────────────────────────────

    public function test_train_model_job_has_correct_tries(): void
    {
        $job = new TrainModelJob(generationId: 1, imageUrls: []);

        $this->assertSame(3, $job->tries);
    }

    public function test_train_model_job_has_correct_timeout(): void
    {
        $job = new TrainModelJob(generationId: 1, imageUrls: []);

        $this->assertSame(120, $job->timeout);
    }

    public function test_train_model_job_has_exponential_backoff(): void
    {
        $job = new TrainModelJob(generationId: 1, imageUrls: []);

        $this->assertSame([30, 60, 120], $job->backoff);
    }

    public function test_train_model_job_stores_constructor_args(): void
    {
        $urls = ['https://example.com/1.jpg', 'https://example.com/2.jpg'];
        $job = new TrainModelJob(generationId: 42, imageUrls: $urls);

        $this->assertSame(42, $job->generationId);
        $this->assertSame($urls, $job->imageUrls);
    }

    // ── PollTrainingStatusJob ───────────────────────────────────────

    public function test_poll_training_job_has_single_try(): void
    {
        $job = new PollTrainingStatusJob(
            generationId: 1,
            startedAt: Carbon::now(),
            pollCount: 0,
        );

        $this->assertSame(1, $job->tries);
    }

    public function test_poll_training_job_stores_constructor_args(): void
    {
        $startedAt = Carbon::parse('2026-04-15 12:00:00');
        $job = new PollTrainingStatusJob(
            generationId: 42,
            startedAt: $startedAt,
            pollCount: 5,
        );

        $this->assertSame(42, $job->generationId);
        $this->assertSame($startedAt, $job->startedAt);
        $this->assertSame(5, $job->pollCount);
    }

    public function test_poll_training_job_defaults_poll_count_to_zero(): void
    {
        $job = new PollTrainingStatusJob(
            generationId: 1,
            startedAt: Carbon::now(),
        );

        $this->assertSame(0, $job->pollCount);
    }

    // ── GeneratePortraitsJob ────────────────────────────────────────

    public function test_generate_portraits_job_has_correct_tries(): void
    {
        $job = new GeneratePortraitsJob(requestId: 1);

        $this->assertSame(3, $job->tries);
    }

    public function test_generate_portraits_job_has_correct_timeout(): void
    {
        $job = new GeneratePortraitsJob(requestId: 1);

        $this->assertSame(60, $job->timeout);
    }

    public function test_generate_portraits_job_stores_request_id(): void
    {
        $job = new GeneratePortraitsJob(requestId: 99);

        $this->assertSame(99, $job->requestId);
    }

    // ── PollGenerationStatusJob ─────────────────────────────────────

    public function test_poll_generation_job_has_single_try(): void
    {
        $job = new PollGenerationStatusJob(
            requestId: 1,
            startedAt: Carbon::now(),
        );

        $this->assertSame(1, $job->tries);
    }

    public function test_poll_generation_job_stores_constructor_args(): void
    {
        $startedAt = Carbon::parse('2026-04-15 14:00:00');
        $job = new PollGenerationStatusJob(
            requestId: 55,
            startedAt: $startedAt,
            pollCount: 3,
        );

        $this->assertSame(55, $job->requestId);
        $this->assertSame($startedAt, $job->startedAt);
        $this->assertSame(3, $job->pollCount);
    }

    public function test_poll_generation_job_defaults_poll_count_to_zero(): void
    {
        $job = new PollGenerationStatusJob(
            requestId: 1,
            startedAt: Carbon::now(),
        );

        $this->assertSame(0, $job->pollCount);
    }

    // ── ShouldQueue ─────────────────────────────────────────────────

    public function test_all_jobs_implement_should_queue(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            new TrainModelJob(1, [])
        );
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            new PollTrainingStatusJob(1, Carbon::now())
        );
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            new GeneratePortraitsJob(1)
        );
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            new PollGenerationStatusJob(1, Carbon::now())
        );
    }
}
