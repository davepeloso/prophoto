# Integration Points - ProPhoto Assets Package

## Overview

Complete analysis of integration points, cross-package connections, and architectural boundaries for the assets package.

## Package Position in Architecture

```
prophoto-ingest
      |
      v (SessionAssociationResolved)
prophoto-assets
      |
      v (AssetSessionContextAttached)
prophoto-intelligence
```

The assets package serves as the canonical media repository and sits at the center of the ProPhoto event loop, consuming decisions from ingest and providing context to intelligence.

## Cross-Package Integration

### Upstream Integration (Consumes From)

#### prophoto-ingest
**Integration Type**: Event-driven consumption
**Event**: `SessionAssociationResolved`
**Handler**: `HandleSessionAssociationResolved`
**Purpose**: Consume ingest session association decisions

**Event Flow**:
```php
// Ingest emits
event(new SessionAssociationResolved(
    decisionId: 'decision-auto-123',
    decisionType: SessionAssignmentDecisionType::AUTO_ASSIGN,
    assetId: 456,
    selectedSessionId: 789,
    // ... other fields
));

// Assets consumes
Event::listen(SessionAssociationResolved::class, HandleSessionAssociationResolved::class);
```

**Data Contract**:
- **Input**: Decision metadata from ingest matching algorithm
- **Output**: Asset session context projection
- **Guarantees**: Idempotent processing via unique decision IDs

**Boundary Characteristics**:
- **Direction**: One-way (ingest -> assets)
- **Coupling**: Loose (event-based)
- **Data**: Decision metadata, not full objects
- **Frequency**: Medium (batch processing events)

#### prophoto-contracts
**Integration Type**: Contract dependency
**Purpose**: Shared interfaces, DTOs, enums, and events
**Usage**: All cross-package communication

**Contract Categories**:
```php
// Asset contracts
use ProPhoto\Contracts\Contracts\Asset\AssetRepositoryContract;
use ProPhoto\Contracts\Contracts\Asset\AssetStorageContract;

// Metadata contracts
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataRepositoryContract;

// DTOs
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\AssetQuery;

// Events
use ProPhoto\Contracts\Events\Ingest\SessionAssociationResolved;
```

**Boundary Characteristics**:
- **Direction**: Dependency (assets depends on contracts)
- **Coupling**: Interface-based (acceptable)
- **Evolution**: Breaking changes require coordination

### Downstream Integration (Provides To)

#### prophoto-intelligence
**Integration Type**: Event-driven provision
**Event**: `AssetSessionContextAttached`
**Purpose**: Provide session context for intelligence processing

**Event Flow**:
```php
// Assets emits
Event::dispatch(new AssetSessionContextAttached(
    assetId: $event->assetId,
    sessionId: $event->selectedSessionId,
    sourceDecisionId: $event->decisionId,
    triggerSource: 'asset_session_context',
    occurredAt: $event->occurredAt
));

// Intelligence consumes (hypothetical)
Event::listen(AssetSessionContextAttached::class, IntelligenceHandler::class);
```

**Data Contract**:
- **Input**: Asset and session association
- **Output**: Context for intelligence processing
- **Guarantees**: Only emitted for new associations

**Boundary Characteristics**:
- **Direction**: One-way (assets -> intelligence)
- **Coupling**: Loose (event-based)
- **Data**: Association context, not full objects

#### Future Consumers
**prophoto-gallery**: Asset repository for display
**prophoto-ai**: Asset storage and metadata for AI processing
**prophoto-export**: Asset access for export operations

## Integration Patterns

### Event-Driven Integration

#### Pattern Characteristics
```php
// Clean event listener registration
Event::listen(SessionAssociationResolved::class, HandleSessionAssociationResolved::class);

// Event emission with proper context
event(new AssetSessionContextAttached(
    assetId: $event->assetId,
    sessionId: $event->selectedSessionId,
    sourceDecisionId: $event->decisionId,
    triggerSource: 'asset_session_context',
    occurredAt: $event->occurredAt
));
```

#### Benefits
- **Loose Coupling**: No direct package dependencies
- **Scalability**: Event-driven architecture supports horizontal scaling
- **Reliability**: Event persistence possible for replay
- **Testability**: Events can be faked for testing

#### Considerations
- **Event Ordering**: Consumers must handle out-of-order events
- **Event Volume**: High-frequency operations could overwhelm consumers
- **Event Schema**: Breaking changes require coordinated updates

### Contract-Based Integration

#### Pattern Characteristics
```php
// Contract dependency injection
public function __construct(
    private readonly AssetStorageContract $assetStorage,
    private readonly AssetMetadataRepositoryContract $metadataRepository,
) {}

// Contract implementation registration
$this->app->singleton(AssetStorageContract::class, LaravelAssetStorage::class);
```

#### Benefits
- **Interface Segregation**: Clear contract boundaries
- **Testability**: Easy to mock contracts
- **Flexibility**: Multiple implementations possible
- **Type Safety**: Compile-time contract checking

#### Considerations
- **Contract Evolution**: Breaking changes impact all implementers
- **Implementation Coupling**: Still coupled to contract definitions
- **Version Management**: Contract versioning complexity

### Database Integration

#### Pattern Characteristics
```php
// Foreign key relationships (downstream only)
public function derivatives(): HasMany
{
    return $this->hasMany(AssetDerivative::class);
}

// No upstream relationships (rule compliance)
// Assets does not query other package tables directly
```

#### Benefits
- **Data Integrity**: Referential constraints enforced
- **Performance**: Optimized joins for related data
- **Simplicity**: Clear data ownership

#### Considerations
- **Cross-Package Queries**: Avoided by design
- **Data Duplication**: Some projection tables needed
- **Migration Coordination**: Schema changes require coordination

## Boundary Enforcement

### Architectural Rules Compliance

#### Package Ownership
```php
// Assets owns canonical media truth
class Asset extends Model
{
    // Asset data is owned by assets package
    protected $fillable = [
        'studio_id', 'type', 'original_filename', 
        // ... asset-specific fields
    ];
}

// No cross-package mutation
// Assets never modifies ingest or booking data
```

#### Event-Driven Boundaries
```php
// Proper event consumption
Event::listen(SessionAssociationResolved::class, HandleSessionAssociationResolved::class);

// Proper event emission
event(new AssetSessionContextAttached(...));
```

#### Contract-Based Dependencies
```php
// Only depends on contracts, not concrete implementations
use ProPhoto\Contracts\Contracts\Asset\AssetStorageContract;

// Implementation registered in service provider
$this->app->singleton(AssetStorageContract::class, LaravelAssetStorage::class);
```

### Anti-Pattern Prevention

#### Direct Package Coupling (Avoided)
```php
// WRONG: Direct dependency on other packages
// use ProPhoto\Ingest\Services\SessionMatchingService;

// CORRECT: Event-driven communication
Event::listen(SessionAssociationResolved::class, HandleSessionAssociationResolved::class);
```

#### Cross-Package Database Queries (Avoided)
```php
// WRONG: Query other package tables
// DB::table('ingest_items')->where(...)

// CORRECT: Use events and projections
// asset_session_contexts table provides needed data
```

#### Shared Mutable State (Avoided)
```php
// WRONG: Shared state across packages
// global variables, static caches

// CORRECT: Event-driven state changes
event(new AssetSessionContextAttached(...));
```

## Integration Health Analysis

### Strengths

#### Clear Boundaries
- **Event Isolation**: Clean event-driven communication
- **Contract Adherence**: Proper use of contract interfaces
- **Data Ownership**: Clear ownership of asset data
- **No Circular Dependencies**: Proper one-way dependencies

#### Architectural Compliance
- **Event Loop**: Proper participation in system event loop
- **Package Rules**: Compliance with all architectural rules
- **Storage Ownership**: Clear storage abstraction
- **Metadata Spine**: Proper metadata preservation

#### Scalability
- **Horizontal Scaling**: Event-driven architecture supports scaling
- **Loose Coupling**: Easy to deploy and scale independently
- **Async Processing**: Events enable asynchronous processing

### Areas for Attention

#### Event Volume Management
```php
// Current: Every asset creation emits 5 events
event(new AssetCreated(...));
event(new AssetStored(...));
event(new AssetMetadataExtracted(...));
event(new AssetMetadataNormalized(...));
event(new AssetReadyV1(...));

// Consideration: Could batch events for high-volume scenarios
```

#### Error Handling
```php
// Current: Events are fire-and-forget
event(new AssetSessionContextAttached(...));

// Consideration: Add event failure monitoring
```

#### Event Schema Evolution
```php
// Current: Events are readonly classes
readonly class AssetSessionContextAttached
{
    public function __construct(
        public int|string $assetId,
        public int|string $sessionId,
        // ...
    ) {}
}

// Consideration: Versioned events for breaking changes
```

## Integration Testing

### Event Testing Patterns
```php
// Test event consumption
Event::fakeExcept([SessionAssociationResolved::class]);
$this->app['events']->dispatch($resolvedEvent);

Event::assertDispatched(AssetSessionContextAttached::class, function ($event) {
    return $event->assetId === $expectedAssetId;
});
```

### Contract Testing Patterns
```php
// Test contract compliance
$this->mock(AssetStorageContract::class, function ($mock) {
    $mock->shouldReceive('putOriginal')
         ->once()
         ->andReturn($storedObjectRef);
});
```

### Integration Test Scenarios
- **End-to-end asset creation** with event verification
- **Session association processing** with idempotency testing
- **Contract implementation testing** with multiple implementations
- **Error handling** with event failure simulation

## Future Integration Points

### Potential Consumers
```php
// prophoto-gallery integration
class GalleryAssetService
{
    public function __construct(
        private AssetRepositoryContract $assetRepository,
    ) {}
}

// prophoto-ai integration
class AIAssetProcessor
{
    public function __construct(
        private AssetStorageContract $assetStorage,
        private AssetMetadataRepositoryContract $metadataRepository,
    ) {}
}
```

### Extension Points
- **New Asset Types**: Extend asset type resolution
- **Additional Metadata Sources**: Implement extractor contracts
- **Custom Storage Drivers**: Implement storage contracts
- **Enhanced Event Processing**: Add new event consumers

### Integration Evolution
- **Event Streaming**: Could move to event streaming platform
- **API Integration**: Could expose REST/GraphQL APIs
- **Message Queues**: Could add message queue support
- **Service Mesh**: Could integrate with service mesh

## Integration Metrics

### Event Metrics
- **Events Consumed**: 1 type (`SessionAssociationResolved`)
- **Events Emitted**: 6 types (`AssetCreated`, `AssetStored`, etc.)
- **Event Frequency**: Medium (session associations) to High (asset creation)
- **Event Latency**: Near real-time (same process)

### Contract Metrics
- **Contracts Implemented**: 7 interfaces
- **Contract Dependencies**: 100% contract-based
- **Contract Evolution**: Stable (version 1)
- **Contract Coverage**: All external interactions

### Database Metrics
- **Cross-Package Tables**: 1 (`asset_session_contexts`)
- **Foreign Key Relationships**: 4 (all downstream)
- **Data Duplication**: Minimal (projection pattern)
- **Query Patterns**: Package-scoped only

---

*Integration analysis shows proper architectural boundaries with clean event-driven communication and contract-based dependencies. The assets package serves as a well-behaved participant in the ProPhoto event loop while maintaining clear ownership of canonical media truth.*
