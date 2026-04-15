<?php

namespace ProPhoto\AI\Tests\Unit\Providers\Astria;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ProPhoto\AI\Providers\Astria\AstriaApiClient;
use ProPhoto\AI\Providers\Astria\AstriaConfig;
use RuntimeException;

class AstriaApiClientTest extends TestCase
{
    private function makeClient(array $responses, array &$history = []): AstriaApiClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $stack]);

        $config = new AstriaConfig(
            apiKey: 'sd_test_key',
            baseUrl: 'https://api.astria.ai',
        );

        return new AstriaApiClient($httpClient, $config);
    }

    // ── createTune ─────────────────────────────────────────────────

    public function test_create_tune_sends_correct_request(): void
    {
        $history = [];
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'id' => 12345,
                'title' => 'prophoto_abc',
                'eta' => '2026-04-15T13:00:00Z',
                'started_training_at' => null,
                'trained_at' => null,
            ])),
        ], $history);

        $result = $client->createTune(
            imageUrls: ['https://example.com/1.jpg', 'https://example.com/2.jpg'],
            className: 'man',
            title: 'prophoto_abc',
            callbackUrl: 'https://prophoto.test/api/webhooks/astria?type=tune&id=1',
        );

        $this->assertSame(12345, $result['id']);
        $this->assertNull($result['trained_at']);

        // Verify request
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/tunes', (string) $request->getUri());
        $this->assertSame('Bearer sd_test_key', $request->getHeaderLine('Authorization'));

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('man', $body['tune']['name']);
        $this->assertSame('prophoto_abc', $body['tune']['title']);
        $this->assertCount(2, $body['tune']['image_urls']);
        $this->assertSame('flux-lora-portrait', $body['tune']['preset']);
        $this->assertSame('lora', $body['tune']['model_type']);
        $this->assertTrue($body['tune']['face_crop']);
        $this->assertSame('https://prophoto.test/api/webhooks/astria?type=tune&id=1', $body['tune']['callback']);
    }

    public function test_create_tune_omits_callback_when_null(): void
    {
        $history = [];
        $client = $this->makeClient([
            new Response(200, [], json_encode(['id' => 1])),
        ], $history);

        $client->createTune(['https://example.com/1.jpg'], 'woman', 'test_title');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayNotHasKey('callback', $body['tune']);
    }

    // ── getTune ────────────────────────────────────────────────────

    public function test_get_tune_returns_response(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'id' => 12345,
                'trained_at' => '2026-04-15T14:00:00Z',
                'started_training_at' => '2026-04-15T12:00:00Z',
            ])),
        ]);

        $result = $client->getTune(12345);

        $this->assertSame(12345, $result['id']);
        $this->assertSame('2026-04-15T14:00:00Z', $result['trained_at']);
    }

    // ── createPrompt ───────────────────────────────────────────────

    public function test_create_prompt_sends_correct_request(): void
    {
        $history = [];
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'id' => 67890,
                'images' => [],
            ])),
        ], $history);

        $result = $client->createPrompt(
            tuneId: 12345,
            prompt: 'professional headshot, studio lighting',
            negativePrompt: 'blurry, distorted',
            numImages: 4,
            callbackUrl: 'https://prophoto.test/api/webhooks/astria?type=prompt&id=5',
        );

        $this->assertSame(67890, $result['id']);
        $this->assertSame([], $result['images']);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/tunes/12345/prompts', (string) $request->getUri());

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('professional headshot, studio lighting', $body['prompt']['text']);
        $this->assertSame('blurry, distorted', $body['prompt']['negative_prompt']);
        $this->assertSame(4, $body['prompt']['num_images']);
        $this->assertSame(1024, $body['prompt']['w']);
        $this->assertSame(1024, $body['prompt']['h']);
    }

    public function test_create_prompt_clamps_num_images_to_range(): void
    {
        $history = [];
        $client = $this->makeClient([
            new Response(200, [], json_encode(['id' => 1, 'images' => []])),
            new Response(200, [], json_encode(['id' => 2, 'images' => []])),
        ], $history);

        // Request 0 images → clamped to 1
        $client->createPrompt(12345, 'test', 'neg', 0);
        $body1 = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame(1, $body1['prompt']['num_images']);

        // Request 20 images → clamped to 8
        $client->createPrompt(12345, 'test', 'neg', 20);
        $body2 = json_decode((string) $history[1]['request']->getBody(), true);
        $this->assertSame(8, $body2['prompt']['num_images']);
    }

    // ── getPrompt ──────────────────────────────────────────────────

    public function test_get_prompt_returns_response(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode([
                'id' => 67890,
                'images' => ['https://cdn.astria.ai/1.jpg', 'https://cdn.astria.ai/2.jpg'],
            ])),
        ]);

        $result = $client->getPrompt(12345, 67890);

        $this->assertSame(67890, $result['id']);
        $this->assertCount(2, $result['images']);
    }

    // ── Retry logic ────────────────────────────────────────────────

    public function test_retries_on_429_then_succeeds(): void
    {
        $client = $this->makeClient([
            RequestException::create(
                new Request('GET', '/tunes/12345'),
                new Response(429, [], 'Rate limited'),
            ),
            new Response(200, [], json_encode(['id' => 12345])),
        ]);

        $result = $client->getTune(12345);

        $this->assertSame(12345, $result['id']);
    }

    public function test_retries_on_500_then_succeeds(): void
    {
        $client = $this->makeClient([
            RequestException::create(
                new Request('GET', '/tunes/12345'),
                new Response(500, [], 'Server error'),
            ),
            new Response(200, [], json_encode(['id' => 12345])),
        ]);

        $result = $client->getTune(12345);

        $this->assertSame(12345, $result['id']);
    }

    public function test_throws_after_max_retries_on_429(): void
    {
        $client = $this->makeClient([
            RequestException::create(
                new Request('GET', '/tunes/12345'),
                new Response(429, [], 'Rate limited'),
            ),
            RequestException::create(
                new Request('GET', '/tunes/12345'),
                new Response(429, [], 'Rate limited'),
            ),
            RequestException::create(
                new Request('GET', '/tunes/12345'),
                new Response(429, [], 'Rate limited'),
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/429/');

        $client->getTune(12345);
    }

    public function test_does_not_retry_on_400(): void
    {
        $history = [];
        $client = $this->makeClient([
            RequestException::create(
                new Request('GET', '/tunes/99999'),
                new Response(400, [], json_encode(['error' => 'Bad request'])),
            ),
        ], $history);

        $this->expectException(RuntimeException::class);

        $client->getTune(99999);
    }
}
