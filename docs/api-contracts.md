# ProPhoto API Contracts

Complete interface definitions, event structures, and data transfer objects for cross-package communication.

## Event Definitions

All events defined in `prophoto-contracts/src/Events/` with full namespace path below.

### Asset Pipeline Events

#### 1. AssetCreated
**Namespace:** `ProPhoto\Contracts\Events\Asset\AssetCreated`
**Purpose:** Asset record created in database
**Fired by:** prophoto-assets (AssetCreationService or controller)
**Listeners:** (TBD - may trigger metadata extraction)

```php
class AssetCreated extends Event {
    public function __construct(
        public string $assetId,
        public string $studio_id,
        public string $organization_id,
        public string $originalFilename,
        public string $mimeType,
        public int $bytes,
        public ?datetime $occurred_at = null
    )
}
```

#### 2. AssetStored
**Namespace:** `ProPhoto\Contracts\Events\Asset\AssetStored`
**Purpose:** File successfully stored to disk (S3/local)
**Fired by:** prophoto-assets (AssetCreationService)
**Listeners:** Next in pipeline

```php
class AssetStored extends Event {
    public function __construct(
        public string $assetId,
        public string $storageDriver,
        public string $storageKey,
        public int $bytes,
        public string $checksum_sha256,
        public ?datetime $occurred_at = null
    )
}
```

#### 3. AssetMetadataExtracted
**Namespace:** `ProPhoto\Contracts\Events\Asset\AssetMetadataExtracted`
**Purpose:** Raw metadata (EXIF, IPTC, etc.) extracted from file
**Fired by:** prophoto-assets (metadata extraction service)
**Listeners:** Metadata normalizer

```php
class AssetMetadataExtracted extends Event {
    public function __construct(
        public string $assetId,
        public array $rawMetadata,        // EXIF/IPTC/XMP arrays
        public string $extractorVersion,
        public ?datetime $occurred_at = null
    )
}
```

#### 4. AssetMetadataNormalized
**Namespace:** `ProPhoto\Contracts\Events\Asset\AssetMetadataNormalized`
**Purpose:** Raw metadata normalized to standard schema
**Fired by:** prophoto-assets (metadata normalizer)
**Listeners:** Intelligence services, gallery

```php
class AssetMetadataNormalized extends Event {
    public function __construct(
        public string $assetId,
        public NormalizedAssetMetadata $metadata,
        public ?datetime $occurred_at = null
    )
}
```

#### 5. AssetDerivativesGenerated
**Namespace:** `ProPhoto\Contracts\Events\Asset\AssetDerivativesGenerated`
**Purpose:** Thumbnails, previews, watermarked versions created
**Fired by:** prophoto-assets (derivative generation service)
**Listeners:** Gallery display

```php
class AssetDerivativesGenerated extends Event {
    public function __construct(
        public string $assetId,
        public array $derivatives,       // [DerivativeType => { path, size }, ...]
        public ?datetime $occurred_at = null
    )
}
```

#### 6. AssetReadyV1
**Namespace:** `ProPhoto\Contracts\Events\Asset\AssetReadyV1`
**Purpose:** Asset fully processed and ready for intelligence/gallery
**Fired by:** prophoto-assets (orchestrator)
**Listeners:** prophoto-intelligence (intelligence generators), prophoto-gallery (display)

```php
class AssetReadyV1 extends Event {
    public function __construct(
        public string $assetId,
        public string $studio_id,
        public string $organization_id,
        public NormalizedAssetMetadata $metadata,
        public ?datetime $occurred_at = null
    )
}
```

---

### Intelligence Events

#### 7. AssetIntelligenceRunStarted
**Namespace:** `ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceRunStarted`
**Purpose:** Intelligence generator run initiated for asset
**Fired by:** prophoto-intelligence (IntelligenceExecutionService)
**Listeners:** (logging, monitoring)

```php
class AssetIntelligenceRunStarted extends Event {
    public function __construct(
        public string $assetId,
        public string $runId,
        public string $generatorType,     // 'labels', 'embeddings', etc.
        public ?datetime $occurred_at = null
    )
}
```

#### 8. AssetIntelligenceGenerated
**Namespace:** `ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceGenerated`
**Purpose:** Labels, insights, or classification results generated
**Fired by:** prophoto-intelligence (IntelligencePersistenceService)
**Listeners:** Gallery (display labels), ingest (match by labels)

```php
class AssetIntelligenceGenerated extends Event {
    public function __construct(
        public string $assetId,
        public string $runId,
        public GeneratorResult $result,   // Contains labels, scores, metadata
        public ?datetime $occurred_at = null
    )
}
```

#### 9. AssetEmbeddingUpdated
**Namespace:** `ProPhoto\Contracts\Events\Intelligence\AssetEmbeddingUpdated`
**Purpose:** Vector embedding computed for similarity search
**Fired by:** prophoto-intelligence (IntelligencePersistenceService)
**Listeners:** Gallery (similarity recommendations)

```php
class AssetEmbeddingUpdated extends Event {
    public function __construct(
        public string $assetId,
        public string $runId,
        public EmbeddingResult $embedding, // Vector and metadata
        public ?datetime $occurred_at = null
    )
}
```

---

### Session Matching Events

#### 10. SessionMatchProposalCreated
**Namespace:** `ProPhoto\Contracts\Events\Ingest\SessionMatchProposalCreated`
**Purpose:** Candidate sessions identified for asset
**Fired by:** prophoto-ingest (SessionMatchCandidateGenerator)
**Listeners:** (logging, human review queue)

```php
class SessionMatchProposalCreated extends Event {
    public function __construct(
        public string $ingestItemId,
        public string $assetId,
        public array $candidates,        // [{ sessionId, score, confidence }, ...]
        public ?datetime $occurred_at = null
    )
}
```

#### 11. SessionAutoAssignmentApplied
**Namespace:** `ProPhoto\Contracts\Events\Ingest\SessionAutoAssignmentApplied`
**Purpose:** Asset automatically matched to session (high confidence)
**Fired by:** prophoto-ingest (SessionMatchDecisionClassifier)
**Listeners:** Assets (creates AssetSessionContext)

```php
class SessionAutoAssignmentApplied extends Event {
    public function __construct(
        public string $ingestItemId,
        public string $assetId,
        public string $sessionId,
        public int $confidenceScore,
        public SessionMatchConfidenceTier $confidenceTier,
        public ?datetime $occurred_at = null
    )
}
```

#### 12. SessionManualAssignmentApplied
**Namespace:** `ProPhoto\Contracts\Events\Ingest\SessionManualAssignmentApplied`
**Purpose:** User manually assigned asset to session
**Fired by:** prophoto-ingest (controller or management command)
**Listeners:** Assets (creates AssetSessionContext)

```php
class SessionManualAssignmentApplied extends Event {
    public function __construct(
        public string $ingestItemId,
        public string $assetId,
        public string $sessionId,
        public string $assignedByUserId,
        public ?datetime $occurred_at = null
    )
}
```

#### 13. SessionManualUnassignmentApplied
**Namespace:** `ProPhoto\Contracts\Events\Ingest\SessionManualUnassignmentApplied`
**Purpose:** User removed asset from session
**Fired by:** prophoto-ingest (controller or management command)
**Listeners:** Assets (removes/marks AssetSessionContext)

```php
class SessionManualUnassignmentApplied extends Event {
    public function __construct(
        public string $ingestItemId,
        public string $assetId,
        public string $sessionId,
        public string $unassignedByUserId,
        public ?datetime $occurred_at = null
    )
}
```

#### 14. SessionAssociationResolved
**Namespace:** `ProPhoto\Contracts\Events\Ingest\SessionAssociationResolved`
**Purpose:** Final session assignment decided (auto or manual)
**Fired by:** prophoto-ingest (SessionAssociationWriteService)
**Listeners:** prophoto-assets (creates AssetSessionContext in database)

```php
class SessionAssociationResolved extends Event {
    public function __construct(
        public string $ingestItemId,
        public string $assetId,
        public string $decisionId,
        public SessionAssignmentDecisionType $decisionType, // AUTO_ASSIGN or MANUAL_*
        public string $selectedSessionId,
        public string $subjectType,                         // 'asset', etc.
        public string $subjectId,
        public SessionMatchConfidenceTier $confidenceTier,
        public ?int $confidenceScore,
        public string $algorithmVersion,
        public ?datetime $occurredAt = null
    )
}
```

---

### Local Package Events

#### prophoto-assets: AssetSessionContextAttached
**Namespace:** `ProPhoto\Assets\Events\AssetSessionContextAttached`
**Purpose:** Session context record created in database
**Fired by:** prophoto-assets (HandleSessionAssociationResolved listener)
**Listeners:** Gallery (can now display asset)

```php
class AssetSessionContextAttached {
    public function __construct(
        public string $assetId,
        public string $sessionId,
        public string $sourceDecisionId,
        public string $triggerSource,
        public ?datetime $occurredAt = null
    )
}
```

#### prophoto-ingest: IngestItemCreated
**Namespace:** `ProPhoto\Ingest\Events\IngestItemCreated`
**Purpose:** Ingest item record created
**Fired by:** prophoto-ingest (IngestItemContextBuilder)
**Listeners:** Session matching service

```php
class IngestItemCreated {
    public function __construct(
        public string $ingestItemId,
        public string $uploadBatchId,
        public string $originalFilename,
        public int $fileSize,
        public ?datetime $occurredAt = null
    )
}
```

---

## Contract Interfaces

All contracts in `prophoto-contracts/src/Contracts/`

### Asset Repository Contract

**Namespace:** `ProPhoto\Contracts\Contracts\Asset\AssetRepositoryContract`

```php
interface AssetRepositoryContract {
    /**
     * Find one asset by canonical identifier.
     */
    public function find(AssetId $assetId): ?AssetRecord;

    /**
     * List assets using filter/query criteria.
     * @return list<AssetRecord>
     */
    public function list(AssetQuery $query): array;

    /**
     * Browse assets using drive-like path semantics.
     */
    public function browse(string $prefixPath, ?BrowseOptions $options = null): BrowseResult;
}
```

**Implemented by:** `EloquentAssetRepository` (prophoto-assets)

### Asset Storage Contract

**Namespace:** `ProPhoto\Contracts\Contracts\Asset\AssetStorageContract`

```php
interface AssetStorageContract {
    public function store(string $path, string $contents, array $options = []): StoredObjectRef;
    public function retrieve(string $path): string;
    public function delete(string $path): bool;
    public function exists(string $path): bool;
}
```

**Implemented by:** `LaravelAssetStorage` (prophoto-assets)

### Asset Metadata Repository Contract

**Namespace:** `ProPhoto\Contracts\Contracts\Metadata\AssetMetadataRepositoryContract`

```php
interface AssetMetadataRepositoryContract {
    public function store(AssetId $assetId, NormalizedAssetMetadata $metadata): void;
    public function retrieve(AssetId $assetId): ?NormalizedAssetMetadata;
    public function query(AssetQuery $query): array;
}
```

**Implemented by:** `EloquentAssetMetadataRepository` (prophoto-assets)

### Asset Metadata Extractor Contract

**Namespace:** `ProPhoto\Contracts\Contracts\Metadata\AssetMetadataExtractorContract`

```php
interface AssetMetadataExtractorContract {
    /**
     * Extract raw metadata from asset file.
     */
    public function extract(AssetId $assetId, string $path): RawMetadataBundle;
}
```

**Implemented by:** `NullAssetMetadataExtractor` (prophoto-assets, stub)

### Asset Metadata Normalizer Contract

**Namespace:** `ProPhoto\Contracts\Contracts\Metadata\AssetMetadataNormalizerContract`

```php
interface AssetMetadataNormalizerContract {
    /**
     * Normalize raw metadata to standard schema.
     */
    public function normalize(RawMetadataBundle $raw): NormalizedAssetMetadata;
}
```

**Implemented by:** `PassThroughAssetMetadataNormalizer` (prophoto-assets, no-op)

### Ingest Service Contract

**Namespace:** `ProPhoto\Contracts\Contracts\Ingest\IngestServiceContract`

```php
interface IngestServiceContract {
    /**
     * Queue an asset for ingestion.
     */
    public function queueIngest(IngestRequest $request): string;

    /**
     * Process an ingest job synchronously.
     */
    public function processIngest(IngestRequest $request): IngestResult;

    /**
     * Get the status of an ingest job.
     */
    public function getIngestStatus(string $jobId): string;
}
```

**Implemented by:** (TBD - likely in prophoto-ingest)

### Intelligence Generator Contract

**Namespace:** `ProPhoto\Contracts\Contracts\Intelligence\AssetIntelligenceGeneratorContract`

```php
interface AssetIntelligenceGeneratorContract {
    public function supports(AssetRecord $asset): bool;
    public function generate(AssetRecord $asset): GeneratorResult;
    public function getVersion(): string;
}
```

**Implemented by:** Multiple generators in prophoto-intelligence

### Gallery Repository Contract

**Namespace:** `ProPhoto\Contracts\Contracts\Gallery\GalleryRepositoryContract`

```php
interface GalleryRepositoryContract {
    public function find(GalleryId $id): ?Gallery;
    public function findBySession(string $sessionId): ?Gallery;
    public function list(string $studioId): array;
}
```

**Implemented by:** (TBD - likely in prophoto-gallery)

---

## Data Transfer Objects (DTOs)

All DTOs in `prophoto-contracts/src/DTOs/`

### AssetId
**Purpose:** Strongly-typed asset identifier

```php
class AssetId {
    public function __construct(public string $value) {}
}
```

### AssetRecord
**Purpose:** Complete asset data

```php
class AssetRecord {
    public function __construct(
        public string $id,
        public string $studio_id,
        public string $organization_id,
        public string $type,
        public string $originalFilename,
        public string $mimeType,
        public int $bytes,
        public string $checksum_sha256,
        public string $storageDriver,
        public string $storageKeyOriginal,
        public string $logicalPath,
        public string $status,
        public ?\DateTime $capturedAt,
        public ?\DateTime $ingestedAt,
        public ?NormalizedAssetMetadata $metadata,
    ) {}
}
```

### AssetQuery
**Purpose:** Asset search/filter parameters

```php
class AssetQuery {
    public function __construct(
        public string $studioId,
        public string $organizationId,
        public ?string $type = null,
        public ?string $status = null,
        public ?\DateTime $fromDate = null,
        public ?\DateTime $toDate = null,
        public ?int $limit = null,
        public ?int $offset = null,
    ) {}
}
```

### BrowseOptions
**Purpose:** Asset browsing filters

```php
class BrowseOptions {
    public function __construct(
        public ?int $limit = null,
        public ?string $nextToken = null,
        public ?callable $filter = null,
    ) {}
}
```

### BrowseResult
**Purpose:** Browse result set

```php
class BrowseResult {
    public function __construct(
        public array $entries,           // BrowseEntry[]
        public ?string $nextToken = null,
        public bool $isTruncated = false,
    ) {}
}
```

### IngestRequest
**Purpose:** Ingest request parameters

```php
class IngestRequest {
    public function __construct(
        public string $uploadBatchId,
        public string $filename,
        public string $mimeType,
        public int $fileSize,
        public string $fileContents,
        public string $studioId,
        public string $organizationId,
        public ?string $sessionHint = null,
        public ?\DateTime $capturedAt = null,
    ) {}
}
```

### IngestResult
**Purpose:** Ingest operation result

```php
class IngestResult {
    public function __construct(
        public string $assetId,
        public string $status,           // success, failure, pending
        public ?string $errorMessage = null,
        public array $metadata = [],
    ) {}
}
```

### NormalizedAssetMetadata
**Purpose:** Standardized metadata

```php
class NormalizedAssetMetadata {
    public function __construct(
        public array $exif = [],
        public array $iptc = [],
        public array $xmp = [],
        public ?string $title = null,
        public ?string $description = null,
        public ?string $copyright = null,
        public ?\DateTime $dateCreated = null,
        public ?array $location = null,
        public array $custom = [],
    ) {}
}
```

### IntelligenceRunContext
**Purpose:** Context for intelligence generation

```php
class IntelligenceRunContext {
    public function __construct(
        public string $assetId,
        public string $runId,
        public array $generatorTypes = [],   // ['labels', 'embeddings', ...]
        public ?\DateTime $startedAt = null,
    ) {}
```

### GeneratorResult
**Purpose:** Generator output

```php
class GeneratorResult {
    public function __construct(
        public string $assetId,
        public string $generatorType,
        public array $labels = [],          // [{label, score}, ...]
        public array $metadata = [],
        public ?EmbeddingResult $embedding = null,
    ) {}
}
```

---

## Enums

All enums in `prophoto-contracts/src/Enums/`

### AssetType
```php
enum AssetType: string {
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
}
```

### DerivativeType
```php
enum DerivativeType: string {
    case THUMBNAIL = 'thumbnail';
    case PREVIEW = 'preview';
    case WATERMARKED = 'watermarked';
    case COMPRESSED = 'compressed';
}
```

### IngestStatus
```php
enum IngestStatus: string {
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case FAILED = 'failed';
}
```

### RunStatus
```php
enum RunStatus: string {
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETE = 'complete';
    case FAILED = 'failed';
}
```

### SessionAssignmentDecisionType
```php
enum SessionAssignmentDecisionType: string {
    case AUTO_ASSIGN = 'auto_assign';
    case MANUAL_ASSIGN = 'manual_assign';
    case MANUAL_UNASSIGN = 'manual_unassign';
}
```

### SessionMatchConfidenceTier
```php
enum SessionMatchConfidenceTier: string {
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
}
```

### Ability (Permissions)
```php
enum Ability: string {
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case SHARE = 'share';
}
```

---

## Exception Classes

All exceptions in `prophoto-contracts/src/Exceptions/`

- `AssetNotFoundException` - Asset not found
- `MetadataReadFailedException` - Metadata extraction failed
- `PermissionDeniedException` - Permission check failed

---

## Related Documentation

- [Component Inventory](./component-inventory.md) - All services and models
- [Data Models](./data-models.md) - Database schema for persistence
- [Project Overview](./project-overview.md) - Architecture and patterns
