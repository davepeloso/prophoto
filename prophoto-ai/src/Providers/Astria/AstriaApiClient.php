<?php

namespace ProPhoto\AI\Providers\Astria;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * HTTP client for the Astria.ai REST API.
 *
 * Handles all HTTP communication with Astria. Maps to their terminology:
 *   - Tune  = model training (POST /tunes, GET /tunes/{id})
 *   - Prompt = generation request (POST /tunes/{tuneId}/prompts, GET /tunes/{tuneId}/prompts/{id})
 *
 * The provider layer (AstriaProvider) handles business logic mapping;
 * this class only handles HTTP.
 */
class AstriaApiClient
{
    private const MAX_RETRIES = 3;
    private const RETRY_BASE_DELAY_MS = 1000;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly AstriaConfig $config,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Create a new tune (model training).
     *
     * @param  array  $imageUrls  Public URLs of training images
     * @param  string $className  Subject class: 'man', 'woman', 'person'
     * @param  string $title      Unique title (used for idempotency)
     * @param  string|null $callbackUrl  Webhook URL for completion notification
     * @return array  Raw Astria tune response
     *
     * @throws RuntimeException on API failure after retries
     */
    public function createTune(
        array $imageUrls,
        string $className,
        string $title,
        ?string $callbackUrl = null,
    ): array {
        $payload = [
            'tune' => [
                'title' => $title,
                'name' => $className,
                'image_urls' => $imageUrls,
                'preset' => $this->config->preset(),
                'model_type' => $this->config->modelType(),
                'face_crop' => $this->config->faceCrop(),
            ],
        ];

        if ($callbackUrl !== null) {
            $payload['tune']['callback'] = $callbackUrl;
        }

        return $this->request('POST', '/tunes', $payload, 60);
    }

    /**
     * Get a tune's current state.
     *
     * @return array  Raw Astria tune response (check trained_at for completion)
     */
    public function getTune(int $tuneId): array
    {
        return $this->request('GET', "/tunes/{$tuneId}");
    }

    /**
     * Create a new prompt (generation request) against a trained tune.
     *
     * @param  int    $tuneId          The trained tune/model ID
     * @param  string $prompt          Text prompt for generation
     * @param  string $negativePrompt  Things to avoid in generation
     * @param  int    $numImages       Number of images to generate (1-8)
     * @param  string|null $callbackUrl  Webhook URL for completion notification
     * @return array  Raw Astria prompt response
     */
    public function createPrompt(
        int $tuneId,
        string $prompt,
        string $negativePrompt,
        int $numImages = 8,
        ?string $callbackUrl = null,
    ): array {
        $payload = [
            'prompt' => [
                'text' => $prompt,
                'negative_prompt' => $negativePrompt,
                'num_images' => min(max($numImages, 1), 8),
                'w' => 1024,
                'h' => 1024,
            ],
        ];

        if ($callbackUrl !== null) {
            $payload['prompt']['callback'] = $callbackUrl;
        }

        return $this->request('POST', "/tunes/{$tuneId}/prompts", $payload);
    }

    /**
     * Get a prompt's current state.
     *
     * @return array  Raw Astria prompt response (check images array for completion)
     */
    public function getPrompt(int $tuneId, int $promptId): array
    {
        return $this->request('GET', "/tunes/{$tuneId}/prompts/{$promptId}");
    }

    /**
     * Send an HTTP request to the Astria API with retry logic.
     *
     * Retries on 429 (rate limit) and 5xx (server error) up to MAX_RETRIES
     * with exponential backoff.
     *
     * @throws RuntimeException on failure after all retries
     */
    private function request(string $method, string $path, array $payload = [], int $timeoutSeconds = 30): array
    {
        $url = $this->config->baseUrl() . $path;
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $options = [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->config->apiKey(),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'timeout' => $timeoutSeconds,
                ];

                if (! empty($payload) && $method !== 'GET') {
                    $options['json'] = $payload;
                }

                $this->logger->debug("Astria API {$method} {$path}", [
                    'attempt' => $attempt,
                    'payload_keys' => array_keys($payload),
                ]);

                $response = $this->httpClient->request($method, $url, $options);
                $body = json_decode((string) $response->getBody(), true);

                $this->logger->debug("Astria API response", [
                    'status' => $response->getStatusCode(),
                    'path' => $path,
                ]);

                return $body;

            } catch (RequestException $e) {
                $lastException = $e;
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;

                // Only retry on 429 (rate limit) or 5xx (server error)
                if ($statusCode === 429 || $statusCode >= 500) {
                    if ($attempt < self::MAX_RETRIES) {
                        $delayMs = self::RETRY_BASE_DELAY_MS * (2 ** ($attempt - 1));
                        $this->logger->warning("Astria API retry", [
                            'attempt' => $attempt,
                            'status' => $statusCode,
                            'delay_ms' => $delayMs,
                            'path' => $path,
                        ]);
                        usleep($delayMs * 1000);

                        continue;
                    }
                }

                // Non-retryable error or max retries exhausted
                $responseBody = $e->hasResponse()
                    ? (string) $e->getResponse()->getBody()
                    : 'No response body';

                $this->logger->error("Astria API error", [
                    'method' => $method,
                    'path' => $path,
                    'status' => $statusCode,
                    'response' => $responseBody,
                    'attempt' => $attempt,
                ]);

                throw new RuntimeException(
                    "Astria API request failed: {$method} {$path} returned {$statusCode}. Response: {$responseBody}",
                    $statusCode,
                    $e,
                );

            } catch (GuzzleException $e) {
                $lastException = $e;

                $this->logger->error("Astria API connection error", [
                    'method' => $method,
                    'path' => $path,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    $delayMs = self::RETRY_BASE_DELAY_MS * (2 ** ($attempt - 1));
                    usleep($delayMs * 1000);

                    continue;
                }

                throw new RuntimeException(
                    "Astria API connection failed: {$method} {$path}. Error: {$e->getMessage()}",
                    0,
                    $e,
                );
            }
        }

        // Should not reach here, but just in case
        throw new RuntimeException(
            "Astria API request failed after " . self::MAX_RETRIES . " attempts",
            0,
            $lastException,
        );
    }
}
