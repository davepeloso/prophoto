<?php

namespace ProPhoto\Ingest\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Models\AssetDerivative;
use ProPhoto\Contracts\Contracts\Asset\AssetStorageContract;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataNormalizerContract;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\MetadataProvenance;
use ProPhoto\Contracts\DTOs\RawMetadataBundle;
use ProPhoto\Contracts\Enums\AssetType;
use ProPhoto\Contracts\Enums\DerivativeType;
use ProPhoto\Contracts\Enums\MetadataScope;
use ProPhoto\Contracts\Events\Asset\AssetCreated;
use ProPhoto\Contracts\Events\Asset\AssetDerivativesGenerated;
use ProPhoto\Contracts\Events\Asset\AssetMetadataExtracted;
use ProPhoto\Contracts\Events\Asset\AssetMetadataNormalized;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Contracts\Events\Asset\AssetStored;
use ProPhoto\Ingest\Models\Image;
use ProPhoto\Ingest\Models\ProxyImage;
use ProPhoto\Ingest\Models\Tag;

class IngestProcessor
{
    protected ?bool $assetSpineTablesAvailableCache = null;

    public function __construct(
        protected array $storageConfig,
        protected array $schemaConfig
    ) {}

    /**
     * Process a single proxy image into final storage
     *
     * Uses normalized metadata from ExifTool when available, falling back
     * to parsing raw EXIF values for backwards compatibility.
     */
    public function process(ProxyImage $proxy, int $sequence, ?array $association = null): Image
    {
        try {
            // Build the final path and filename
            $finalPath = $this->buildPath($proxy, $sequence);
            $finalFilename = $this->buildFilename($proxy, $sequence);

            // Get source and destination disk
            $tempDisk = $this->storageConfig['temp_disk'] ?? 'local';
            $finalDisk = $this->storageConfig['final_disk'] ?? 'local';

            // Log processing start
            \Log::info('Processing image ingest', [
                'proxy_uuid' => $proxy->uuid,
                'sequence' => $sequence,
                'temp_disk' => $tempDisk,
                'final_disk' => $finalDisk,
                'temp_path' => $proxy->temp_path,
                'final_path' => $finalPath,
                'final_filename' => $finalFilename,
                'extraction_method' => $proxy->extraction_method ?? 'unknown',
            ]);

            // Verify source file exists
            if (!Storage::disk($tempDisk)->exists($proxy->temp_path)) {
                \Log::error('Source temp file does not exist', [
                    'proxy_uuid' => $proxy->uuid,
                    'temp_disk' => $tempDisk,
                    'temp_path' => $proxy->temp_path,
                ]);
                throw new \Exception("Source file not found: {$proxy->temp_path}");
            }

            // Read file content from temp disk
            $fileContent = Storage::disk($tempDisk)->get($proxy->temp_path);

            \Log::info('Retrieved file content', [
                'proxy_uuid' => $proxy->uuid,
                'file_size' => strlen($fileContent),
            ]);

            // Ensure final path directory exists
            Storage::disk($finalDisk)->makeDirectory($finalPath, 0755, true);

            // Move to final location using put() with file content
            $fullFinalPath = $finalPath . '/' . $finalFilename;
            Storage::disk($finalDisk)->put($fullFinalPath, $fileContent);

            // Verify file was written successfully
            if (!Storage::disk($finalDisk)->exists($fullFinalPath)) {
                \Log::error('Failed to verify written file', [
                    'proxy_uuid' => $proxy->uuid,
                    'final_disk' => $finalDisk,
                    'final_path' => $fullFinalPath,
                ]);
                throw new \Exception("File write failed: {$fullFinalPath}");
            }

            \Log::info('File moved successfully', [
                'proxy_uuid' => $proxy->uuid,
                'final_disk' => $finalDisk,
                'final_path' => $fullFinalPath,
                'file_size' => Storage::disk($finalDisk)->size($fullFinalPath),
            ]);

            // Parse metadata with logging
            $metadata = $proxy->metadata ?? [];
            \Log::info('Parsing metadata', [
                'proxy_uuid' => $proxy->uuid,
                'metadata_keys' => array_keys($metadata),
                'total_keys' => count($metadata),
                'has_normalized_fields' => isset($metadata['date_taken']) || isset($metadata['f_stop']),
            ]);

            // Get GPS coordinates - prefer normalized fields from ExifTool
            $gpsData = $this->extractGpsData($metadata);

            // Create permanent image record
            // Use normalized fields from ExifTool when available, with fallback parsing
            $image = Image::create([
                'file_name' => $finalFilename,
                'file_path' => $fullFinalPath,
                'disk' => $finalDisk,
                'size' => $metadata['file_size'] ?? $metadata['FileSize'] ?? strlen($fileContent),
                'date_taken' => $this->extractDateTaken($metadata),
                'camera_make' => $metadata['camera_make'] ?? $metadata['Make'] ?? null,
                'camera_model' => $metadata['camera_model'] ?? $metadata['Model'] ?? null,
                'lens' => $metadata['lens'] ?? $metadata['LensModel'] ?? null,
                'f_stop' => $this->extractFStop($metadata),
                'iso' => $this->extractISO($metadata),
                'shutter_speed' => $this->extractShutterSpeed($metadata),
                'focal_length' => $this->extractFocalLength($metadata),
                'gps_lat' => $gpsData['lat'],
                'gps_lng' => $gpsData['lng'],
                'raw_metadata' => $proxy->metadata_raw ?? $metadata,
                'imageable_type' => $association['type'] ?? null,
                'imageable_id' => $association['id'] ?? null,
            ]);

            \Log::info('Image record created', [
                'image_id' => $image->id,
                'proxy_uuid' => $proxy->uuid,
                'file_path' => $image->file_path,
                'camera' => $image->camera_make . ' ' . $image->camera_model,
                'date_taken' => $image->date_taken,
            ]);

            // Sync tags from both tags_json (legacy) and tags relationship
            $tagIds = [];
            
            // Get tags from relationship (preferred method)
            if ($proxy->tags()->exists()) {
                $tagIds = $proxy->tags()->pluck('id')->toArray();
            }
            
            // Also include tags from tags_json for backwards compatibility
            if (!empty($proxy->tags_json)) {
                foreach ($proxy->tags_json as $tagName) {
                    $tag = Tag::findOrCreateByName($tagName);
                    if (!in_array($tag->id, $tagIds)) {
                        $tagIds[] = $tag->id;
                    }
                }
            }
            
            if (!empty($tagIds)) {
                $image->tags()->sync($tagIds);

                \Log::info('Tags synced', [
                    'image_id' => $image->id,
                    'tag_count' => count($tagIds),
                ]);
            }

            $this->syncAssetSpineDualWrite(
                proxy: $proxy,
                image: $image,
                metadata: $metadata,
                association: $association,
                tempDisk: $tempDisk,
                finalDisk: $finalDisk,
                finalPath: $finalPath,
                fullFinalPath: $fullFinalPath,
                finalFilename: $finalFilename,
                fileContent: $fileContent,
            );

            // Cleanup temp files
            $this->cleanup($proxy);

            \Log::info('Image ingest completed successfully', [
                'image_id' => $image->id,
                'proxy_uuid' => $proxy->uuid,
            ]);

            return $image;
        } catch (\Exception $e) {
            \Log::error('Image ingest failed', [
                'proxy_uuid' => $proxy->uuid,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Additive Phase 3 path: dual-write canonical asset data without
     * changing legacy ingest-to-gallery behavior.
     */
    protected function syncAssetSpineDualWrite(
        ProxyImage $proxy,
        Image $image,
        array $metadata,
        ?array $association,
        string $tempDisk,
        string $finalDisk,
        string $finalPath,
        string $fullFinalPath,
        string $finalFilename,
        string $fileContent,
    ): void {
        if (!(bool) config('ingest.asset_spine.dual_write', false)) {
            return;
        }

        $failOpen = (bool) config('ingest.asset_spine.fail_open', true);
        $start = microtime(true);
        $telemetry = [
            'telemetry' => 'ingest.asset_spine_dual_write',
            'proxy_uuid' => $proxy->uuid,
            'image_id' => $image->id,
            'temp_disk' => $tempDisk,
            'final_disk' => $finalDisk,
            'final_path' => $fullFinalPath,
            'fail_open' => $failOpen,
        ];

        Log::info('Asset spine dual-write started', $telemetry + [
            'status' => 'started',
        ]);

        try {
            $result = $this->executeAssetSpineDualWrite(
                proxy: $proxy,
                image: $image,
                metadata: $metadata,
                association: $association,
                tempDisk: $tempDisk,
                finalDisk: $finalDisk,
                finalPath: $finalPath,
                fullFinalPath: $fullFinalPath,
                finalFilename: $finalFilename,
                fileContent: $fileContent,
            );

            Log::info('Asset spine dual-write completed', $telemetry + $result + [
                'status' => 'completed',
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
        } catch (\Throwable $e) {
            Log::error('Asset spine dual-write failed', $telemetry + [
                'status' => 'failed',
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            if ($failOpen) {
                report($e);

                return;
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function executeAssetSpineDualWrite(
        ProxyImage $proxy,
        Image $image,
        array $metadata,
        ?array $association,
        string $tempDisk,
        string $finalDisk,
        string $finalPath,
        string $fullFinalPath,
        string $finalFilename,
        string $fileContent,
    ): array {
        if (!$this->assetSpineTablesAvailable()) {
            throw new \RuntimeException('Asset spine tables are not available.');
        }

        if (
            !app()->bound(AssetStorageContract::class)
            || !app()->bound(AssetMetadataRepositoryContract::class)
            || !app()->bound(AssetMetadataNormalizerContract::class)
        ) {
            throw new \RuntimeException('Asset spine services are not bound in the container.');
        }

        /** @var AssetStorageContract $assetStorage */
        $assetStorage = app(AssetStorageContract::class);
        /** @var AssetMetadataRepositoryContract $metadataRepository */
        $metadataRepository = app(AssetMetadataRepositoryContract::class);
        /** @var AssetMetadataNormalizerContract $metadataNormalizer */
        $metadataNormalizer = app(AssetMetadataNormalizerContract::class);

        $mimeType = $this->resolveMimeType($proxy, $metadata, $finalDisk, $fullFinalPath);
        $assetType = $this->resolveAssetType($finalFilename, $mimeType);
        $checksumSha256 = hash('sha256', $fileContent);
        $bytes = (int) ($metadata['file_size'] ?? $metadata['FileSize'] ?? strlen($fileContent));
        $studioId = (string) ($metadata['studio_id'] ?? $proxy->user_id ?? 'default');

        $asset = Asset::query()
            ->where('storage_driver', $finalDisk)
            ->where('storage_key_original', $fullFinalPath)
            ->first();

        $wasCreated = $asset === null;
        $previousChecksum = (string) ($asset?->checksum_sha256 ?? '');
        $previousBytes = (int) ($asset?->bytes ?? 0);

        if ($asset === null) {
            $asset = new Asset();
        }

        $assetMetadata = is_array($asset->metadata) ? $asset->metadata : [];
        $assetMetadata['ingest'] = [
            'proxy_uuid' => $proxy->uuid,
            'image_id' => $image->id,
            'user_id' => $proxy->user_id,
            'legacy_file_path' => $image->file_path,
            'association' => [
                'type' => $image->imageable_type,
                'id' => $image->imageable_id,
            ],
        ];

        $asset->fill([
            'studio_id' => $studioId,
            'organization_id' => isset($metadata['organization_id']) ? (string) $metadata['organization_id'] : null,
            'type' => $assetType->value,
            'original_filename' => $finalFilename,
            'mime_type' => $mimeType,
            'bytes' => $bytes,
            'checksum_sha256' => $checksumSha256,
            'storage_driver' => $finalDisk,
            'storage_key_original' => $fullFinalPath,
            'logical_path' => trim($finalPath, '/'),
            'captured_at' => $this->extractDateTaken($metadata),
            'ingested_at' => now(),
            'status' => 'ready',
            'metadata' => $assetMetadata,
        ]);
        $asset->save();

        $assetId = AssetId::from($asset->id);
        $occurredAt = now()->toISOString();

        if ($wasCreated) {
            event(new AssetCreated(
                assetId: $assetId,
                studioId: $asset->studio_id,
                type: $assetType,
                logicalPath: (string) $asset->logical_path,
                occurredAt: $occurredAt,
            ));
        }

        $storedChanged = $wasCreated
            || $previousChecksum !== (string) $asset->checksum_sha256
            || $previousBytes !== (int) ($asset->bytes ?? 0);

        if ($storedChanged) {
            event(new AssetStored(
                assetId: $assetId,
                storageDriver: (string) $asset->storage_driver,
                storageKeyOriginal: (string) $asset->storage_key_original,
                bytes: (int) ($asset->bytes ?? 0),
                checksumSha256: (string) $asset->checksum_sha256,
                occurredAt: $occurredAt,
            ));
        }

        $rawPayload = (is_array($proxy->metadata_raw) && $proxy->metadata_raw !== [])
            ? $proxy->metadata_raw
            : $metadata;
        $rawBundle = new RawMetadataBundle(
            payload: $rawPayload,
            source: (string) ($proxy->extraction_method ?? 'ingest'),
            toolVersion: $this->extractToolVersion($rawPayload),
            schemaVersion: 'v1',
            hash: $this->hashPayload($rawPayload),
        );

        $provenance = new MetadataProvenance(
            source: (string) ($proxy->extraction_method ?? 'ingest'),
            toolVersion: $this->extractToolVersion($rawPayload),
            recordedAt: $occurredAt,
            context: [
                'proxy_uuid' => $proxy->uuid,
                'image_id' => $image->id,
                'source' => 'ingest-dual-write',
            ],
        );

        $snapshot = $metadataRepository->get($assetId, MetadataScope::BOTH);

        $rawWritten = false;
        if (
            $snapshot->raw === null
            || $snapshot->raw->hash !== $rawBundle->hash
            || $snapshot->raw->source !== $rawBundle->source
        ) {
            $metadataRepository->storeRaw($assetId, $rawBundle, $provenance);
            $rawWritten = true;

            event(new AssetMetadataExtracted(
                assetId: $assetId,
                source: $rawBundle->source,
                extractedAt: $occurredAt,
                occurredAt: $occurredAt,
            ));
        }

        $normalized = $metadataNormalizer->normalize($rawBundle);
        $normalizedHash = $this->hashPayload($normalized->payload);
        $existingNormalizedHash = $snapshot->normalized !== null
            ? $this->hashPayload($snapshot->normalized->payload)
            : null;

        $normalizedWritten = false;
        if (
            $snapshot->normalized === null
            || $snapshot->normalized->schemaVersion !== $normalized->schemaVersion
            || $existingNormalizedHash !== $normalizedHash
        ) {
            $metadataRepository->storeNormalized($assetId, $normalized, $provenance);
            $normalizedWritten = true;

            event(new AssetMetadataNormalized(
                assetId: $assetId,
                schemaVersion: $normalized->schemaVersion,
                normalizedAt: $occurredAt,
                occurredAt: $occurredAt,
            ));
        }

        $generatedDerivatives = [];

        if ($this->registerDerivative(
            asset: $asset,
            assetId: $assetId,
            assetStorage: $assetStorage,
            sourceDisk: $tempDisk,
            sourcePath: $proxy->thumbnail_path,
            derivativeType: DerivativeType::THUMBNAIL,
            proxyUuid: $proxy->uuid,
        )) {
            $generatedDerivatives[] = DerivativeType::THUMBNAIL;
        }

        if ($this->registerDerivative(
            asset: $asset,
            assetId: $assetId,
            assetStorage: $assetStorage,
            sourceDisk: $tempDisk,
            sourcePath: $proxy->preview_path,
            derivativeType: DerivativeType::PREVIEW,
            proxyUuid: $proxy->uuid,
            width: $proxy->preview_width,
        )) {
            $generatedDerivatives[] = DerivativeType::PREVIEW;
        }

        if ($generatedDerivatives !== []) {
            event(new AssetDerivativesGenerated(
                assetId: $assetId,
                derivativeTypes: $generatedDerivatives,
                occurredAt: $occurredAt,
            ));
        }

        $hasOriginal = Storage::disk((string) $asset->storage_driver)->exists((string) $asset->storage_key_original);
        $hasDerivatives = AssetDerivative::query()->where('asset_id', $asset->id)->exists();
        $hasNormalizedMetadata = $snapshot->normalized !== null || $normalizedWritten;

        if ($wasCreated || $rawWritten || $normalizedWritten || $generatedDerivatives !== []) {
            event(new AssetReadyV1(
                assetId: $assetId,
                studioId: $asset->studio_id,
                status: (string) ($asset->status ?? 'ready'),
                hasOriginal: $hasOriginal,
                hasNormalizedMetadata: $hasNormalizedMetadata,
                hasDerivatives: $hasDerivatives,
                occurredAt: $occurredAt,
            ));
        }

        $galleryImageId = $this->syncGalleryImageAssociation(
            association: $association,
            asset: $asset,
            ingestImage: $image,
            proxy: $proxy,
            metadata: $metadata,
            filename: $finalFilename
        );

        return [
            'asset_id' => $asset->id,
            'gallery_image_id' => $galleryImageId,
            'asset_created' => $wasCreated,
            'asset_stored_changed' => $storedChanged,
            'raw_metadata_written' => $rawWritten,
            'normalized_metadata_written' => $normalizedWritten,
            'generated_derivatives' => array_map(
                static fn (DerivativeType $type): string => $type->value,
                $generatedDerivatives
            ),
            'has_original' => $hasOriginal,
            'has_derivatives' => $hasDerivatives,
        ];
    }

    protected function registerDerivative(
        Asset $asset,
        AssetId $assetId,
        AssetStorageContract $assetStorage,
        string $sourceDisk,
        ?string $sourcePath,
        DerivativeType $derivativeType,
        string $proxyUuid,
        ?int $width = null,
        ?int $height = null,
    ): bool {
        if ($sourcePath === null || trim($sourcePath) === '') {
            return false;
        }

        if (!Storage::disk($sourceDisk)->exists($sourcePath)) {
            return false;
        }

        $existingDerivative = AssetDerivative::query()
            ->where('asset_id', $asset->id)
            ->where('type', $derivativeType->value)
            ->first();

        if ($existingDerivative !== null && $assetStorage->exists($assetId, $derivativeType)) {
            return false;
        }

        $temporaryPath = $this->copyDiskFileToTemporaryPath($sourceDisk, $sourcePath);
        if ($temporaryPath === null) {
            throw new \RuntimeException("Failed to create temporary file for derivative: {$sourcePath}");
        }

        try {
            $mimeType = $this->detectMimeTypeFromPath($sourcePath);
            $stored = $assetStorage->putDerivative(
                assetId: $assetId,
                derivativeType: $derivativeType,
                sourcePath: $temporaryPath,
                metadata: [
                    'storage_driver' => (string) $asset->storage_driver,
                    'studio_id' => (string) $asset->studio_id,
                    'mime_type' => $mimeType,
                    'extension' => (string) pathinfo($sourcePath, PATHINFO_EXTENSION),
                    'proxy_uuid' => $proxyUuid,
                    'source_path' => $sourcePath,
                ],
            );
        } finally {
            @unlink($temporaryPath);
        }

        AssetDerivative::query()->updateOrCreate(
            [
                'asset_id' => $asset->id,
                'type' => $derivativeType->value,
            ],
            [
                'storage_key' => $stored->storageKey,
                'mime_type' => $stored->mimeType,
                'bytes' => $stored->bytes,
                'width' => $width,
                'height' => $height,
                'metadata' => [
                    'source_disk' => $sourceDisk,
                    'source_path' => $sourcePath,
                    'proxy_uuid' => $proxyUuid,
                    'telemetry' => 'ingest.asset_spine_dual_write',
                ],
            ]
        );

        return true;
    }

    protected function syncGalleryImageAssociation(
        ?array $association,
        Asset $asset,
        Image $ingestImage,
        ProxyImage $proxy,
        array $metadata,
        string $filename
    ): ?int {
        if (!is_array($association)) {
            return null;
        }

        $targetType = (string) ($association['type'] ?? '');
        $targetId = isset($association['id']) ? (int) $association['id'] : 0;

        if ($targetId <= 0) {
            return null;
        }

        $galleryClass = \ProPhoto\Gallery\Models\Gallery::class;
        $galleryImageClass = \ProPhoto\Gallery\Models\Image::class;

        if (!class_exists($galleryClass) || !class_exists($galleryImageClass)) {
            return null;
        }

        if ($targetType !== $galleryClass) {
            return null;
        }

        if (!Schema::hasTable('images') || !Schema::hasColumn('images', 'asset_id')) {
            Log::warning('Skipped gallery asset association (images.asset_id unavailable)', [
                'telemetry' => 'ingest.asset_spine_dual_write',
                'asset_id' => $asset->id,
                'association_type' => $targetType,
                'association_id' => $targetId,
            ]);

            return null;
        }

        /** @var \ProPhoto\Gallery\Models\Gallery|null $gallery */
        $gallery = $galleryClass::query()->find($targetId);
        if ($gallery === null) {
            return null;
        }

        $existingGalleryImage = $galleryImageClass::query()
            ->where('gallery_id', $gallery->id)
            ->where('asset_id', $asset->id)
            ->first();

        $sortOrder = $existingGalleryImage !== null
            ? (int) ($existingGalleryImage->sort_order ?? 0)
            : ((int) $galleryImageClass::query()
                ->where('gallery_id', $gallery->id)
                ->max('sort_order')) + 1;

        $dimensions = $this->extractDimensions($metadata);
        $imageMetadata = [
            'ingest_proxy_uuid' => $proxy->uuid,
            'ingest_image_id' => $ingestImage->id,
            'source' => 'ingest_asset_spine',
            'storage_driver' => $asset->storage_driver,
            'storage_key_original' => $asset->storage_key_original,
        ];

        /** @var \ProPhoto\Gallery\Models\Image $galleryImage */
        $galleryImage = $galleryImageClass::query()->updateOrCreate(
            [
                'gallery_id' => $gallery->id,
                'asset_id' => $asset->id,
            ],
            [
                'filename' => $filename,
                'original_filename' => $filename,
                'file_size' => (int) ($asset->bytes ?? 0),
                'mime_type' => (string) ($asset->mime_type ?? 'application/octet-stream'),
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'metadata' => $imageMetadata,
                'uploaded_by_user_id' => $proxy->user_id,
                'uploaded_at' => now(),
                'sort_order' => $sortOrder,
            ]
        );

        return (int) $galleryImage->id;
    }

    protected function copyDiskFileToTemporaryPath(string $disk, string $path): ?string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'pp-asset-');
        if ($tmpPath === false) {
            return null;
        }

        $content = Storage::disk($disk)->get($path);
        file_put_contents($tmpPath, $content);

        return $tmpPath;
    }

    protected function assetSpineTablesAvailable(): bool
    {
        if ($this->assetSpineTablesAvailableCache !== null) {
            return $this->assetSpineTablesAvailableCache;
        }

        $tables = config('prophoto-assets.tables', []);
        $requiredTables = [
            (string) ($tables['assets'] ?? 'assets'),
            (string) ($tables['asset_derivatives'] ?? 'asset_derivatives'),
            (string) ($tables['asset_metadata_raw'] ?? 'asset_metadata_raw'),
            (string) ($tables['asset_metadata_normalized'] ?? 'asset_metadata_normalized'),
        ];

        try {
            foreach ($requiredTables as $table) {
                if (!Schema::hasTable($table)) {
                    $this->assetSpineTablesAvailableCache = false;

                    return false;
                }
            }
        } catch (\Throwable $e) {
            $this->assetSpineTablesAvailableCache = false;

            return false;
        }

        $this->assetSpineTablesAvailableCache = true;

        return true;
    }

    protected function resolveMimeType(ProxyImage $proxy, array $metadata, string $disk, string $path): string
    {
        $mimeType = $metadata['mime_type'] ?? $metadata['MIMEType'] ?? $metadata['MimeType'] ?? null;
        if (is_string($mimeType) && trim($mimeType) !== '') {
            return trim($mimeType);
        }

        try {
            $resolved = Storage::disk($disk)->mimeType($path);
            if (is_string($resolved) && trim($resolved) !== '') {
                return $resolved;
            }
        } catch (\Throwable) {
            // Fallback below.
        }

        return $this->detectMimeTypeFromPath($proxy->filename);
    }

    protected function detectMimeTypeFromPath(string $path): string
    {
        return match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'heic', 'heif' => 'image/heic',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            default => 'application/octet-stream',
        };
    }

    protected function resolveAssetType(string $filename, string $mimeType): AssetType
    {
        if (str_starts_with(strtolower($mimeType), 'video/')) {
            return AssetType::VIDEO;
        }

        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => AssetType::JPEG,
            'heic', 'heif' => AssetType::HEIC,
            'png' => AssetType::PNG,
            'raw', 'dng', 'cr2', 'cr3', 'nef', 'arw', 'raf', 'orf', 'rw2' => AssetType::RAW,
            default => AssetType::UNKNOWN,
        };
    }

    /**
     * @return array{width: ?int, height: ?int}
     */
    protected function extractDimensions(array $metadata): array
    {
        $width = $metadata['width'] ?? $metadata['ImageWidth'] ?? null;
        $height = $metadata['height'] ?? $metadata['ImageHeight'] ?? null;

        return [
            'width' => is_numeric($width) ? (int) $width : null,
            'height' => is_numeric($height) ? (int) $height : null,
        ];
    }

    protected function extractToolVersion(array $payload): ?string
    {
        foreach (['tool_version', 'exiftool_version', 'ExifToolVersion'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    protected function hashPayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return hash('sha256', serialize($payload));
        }

        return hash('sha256', $json);
    }

    /**
     * Build the final storage path based on schema config
     */
    protected function buildPath(ProxyImage $proxy, int $sequence): string
    {
        $pattern = $this->schemaConfig['path'] ?? 'images/{date:Y}/{date:m}';

        return $this->replacePlaceholders($pattern, $proxy, $sequence);
    }

    /**
     * Build the final filename based on schema config
     */
    protected function buildFilename(ProxyImage $proxy, int $sequence): string
    {
        $pattern = $this->schemaConfig['filename'] ?? '{sequence}-{original}';
        $filename = $this->replacePlaceholders($pattern, $proxy, $sequence);

        // Ensure we keep the original extension
        $originalExt = pathinfo($proxy->filename, PATHINFO_EXTENSION);
        $newExt = pathinfo($filename, PATHINFO_EXTENSION);

        if (empty($newExt)) {
            $filename .= '.' . $originalExt;
        }

        return $filename;
    }

    /**
     * Replace placeholders in path/filename patterns
     */
    protected function replacePlaceholders(string $pattern, ProxyImage $proxy, int $sequence): string
    {
        $dateTaken = $this->parseDateTime($proxy->metadata) ?? now();

        $replacements = [
            '{original}' => pathinfo($proxy->filename, PATHINFO_FILENAME),
            '{uuid}' => $proxy->uuid,
            '{camera}' => Str::slug($proxy->metadata['Make'] ?? 'unknown'),
            '{model}' => Str::slug($proxy->metadata['Model'] ?? 'unknown'),
        ];

        // Handle special tags
        $projectTag = $proxy->getProjectTag();
        $filenameTag = $proxy->getFilenameTag();
        
        $replacements['{project}'] = $projectTag ? Str::slug($projectTag->name) : '';
        $replacements['{filename}'] = $filenameTag ? Str::slug($filenameTag->name) : '';

        // Handle sequence with padding
        $padding = $this->schemaConfig['sequence_padding'] ?? 3;
        $replacements['{sequence}'] = str_pad($sequence, $padding, '0', STR_PAD_LEFT);

        // Handle date patterns {date:FORMAT}
        $pattern = preg_replace_callback('/\{date:([^}]+)\}/', function ($matches) use ($dateTaken) {
            return $dateTaken->format($matches[1]);
        }, $pattern);

        return str_replace(array_keys($replacements), array_values($replacements), $pattern);
    }

    /**
     * Parse date/time from metadata
     */
    protected function parseDateTime(array $metadata): ?Carbon
    {
        $dateKeys = ['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'];

        foreach ($dateKeys as $key) {
            if (!empty($metadata[$key])) {
                try {
                    return Carbon::parse($metadata[$key]);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Parse aperture value
     */
    protected function parseAperture($value): ?float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        if (is_string($value) && str_contains($value, '/')) {
            [$num, $den] = explode('/', $value);
            return $den > 0 ? round($num / $den, 2) : null;
        }

        return null;
    }

    /**
     * Parse shutter speed value
     */
    protected function parseShutterSpeed($value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && str_contains($value, '/')) {
            [$num, $den] = explode('/', $value);
            return $den > 0 ? $num / $den : null;
        }

        return null;
    }

    /**
     * Parse focal length value
     */
    protected function parseFocalLength($value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            preg_match('/(\d+)/', $value, $matches);
            return isset($matches[1]) ? (int) $matches[1] : null;
        }

        return null;
    }

    /**
     * Parse ISO value
     */
    protected function parseISO($value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            preg_match('/(\d+)/', $value, $matches);
            return isset($matches[1]) ? (int) $matches[1] : null;
        }

        return null;
    }

    /**
     * Extract date taken - prefers normalized field, falls back to parsing
     */
    protected function extractDateTaken(array $metadata): ?Carbon
    {
        // First check for pre-normalized date_taken from ExifTool
        if (!empty($metadata['date_taken'])) {
            try {
                return Carbon::parse($metadata['date_taken']);
            } catch (\Exception $e) {
                // Fall through to legacy parsing
            }
        }

        // Fall back to legacy parsing
        return $this->parseDateTime($metadata);
    }

    /**
     * Extract f-stop - prefers normalized field, falls back to parsing
     */
    protected function extractFStop(array $metadata): ?float
    {
        // First check for pre-normalized f_stop from ExifTool
        if (isset($metadata['f_stop']) && is_numeric($metadata['f_stop'])) {
            return round((float) $metadata['f_stop'], 2);
        }

        // Fall back to legacy parsing
        return $this->parseAperture($metadata['FNumber'] ?? null);
    }

    /**
     * Extract ISO - prefers normalized field, falls back to parsing
     */
    protected function extractISO(array $metadata): ?int
    {
        // First check for pre-normalized iso from ExifTool
        if (isset($metadata['iso']) && is_numeric($metadata['iso'])) {
            return (int) $metadata['iso'];
        }

        // Fall back to legacy parsing
        return $this->parseISO($metadata['ISOSpeedRatings'] ?? $metadata['ISO'] ?? null);
    }

    /**
     * Extract shutter speed - prefers normalized field, falls back to parsing
     */
    protected function extractShutterSpeed(array $metadata): ?float
    {
        // First check for pre-normalized shutter_speed from ExifTool
        if (isset($metadata['shutter_speed']) && is_numeric($metadata['shutter_speed'])) {
            return (float) $metadata['shutter_speed'];
        }

        // Fall back to legacy parsing
        return $this->parseShutterSpeed($metadata['ExposureTime'] ?? null);
    }

    /**
     * Extract focal length - prefers normalized field, falls back to parsing
     */
    protected function extractFocalLength(array $metadata): ?int
    {
        // First check for pre-normalized focal_length from ExifTool
        if (isset($metadata['focal_length']) && is_numeric($metadata['focal_length'])) {
            return (int) $metadata['focal_length'];
        }

        // Fall back to legacy parsing
        return $this->parseFocalLength($metadata['FocalLength'] ?? null);
    }

    /**
     * Extract GPS data - prefers normalized fields, falls back to parsing
     */
    protected function extractGpsData(array $metadata): array
    {
        $result = ['lat' => null, 'lng' => null];

        // First check for pre-normalized GPS from ExifTool
        if (isset($metadata['gps_lat']) && isset($metadata['gps_lng'])) {
            $result['lat'] = (float) $metadata['gps_lat'];
            $result['lng'] = (float) $metadata['gps_lng'];
            return $result;
        }

        // Fall back to legacy parsing
        return $this->parseGpsCoordinates($metadata);
    }

    /**
     * Parse GPS coordinates from EXIF (legacy fallback)
     */
    protected function parseGpsCoordinates(array $metadata): array
    {
        $result = ['lat' => null, 'lng' => null];

        if (empty($metadata['GPSLatitude']) || empty($metadata['GPSLongitude'])) {
            return $result;
        }

        try {
            $lat = $this->gpsToDecimal(
                $metadata['GPSLatitude'],
                $metadata['GPSLatitudeRef'] ?? 'N'
            );

            $lng = $this->gpsToDecimal(
                $metadata['GPSLongitude'],
                $metadata['GPSLongitudeRef'] ?? 'E'
            );

            if ($lat !== null && $lng !== null) {
                $result['lat'] = $lat;
                $result['lng'] = $lng;
            }
        } catch (\Exception $e) {
            \Log::debug('GPS parsing failed', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Convert GPS EXIF format to decimal degrees
     */
    protected function gpsToDecimal($coordinate, string $hemisphere): ?float
    {
        if (is_string($coordinate)) {
            $coordinate = explode(',', $coordinate);
        }

        if (!is_array($coordinate) || count($coordinate) < 3) {
            return null;
        }

        $degrees = $this->evalFraction($coordinate[0]);
        $minutes = $this->evalFraction($coordinate[1]);
        $seconds = $this->evalFraction($coordinate[2]);

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        if (in_array($hemisphere, ['S', 'W'])) {
            $decimal *= -1;
        }

        return round($decimal, 8);
    }

    /**
     * Evaluate EXIF fraction string (e.g., "1/250")
     */
    protected function evalFraction($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && str_contains($value, '/')) {
            [$numerator, $denominator] = explode('/', $value);
            return $denominator > 0 ? (float) $numerator / (float) $denominator : 0;
        }

        return 0;
    }

    /**
     * Cleanup temporary files and proxy record
     */
    protected function cleanup(ProxyImage $proxy): void
    {
        try {
            $tempDisk = $this->storageConfig['temp_disk'] ?? 'local';

            \Log::info('Cleaning up proxy files', [
                'proxy_uuid' => $proxy->uuid,
                'temp_path' => $proxy->temp_path,
                'thumbnail_path' => $proxy->thumbnail_path,
                'preview_path' => $proxy->preview_path,
            ]);

            // Delete temp file
            if (Storage::disk($tempDisk)->exists($proxy->temp_path)) {
                Storage::disk($tempDisk)->delete($proxy->temp_path);
            }

            // Delete thumbnail
            if ($proxy->thumbnail_path && Storage::disk($tempDisk)->exists($proxy->thumbnail_path)) {
                Storage::disk($tempDisk)->delete($proxy->thumbnail_path);
            }

            // Delete preview
            if ($proxy->preview_path && Storage::disk($tempDisk)->exists($proxy->preview_path)) {
                Storage::disk($tempDisk)->delete($proxy->preview_path);
            }

            // Delete proxy record
            $proxy->delete();

            \Log::info('Proxy cleanup completed', [
                'proxy_uuid' => $proxy->uuid,
            ]);
        } catch (\Exception $e) {
            \Log::error('Proxy cleanup failed', [
                'proxy_uuid' => $proxy->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
