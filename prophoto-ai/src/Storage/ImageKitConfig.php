<?php

namespace ProPhoto\AI\Storage;

/**
 * Typed configuration accessor for the ImageKit storage/delivery layer.
 *
 * Reads from the `ai.storage.imagekit` config namespace.
 */
readonly class ImageKitConfig
{
    public function __construct(
        private string $publicKey,
        private string $privateKey,
        private string $urlEndpoint,
    ) {}

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function privateKey(): string
    {
        return $this->privateKey;
    }

    public function urlEndpoint(): string
    {
        return rtrim($this->urlEndpoint, '/');
    }

    /**
     * Validate that all required keys are present and non-empty.
     */
    public function validate(): bool
    {
        return ! empty($this->publicKey)
            && ! empty($this->privateKey)
            && ! empty($this->urlEndpoint);
    }

    /**
     * Create an ImageKitConfig from the Laravel config array.
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            publicKey: $config['public_key'] ?? '',
            privateKey: $config['private_key'] ?? '',
            urlEndpoint: $config['url_endpoint'] ?? '',
        );
    }
}
