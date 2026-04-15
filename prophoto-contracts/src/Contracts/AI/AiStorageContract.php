<?php

namespace ProPhoto\Contracts\Contracts\AI;

use ProPhoto\Contracts\DTOs\AI\StorageResult;

interface AiStorageContract
{
    /**
     * Upload a file from a source URL into permanent storage.
     * The implementation fetches the file from the (potentially transient) source URL
     * and stores it permanently.
     *
     * @param string $sourceUrl   Transient URL from provider (e.g., Astria output)
     * @param string $fileName    Desired filename
     * @param string $folder      Storage folder/path
     * @param array  $tags        Optional tags for organization
     */
    public function upload(string $sourceUrl, string $fileName, string $folder, array $tags = []): StorageResult;

    /**
     * Generate a delivery URL with optional transformation parameters.
     * Transforms are applied on-the-fly by the delivery layer (e.g., ImageKit URL transforms).
     *
     * @param string $fileId      File identifier from a previous upload
     * @param array  $transforms  Provider-specific transform params (e.g., ['e-bgremove' => ''])
     */
    public function generateUrl(string $fileId, array $transforms = []): string;

    /**
     * Generate a signed delivery URL with expiry.
     */
    public function generateSignedUrl(string $fileId, array $transforms = [], int $expireSeconds = 3600): string;

    /**
     * Remove a file from storage.
     */
    public function delete(string $fileId): bool;

    /**
     * Verify storage configuration (API keys, connectivity).
     */
    public function validateConfiguration(): bool;
}
