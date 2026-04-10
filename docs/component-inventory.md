# ProPhoto Component Inventory

Complete index of all services, repositories, models, events, and contract implementations across all 11 packages.

## Quick Index

- [Services (Business Logic)](#services)
- [Repositories (Data Access)](#repositories)
- [Models (Database Entities)](#models)
- [Events (Event-Driven Communication)](#events)
- [Contracts (Interface Definitions)](#contracts)
- [DTOs (Data Transfer Objects)](#dtos)
- [Enums (Type Definitions)](#enums)
- [Event Listeners](#event-listeners)
- [Cross-Package Dependencies](#cross-package-dependencies)

---

## Services

### prophoto-access (2 services)
- **PermissionService** - Query and grant permissions within organization/studio scope
- **AccessServiceProvider** - Binds access contracts (uses Spatie/laravel-permission)

### prophoto-assets (5 services)
- **AssetCreationService** - Create new asset records
- **EloquentAssetRepository** - Implements AssetRepositoryContract (find, list, browse)
- **EloquentAssetMetadataRepository** - Implements AssetMetadataRepositoryContract
- **NullAssetMetadataExtractor** - Implements AssetMetadataExtractorContract (stub)
- **PassThroughAssetMetadataNormalizer** - Implements AssetMetadataNormalizerContract (no-op)
- **DefaultAssetPathResolver** - Implements AssetPathResolverContract
- **LaravelAssetStorage** - Implements AssetStorageContract (S3/local disk)
- **LaravelSignedUrlGenerator** - Implements SignedUrlGeneratorContract

### prophoto-gallery (0 explicit services)
- Gallery management logic in models and policies

### prophoto-booking (1+ services)
- Calendar sync logic (Google Calendar integration)
- Session/booking management

### prophoto-ingest (7 services)
- **SessionMatchingService** - Orchestrator for session matching flow
- **IngestItemContextBuilder** - Build context for ingest items
- **IngestItemSessionMatchingFlowService** - End-to-end matching flow
- **SessionAssociationWriteService** - Persist session associations
- **BatchUploadRecognitionService** - Process batch uploads
- **SessionMatchCandidateGenerator** - Generate match candidates from sessions
- **SessionMatchScoringService** - Score candidates using algorithm
- **SessionMatchDecisionClassifier** - Classify match decision
- **SessionAssignmentRepository** - Query/persist assignments
- **SessionAssignmentDecisionRepository** - Query/persist decisions

### prophoto-intelligence (3 services)
- **IntelligenceExecutionService** - Execute generators on assets
- **IntelligencePersistenceService** - Store results (labels, embeddings)
- **IntelligenceRunRepository** - Query intelligence runs
- **Generator Registry** - Pluggable intelligence generators

### prophoto-interactions (0 explicit services)
- Minimal, interaction tracking in models

### prophoto-ai (3+ services)
- **AiTrainingService** - Train AI models
- **AiGenerationService** - Generate portraits from trained models
- **QuotaTrackingService** - Manage per-studio quotas

### prophoto-invoicing (2 services)
- **InvoiceGenerationService** - Create invoice records
- **PdfExportService** - Generate PDF using dompdf

### prophoto-notifications (2 services)
- **NotificationService** - Send notifications
- **TemplateEngine** - Render email templates

### prophoto-contracts (0 services)
- Contracts only, no implementation

---

## Repositories

### Data Access Classes

**prophoto-assets**
- `EloquentAssetRepository` - Queries: find(AssetId), list(AssetQuery), browse(path)

**prophoto-ingest**
- `SessionAssignmentRepository` - Stores session→asset assignments
- `SessionAssignmentDecisionRepository` - Stores matching decisions

**prophoto-intelligence**
- `IntelligenceRunRepository` - Stores intelligence run records

**prophoto-contracts** (Interfaces)
- `AssetRepositoryContract` - Define repository behavior
- `AssetMetadataRepositoryContract`
- `AssetLabelRepositoryContract`
- `AssetEmbeddingRepositoryContract`
- `GalleryRepositoryContract`

---

## Models

### prophoto-access (4 models)
- `Organization` - Top-level tenant container
- `Studio` - Studio within organization (many-to-one)
- `PermissionContext` - Scoped permission boundaries
- `OrganizationDocument` - Org-level documents

**Relationships:**
- Organization → Studios (one-to-many)
- All models include organization_id, studio_id (multi-tenancy)

### prophoto-assets (5 models)
- `Asset` - Media asset (file store, metadata, checksums)
- `AssetMetadataRaw` - Extracted EXIF/metadata
- `AssetMetadataNormalized` - Normalized metadata view
- `AssetDerivative` - Resized/processed versions
- `AssetSessionContext` - Session→Asset associations

**Relationships:**
- Asset → AssetMetadataRaw (one-to-many)
- Asset → AssetMetadataNormalized (one-to-many)
- Asset → AssetDerivative (one-to-many)
- Asset → AssetSessionContext (one-to-many)

### prophoto-booking (2 models)
- `Session` - Photo session (scheduled date, location, rate)
- `BookingRequest` - Booking request from client

**Relationships:**
- Session → Studio (belongs-to)
- Session → Organization (belongs-to)
- Session → Gallery (one-to-one)
- Session → BookingRequest (implied)

### prophoto-gallery (9 models)
- `Gallery` - Gallery container (links to session)
- `Image` - Individual image in gallery (links to asset)
- `ImageVersion` - Image variations (watermarked, etc.)
- `ImageTag` - Tagging on images
- `GalleryCollection` - Collections within gallery
- `GalleryShare` - Sharing tokens and metadata
- `GalleryTemplate` - Gallery style templates
- `GalleryComment` - Client comments on images
- `GalleryAccessLog` - Access tracking

**Relationships:**
- Gallery → Session (belongs-to, session_id)
- Image → Asset (foreign key: asset_id)
- Image → Gallery (belongs-to)
- GalleryCollection → Gallery (belongs-to)

### prophoto-interactions (1 model)
- `ImageInteraction` - Ratings, approvals, comments

### prophoto-invoicing (3 models)
- `Invoice` - Invoice record
- `InvoiceItem` - Line item
- `CustomFee` - Custom charges

### prophoto-ai (3 models)
- `AiGeneration` - AI training run
- `AiGenerationRequest` - Portrait generation request
- `AiGeneratedPortrait` - Generated portrait record

### prophoto-notifications (1 model)
- `Message` - Email log (tracking delivery)

### prophoto-intelligence (0 explicit models)
- Uses implicit models defined via contracts (AssetLabel, AssetEmbedding)

---

## Events

All event definitions in prophoto-contracts/src/Events/

### Asset Pipeline Events (6)
1. `AssetCreated` - Asset record created
2. `AssetStored` - File stored to disk
3. `AssetMetadataExtracted` - Raw metadata extracted
4. `AssetMetadataNormalized` - Metadata normalized
5. `AssetDerivativesGenerated` - Resized versions created
6. `AssetReadyV1` - Asset fully processed, ready for intelligence

### Intelligence Events (3)
1. `AssetIntelligenceRunStarted` - Generator run initiated
2. `AssetIntelligenceGenerated` - Labels/insights generated
3. `AssetEmbeddingUpdated` - Embeddings computed

### Session Matching Events (5)
1. `SessionMatchProposalCreated` - Candidate matches proposed
2. `SessionAutoAssignmentApplied` - Auto-assignment to session
3. `SessionManualAssignmentApplied` - User manually assigned
4. `SessionManualUnassignmentApplied` - User unassigned
5. `SessionAssociationResolved` - Final assignment decided

### Local Events

**prophoto-assets/src/Events/**
- `AssetSessionContextAttached` - Session context attached (internal)

**prophoto-ingest/src/Events/**
- `IngestItemCreated` - Ingest item created

---

## Contracts

All interface definitions in prophoto-contracts/src/Contracts/

### Asset Contracts (5)
- `AssetRepositoryContract` - Query/browse assets (find, list, browse)
- `AssetStorageContract` - File storage operations
- `AssetPathResolverContract` - Path resolution logic
- `SignedUrlGeneratorContract` - Generate signed URLs

### Metadata Contracts (3)
- `AssetMetadataRepositoryContract` - Query/store metadata
- `AssetMetadataExtractorContract` - Extract metadata from files
- `AssetMetadataNormalizerContract` - Normalize raw metadata

### Ingest Contracts (1)
- `IngestServiceContract` - Queue and process ingests

### Gallery Contracts (1)
- `GalleryRepositoryContract` - Gallery queries

### Intelligence Contracts (3)
- `AssetIntelligenceGeneratorContract` - Generator interface
- `AssetLabelRepositoryContract` - Store/query labels
- `AssetEmbeddingRepositoryContract` - Store/query embeddings

### Access Contracts (1)
- `AccessPolicyContract` - Permission checking

---

## DTOs

All DTOs in prophoto-contracts/src/DTOs/

### Asset DTOs (8)
- `AssetId` - Asset identifier
- `AssetRecord` - Complete asset data
- `AssetQuery` - Query filter parameters
- `AssetMetadata` - Metadata structure
- `StoredObjectRef` - Storage reference

### Browse DTOs (3)
- `BrowseOptions` - Browse filters
- `BrowseEntry` - Single entry
- `BrowseResult` - Browse result set

### Ingest DTOs (2)
- `IngestRequest` - Upload request
- `IngestResult` - Ingest completion result

### Gallery DTOs (1)
- `GalleryId` - Gallery identifier

### Intelligence DTOs (4)
- `IntelligenceRunContext` - Run context
- `SessionContextSnapshot` - Session snapshot
- `LabelResult` - Label generation result
- `EmbeddingResult` - Embedding result

### Permission DTOs (1)
- `PermissionDecision` - Permission decision

### Metadata DTOs (5)
- `RawMetadataBundle` - Extracted EXIF
- `NormalizedAssetMetadata` - Normalized view
- `AssetMetadataSnapshot` - Snapshot in time
- `MetadataProvenance` - Provenance tracking

---

## Enums

All enums in prophoto-contracts/src/Enums/

### Asset Enums (2)
- `AssetType` - Image, Video, Audio, etc.
- `DerivativeType` - Thumbnail, Preview, Watermark, etc.

### Processing Enums (2)
- `IngestStatus` - Status of ingest job
- `MetadataScope` - EXIF, IPTC, etc.

### Intelligence Enums (4)
- `RunStatus` - Pending, Running, Complete, Failed
- `RunScope` - What to generate for
- `SessionContextReliability` - Confidence level
- `SessionMatchConfidenceTier` - Match quality tier

### Session Assignment Enums (6)
- `SessionAssignmentDecisionType` - Auto vs. Manual assignment
- `SessionAssignmentMode` - Assignment mode
- `SessionAssignmentLockState` - Lock status
- `SessionAssignmentLockEffect` - Effect of lock
- `SessionAssociationSource` - Origin of assignment
- `SessionAssociationSubjectType` - Subject type (asset, etc.)

### Permission Enums (1)
- `Ability` - Permission abilities (view, edit, delete, etc.)

---

## Event Listeners

### Registered Listeners

**prophoto-assets/AssetServiceProvider.php**
```php
Event::listen(
    SessionAssociationResolved::class,
    HandleSessionAssociationResolved::class
);
```
- **Handler:** `prophoto-assets/src/Listeners/HandleSessionAssociationResolved.php`
- **Action:** Creates AssetSessionContext when session assignment resolved
- **Emits:** `AssetSessionContextAttached` event

### Unregistered Listeners (To Verify)
- Intelligence service may listen to `AssetReadyV1` (verify in boot method)
- Ingest service may publish events (verify in services)

---

## Cross-Package Dependencies

### Direct Dependencies (What Each Package Imports)

```
prophoto-contracts
  ← prophoto-access (imports Ability enum, contracts)
  ← prophoto-assets (imports all asset contracts, events)
  ← prophoto-booking (imports contracts)
  ← prophoto-gallery (imports Gallery contract)
  ← prophoto-ingest (imports Ingest contract, events)
  ← prophoto-intelligence (imports Intelligence contracts, events)
  ← prophoto-interactions (imports contracts)
  ← prophoto-ai (imports contracts)
  ← prophoto-invoicing (imports contracts)
  ← prophoto-notifications (imports contracts)

prophoto-assets
  ← prophoto-gallery (imports for asset_id FK)
  ← prophoto-ingest (session matching depends on assets)
  ← prophoto-intelligence (runs generators on assets)
  ← prophoto-ai (reads assets for training)

prophoto-gallery
  ← prophoto-assets (asset references)
  ← prophoto-access (studio/org scoping)
  ← prophoto-interactions (image interactions)
  ← prophoto-ai (AI generation requests)

prophoto-booking
  ← (imported by: ingest, gallery)

prophoto-ingest
  ← prophoto-assets (session matching on assets)
  ← prophoto-booking (session model)
  ← prophoto-contracts (event definitions)

prophoto-intelligence
  ← prophoto-assets (generator input)
  ← prophoto-contracts (event definitions)

prophoto-ai
  ← prophoto-gallery (gallery context)

prophoto-access
  ← (imported by gallery, all models)

prophoto-interactions
  ← prophoto-gallery (gallery context)

prophoto-invoicing
  ← (imported by: none explicit)

prophoto-notifications
  ← (imported by: none explicit)
```

### Event Flow Dependencies

**Upload → Asset Creation → Intelligence**
```
Ingest (processes file)
  → Assets (creates Asset, emits AssetReadyV1)
  → Intelligence (listens to AssetReadyV1, generates labels/embeddings)
  → Events published: AssetIntelligenceGenerated, AssetEmbeddingUpdated
```

**Session Matching → Asset Association**
```
Ingest (matches session)
  → emits SessionAssociationResolved
  → Assets (listens, creates AssetSessionContext)
  → emits AssetSessionContextAttached
  → Gallery (can now display asset)
```

**Gallery → Client Access**
```
Booking (creates Session)
  → Gallery (creates Gallery for session)
  → Assets (associated via AssetSessionContext)
  → Interactions (clients rate/comment)
  → Notifications (sends updates)
```

---

## Service Binding Summary

| Package | Contract Interface | Implementation | Scope |
|---------|-------------------|----------------|-------|
| assets | AssetRepositoryContract | EloquentAssetRepository | singleton |
| assets | AssetStorageContract | LaravelAssetStorage | singleton |
| assets | AssetPathResolverContract | DefaultAssetPathResolver | singleton |
| assets | SignedUrlGeneratorContract | LaravelSignedUrlGenerator | singleton |
| assets | AssetMetadataRepositoryContract | EloquentAssetMetadataRepository | singleton |
| assets | AssetMetadataExtractorContract | NullAssetMetadataExtractor | singleton |
| assets | AssetMetadataNormalizerContract | PassThroughAssetMetadataNormalizer | singleton |
| ingest | SessionAssignmentRepository | SessionAssignmentRepository | singleton |
| ingest | SessionAssignmentDecisionRepository | SessionAssignmentDecisionRepository | singleton |
| ingest | SessionMatchingService | SessionMatchingService | singleton |
| intelligence | IntelligenceRunRepository | IntelligenceRunRepository | singleton |
| intelligence | IntelligenceExecutionService | IntelligenceExecutionService | singleton |
| intelligence | IntelligencePersistenceService | IntelligencePersistenceService | singleton |

---

## Test Coverage by Component

### Well Tested (8+ test files)
- prophoto-assets (9 tests)
- prophoto-contracts (8 tests)
- prophoto-ingest (9 tests)
- prophoto-intelligence (13 tests)

### Not Tested (0 test files)
- prophoto-access
- prophoto-ai
- prophoto-gallery
- prophoto-interactions
- prophoto-invoicing
- prophoto-notifications
- prophoto-booking

**Total Gap:** 7 packages without test coverage

---

## Related Documentation

- [Project Overview](./project-overview.md) - Architecture and patterns
- [Source Tree Analysis](./source-tree-analysis.md) - File locations
- [API Contracts](./api-contracts.md) - Event definitions and interfaces
- [Data Models](./data-models.md) - Database schema
