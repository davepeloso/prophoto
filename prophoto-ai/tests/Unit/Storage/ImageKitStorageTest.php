<?php

namespace ProPhoto\AI\Tests\Unit\Storage;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ProPhoto\AI\Storage\ImageKitConfig;
use ProPhoto\AI\Storage\ImageKitStorage;
use ProPhoto\Contracts\Contracts\AI\AiStorageContract;
use RuntimeException;

class ImageKitStorageTest extends TestCase
{
    private ImageKitConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new ImageKitConfig(
            publicKey: 'public_test',
            privateKey: 'private_test',
            urlEndpoint: 'https://ik.imagekit.io/prophoto',
        );
    }

    private function makeStorage(array $responses, array &$history = []): ImageKitStorage
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $httpClient = new Client(['handler' => $stack]);

        return new ImageKitStorage($httpClient, $this->config);
    }

    // ── Contract implementation ────────────────────────────────────

    public function test_implements_ai_storage_contract(): void
    {
        $storage = $this->makeStorage([]);

        $this->assertInstanceOf(AiStorageContract::class, $storage);
    }

    // ── Upload ─────────────────────────────────────────────────────

    public function test_upload_sends_correct_request(): void
    {
        $history = [];
        $storage = $this->makeStorage([
            new Response(200, [], json_encode([
                'fileId' => 'ik_abc123',
                'url' => 'https://ik.imagekit.io/prophoto/ai-portraits/portrait.jpg',
                'thumbnailUrl' => 'https://ik.imagekit.io/prophoto/tr:n-ik_ml_thumbnail/ai-portraits/portrait.jpg',
                'filePath' => '/ai-portraits/portrait.jpg',
                'name' => 'portrait.jpg',
                'size' => 94466,
                'width' => 1024,
                'height' => 1024,
                'fileType' => 'image',
            ])),
        ], $history);

        $result = $storage->upload(
            sourceUrl: 'https://cdn.astria.ai/output/temp-image.jpg',
            fileName: 'portrait.jpg',
            folder: '/ai-portraits',
            tags: ['ai-generated', 'gallery-42'],
        );

        // Verify result
        $this->assertSame('ik_abc123', $result->fileId);
        $this->assertSame('https://ik.imagekit.io/prophoto/ai-portraits/portrait.jpg', $result->url);
        $this->assertSame('https://ik.imagekit.io/prophoto/tr:n-ik_ml_thumbnail/ai-portraits/portrait.jpg', $result->thumbnailUrl);
        $this->assertSame(94466, $result->fileSize);
        $this->assertSame('/ai-portraits/portrait.jpg', $result->metadata['filePath']);
        $this->assertSame(1024, $result->metadata['width']);

        // Verify request
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('upload.imagekit.io', (string) $request->getUri());

        // Verify auth (Basic auth with private key)
        $authHeader = $request->getHeaderLine('Authorization');
        $this->assertStringStartsWith('Basic', $authHeader);

        // Verify multipart body contains our params
        $body = (string) $request->getBody();
        $this->assertStringContainsString('https://cdn.astria.ai/output/temp-image.jpg', $body);
        $this->assertStringContainsString('portrait.jpg', $body);
        $this->assertStringContainsString('/ai-portraits', $body);
    }

    public function test_upload_without_tags(): void
    {
        $history = [];
        $storage = $this->makeStorage([
            new Response(200, [], json_encode([
                'fileId' => 'ik_notags',
                'url' => 'https://ik.imagekit.io/prophoto/test.jpg',
                'size' => 5000,
            ])),
        ], $history);

        $result = $storage->upload(
            sourceUrl: 'https://example.com/image.jpg',
            fileName: 'test.jpg',
            folder: '/test',
        );

        $this->assertSame('ik_notags', $result->fileId);

        // Body should not contain tags field
        $body = (string) $history[0]['request']->getBody();
        $this->assertStringNotContainsString('"tags"', $body);
    }

    public function test_upload_throws_on_api_failure(): void
    {
        $storage = $this->makeStorage([
            new Response(500, [], 'Internal server error'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/ImageKit upload failed/');

        $storage->upload('https://example.com/img.jpg', 'test.jpg', '/folder');
    }

    // ── URL Generation ─────────────────────────────────────────────

    public function test_generate_url_without_transforms(): void
    {
        $storage = $this->makeStorage([]);

        $url = $storage->generateUrl('ai-portraits/portrait.jpg');

        $this->assertSame('https://ik.imagekit.io/prophoto/ai-portraits/portrait.jpg', $url);
    }

    public function test_generate_url_with_resize_transforms(): void
    {
        $storage = $this->makeStorage([]);

        $url = $storage->generateUrl('ai-portraits/portrait.jpg', [
            'w' => '400',
            'h' => '300',
        ]);

        $this->assertSame('https://ik.imagekit.io/prophoto/tr:w-400,h-300/ai-portraits/portrait.jpg', $url);
    }

    public function test_generate_url_with_format_transform(): void
    {
        $storage = $this->makeStorage([]);

        $url = $storage->generateUrl('portrait.jpg', ['f' => 'webp']);

        $this->assertSame('https://ik.imagekit.io/prophoto/tr:f-webp/portrait.jpg', $url);
    }

    public function test_generate_url_with_ai_extension_transforms(): void
    {
        $storage = $this->makeStorage([]);

        $url = $storage->generateUrl('portrait.jpg', [
            'e-bgremove' => '',
            'e-retouch' => '',
        ]);

        $this->assertSame('https://ik.imagekit.io/prophoto/tr:e-bgremove,e-retouch/portrait.jpg', $url);
    }

    public function test_generate_url_with_mixed_transforms(): void
    {
        $storage = $this->makeStorage([]);

        $url = $storage->generateUrl('portrait.jpg', [
            'w' => '800',
            'e-upscale' => '',
            'f' => 'avif',
        ]);

        $this->assertSame('https://ik.imagekit.io/prophoto/tr:w-800,e-upscale,f-avif/portrait.jpg', $url);
    }

    // ── Signed URL ─────────────────────────────────────────────────

    public function test_generate_signed_url_includes_signature_params(): void
    {
        $storage = $this->makeStorage([]);

        $url = $storage->generateSignedUrl('portrait.jpg', [], 3600);

        $this->assertStringContainsString('ik-s=', $url);
        $this->assertStringContainsString('ik-t=', $url);
        $this->assertStringContainsString('https://ik.imagekit.io/prophoto/portrait.jpg', $url);
    }

    public function test_generate_signed_url_with_transforms(): void
    {
        $storage = $this->makeStorage([]);

        $url = $storage->generateSignedUrl('portrait.jpg', ['w' => '400'], 1800);

        $this->assertStringContainsString('tr:w-400', $url);
        $this->assertStringContainsString('ik-s=', $url);
        $this->assertStringContainsString('ik-t=', $url);
    }

    // ── Delete ─────────────────────────────────────────────────────

    public function test_delete_sends_correct_request(): void
    {
        $history = [];
        $storage = $this->makeStorage([
            new Response(204, []),
        ], $history);

        $result = $storage->delete('ik_abc123');

        $this->assertTrue($result);

        $request = $history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringEndsWith('/files/ik_abc123', (string) $request->getUri());
    }

    public function test_delete_returns_false_on_failure(): void
    {
        $storage = $this->makeStorage([
            new Response(404, [], 'Not found'),
        ]);

        $result = $storage->delete('ik_nonexistent');

        $this->assertFalse($result);
    }

    // ── Validate Configuration ─────────────────────────────────────

    public function test_validate_configuration_delegates_to_config(): void
    {
        $storage = $this->makeStorage([]);

        $this->assertTrue($storage->validateConfiguration());
    }

    public function test_validate_configuration_fails_with_empty_config(): void
    {
        $emptyConfig = new ImageKitConfig(publicKey: '', privateKey: '', urlEndpoint: '');
        $storage = new ImageKitStorage(new Client(), $emptyConfig);

        $this->assertFalse($storage->validateConfiguration());
    }
}
