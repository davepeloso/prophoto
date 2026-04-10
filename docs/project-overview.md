# ProPhoto Project Overview

## Executive Summary

ProPhoto is a Laravel-based modular monolith designed to manage professional photography workflows. It handles the complete lifecycle from booking through gallery delivery, including asset management, access control, AI-powered intelligence, and client-facing gallery sharing.

**Architecture:** 11 interdependent Laravel packages  
**Primary Pattern:** Event-driven modular monolith  
**Core Principle:** Contracts-first interface definition enabling package independence

## Strategic Architecture

### System Goals
1. **Modularity:** Each functional domain (assets, booking, galleries, AI) operates as independent package
2. **Scalability:** Event-driven communication allows packages to scale independently
3. **Flexibility:** Contract-based interfaces support multiple implementations
4. **Testability:** Clear boundaries between packages enable comprehensive testing

### Architecture Diagram (Simplified)

```
┌─────────────────────────────────────────────────┐
│         CLIENT APPLICATIONS / UI               │
├─────────────────────────────────────────────────┤
│  Routes & Controllers (per package)            │
├─────────────────────────────────────────────────┤
│   Service Layer (business logic)               │
│  ┌──────────────────────────────────────────┐ │
│  │  Access   │ Booking │ Ingest │ Gallery   │ │
│  │  Invoicing│   AI    │ Intell.│Notif.    │ │
│  └──────────────────────────────────────────┘ │
├─────────────────────────────────────────────────┤
│  Event Bus (Laravel Events)                    │
│  ↓ AsyncQueue (jobs, listeners)               │
├─────────────────────────────────────────────────┤
│   Repository Layer (Eloquent Models)          │
│  Assets │ Galleries │ Sessions │ Permissions  │
├─────────────────────────────────────────────────┤
│   Database (normalized schema per domain)     │
└─────────────────────────────────────────────────┘
```

### Core Dependencies (Dependency Chain)

**Foundation (No Dependencies)**
- `prophoto-contracts` - Shared interfaces, DTOs, enums, event definitions

**Package Dependencies (All → contracts)**
```
contracts (foundation)
  ↑
  ├── access (RBAC)
  ├── assets (media repository)
  ├── gallery (← depends on: access, assets)
  ├── booking (session model)
  ├── ingest (← depends on: assets, booking)
  ├── intelligence (← depends on: assets)
  ├── interactions (← depends on: gallery)
  ├── ai (← depends on: gallery)
  ├── invoicing
  └── notifications
```

**Critical Integration Path**
1. **Booking** creates Session
2. **Ingest** processes uploads → matches Session → creates IngestItems
3. **Assets** stores files → extracts/normalizes metadata
4. **Intelligence** runs generators on Assets (labels, embeddings)
5. **Gallery** packages Assets into shared view
6. **Interactions** enable client ratings/comments
7. **Invoicing** tracks work
8. **Notifications** delivers updates

## Technology Stack

### Core Framework
- **Laravel 11.0+** - Web application framework
- **PHP 8.2+** - Language requirement
- **Illuminate Components** - Database, events, mail, notifications, filesystem
- **Spatie Laravel Permission** - RBAC implementation (in access package)

### Data & Persistence
- **Eloquent ORM** - All packages use Illuminate/database
- **Migrations** - Per-package database changes (42 total migrations)
- **Relationships** - HasMany, BelongsTo, HasOne patterns extensively used

### Testing & Quality
- **PHPUnit 11.0** - Primary testing framework
- **Pest 2.0** - Alternative testing framework (booking, invoicing, notifications use)
- **Test Coverage:** Varies by package (8-13 tests in core packages, 0 in some)

### External Integrations
- **Google Calendar API** - Calendar sync (prophoto-booking)
- **File Storage** - Laravel filesystem abstraction (S3, local disk)
- **Laravel Queue** - For async ingest/intelligence jobs

## Package Descriptions

### Foundational

**prophoto-contracts**
- Type: PHP Library (no Laravel dependencies)
- Purpose: Shared interfaces, DTOs, enums, events
- Key exports: AssetRepositoryContract, IngestServiceContract, event classes
- Responsibility: Contract definitions only, no implementation

### Access & Permission

**prophoto-access**
- Type: Laravel Package
- Dependencies: Spatie/laravel-permission, contracts
- Purpose: RBAC and authorization layer
- Key models: Organization, Studio, PermissionContext
- Patterns: Uses Spatie's Role/Permission system

### Media Management

**prophoto-assets**
- Type: Laravel Package
- Dependencies: contracts
- Purpose: Canonical media asset repository
- Key models: Asset, AssetMetadata*, AssetDerivative, AssetSessionContext
- Patterns: Event listeners for metadata extraction, session association
- Migrations: 6 (assets, metadata, derivatives, session context)

**prophoto-gallery**
- Type: Laravel Package  
- Dependencies: contracts, access, assets
- Purpose: Gallery management, client-facing views
- Key models: Gallery, Image, GalleryCollection, GalleryShare, GalleryTemplate
- Patterns: Policies for authorization, magic link tokens
- Migrations: 15 (most complex schema)

### Booking & Ingest

**prophoto-booking**
- Type: Laravel Package
- Dependencies: contracts, Google Calendar API
- Purpose: Booking workflow and session management
- Key models: Session (photo_sessions table), BookingRequest
- Patterns: Calendar sync, conflict detection
- Migrations: 2 (basic session model)

**prophoto-ingest**
- Type: Laravel Package
- Dependencies: contracts, assets, booking
- Purpose: Upload ingestion and session auto-matching
- Key services: SessionMatchingService, IngestItemSessionMatchingFlowService, SessionAssociationWriteService
- Patterns: Multi-step matching with scoring algorithms
- Repositories: SessionAssignmentRepository, SessionAssignmentDecisionRepository
- Migrations: 2 (assignment tracking)

### Intelligence & AI

**prophoto-intelligence**
- Type: Laravel Package
- Dependencies: contracts, assets
- Purpose: Derived intelligence run orchestration
- Key services: IntelligenceExecutionService, IntelligencePersistenceService
- Key models: Implicit (no explicit models file, uses contracts)
- Patterns: Generator registry, async execution
- Repositories: IntelligenceRunRepository
- Migrations: 3 (intelligence runs, results)

**prophoto-ai**
- Type: Laravel Package
- Dependencies: contracts, gallery
- Purpose: AI model training and image generation
- Key models: AiGeneration, AiGenerationRequest, AiGeneratedPortrait
- Patterns: Model training, quota tracking, cost tracking
- Migrations: 3 (AI models and requests)

### Features

**prophoto-interactions**
- Type: Laravel Package
- Dependencies: contracts, gallery
- Purpose: Image ratings, comments, approvals
- Key models: ImageInteraction
- Patterns: Client interaction tracking
- Migrations: 1

**prophoto-invoicing**
- Type: Laravel Package
- Dependencies: contracts
- Purpose: Invoice generation and tracking
- Key models: Invoice, InvoiceItem, CustomFee
- Dependencies: barryvdh/laravel-dompdf for PDF generation
- Migrations: 3

### Infrastructure

**prophoto-notifications**
- Type: Laravel Package
- Dependencies: contracts
- Purpose: Email notifications and templates
- Key models: Message
- Patterns: Async email sending
- Migrations: 1

## Event-Driven Communication Patterns

### Asset Pipeline Events
```
AssetCreated 
  → AssetStored 
  → AssetMetadataExtracted 
  → AssetMetadataNormalized 
  → AssetDerivativesGenerated 
  → AssetReadyV1
```

### Session Matching Events
```
IngestItemCreated 
  → SessionAutoAssignmentApplied (or)
  → SessionMatchProposalCreated 
  → SessionManualAssignmentApplied 
  → SessionAssociationResolved 
  → AssetSessionContextAttached
```

### Intelligence Events
```
AssetReadyV1 (or)
AssetIntelligenceRunStarted 
  → AssetIntelligenceGenerated 
  → AssetEmbeddingUpdated
```

## Service Binding Pattern

All packages follow Laravel service provider pattern:

```php
class PackageServiceProvider extends ServiceProvider {
    public function register(): void {
        // Bind contracts to implementations
        $this->app->singleton(ContractInterface::class, Implementation::class);
    }
    
    public function boot(): void {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        Event::listen(EventClass::class, ListenerClass::class); // if needed
    }
}
```

## Data Flow Examples

### Example 1: Upload Processing
```
1. File uploaded → creates IngestRequest
2. IngestServiceProvider queues/processes upload
3. File stored in S3/local → AssetStored event
4. Metadata extracted → AssetMetadataExtracted event
5. Metadata normalized → AssetMetadataNormalized event
6. Asset ready → AssetReadyV1 event
7. Intelligence services listen for AssetReadyV1
8. Generators create labels/embeddings
```

### Example 2: Session Auto-Assignment
```
1. Asset matches multiple Sessions by metadata/date
2. SessionMatchingService scores candidates
3. Highest score → SessionAutoAssignmentApplied
4. Asset linked to Session → SessionAssociationResolved event
5. Assets service listens, creates AssetSessionContext
6. Gallery can now display asset in session gallery
```

## Scalability Considerations

### Horizontal Scaling
- Event listeners can be offloaded to queue workers
- Database reads can use replicas
- Assets stored in distributed storage (S3)
- Intelligence generation can be distributed

### Performance
- Asset metadata normalized once, cached
- Session matching scoring cached
- Gallery queries indexed on studio_id, organization_id
- Intelligence runs stored for reuse

## Security Model

### Multi-Tenancy
- Studio-scoped resources (all models include studio_id)
- Organization-scoped access (organization_id on key models)
- RBAC enforced via Spatie permissions + custom policies

### Access Control
- Gallery access via access_code (public link) or magic_link
- API protected via standard Laravel auth
- Session-scoped data isolation

## Deployment Architecture

### Service Distribution Options
1. **Monolithic** - All packages in single Laravel app
2. **Modular** - Each package as separate Laravel app with shared database
3. **Microservices** - Packages as separate services with messaging

Currently deployed as **monolithic** (all packages in one Laravel app).

## Known Technical Debt

### Test Coverage Gaps
- 0 tests: access, ai, gallery, interactions, invoicing, notifications, booking
- Full tests: assets (9), contracts (8), ingest (9), intelligence (13)
- **Gap:** 7 packages without test suite

### Event Registration
- Asset service registers SessionAssociationResolved listener
- Ingest, Intelligence services may lack event listener registration (verify in boot methods)

### Documentation Gaps
- Some complex services (SessionMatchingService) lack inline documentation
- Intelligence generator registry pattern not fully documented

## Future Considerations

### Planned Enhancements
- Comprehensive test coverage for all packages
- Event listener registration verification
- API documentation (openapi/swagger)
- Performance optimization for large galleries

### Modularity Options
- Extract intelligence generation to separate service
- Make AI training optional/pluggable
- Support multiple metadata extractors

## Related Documentation

- [Source Tree Analysis](./source-tree-analysis.md) - Code organization
- [Component Inventory](./component-inventory.md) - All services/models
- [API Contracts](./api-contracts.md) - Event and interface definitions
- [Data Models](./data-models.md) - Complete database schema
- Individual package docs in `/docs/architecture/PACKAGE-*.md`
