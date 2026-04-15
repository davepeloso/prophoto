<?php

namespace ProPhoto\AI\Storage;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ProPhoto\Contracts\Contracts\AI\AiStorageContract;
use ProPhoto\Contracts\DTOs\AI\StorageResult;
use RuntimeException;

/**
 * ImageKit implementation of the AI storage/delivery contract.
 *
 * Handles two concerns:
 *   1. Upload — fetches images from transient provider URLs and stores them
 *      permanently in ImageKit (backed by DigitalOcean Spaces).
 *   2. Delivery — generates CDN URLs with optional transforms applied via
 *      URL parameters (resize, format, bg removal, retouch, etc.).
 *
 * Uses the ImageKit HTTP API directly via Guzzle. Auth is HTTP Basic with
 * the private key as username (password is ignored by ImageKit).
 *
 * API endpoints:
 *   - Upload: POST https://upload.imagekit.io/api/v1/files/upload
 *   - Delete: DELETE https://api.imagekit.io/v1/files/{fileId}
 *   - URL generation: string concatenation on the URL endpoint (no API call)
 */
class ImageKitStorage implements AiStorageContract
{
    private const UPLOAD_BASE = 'https://upload.imagekit.io/api/v1/files/upload';
    private const API_BASE = 'https://api.imagekit.io/v1';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly ImageKitConfig $config,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Upload a file from a source URL into ImageKit storage.
     *
     * ImageKit accepts HTTP/HTTPS URLs as the `file` parameter — it fetches the
     * image from the source URL and stores it permanently. No intermediate download.
     * The source URL must respond within 8 seconds.
     */
    public function upload(string $sourceUrl, string $fileName, string $folder, array $tags = []): StorageResult
    {
        $formParams = [
            'file' => $sourceUrl,
            'fileName' => $fileName,
            'folder' => $folder,
            'useUniqueFileName' => 'true',
        ];

        if (! empty($tags)) {
            $formParams['tags'] = json_encode($tags);
        }

        try {
            $response = $this->httpClient->request('POST', self::UPLOAD_BASE, [
                'auth' => [$this->config->privateKey(), ''],
                'multipart' => $this->buildMultipart($formParams),
                'timeout' => 30,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            $this->logger->debug('ImageKit upload success', [
                'fileId' => $body['fileId'] ?? null,
                'filePath' => $body['filePath'] ?? null,
            ]);

            return new StorageResult(
                fileId: $body['fileId'],
                url: $body['url'],
                thumbnailUrl: $body['thumbnailUrl'] ?? null,
                fileSize: $body['size'] ?? null,
                metadata: [
                    'filePath' => $body['filePath'] ?? null,
                    'name' => $body['name'] ?? null,
                    'width' => $body['width'] ?? null,
                    'height' => $body['height'] ?? null,
                    'fileType' => $body['fileType'] ?? null,
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error('ImageKit upload failed', [
                'sourceUrl' => $sourceUrl,
                'fileName' => $fileName,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                "ImageKit upload failed for {$fileName}: {$e->getMessage()}",
                0,
                $e,
            );
        }
    }

    /**
     * Generate a delivery URL with optional transformations.
     *
     * Transforms are applied via URL path parameters. ImageKit URL format:
     *   {urlEndpoint}/tr:{transforms}/{filePath}
     *
     * Transform examples:
     *   ['w' => '400', 'h' => '300']         → tr:w-400,h-300
     *   ['f' => 'webp']                       → tr:f-webp
     *   ['e-bgremove' => '']                  → tr:e-bgremove
     *   ['e-retouch' => '', 'e-upscale' => ''] → tr:e-retouch:e-upscale
     *
     * @param string $fileId     The filePath from a previous upload (e.g., /ai-portraits/image.jpg)
     * @param array  $transforms Key-value pairs for transforms
     */
    public function generateUrl(string $fileId, array $transforms = []): string
    {
        $baseUrl = $this->config->urlEndpoint();

        if (empty($transforms)) {
            return "{$baseUrl}/{$fileId}";
        }

        $transformString = $this->buildTransformString($transforms);

        return "{$baseUrl}/tr:{$transformString}/{$fileId}";
    }

    /**
     * Generate a signed delivery URL with expiry.
     *
     * Signed URLs use HMAC-SHA1 with the private key. The signature covers
     * the URL path + expiry timestamp.
     */
    public function generateSignedUrl(string $fileId, array $transforms = [], int $expireSeconds = 3600): string
    {
        $url = $this->generateUrl($fileId, $transforms);

        $expire = time() + $expireSeconds;

        // Parse the URL to get the path for signing
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? "/{$fileId}";

        // ImageKit signature: HMAC-SHA1(privateKey, path + expireTimestamp)
        $signaturePayload = $path . $expire;
        $signature = hash_hmac('sha1', $signaturePayload, $this->config->privateKey());

        $separator = str_contains($url, '?') ? '&' : '?';

        return "{$url}{$separator}ik-s={$signature}&ik-t={$expire}";
    }

    /**
     * Remove a file from ImageKit storage.
     */
    public function delete(string $fileId): bool
    {
        try {
            $this->httpClient->request('DELETE', self::API_BASE . "/files/{$fileId}", [
                'auth' => [$this->config->privateKey(), ''],
                'timeout' => 15,
            ]);

            $this->logger->debug('ImageKit file deleted', ['fileId' => $fileId]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('ImageKit delete failed', [
                'fileId' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify storage configuration.
     */
    public function validateConfiguration(): bool
    {
        return $this->config->validate();
    }

    /**
     * Build a transform string from key-value pairs.
     *
     * Handles two formats:
     *   - Key-value: ['w' => '400'] → 'w-400'
     *   - Flag only: ['e-bgremove' => ''] → 'e-bgremove'
     */
    private function buildTransformString(array $transforms): string
    {
        $parts = [];

        foreach ($transforms as $key => $value) {
            if ($value === '' || $value === null) {
                $parts[] = $key;
            } else {
                $parts[] = "{$key}-{$value}";
            }
        }

        return implode(',', $parts);
    }

    /**
     * Convert a flat array to multipart form data for Guzzle.
     */
    private function buildMultipart(array $params): array
    {
        $multipart = [];

        foreach ($params as $name => $value) {
            $multipart[] = [
                'name' => $name,
                'contents' => (string) $value,
            ];
        }

        return $multipart;
    }
}
