# Complete File Inventory - ProPhoto Assets Package

## Overview

Complete inventory of all 38 files in the `prophoto-assets` package with signatures, purposes, and implementation details.

## Configuration Files

### composer.json
- **Path**: `/prophoto-assets/composer.json`
- **Lines**: 54
- **Purpose**: Package definition and dependencies
- **Key Dependencies**: `prophoto/contracts`, Laravel 11/12 components
- **Autoload**: PSR-4 `ProPhoto\Assets\` namespace
- **Scripts**: `test` command for PHPUnit

### config/assets.php
- **Path**: `/prophoto-assets/config/assets.php`
- **Purpose**: Package configuration (storage settings, metadata options)

## Service Provider

### AssetServiceProvider.php
- **Path**: `/prophoto-assets/src/AssetServiceProvider.php`
- **Lines**: 63
- **Purpose**: Laravel service provider - registers all contracts and services
- **Key Bindings**:
  - `AssetPathResolverContract` -> `DefaultAssetPathResolver`
  - `SignedUrlGeneratorContract` -> `LaravelSignedUrlGenerator`
  - `AssetStorageContract` -> `LaravelAssetStorage`
  - `AssetRepositoryContract` -> `EloquentAssetRepository`
  - `AssetMetadataExtractorContract` -> `NullAssetMetadataExtractor`
  - `AssetMetadataNormalizerContract` -> `PassThroughAssetMetadataNormalizer`
  - `AssetMetadataRepositoryContract` -> `EloquentAssetMetadataRepository`
  - `AssetCreationService` (singleton)
- **Event Listener**: Registers `HandleSessionAssociationResolved` for `SessionAssociationResolved`
- **Console Command**: Registers `RenormalizeAssetsMetadataCommand`

## Models (5 files)

### Asset.php
- **Path**: `/prophoto-assets/src/Models/Asset.php`
- **Lines**: 54
- **Purpose**: Canonical asset record - the Asset Spine
- **Table**: `assets`
- **Key Fields**: `studio_id`, `type`, `original_filename`, `mime_type`, `bytes`, `checksum_sha256`, `storage_driver`, `storage_key_original`, `logical_path`, `status`, `captured_at`, `ingested_at`, `metadata`
- **Relationships**:
  - `derivatives()` - HasMany to `AssetDerivative`
  - `rawMetadata()` - HasMany to `AssetMetadataRaw`
  - `normalizedMetadata()` - HasMany to `AssetMetadataNormalized`
- **Casts**: `bytes` (integer), `captured_at`/`ingested_at` (datetime), `metadata` (array)

### AssetDerivative.php
- **Path**: `/prophoto-assets/src/Models/AssetDerivative.php`
- **Lines**: 35
- **Purpose**: Processed asset derivatives (thumbnails, resized versions)
- **Table**: `asset_derivatives`
- **Key Fields**: `asset_id`, `type`, `storage_key`, `mime_type`, `bytes`, `width`, `height`, `metadata`
- **Relationships**: `asset()` - BelongsTo `Asset`
- **Casts**: `bytes`/`width`/`height` (integer), `metadata` (array)

### AssetMetadataRaw.php
- **Path**: `/prophoto-assets/src/Models/AssetMetadataRaw.php`
- **Lines**: 33
- **Purpose**: Immutable raw metadata extraction records
- **Table**: `asset_metadata_raw`
- **Key Fields**: `asset_id`, `source`, `tool_version`, `extracted_at`, `payload`, `payload_hash`, `metadata`
- **Relationships**: `asset()` - BelongsTo `Asset`
- **Casts**: `extracted_at` (datetime), `payload`/`metadata` (array)

### AssetMetadataNormalized.php
- **Path**: `/prophoto-assets/src/Models/AssetMetadataNormalized.php`
- **Lines**: 57
- **Purpose**: Queryable normalized metadata projection
- **Table**: `asset_metadata_normalized`
- **Key Fields**: `asset_id`, `schema_version`, `media_kind`, `normalized_at`, `captured_at`, `camera_make`, `camera_model`, `mime_type`, `file_size`, `lens`, `color_profile`, `rating`, `page_count`, `duration_seconds`, `has_gps`, `iso`, `width`, `height`, `exif_orientation`, `payload`, `metadata`
- **Relationships**: `asset()` - BelongsTo `Asset`
- **Casts**: Extensive datetime, integer, float, boolean, and array casts for metadata fields

### AssetSessionContext.php
- **Path**: `/prophoto-assets/src/Models/AssetSessionContext.php`
- **Lines**: 32
- **Purpose**: Asset-to-session association projection table
- **Table**: `asset_session_contexts`
- **Key Fields**: `asset_id`, `session_id`, `source_decision_id`, `decision_type`, `subject_type`, `subject_id`, `ingest_item_id`, `confidence_tier`, `confidence_score`, `algorithm_version`, `occurred_at`
- **Casts**: `asset_id`/`session_id` (integer), `confidence_score` (float), `occurred_at` (datetime)

## Services (6 files)

### AssetCreationService.php
- **Path**: `/prophoto-assets/src/Services/Assets/AssetCreationService.php`
- **Lines**: 197
- **Purpose**: End-to-end asset creation pipeline with metadata processing
- **Key Methods**:
  - `createFromFile(string $sourcePath, array $attributes): Asset` - Main creation method
  - `resolveAssetType(string $filename, string $mimeType): AssetType` - Type detection
  - `detectMimeType(string $sourcePath): string` - MIME type detection
  - `hashPayload(array $payload): string` - Payload hashing
- **Dependencies**: `AssetStorageContract`, `AssetMetadataRepositoryContract`, `AssetMetadataNormalizerContract`
- **Events Emitted**: `AssetCreated`, `AssetStored`, `AssetMetadataExtracted`, `AssetMetadataNormalized`, `AssetReadyV1`
- **Error Handling**: Validates file existence, throws `InvalidArgumentException` for invalid paths

### AssetRegistrar.php
- **Path**: `/prophoto-assets/src/Services/Assets/AssetRegistrar.php`
- **Lines**: 60
- **Purpose**: Simple asset registration without full pipeline
- **Key Methods**: `register(array $attributes): Asset` - Basic asset creation
- **Events Emitted**: `AssetCreated`, `AssetStored`
- **Use Case**: Additive registration that doesn't alter ingest/gallery behavior

### EloquentAssetMetadataRepository.php
- **Path**: `/prophoto-assets/src/Services/Metadata/EloquentAssetMetadataRepository.php`
- **Lines**: 121
- **Purpose**: Metadata persistence and retrieval using Eloquent
- **Key Methods**:
  - `storeRaw(AssetId, RawMetadataBundle, MetadataProvenance): void`
  - `storeNormalized(AssetId, NormalizedAssetMetadata, MetadataProvenance): void`
  - `get(AssetId, MetadataScope): AssetMetadataSnapshot`
- **Implementation**: Uses `updateOrCreate` for normalized metadata to handle schema versions

### PassThroughAssetMetadataNormalizer.php
- **Path**: `/prophoto-assets/src/Services/Metadata/PassThroughAssetMetadataNormalizer.php`
- **Lines**: 457
- **Purpose**: Comprehensive metadata normalization from raw to structured format
- **Key Methods**:
  - `normalize(RawMetadataBundle): NormalizedAssetMetadata` - Main normalization
  - `detectMediaKind(array): string` - Media type detection
  - `firstValue(array, array): mixed` - Value extraction helpers
  - Type conversion helpers: `toString()`, `toInt()`, `toFloat()`, `toBool()`, `parseDate()`
- **Normalization Features**: EXIF, IPTC, XMP, PDF info, video metadata, GPS data
- **Output Structure**: Nested payload with media, camera, exposure, color, GPS, document, video sections

### NullAssetMetadataExtractor.php
- **Path**: `/prophoto-assets/src/Services/Metadata/NullAssetMetadataExtractor.php`
- **Lines**: 30
- **Purpose**: No-op metadata extractor - provides basic file info only
- **Key Methods**:
  - `extract(string $sourcePath): RawMetadataBundle` - Returns basic file metadata
  - `supports(string $mimeType): bool` - Always returns true
- **Use Case**: Default extractor when no specialized extraction is needed

### LaravelAssetStorage.php
- **Path**: `/prophoto-assets/src/Services/Storage/LaravelAssetStorage.php`
- **Lines**: 140
- **Purpose**: Storage abstraction using Laravel's Storage facade
- **Key Methods**:
  - `putOriginal(string, AssetId, array): StoredObjectRef` - Store original file
  - `putDerivative(AssetId, DerivativeType, string, array): StoredObjectRef` - Store derivative
  - `getOriginalStream(AssetId): mixed` - Get original file stream
  - `getDerivativeUrl(AssetId, DerivativeType, array): string` - Get derivative URL
  - `delete(AssetId): void` - Delete asset and derivatives
  - `exists(AssetId, DerivativeType): bool` - Check existence
- **Dependencies**: `AssetPathResolverContract`, `SignedUrlGeneratorContract`

### LaravelSignedUrlGenerator.php
- **Path**: `/prophoto-assets/src/Services/Storage/LaravelSignedUrlGenerator.php`
- **Lines**: 59
- **Purpose**: Generate signed URLs for asset access
- **Key Methods**:
  - `forStorageKey(string, string, DateTimeInterface, array): string` - Storage key URLs
  - `forAssetDerivative(AssetId, DerivativeType, DateTimeInterface, array): string` - Derivative URLs
  - `defaultExpiryFromNow(): DateTimeImmutable` - Default expiry calculation
- **Features**: Falls back to regular URLs if temporary URLs not supported

### DefaultAssetPathResolver.php
- **Path**: `/prophoto-assets/src/Services/Path/DefaultAssetPathResolver.php`
- **Lines**: 62
- **Purpose**: Generate storage paths for assets and derivatives
- **Key Methods**:
  - `originalKey(AssetId, studioId, filename): string` - Original file paths
  - `derivativeKey(AssetId, studioId, DerivativeType, extension): string` - Derivative paths
  - `logicalPath(AssetId, studioId, prefix): string` - Logical paths
- **Path Format**: `{studioId}/assets/{assetId}/original/{safeFilename}`

## Repositories (1 file)

### EloquentAssetRepository.php
- **Path**: `/prophoto-assets/src/Repositories/EloquentAssetRepository.php`
- **Lines**: 149
- **Purpose**: Asset query and browsing implementation
- **Key Methods**:
  - `find(AssetId): ?AssetRecord` - Single asset lookup
  - `list(AssetQuery): array` - Filtered asset listing
  - `browse(string, ?BrowseOptions): BrowseResult` - Directory-style browsing
- **Query Features**: Studio filtering, type filtering, path prefix, status filtering, custom filters
- **Browse Features**: File/folder listing, recursive options, pagination support

## Listeners (1 file)

### HandleSessionAssociationResolved.php
- **Path**: `/prophoto-assets/src/Listeners/HandleSessionAssociationResolved.php`
- **Lines**: 56
- **Purpose**: Consume ingest session association events and persist asset context
- **Key Methods**: `handle(SessionAssociationResolved): void` - Event handler
- **Logic**:
  - Only processes `AUTO_ASSIGN` decisions
  - Validates required fields (asset_id, session_id)
  - Uses `insertOrIgnore` for idempotency
  - Emits `AssetSessionContextAttached` on successful insert
- **Database**: Direct table insert for performance

## Events (1 file)

### AssetSessionContextAttached.php
- **Path**: `/prophoto-assets/src/Events/AssetSessionContextAttached.php`
- **Lines**: 19
- **Purpose**: Emitted when new asset session context is persisted
- **Properties**: `assetId`, `sessionId`, `sourceDecisionId`, `triggerSource`, `occurredAt`
- **Note**: Not emitted for idempotent/replayed no-op attempts

## Console Commands (1 file)

### RenormalizeAssetsMetadataCommand.php
- **Path**: `/prophoto-assets/src/Console/Commands/RenormalizeAssetsMetadataCommand.php`
- **Lines**: 118
- **Purpose**: Rebuild normalized metadata from latest raw records
- **Signature**: `prophoto-assets:renormalize [--asset-id=] [--limit=0] [--dry-run]`
- **Features**: Single asset processing, batch limits, dry-run mode
- **Process**: Finds latest raw metadata, normalizes, stores with provenance

## Migrations (6 files)

### 2026_03_08_000001_create_assets_table.php
- **Lines**: 38
- **Purpose**: Core assets table creation
- **Indexes**: `studio_id`, `organization_id`, `type`, `mime_type`, `checksum_sha256`, `logical_path`, `captured_at`, `ingested_at`, `status`
- **Composite Index**: `idx_assets_studio_checksum` on `(studio_id, checksum_sha256)`

### 2026_03_08_000002_create_asset_derivatives_table.php
- **Lines**: 32
- **Purpose**: Asset derivatives table
- **Foreign Key**: `asset_id` -> `assets.id` (cascade delete)
- **Indexes**: `type`, composite `idx_asset_derivatives_asset_type`

### 2026_03_08_000003_create_asset_metadata_raw_table.php
- **Lines**: 31
- **Purpose**: Raw metadata storage
- **Foreign Key**: `asset_id` -> `assets.id` (cascade delete)
- **Indexes**: `idx_asset_metadata_raw_asset_source` on `(asset_id, source)`

### 2026_03_08_000004_create_asset_metadata_normalized_table.php
- **Lines**: 34
- **Purpose**: Normalized metadata projection
- **Foreign Key**: `asset_id` -> `assets.id` (cascade delete)
- **Indexes**: `schema_version`, `camera_make`, `lens`, `iso`, composite `idx_asset_metadata_normalized_asset_schema`

### 2026_03_09_001100_expand_asset_metadata_normalized_index_columns.php
- **Lines**: 110
- **Purpose**: Add extensive index columns to normalized metadata
- **Added Columns**: `media_kind`, `captured_at`, `mime_type`, `file_size`, `camera_model`, `exif_orientation`, `rating`, `color_profile`, `page_count`, `duration_seconds`, `has_gps`
- **Indexes**: Individual indexes on most added columns

### 2026_04_05_120000_create_asset_session_contexts_table.php
- **Lines**: 38
- **Purpose**: Asset session association projection
- **Foreign Key**: `asset_id` -> `assets.id` (cascade delete)
- **Unique Index**: `source_decision_id`
- **Indexes**: `asset_id`, `session_id`, `decision_type`, `subject_type`

## Tests (8 files)

### AssetCreationServiceTest.php
- **Lines**: 51
- **Purpose**: Test asset creation pipeline
- **Coverage**: File creation, metadata persistence, storage verification

### HandleSessionAssociationResolvedTest.php
- **Lines**: 165
- **Purpose**: Test session association event handling
- **Coverage**: Auto-assign processing, idempotency, decision type filtering, edge cases

### AssetRepositoryTest.php
- **Purpose**: Repository query and browsing functionality

### AssetMetadataLifecycleTest.php
- **Purpose**: Metadata extraction and normalization lifecycle

### AssetMetadataRepositoryTest.php
- **Purpose**: Metadata repository operations

### AssetEventContractShapeTest.php
- **Purpose**: Event contract validation

### MetadataNormalizerSchemaTest.php
- **Purpose**: Metadata normalization schema validation

### RenormalizeAssetsMetadataCommandTest.php
- **Purpose**: Console command functionality

---

*Total files analyzed: 38 files, ~1,200 lines of code*
