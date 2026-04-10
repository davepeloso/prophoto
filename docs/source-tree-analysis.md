# ProPhoto Source Tree Analysis

Directory structure, critical paths, and file organization for all 11 packages.

## Overall Project Structure

```
/sessions/dazzling-adoring-bell/mnt/prophoto/
├── AGENTS.md                          # AI agent definitions
├── RULES.md                           # Project rules & constraints
├── SYSTEM.md                          # System design overview
├── TODO.md                            # Development tasks
├── docs/                              # Documentation (this folder)
├── prophoto-access/                   # RBAC package
├── prophoto-ai/                       # AI orchestration
├── prophoto-assets/                   # Media asset repository
├── prophoto-booking/                  # Booking workflow
├── prophoto-contracts/                # Shared interfaces & DTOs
├── prophoto-gallery/                  # Gallery management
├── prophoto-ingest/                   # Upload ingestion
├── prophoto-intelligence/             # Intelligence generation
├── prophoto-interactions/             # Image interactions
├── prophoto-invoicing/                # Invoice management
├── prophoto-notifications/            # Notifications
├── .claude/                           # Claude skills & config
├── .gemini/                           # Gemini config
└── _bmad/                             # BMAD documentation templates
```

## Package Structure Template

All 11 packages follow this structure:

```
prophoto-{name}/
├── composer.json                      # PHP dependencies
├── phpunit.xml or pest.xml           # Test configuration
├── src/
│   ├── {Package}ServiceProvider.php   # Service provider (Laravel packages)
│   ├── Models/                        # Eloquent models
│   ├── Services/                      # Business logic
│   ├── Repositories/                  # Data access
│   ├── Listeners/                     # Event listeners
│   ├── Events/                        # Event classes
│   ├── Console/
│   │   └── Commands/                  # CLI commands
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Resources/
│   ├── Contracts/                     # (contracts package only)
│   ├── DTOs/                          # Data transfer objects
│   ├── Enums/                         # Enumerations
│   └── Exceptions/
├── database/
│   ├── migrations/                    # Schema migrations
│   └── seeders/                       # Test data
├── tests/                             # Test suites
├── config/                            # Package configuration
└── routes/                            # API routes (if applicable)
```

## Package-by-Package Directory Breakdown

### prophoto-contracts

**Type:** PHP Library (shared foundation)  
**No Service Provider:** Contracts only, no Laravel integration

```
prophoto-contracts/
├── src/
│   ├── Contracts/
│   │   ├── Access/
│   │   │   └── AccessPolicyContract.php
│   │   ├── Asset/
│   │   │   ├── AssetRepositoryContract.php
│   │   │   ├── AssetStorageContract.php
│   │   │   ├── AssetPathResolverContract.php
│   │   │   └── SignedUrlGeneratorContract.php
│   │   ├── Metadata/
│   │   │   ├── AssetMetadataRepositoryContract.php
│   │   │   ├── AssetMetadataExtractorContract.php
│   │   │   ├── AssetMetadataNormalizerContract.php
│   │   │   └── MetadataReaderContract.php
│   │   ├── Ingest/
│   │   │   └── IngestServiceContract.php
│   │   ├── Gallery/
│   │   │   └── GalleryRepositoryContract.php
│   │   └── Intelligence/
│   │       ├── AssetIntelligenceGeneratorContract.php
│   │       ├── AssetLabelRepositoryContract.php
│   │       └── AssetEmbeddingRepositoryContract.php
│   ├── DTOs/
│   │   ├── AssetId.php
│   │   ├── AssetMetadata.php
│   │   ├── AssetQuery.php
│   │   ├── AssetRecord.php
│   │   ├── AssetSessionContext.php
│   │   ├── BrowseOptions.php
│   │   ├── BrowseResult.php
│   │   ├── GalleryId.php
│   │   ├── IngestRequest.php
│   │   ├── IngestResult.php
│   │   ├── StoredObjectRef.php
│   │   ├── IntelligenceRunContext.php
│   │   ├── SessionContextSnapshot.php
│   │   ├── LabelResult.php
│   │   ├── EmbeddingResult.php
│   │   ├── GeneratorResult.php
│   │   └── PermissionDecision.php
│   ├── Enums/
│   │   ├── Ability.php              # Permission abilities
│   │   ├── AssetType.php
│   │   ├── DerivativeType.php
│   │   ├── IngestStatus.php
│   │   ├── MetadataScope.php
│   │   ├── RunStatus.php
│   │   ├── RunScope.php
│   │   ├── SessionContextReliability.php
│   │   ├── SessionAssociationSource.php
│   │   ├── SessionAssignmentDecisionType.php
│   │   ├── SessionAssignmentMode.php
│   │   ├── SessionAssignmentLockState.php
│   │   ├── SessionAssignmentLockEffect.php
│   │   ├── SessionAssociationLockState.php
│   │   ├── SessionMatchConfidenceTier.php
│   │   └── SessionAssociationSubjectType.php
│   ├── Events/
│   │   ├── Asset/
│   │   │   ├── AssetCreated.php
│   │   │   ├── AssetStored.php
│   │   │   ├── AssetMetadataExtracted.php
│   │   │   ├── AssetMetadataNormalized.php
│   │   │   ├── AssetDerivativesGenerated.php
│   │   │   └── AssetReadyV1.php
│   │   ├── Intelligence/
│   │   │   ├── AssetIntelligenceRunStarted.php
│   │   │   ├── AssetIntelligenceGenerated.php
│   │   │   └── AssetEmbeddingUpdated.php
│   │   └── Ingest/
│   │       ├── SessionMatchProposalCreated.php
│   │       ├── SessionAutoAssignmentApplied.php
│   │       ├── SessionManualAssignmentApplied.php
│   │       ├── SessionManualUnassignmentApplied.php
│   │       └── SessionAssociationResolved.php
│   └── Exceptions/
│       ├── AssetNotFoundException.php
│       ├── MetadataReadFailedException.php
│       └── PermissionDeniedException.php
└── tests/                             # 8 test files
```

**Critical Files:**
- `Contracts/Asset/*.php` - Asset repository interface
- `DTOs/*.php` - Data structures (65+ DTO classes)
- `Events/*/*.php` - Event definitions (15+ event classes)
- `Enums/*.php` - Type definitions (18+ enums)

### prophoto-access

**Type:** Laravel Package  
**Responsibility:** RBAC, permissions, organization/studio structure

```
prophoto-access/
├── src/
│   ├── AccessServiceProvider.php      # Service bindings
│   ├── Models/
│   │   ├── Organization.php           # Top-level organization
│   │   ├── Studio.php                 # Studio within organization
│   │   ├── OrganizationDocument.php   # Organization-level docs
│   │   └── PermissionContext.php      # Permission boundaries
│   ├── Services/
│   │   └── PermissionService.php      # Permission query/grant logic
│   ├── Policies/                      # Authorization policies
│   ├── Console/
│   │   └── Commands/                  # Setup commands
│   └── Helpers/
│       └── helpers.php                # Helper functions
├── database/migrations/               # 6 migrations
│   ├── 2024_xx_xx_create_organizations_table.php
│   ├── 2024_xx_xx_create_studios_table.php
│   ├── 2024_xx_xx_create_roles_table.php
│   ├── 2024_xx_xx_create_permissions_table.php
│   ├── 2024_xx_xx_create_role_has_permissions_table.php
│   └── 2024_xx_xx_create_model_has_roles_table.php
├── config/
│   └── access.php                     # Configuration
├── tests/                             # 0 test files (GAP)
└── composer.json
```

**Key Integration Points:**
- All models include `studio_id` and `organization_id` (multi-tenancy)
- Uses Spatie/laravel-permission for role/permission system
- PermissionService handles authorization logic

### prophoto-assets

**Type:** Laravel Package  
**Responsibility:** Canonical media asset repository, metadata management

```
prophoto-assets/
├── src/
│   ├── AssetServiceProvider.php       # Bindings (6):
│   │                                  # - AssetRepositoryContract
│   │                                  # - AssetStorageContract
│   │                                  # - AssetPathResolverContract
│   │                                  # - SignedUrlGeneratorContract
│   │                                  # - AssetMetadataRepositoryContract
│   │                                  # - AssetCreationService
│   ├── Models/
│   │   ├── Asset.php                  # Main asset model
│   │   ├── AssetMetadataRaw.php       # Extracted metadata
│   │   ├── AssetMetadataNormalized.php# Normalized metadata
│   │   ├── AssetDerivative.php        # Resized/processed versions
│   │   └── AssetSessionContext.php    # Session associations
│   ├── Services/
│   │   ├── Assets/
│   │   │   └── AssetCreationService.php # Create new assets
│   │   ├── Metadata/
│   │   │   ├── EloquentAssetMetadataRepository.php
│   │   │   ├── NullAssetMetadataExtractor.php (default)
│   │   │   └── PassThroughAssetMetadataNormalizer.php
│   │   ├── Path/
│   │   │   └── DefaultAssetPathResolver.php # Path logic
│   │   └── Storage/
│   │       ├── LaravelAssetStorage.php
│   │       └── LaravelSignedUrlGenerator.php
│   ├── Repositories/
│   │   └── EloquentAssetRepository.php # Query/browse assets
│   ├── Listeners/
│   │   └── HandleSessionAssociationResolved.php # Listens to ingest events
│   ├── Events/
│   │   └── AssetSessionContextAttached.php
│   ├── Console/
│   │   └── Commands/
│   │       └── RenormalizeAssetsMetadataCommand.php
│   ├── Http/
│   │   └── Controllers/
│   └── Exceptions/
├── database/migrations/               # 6 migrations
│   ├── 2024_xx_xx_create_assets_table.php
│   ├── 2024_xx_xx_create_asset_metadata_raw_table.php
│   ├── 2024_xx_xx_create_asset_metadata_normalized_table.php
│   ├── 2024_xx_xx_create_asset_derivatives_table.php
│   ├── 2024_xx_xx_create_asset_session_contexts_table.php
│   └── 2024_xx_xx_create_indexes.php
├── config/
│   └── assets.php                     # Storage drivers, paths
├── tests/                             # 9 test files (GOOD)
│   ├── Unit/AssetRepositoryTest.php
│   ├── Unit/MetadataTest.php
│   ├── Feature/AssetCreationTest.php
│   └── ...
└── composer.json
```

**Key Points:**
- Event listener for `SessionAssociationResolved` (from ingest)
- Repositories implement contracts/Asset interfaces
- Metadata extraction is pluggable (currently null)

### prophoto-gallery

**Type:** Laravel Package  
**Responsibility:** Gallery creation, management, sharing, client views

```
prophoto-gallery/
├── src/
│   ├── GalleryServiceProvider.php     # No contract bindings
│   │                                  # Loads migrations, routes, views, policies
│   ├── Models/
│   │   ├── Gallery.php                # Main gallery model
│   │   ├── Image.php                  # Images in gallery
│   │   ├── ImageVersion.php           # Image variations
│   │   ├── ImageTag.php               # Image tagging
│   │   ├── GalleryCollection.php      # Collections within gallery
│   │   ├── GalleryShare.php           # Sharing with clients
│   │   ├── GalleryTemplate.php        # Gallery templates
│   │   ├── GalleryComment.php         # Client comments
│   │   └── GalleryAccessLog.php       # Access tracking
│   ├── Policies/
│   │   ├── GalleryCollectionPolicy.php # Registered with Gate
│   │   ├── GallerySharePolicy.php
│   │   └── GalleryTemplatePolicy.php
│   ├── Http/
│   │   └── Controllers/               # API endpoints
│   ├── Console/
│   │   └── Commands/
│   │       └── BackfillGalleryImageAssetIdsCommand.php
│   ├── resources/
│   │   └── views/                     # Gallery templates
│   ├── routes/
│   │   └── api.php                    # Gallery routes
│   └── Exceptions/
├── database/migrations/               # 15 migrations (most complex)
│   ├── 2024_xx_xx_create_galleries_table.php
│   ├── 2024_xx_xx_create_images_table.php
│   ├── 2024_xx_xx_create_image_versions_table.php
│   ├── 2024_xx_xx_create_image_tags_table.php
│   ├── 2024_xx_xx_create_gallery_collections_table.php
│   ├── 2024_xx_xx_create_gallery_shares_table.php
│   ├── 2024_xx_xx_create_gallery_templates_table.php
│   ├── 2024_xx_xx_create_gallery_comments_table.php
│   ├── 2024_xx_xx_create_gallery_access_logs_table.php
│   └── ... (indexes, constraints)
├── config/
│   └── gallery.php
├── tests/                             # 0 test files (GAP)
└── composer.json
```

**Key Relationships:**
- Gallery → Session (one-to-one via session_id)
- Image → Asset (via asset_id, links to prophoto-assets)
- GalleryShare → magic link tokens for client access

### prophoto-booking

**Type:** Laravel Package  
**Responsibility:** Photo session booking, scheduling, calendar sync

```
prophoto-booking/
├── src/
│   ├── BookingServiceProvider.php     # No contract bindings
│   ├── Models/
│   │   ├── Session.php                # Photo session (photo_sessions table)
│   │   └── BookingRequest.php         # Booking requests
│   ├── Services/
│   │   └── (Calendar sync logic)
│   ├── Listeners/
│   │   └── (Calendar event handlers)
│   ├── Http/
│   │   └── Controllers/
│   ├── Console/
│   │   └── Commands/
│   │       └── SyncCalendarCommand.php # Google Calendar sync
│   └── Exceptions/
├── database/migrations/               # 2 migrations
│   ├── 2024_xx_xx_create_photo_sessions_table.php
│   └── 2024_xx_xx_create_booking_requests_table.php
├── config/
│   └── booking.php
├── tests/                             # 0 test files (CRITICAL GAP)
└── composer.json
```

**Key Integration:**
- Session model is core (used by gallery, ingest, intelligence)
- Google Calendar API integration for scheduling
- No event publishing visible (may emit during booking lifecycle)

### prophoto-ingest

**Type:** Laravel Package  
**Responsibility:** Upload processing, session auto-matching, metadata extraction

```
prophoto-ingest/
├── src/
│   ├── IngestServiceProvider.php      # 10 service bindings:
│   │                                  # Repos: SessionAssignmentRepository,
│   │                                  #        SessionAssignmentDecisionRepository
│   │                                  # Services: IngestItemContextBuilder,
│   │                                  #           BatchUploadRecognitionService,
│   │                                  #           SessionAssociationWriteService,
│   │                                  #           SessionMatchingService,
│   │                                  #           IngestItemSessionMatchingFlowService
│   │                                  # Matching: SessionMatchCandidateGenerator,
│   │                                  #           SessionMatchScoringService,
│   │                                  #           SessionMatchDecisionClassifier
│   ├── Services/
│   │   ├── IngestItemContextBuilder.php
│   │   ├── IngestItemSessionMatchingFlowService.php
│   │   ├── SessionMatchingService.php # Orchestrator
│   │   ├── BatchUploadRecognitionService.php
│   │   ├── SessionAssociationWriteService.php
│   │   └── Matching/
│   │       ├── SessionMatchCandidateGenerator.php # Find candidates
│   │       ├── SessionMatchScoringService.php     # Score candidates
│   │       └── SessionMatchDecisionClassifier.php # Classify decision
│   ├── Repositories/
│   │   ├── SessionAssignmentRepository.php
│   │   └── SessionAssignmentDecisionRepository.php
│   ├── Events/
│   │   └── IngestItemCreated.php
│   ├── Models/
│   │   └── (Uses contracts models)
│   ├── Console/
│   │   └── Commands/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Requests/
│   └── Exceptions/
├── database/migrations/               # 2 migrations
│   ├── 2024_xx_xx_create_session_assignments_table.php
│   └── 2024_xx_xx_create_session_assignment_decisions_table.php
├── config/
│   └── ingest.php                     # Matching thresholds
├── tests/                             # 9 test files (GOOD)
│   ├── Unit/MatchingServiceTest.php
│   ├── Unit/ScoringServiceTest.php
│   ├── Feature/IngestFlowTest.php
│   └── ...
└── composer.json
```

**Key Responsibilities:**
- Receives uploads (likely from HTTP controller)
- Runs session matching algorithm (candidate generation → scoring → decision)
- Publishes `SessionAssociationResolved` event for assets to listen
- 3-step matching service architecture

### prophoto-intelligence

**Type:** Laravel Package  
**Responsibility:** Derived intelligence generation (labels, embeddings, AI analysis)

```
prophoto-intelligence/
├── src/
│   ├── IntelligenceServiceProvider.php # 3 service bindings:
│   │                                   # Repo: IntelligenceRunRepository
│   │                                   # Services: IntelligenceExecutionService,
│   │                                   #           IntelligencePersistenceService
│   ├── Orchestration/
│   │   ├── IntelligenceExecutionService.php # Run generators
│   │   └── IntelligencePersistenceService.php # Store results
│   ├── Generators/
│   │   ├── AssetLabelGenerator.php
│   │   ├── AssetEmbeddingGenerator.php
│   │   └── (Custom generators)
│   ├── Repositories/
│   │   └── IntelligenceRunRepository.php # Query runs
│   ├── Listeners/
│   │   └── (AssetReadyV1 listener - may be missing)
│   ├── Console/
│   │   └── Commands/
│   │       └── GenerateIntelligenceCommand.php
│   └── Exceptions/
├── database/migrations/               # 3 migrations
│   ├── 2024_xx_xx_create_intelligence_runs_table.php
│   ├── 2024_xx_xx_create_asset_labels_table.php
│   └── 2024_xx_xx_create_asset_embeddings_table.php
├── config/
│   └── intelligence.php
├── tests/                             # 13 test files (GOOD)
│   ├── Unit/ExecutionServiceTest.php
│   ├── Unit/GeneratorTest.php
│   ├── Feature/IntelligenceRunTest.php
│   └── ...
└── composer.json
```

**Key Pattern:**
- Generator registry (pluggable intelligence sources)
- Async execution via queue
- Publishes `AssetIntelligenceGenerated`, `AssetEmbeddingUpdated` events

### prophoto-interactions

**Type:** Laravel Package  
**Responsibility:** Image interactions (ratings, approvals, comments)

```
prophoto-interactions/
├── src/
│   ├── InteractionsServiceProvider.php # No specific bindings
│   ├── Models/
│   │   └── ImageInteraction.php        # Ratings, approvals, comments
│   ├── Http/
│   │   └── Controllers/
│   ├── Services/
│   └── Exceptions/
├── database/migrations/               # 1 migration
│   └── 2024_xx_xx_create_image_interactions_table.php
├── config/
│   └── interactions.php
├── tests/                             # 0 test files (GAP)
└── composer.json
```

**Lightweight:** Minimal, depends on gallery, captures client feedback

### prophoto-ai

**Type:** Laravel Package  
**Responsibility:** AI model training, portrait generation, quota tracking

```
prophoto-ai/
├── src/
│   ├── AIServiceProvider.php           # No specific bindings
│   ├── Models/
│   │   ├── AiGeneration.php            # AI training runs
│   │   ├── AiGenerationRequest.php     # Portrait generation requests
│   │   └── AiGeneratedPortrait.php     # Generated portrait models
│   ├── Services/
│   │   ├── AiTrainingService.php
│   │   ├── AiGenerationService.php
│   │   └── QuotaTrackingService.php
│   ├── Http/
│   │   └── Controllers/
│   ├── Console/
│   │   └── Commands/
│   └── Jobs/
│       ├── TrainAiModelJob.php
│       └── GeneratePortraitJob.php
├── database/migrations/               # 3 migrations
│   ├── 2024_xx_xx_create_ai_generations_table.php
│   ├── 2024_xx_xx_create_ai_generation_requests_table.php
│   └── 2024_xx_xx_create_ai_generated_portraits_table.php
├── config/
│   └── ai.php                         # Model configs, quotas
├── tests/                             # 0 test files (GAP)
└── composer.json
```

**Key Features:**
- Async training via queue jobs
- Quota management per studio
- Cost tracking for AI services
- Depends on gallery (needs gallery context)

### prophoto-invoicing

**Type:** Laravel Package  
**Responsibility:** Invoice generation, line items, custom fees

```
prophoto-invoicing/
├── src/
│   ├── InvoicingServiceProvider.php    # No specific bindings
│   ├── Models/
│   │   ├── Invoice.php                 # Invoice records
│   │   ├── InvoiceItem.php             # Line items
│   │   └── CustomFee.php               # Custom charges
│   ├── Services/
│   │   ├── InvoiceGenerationService.php
│   │   └── PdfExportService.php        # Uses barryvdh/dompdf
│   ├── Http/
│   │   └── Controllers/
│   ├── Console/
│   │   └── Commands/
│   └── Jobs/
│       └── GenerateInvoicePdfJob.php
├── database/migrations/               # 3 migrations
│   ├── 2024_xx_xx_create_invoices_table.php
│   ├── 2024_xx_xx_create_invoice_items_table.php
│   └── 2024_xx_xx_create_custom_fees_table.php
├── config/
│   └── invoicing.php                  # Invoice templates, tax rates
├── tests/                             # 0 test files (GAP)
└── composer.json
```

**Integration:**
- Generates from booking/session data
- PDF export for client delivery
- Custom fees for upsells

### prophoto-notifications

**Type:** Laravel Package  
**Responsibility:** Email notifications, templates, delivery tracking

```
prophoto-notifications/
├── src/
│   ├── NotificationsServiceProvider.php # No specific bindings
│   ├── Models/
│   │   └── Message.php                 # Email log
│   ├── Services/
│   │   ├── NotificationService.php
│   │   └── TemplateEngine.php
│   ├── Mail/
│   │   ├── GallerySharedMail.php
│   │   ├── GalleryReadyMail.php
│   │   ├── BookingConfirmationMail.php
│   │   └── InvoiceMail.php
│   ├── Http/
│   │   └── Controllers/
│   ├── Console/
│   │   └── Commands/
│   │       └── RetryFailedNotificationsCommand.php
│   └── Jobs/
│       └── SendNotificationJob.php
├── database/migrations/               # 1 migration
│   └── 2024_xx_xx_create_messages_table.php
├── config/
│   └── notifications.php
├── resources/
│   └── mails/                         # Email templates
│       ├── gallery-shared.blade.php
│       ├── gallery-ready.blade.php
│       └── ...
├── tests/                             # 0 test files (GAP)
└── composer.json
```

**Key Features:**
- Async email delivery via queue
- Template system for different notification types
- Delivery tracking and retry logic

## Critical File Locations (by Type)

### Service Providers (All packages)
```
prophoto-{name}/src/{Package}ServiceProvider.php
```

### Models
```
prophoto-assets/src/Models/Asset.php
prophoto-gallery/src/Models/Gallery.php
prophoto-gallery/src/Models/Image.php
prophoto-booking/src/Models/Session.php
prophoto-access/src/Models/Organization.php
prophoto-access/src/Models/Studio.php
```

### Repositories
```
prophoto-assets/src/Repositories/EloquentAssetRepository.php
prophoto-ingest/src/Repositories/SessionAssignmentRepository.php
prophoto-intelligence/src/Repositories/IntelligenceRunRepository.php
```

### Event Listeners
```
prophoto-assets/src/Listeners/HandleSessionAssociationResolved.php
(Intelligence and Ingest may have additional listeners)
```

### Contracts (Foundation)
```
prophoto-contracts/src/Contracts/Asset/AssetRepositoryContract.php
prophoto-contracts/src/Contracts/Ingest/IngestServiceContract.php
prophoto-contracts/src/Contracts/Intelligence/AssetIntelligenceGeneratorContract.php
```

### Migrations (All packages)
```
prophoto-{name}/database/migrations/*.php
```

### Tests
```
prophoto-assets/tests/
prophoto-contracts/tests/
prophoto-ingest/tests/
prophoto-intelligence/tests/
```

## Dependency Injection Entry Points

All service bindings defined in package ServiceProviders:

```php
$this->app->singleton(ContractInterface::class, Implementation::class);
```

Key binding locations:
- `prophoto-assets/src/AssetServiceProvider.php` (6 bindings)
- `prophoto-ingest/src/IngestServiceProvider.php` (10 bindings)
- `prophoto-intelligence/src/IntelligenceServiceProvider.php` (3 bindings)
- `prophoto-access/src/AccessServiceProvider.php` (permissions)
- `prophoto-gallery/src/GalleryServiceProvider.php` (policies)

## Related Documentation

- [Component Inventory](./component-inventory.md) - Classes, methods, exports
- [Data Models](./data-models.md) - Database schema details
- [API Contracts](./api-contracts.md) - Event and interface definitions
