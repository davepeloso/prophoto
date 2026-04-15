# Code Patterns - ProPhoto Assets Package

## Overview

Analysis of recurring code patterns, architectural idioms, and implementation conventions used throughout the assets package.

## Repository Patterns

### Eloquent Repository Pattern

#### Standard Repository Structure
```php
class EloquentAssetRepository implements AssetRepositoryContract
{
    public function find(AssetId $assetId): ?AssetRecord
    {
        $asset = Asset::query()->find($assetId->value);
        return $asset ? $this->mapRecord($asset) : null;
    }

    public function list(AssetQuery $query): array
    {
        $builder = Asset::query();
        
        // Apply filters
        if ($query->studioId !== null) {
            $builder->where('studio_id', (string) $query->studioId);
        }
        
        $assets = $builder->get();
        return $assets->map(fn($asset) => $this->mapRecord($asset))->all();
    }

    private function mapRecord(Asset $asset): AssetRecord
    {
        return new AssetRecord(
            id: AssetId::from($asset->id),
            studioId: $asset->studio_id,
            type: AssetType::tryFrom((string) $asset->type) ?? AssetType::UNKNOWN,
            // ... field mapping
        );
    }
}
```

#### Pattern Characteristics
- **Contract Implementation**: Always implements a contract interface
- **Query Builder**: Uses Laravel's query builder for filtering
- **DTO Mapping**: Maps Eloquent models to immutable DTOs
- **Null Safety**: Returns null for missing records
- **Type Safety**: Uses enums and value objects

#### Benefits
- **Testability**: Easy to mock contracts
- **Flexibility**: Multiple implementations possible
- **Type Safety**: Compile-time type checking
- **Separation**: Clear separation between data and presentation

---

## Service Patterns

### Service Constructor Pattern

#### Standard Service Dependencies
```php
class AssetCreationService
{
    public function __construct(
        private readonly AssetStorageContract $assetStorage,
        private readonly AssetMetadataRepositoryContract $metadataRepository,
        private readonly AssetMetadataNormalizerContract $metadataNormalizer,
    ) {}
}
```

#### Pattern Characteristics
- **Readonly Properties**: Immutable dependency injection
- **Contract Dependencies**: Depends on interfaces, not implementations
- **Constructor Injection**: All dependencies injected via constructor
- **No Service Locator**: Direct injection, no service locator pattern

#### Benefits
- **Immutability**: Dependencies can't be changed after injection
- **Testability**: Easy to inject mock dependencies
- **Explicit Dependencies**: Clear what service needs
- **Type Safety**: Compile-time dependency checking

---

### Service Coordination Pattern

#### End-to-End Service Coordination
```php
public function createFromFile(string $sourcePath, array $attributes = []): Asset
{
    // 1. Validation
    if (!is_file($sourcePath)) {
        throw new \InvalidArgumentException("Source file does not exist: {$sourcePath}");
    }

    // 2. Core entity creation
    $asset = Asset::query()->create([...]);

    // 3. Event emission
    event(new AssetCreated(...));

    // 4. Storage operation
    $storedRef = $this->assetStorage->putOriginal($sourcePath, $assetId, $metadata);

    // 5. Metadata processing
    $rawBundle = $this->metadataExtractor->extract($sourcePath);
    $this->metadataRepository->storeRaw($assetId, $rawBundle, $provenance);

    // 6. Normalization
    $normalized = $this->metadataNormalizer->normalize($rawBundle);
    $this->metadataRepository->storeNormalized($assetId, $normalized, $provenance);

    // 7. Final event emission
    event(new AssetReadyV1(...));

    return $asset;
}
```

#### Pattern Characteristics
- **Sequential Operations**: Clear step-by-step processing
- **Event Boundaries**: Events at key transition points
- **Error Handling**: Validation at start, exceptions for failures
- **Service Coordination**: Coordinates multiple services
- **Atomic Operations**: Either complete success or failure

#### Benefits
- **Clarity**: Clear processing sequence
- **Observability**: Events provide visibility into process
- **Reliability**: Fail-fast with proper error handling
- **Testability**: Each step can be tested independently

---

## Event Patterns

### Event Listener Registration Pattern

#### Service Provider Registration
```php
class AssetServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(SessionAssociationResolved::class, HandleSessionAssociationResolved::class);
    }
}
```

#### Pattern Characteristics
- **Provider Registration**: Events registered in service providers
- **Class-Based Listeners**: Uses dedicated listener classes
- **Event-Handler Mapping**: One listener per event type
- **Boot Method**: Registration happens in boot() method

---

### Event Emission Pattern

#### Structured Event Emission
```php
// Create event with proper structure
$assetId = AssetId::from($asset->id);
$occurredAt = now()->toISOString();

event(new AssetCreated(
    assetId: $assetId,
    studioId: $asset->studio_id,
    type: $assetType,
    logicalPath: (string) $asset->logical_path,
    occurredAt: $occurredAt,
));
```

#### Pattern Characteristics
- **Readonly Events**: Events are immutable readonly classes
- **Typed Properties**: Strong typing for all event fields
- **Timestamps**: All events include occurred_at timestamp
- **ID-Based**: Carries IDs, not full objects
- **ISO 8601**: Consistent timestamp format

#### Benefits
- **Immutability**: Events can't be modified after creation
- **Serialization**: Easy to serialize and store events
- **Decoupling**: No object coupling between packages
- **Debugging**: Clear event structure for debugging

---

### Event Handling Pattern

#### Conditional Event Processing
```php
public function handle(SessionAssociationResolved $event): void
{
    // Early validation
    if ($event->decisionType !== SessionAssignmentDecisionType::AUTO_ASSIGN) {
        return;
    }

    if ($event->assetId === null || $event->selectedSessionId === null) {
        return;
    }

    // Idempotent processing
    $inserted = DB::table('asset_session_contexts')->insertOrIgnore([
        'asset_id' => $event->assetId,
        'session_id' => $event->selectedSessionId,
        'source_decision_id' => (string) $event->decisionId,
        // ... other fields
    ]);

    // Conditional downstream emission
    if ((int) $inserted < 1) {
        return;
    }

    Event::dispatch(new AssetSessionContextAttached(...));
}
```

#### Pattern Characteristics
- **Early Returns**: Validation failures return early
- **Idempotency**: Uses unique constraints for idempotent processing
- **Conditional Emission**: Only emit downstream events on success
- **Database Operations**: Direct database operations for performance
- **Error Resilience**: Graceful handling of edge cases

#### Benefits
- **Performance**: Avoids unnecessary processing
- **Reliability**: Idempotent processing prevents duplicates
- **Clarity**: Clear decision flow
- **Debugging**: Easy to trace processing decisions

---

## Metadata Patterns

### Raw Metadata Pattern

#### Immutable Raw Metadata Storage
```php
class AssetMetadataRaw extends Model
{
    protected $table = 'asset_metadata_raw';
    
    protected $fillable = [
        'asset_id', 'source', 'tool_version', 'extracted_at',
        'payload', 'payload_hash', 'metadata'
    ];

    protected $casts = [
        'extracted_at' => 'datetime',
        'payload' => 'array',
        'metadata' => 'array',
    ];
}
```

#### Pattern Characteristics
- **Append-Only**: Raw metadata never updated, only inserted
- **Source Tracking**: Records extraction source and version
- **Hash Storage**: Stores payload hash for duplicate detection
- **JSON Payload**: Flexible storage for arbitrary metadata
- **Timestamp**: Records when extraction occurred

#### Benefits
- **Audit Trail**: Complete history of extractions
- **Integrity**: Hash-based duplicate detection
- **Flexibility**: Supports any metadata format
- **Traceability**: Clear provenance information

---

### Normalized Metadata Pattern

#### Queryable Normalized Metadata
```php
class AssetMetadataNormalized extends Model
{
    protected $table = 'asset_metadata_normalized';
    
    protected $fillable = [
        'asset_id', 'schema_version', 'media_kind', 'normalized_at',
        'captured_at', 'camera_make', 'camera_model', 'mime_type',
        'file_size', 'width', 'height', 'iso', 'rating',
        // ... many indexed fields
        'payload', 'metadata'
    ];

    protected $casts = [
        'normalized_at' => 'datetime',
        'captured_at' => 'datetime',
        'payload' => 'array',
        'metadata' => 'array',
        'has_gps' => 'boolean',
    ];
}
```

#### Pattern Characteristics
- **Schema Versioning**: Supports multiple schema versions
- **Indexed Fields**: Frequently queried data as columns
- **Update Pattern**: Uses updateOrCreate for schema evolution
- **JSON Payload**: Full normalized data in JSON
- **Extensive Indexing**: Optimized for query performance

#### Benefits
- **Performance**: Fast queries on indexed fields
- **Evolution**: Schema versioning supports changes
- **Completeness**: Full data available in JSON payload
- **Flexibility**: Balance between queryability and flexibility

---

### Metadata Normalization Pattern

#### Comprehensive Normalization Logic
```php
public function normalize(RawMetadataBundle $rawBundle): NormalizedAssetMetadata
{
    $payload = $rawBundle->payload;
    
    // Media kind detection
    $mediaKind = $this->detectMediaKind($payload);
    
    // Captured at resolution
    $capturedAtCandidate = $this->firstValueWithKey($payload, [
        'user_captured_at', 'DateTimeOriginal', 'CreateDate',
        'MediaCreateDate', 'creation_date', 'date_taken',
        'FileModifyDate', 'file_mtime', 'ingested_at',
    ]);
    $capturedAt = $this->parseDate($capturedAtCandidate['value']);
    
    // Technical specifications
    $width = $this->toInt($this->firstValue($payload, ['width', 'ImageWidth', 'ExifImageWidth']));
    $height = $this->toInt($this->firstValue($payload, ['height', 'ImageHeight', 'ExifImageHeight']));
    
    // Build structured payload
    $normalizedPayload = [
        'media_kind' => $mediaKind,
        'captured_at' => $capturedAt,
        'dimensions' => ['width' => $width, 'height' => $height],
        // ... structured sections
    ];

    return new NormalizedAssetMetadata(
        schemaVersion: $schemaVersion,
        payload: $normalizedPayload,
        index: $index,
    );
}
```

#### Pattern Characteristics
- **Multi-Source Resolution**: Tries multiple field names for same data
- **Type Conversion**: Safe conversion with null handling
- **Structured Output**: Organized into logical sections
- **Index Generation**: Creates searchable index fields
- **Fallback Logic**: Graceful degradation when data missing

#### Benefits
- **Robustness**: Handles various metadata formats
- **Consistency**: Standardized output structure
- **Searchability**: Index fields enable efficient queries
- **Flexibility**: Extensible to new metadata types

---

## Storage Patterns

### Storage Abstraction Pattern

#### Contract-Based Storage Interface
```php
interface AssetStorageContract
{
    public function putOriginal(string $sourcePath, AssetId $assetId, array $metadata = []): StoredObjectRef;
    public function putDerivative(AssetId $assetId, DerivativeType $derivativeType, string $sourcePath, array $metadata = []): StoredObjectRef;
    public function getOriginalStream(AssetId $assetId): mixed;
    public function getDerivativeUrl(AssetId $assetId, DerivativeType $derivativeType, array $options = []): string;
    public function delete(AssetId $assetId): void;
    public function exists(AssetId $assetId, DerivativeType $type = DerivativeType::ORIGINAL): bool;
}
```

#### Pattern Characteristics
- **Contract Interface**: Clear interface definition
- **Type Safety**: Strong typing for parameters and returns
- **Abstraction**: Hides storage implementation details
- **Multiple Operations**: Supports various storage operations
- **Return Objects**: Returns structured reference objects

#### Benefits
- **Testability**: Easy to mock for testing
- **Flexibility**: Multiple storage implementations possible
- **Type Safety**: Compile-time type checking
- **Consistency**: Standardized interface across implementations

---

### Path Resolution Pattern

#### Hierarchical Path Generation
```php
public function originalKey(AssetId $assetId, int|string $studioId, string $originalFilename): string
{
    $safeFilename = $this->safeFilename($originalFilename);

    return trim((string) $studioId, '/')
        . '/assets/' . $assetId->toString()
        . '/original/' . $safeFilename;
}

public function derivativeKey(AssetId $assetId, int|string $studioId, DerivativeType $derivativeType, string $extension): string
{
    $safeExt = ltrim(strtolower($extension), '.');

    return trim((string) $studioId, '/')
        . '/assets/' . $assetId->toString()
        . '/derivatives/' . $derivativeType->value
        . '.' . ($safeExt !== '' ? $safeExt : 'bin');
}
```

#### Pattern Characteristics
- **Hierarchical Structure**: Organized by studio and asset
- **Safe Filenames**: Sanitizes filenames for storage safety
- **Consistent Format**: Predictable path structure
- **Type-Specific**: Different patterns for different file types
- **Extensible**: Easy to add new path patterns

#### Benefits
- **Organization**: Logical file organization
- **Safety**: Prevents filename-based attacks
- **Predictability**: Easy to generate and parse paths
- **Scalability**: Supports large numbers of assets

---

## Model Patterns

### Eloquent Model Pattern

#### Standard Model Structure
```php
class Asset extends Model
{
    use HasFactory;

    protected $table = 'assets';

    protected $fillable = [
        'studio_id', 'organization_id', 'type', 'original_filename',
        'mime_type', 'bytes', 'checksum_sha256', 'storage_driver',
        'storage_key_original', 'logical_path', 'status',
        'captured_at', 'ingested_at', 'metadata'
    ];

    protected $casts = [
        'bytes' => 'integer',
        'captured_at' => 'datetime',
        'ingested_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function derivatives(): HasMany
    {
        return $this->hasMany(AssetDerivative::class);
    }

    public function rawMetadata(): HasMany
    {
        return $this->hasMany(AssetMetadataRaw::class);
    }

    public function normalizedMetadata(): HasMany
    {
        return $this->hasMany(AssetMetadataNormalized::class);
    }
}
```

#### Pattern Characteristics
- **Explicit Table**: Always specifies table name
- **Fillable Fields**: Explicit mass-assignment fields
- **Type Casting**: Proper casting for data types
- **Relationships**: Clear relationship definitions
- **Factory Support**: Includes HasFactory trait

#### Benefits
- **Security**: Explicit fillable prevents mass-assignment issues
- **Type Safety**: Casting ensures correct data types
- **Relationships**: Clear data relationships
- **Testability**: Factory support for testing

---

### Relationship Pattern

#### Downstream-Only Relationships
```php
// Assets owns these relationships
public function derivatives(): HasMany
{
    return $this->hasMany(AssetDerivative::class);
}

public function sessionContexts(): HasMany
{
    return $this->hasMany(AssetSessionContext::class);
}

// No upstream relationships (rule compliance)
// Assets does not belong to other package tables
```

#### Pattern Characteristics
- **Downstream Only**: Only has relationships to owned tables
- **Cascade Delete**: Related records deleted when asset deleted
- **No Upstream**: No relationships to other package tables
- **Clear Ownership**: Relationships reflect data ownership

#### Benefits
- **Architectural Compliance**: Follows package ownership rules
- **Data Integrity**: Cascade deletes maintain consistency
- **Performance**: Optimized queries for related data
- **Clarity**: Clear data ownership boundaries

---

## Testing Patterns

### Integration Test Pattern

#### Complete Flow Testing
```php
public function test_it_creates_asset_and_persists_metadata_from_file(): void
{
    // Setup: Create test file
    $tmp = tempnam(sys_get_temp_dir(), 'asset-create-');
    file_put_contents($tmp, 'asset-creation-test');

    // Execute: Use real service
    /** @var AssetCreationService $service */
    $service = $this->app->make(AssetCreationService::class);
    $asset = $service->createFromFile($tmp, [...]);

    // Verify: Check asset creation
    $this->assertSame('ready', $asset->status);
    $this->assertTrue(Storage::disk($asset->storage_driver)->exists($asset->storage_key_original));

    // Verify: Check metadata processing
    $snapshot = $metadataRepository->get(AssetId::from($asset->id), MetadataScope::BOTH);
    $this->assertNotNull($snapshot->raw);
    $this->assertNotNull($snapshot->normalized);

    // Cleanup
    @unlink($tmp);
}
```

#### Pattern Characteristics
- **Real Services**: Uses actual service implementations
- **File Operations**: Uses real file system operations
- **Database Transactions**: Uses database for verification
- **Complete Flow**: Tests entire process from input to output
- **Cleanup**: Proper cleanup of temporary resources

#### Benefits
- **Realism**: Tests actual behavior, not mocks
- **Integration**: Tests service coordination
- **Confidence**: High confidence in working code
- **End-to-End**: Validates complete workflows

---

### Event Testing Pattern

#### Event Verification Testing
```php
public function test_auto_assign_writes_asset_session_context_row(): void
{
    // Setup: Fake events selectively
    Event::fakeExcept([SessionAssociationResolved::class]);

    // Execute: Dispatch event
    $this->app['events']->dispatch($resolvedEvent);

    // Verify: Database state
    $row = DB::table('asset_session_contexts')
        ->where('source_decision_id', 'decision-auto-1')
        ->first();
    $this->assertNotNull($row);

    // Verify: Downstream events
    Event::assertDispatched(AssetSessionContextAttached::class, function ($event) use ($assetId) {
        return (int) $event->assetId === $assetId
            && (int) $event->sessionId === 5001;
    });
}
```

#### Pattern Characteristics
- **Selective Faking**: Fakes some events, allows others
- **Database Verification**: Checks database state changes
- **Event Assertions**: Verifies downstream events
- **Helper Methods**: Uses helpers for test data
- **Multiple Verification**: Checks both state and events

#### Benefits
- **Isolation**: Controls which events are processed
- **Verification**: Complete verification of effects
- **Event Testing**: Validates event emission
- **State Testing**: Validates database changes

---

## Configuration Patterns

### Service Provider Pattern

#### Comprehensive Service Registration
```php
class AssetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Contract bindings
        $this->app->singleton(AssetPathResolverContract::class, DefaultAssetPathResolver::class);
        $this->app->singleton(SignedUrlGeneratorContract::class, LaravelSignedUrlGenerator::class);
        $this->app->singleton(AssetStorageContract::class, LaravelAssetStorage::class);
        $this->app->singleton(AssetRepositoryContract::class, EloquentAssetRepository::class);
        $this->app->singleton(AssetMetadataExtractorContract::class, NullAssetMetadataExtractor::class);
        $this->app->singleton(AssetMetadataNormalizerContract::class, PassThroughAssetMetadataNormalizer::class);
        $this->app->singleton(AssetMetadataRepositoryContract::class, EloquentAssetMetadataRepository::class);
        
        // Service bindings
        $this->app->singleton(AssetCreationService::class);
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register event listeners
        Event::listen(SessionAssociationResolved::class, HandleSessionAssociationResolved::class);

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                RenormalizeAssetsMetadataCommand::class,
            ]);
        }
    }
}
```

#### Pattern Characteristics
- **Contract Bindings**: All contracts bound to implementations
- **Singleton Pattern**: Services registered as singletons
- **Separation**: Register vs Boot method separation
- **Event Registration**: Events registered in boot method
- **Console Commands**: Commands conditionally registered

#### Benefits
- **Dependency Injection**: Proper DI container usage
- **Performance**: Singleton pattern for performance
- **Organization**: Clear organization of bindings
- **Flexibility**: Easy to swap implementations

---

## Error Handling Patterns

### Validation Pattern

#### Input Validation
```php
public function createFromFile(string $sourcePath, array $attributes = []): Asset
{
    if (!is_file($sourcePath)) {
        throw new \InvalidArgumentException("Source file does not exist: {$sourcePath}");
    }

    if (!is_readable($sourcePath)) {
        throw new \InvalidArgumentException("Source file is not readable: {$sourcePath}");
    }

    // Continue with processing...
}
```

#### Pattern Characteristics
- **Early Validation**: Validate inputs at method start
- **Specific Exceptions**: Use appropriate exception types
- **Clear Messages**: Descriptive error messages
- **Fail Fast**: Fail immediately on invalid input

#### Benefits
- **Reliability**: Prevents processing invalid data
- **Debugging**: Clear error messages help debugging
- **Performance**: Avoids processing invalid data
- **Safety**: Prevents security issues

---

### Graceful Degradation Pattern

#### Metadata Processing Resilience
```php
try {
    $normalized = $this->metadataNormalizer->normalize($rawBundle);
    $this->metadataRepository->storeNormalized($assetId, $normalized, $provenance);
} catch (\Throwable $e) {
    // Log error but don't fail asset creation
    logger()->warning('Metadata normalization failed', [
        'asset_id' => $assetId->value,
        'error' => $e->getMessage(),
    ]);
}
```

#### Pattern Characteristics
- **Exception Handling**: Catches and handles exceptions
- **Logging**: Records errors for debugging
- **Continuation**: Continues processing despite failures
- **Graceful**: Non-critical failures don't stop processing

#### Benefits
- **Resilience**: System continues working despite failures
- **Observability**: Errors are logged for monitoring
- **User Experience**: Non-critical issues don't affect users
- **Debugging**: Error logs help identify issues

---

## Summary

The assets package demonstrates consistent, high-quality code patterns that align with Laravel best practices and ProPhoto's architectural principles:

### Key Pattern Strengths
- **Contract-Based Design**: Clear interfaces and dependency injection
- **Event-Driven Architecture**: Proper event boundaries and handling
- **Repository Pattern**: Clean data access abstraction
- **Service Coordination**: Well-orchestrated business logic
- **Test Integration**: Comprehensive integration testing

### Architectural Compliance
- **Package Boundaries**: Clear ownership and no cross-package queries
- **Event Boundaries**: Proper event-driven communication
- **Database Patterns**: Append-only metadata with queryable projections
- **Storage Abstraction**: Clean storage contract implementation

### Code Quality Indicators
- **Type Safety**: Strong typing throughout
- **Immutability**: Readonly properties and events
- **Error Handling**: Proper validation and graceful degradation
- **Documentation**: Clear method signatures and structure

These patterns provide a solid foundation for maintainable, testable, and scalable code that properly implements ProPhoto's disciplined modular monolith architecture.

---

*Code patterns analysis shows consistent, high-quality implementation patterns that align with both Laravel best practices and ProPhoto's specific architectural requirements.*
