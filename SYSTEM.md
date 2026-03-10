# ProPhoto System Architecture

## Overview

ProPhoto is a **disciplined modular monolith** designed for Laravel's Eloquent ORM and migration system. It uses **intentional, one-directional dependencies** between packages that reflect real user workflows and asset lifecycle stages.

This is **not** a pure onion/hexagonal architecture. It's a **pragmatic modular monolith** that:
- Matches Laravel/Eloquent reality
- Allows intentional upstream dependencies
- Prevents circular dependency hell
- Scales cleanly if you ever need to extract services

**Authoritative constraints:** See [`RULES.md`](RULES.md).  
This document provides explanation, examples, and expanded guidance.

---

## Architecture Pattern

```
┌────────────────────────────────────────────────────────┐
│                    prophoto-contracts                  │
│        (DTOs, interfaces, enums, shared events)        │
└────────────────────────────────────────────────────────┘
                         ▲
                         │
┌────────────────────────────────────────────────────────┐
│                    prophoto-core                       │
│  (tenancy, settings, audit, security, notifications)   │
│                     [PLANNED]                          │
└────────────────────────────────────────────────────────┘
                         ▲
                         │
┌────────────────────────────────────────────────────────┐
│                   prophoto-access                      │
│        (RBAC, contextual permissions, roles)           │
└────────────────────────────────────────────────────────┘
                         ▲
                         │
┌────────────────────────────────────────────────────────┐
│              Vertical Domain Packages                  │
│                                                        │
│  ingest → gallery → interactions                       │
│          ↑                                             │
│        booking                                         │
│          ↑                                             │
│        billing (invoicing + payments)                  │
│                                                        │
│  ai → gallery                                          │
│                                                        │
│  notifications → gallery, booking, billing             │
└────────────────────────────────────────────────────────┘
```

---

## Current Package Inventory

### Foundation (2 packages)
1. **prophoto-contracts** - Shared interfaces, DTOs, enums, events
2. **prophoto-access** - RBAC + tenancy (Studios, Organizations, Permissions)

### Core Infrastructure (planned)
3. **prophoto-core** - Tenancy, settings, audit, security, notifications *(will be created when we extract from access)*

### Vertical Domain Packages (7 packages)
4. **prophoto-gallery** - Gallery, Image, ImageVersion models + controllers + policies
5. **prophoto-booking** - Session, BookingRequest models + controllers + policies
6. **prophoto-invoicing** - Invoice, InvoiceItem, CustomFee models + controllers + policies
7. **prophoto-ai** - AiGeneration, AiGenerationRequest, AiGeneratedPortrait models
8. **prophoto-interactions** - ImageInteraction model (ratings, approvals, comments)
9. **prophoto-ingest** - StagingImage model + upload/culling UI
10. **prophoto-notifications** - Message model + email notification system

### Utilities (1 package)
11. **prophoto-debug** - Debug and tracing utilities

**Total: 11 packages (10 exist, 1 planned)**

---

## Core Dependency Rules

### Rule 1: Contracts are the innermost dependency

**prophoto-contracts** has **zero dependencies**.

All other packages may depend on it.

Contracts define:
- DTOs (value objects, data transfer objects)
- Service interfaces (contracts for services)
- Shared enums (AssetType, DerivativeType, etc.)
- Cross-domain events (AssetIngested, GalleryCreated, etc.)

**Contracts never reference Eloquent models.**

---

### Rule 2: Core infrastructure is upstream of domains (planned)

**prophoto-core** (when created) will provide **cross-cutting concerns**:
- Tenancy context (Studio, Organization resolution)
- Settings & feature flags
- Audit logging
- Security utilities (magic links, rate limiting)
- Notification primitives

Domain packages may **use** core services, but core **never** depends on domains.

**Current state**: Studio/Organization models temporarily live in **prophoto-access** until we create prophoto-core.

**Important guardrail**: Access may only host the **minimum tenant models needed for authorization checks**. If it's needed to answer "is this user allowed?" → OK in prophoto-access. If it's needed to answer "what is this studio/org?" (settings, branding, documents, billing info) → that belongs in prophoto-core. Access must not become "identity + tenancy + RBAC + everything else".

---

### Rule 3: Access is a shared service, not a domain

**prophoto-access** owns:
- RBAC roles & permissions
- Contextual permission logic
- Permission constants
- Access middleware
- **Temporarily**: Studio & Organization models (will move to prophoto-core)

Domain packages:
- Reference permission constants
- Implement policies locally
- Never embed permission logic themselves

**Access does not own domain models** (except temporary tenancy models).

---

## Package Dependency Flow (Laravel-Realistic)

**Two kinds of coupling exist in Laravel packages:**

1. **Compile-time** (Composer deps, PHP interfaces, events, classes)
2. **Runtime** (database tables + Eloquent relationships + migrations)

You can't pretend runtime coupling doesn't exist — so you manage it deliberately.

### A) Compile-time dependency rules (hard rules)

- **prophoto-contracts** depends on **nothing**
- Every "feature package" may depend on:
  - prophoto-contracts
  - prophoto-access (abilities / RBAC checks)
  - Laravel framework packages it truly needs (Filament, Inertia, etc.)
- Feature packages should **not** import each other's models directly

### B) Runtime coupling rules (database + Eloquent reality)

Because packages ship migrations, you will have "foreign key" and relationship coupling. That's OK — just constrain it:

- **Ownership rule**: A table belongs to exactly one package (that package owns its migrations + model)
- **Reference rule**: Other packages may reference the table via:
  - An **ID value** (e.g., `gallery_id`) and/or
  - A **contract/DTO**
  - And optionally an **Eloquent relationship only inside the sandbox app**, not inside the packages (best discipline)

If you want relationships inside packages, keep them **one direction** and treat them as "allowed dependencies."

### C) "Allowed dependency directions" (practical + clean)

- **Access/Core → nobody**
  - prophoto-access owns tenancy + user/org/studio identity and permissions

- **Feature packages → can reference Access identity**
  - e.g., Gallery has `studio_id`, Booking has `organization_id`

- **Feature-to-feature**: Prefer events/contracts, but allow *one-way* relationships where it's genuinely foundational:
  - prophoto-gallery may own Image models and allow other packages to reference `image_id`
  - prophoto-interactions can reference `image_id` without importing Image model (store ids + load via repository/contract)

---

## Vertical Domain Dependencies (Intentional & Allowed)

Domain packages are allowed to depend on **upstream domains** when the dependency reflects a real business flow and earlier lifecycle stage.

### Allowed dependency directions

| **From** | **May depend on** |
|----------|-------------------|
| ingest | gallery |
| gallery | interactions |
| booking | gallery |
| billing (invoicing) | booking, gallery |
| ai | gallery |
| notifications | gallery, booking, billing |

### Disallowed dependency directions

| **❌ Disallowed** | **Reason** |
|-------------------|------------|
| gallery → ingest | breaks ingestion lifecycle |
| gallery → billing | galleries should not care about money |
| booking → ai | unrelated domains |
| any domain → access internals | permissions are referenced, not implemented |

**Rule:**

> A domain may depend only on domains that appear *earlier* in the asset lifecycle.
> Example lifecycle: `capture → ingest → assets → galleries → delivery`.

---

## Eloquent Reality Rule (Important)

Because ProPhoto uses **Eloquent**:

✅ Domain packages **may reference models from upstream packages**
✅ Foreign keys across package tables are **allowed**
✅ Eloquent relationships are allowed **only upstream**

### Examples:

```php
// ✅ Allowed (downstream → upstream)
Session → belongsTo Gallery
ImageInteraction → belongsTo Image
Invoice → belongsTo Session
AiGeneration → belongsTo Gallery

// ❌ Not allowed (upstream → downstream)
Gallery → belongsTo Invoice
Image → belongsTo Payment
```

**This is not a violation of clean architecture** - it's Laravel reality. Foreign keys exist. Eloquent relationships exist. We embrace this instead of fighting it.

---

## Migration Ownership Rule

- Each package owns **only its own tables**
- Migrations live in the package that owns the model
- Foreign keys referencing upstream tables are **allowed and expected**

### Example:

**prophoto-gallery** owns:
- `galleries` table
- `images` table
- `image_versions` table

**prophoto-interactions** owns:
- `image_interactions` table
- `image_interactions.image_id → images.id` (foreign key is valid)

**prophoto-booking** owns:
- `photo_sessions` table
- `booking_requests` table
- `photo_sessions.gallery_id → galleries.id` (foreign key is valid)

**No shared tables. No cross-package migrations.**

---

## Storage Ownership Rule

- Each physical media object must have one canonical asset identity.
- Original files must be owned by the package responsible for asset identity (planned: `prophoto-assets`).
- Other packages reference assets by ID and must not store independent copies of the same original media.
- Derivatives must reference the canonical asset identity.
- Storage path conventions must be resolved through contracts, not hardcoded across packages.

**Current state**: `prophoto-ingest` currently owns staging/final paths during transition. This will move to canonical ownership once `prophoto-assets` is implemented.

---

## Event-Driven Integration (Preferred)

When one domain needs to react to another **without a direct dependency**:

✅ Emit a domain event
✅ Listen in the dependent package
✅ Avoid synchronous service calls when possible

### Examples:

- `SessionConfirmed` → Gallery package listener auto-creates gallery
- `AssetIngested` → Notifications package sends email
- `InvoicePaid` → Gallery package unlocks delivery

**Events live in prophoto-contracts.**

Domain event rules:
- Events are historical facts and must be immutable after introduction.
- If event shape changes, introduce a new versioned event.
- Events should carry stable identifiers (IDs), not full Eloquent model payloads.

---

## Anti-Patterns (Strictly Avoid)

❌ **Circular dependencies between packages**
❌ **"God" packages that own multiple domains**
❌ **Domain logic in the sandbox app**
❌ **Cross-package migrations**
❌ **Access package importing domain models**

---

## Mental Model (Keep You Sane)

Think of packages as **mini-apps inside one Laravel app**:

- They know about *earlier* steps in the workflow
- They don't know about *later* steps
- Infrastructure sits above everything
- Contracts glue it together

### Decision Framework

If you ever ask: *"Should package A depend on package B?"*

Answer: *"Does A happen **after** B in the real world?"*

- If yes → **allowed**
- If no → **wrong direction**

---

## Why This Works

✅ **Matches how Laravel actually behaves** (Eloquent, migrations, relationships)
✅ **Lets you ship without architectural guilt** (pragmatic, not dogmatic)
✅ **Prevents dependency spaghetti** (one-directional flow)
✅ **Scales cleanly** if you ever split services later

---

## Foundation Packages (Detailed)

### prophoto-contracts ⭐
**Purpose**: Shared interfaces, DTOs, enums, and exceptions
**Dependencies**: None (by design)
**Status**: ✅ Active, stable core

**Key Contents**:
- Service interfaces (IngestServiceContract, GalleryRepositoryContract, StorageContract)
- DTOs (AssetId, GalleryId, IngestRequest, IngestResult, AssetMetadata)
- Enums (AssetType, DerivativeType, IngestStatus, Ability)
- Events (AssetIngested, GalleryCreated, SessionBooked)
- Exceptions (AssetNotFoundException, PermissionDeniedException)

**Rule**: Contracts never depend on domain packages. All other packages depend on this.

---

### prophoto-core (PLANNED)
**Purpose**: Cross-cutting infrastructure services
**Dependencies**: `prophoto-contracts`
**Status**: 📝 Planned (will be created when extracting from prophoto-access)

**Will provide**:
- **Tenancy**: Studio, Organization models + context resolution
- **Settings**: Feature flags, studio-level config
- **Audit**: Activity logging, audit trails
- **Security**: Magic links, rate limiting, token management
- **Notifications**: Email dispatch primitives

**Current state**: These concerns are split between prophoto-access (tenancy) and package skeletons. Will consolidate into prophoto-core later.

---

### prophoto-access (RBAC + Temporary Tenancy)
**Purpose**: Security and authorization layer
**Dependencies**: `prophoto-contracts`, `spatie/laravel-permission`
**Status**: ✅ Active, provides authorization for all packages

**Current Scope**:

#### Core Models (Temporary - will move to prophoto-core)
- **Studio** - Top-level tenant (photography studio)
- **Organization** - Sub-tenant (client organizations)
- **OrganizationDocument** - Contracts, agreements attached to orgs
- **PermissionContext** - Contextual permissions (user can edit THIS gallery)

**Guardrail**: These models exist here ONLY for authorization checks ("is this user allowed?"). Any business logic about studios/orgs (settings, branding, billing info) belongs in prophoto-core, not here.

#### RBAC System (Permanent)
- **Roles**: `studio_user`, `client_user`, `guest_user`, `vendor_user`
- **50+ Permissions** (see `Permissions.php`):
  - Gallery: `VIEW_GALLERIES`, `UPLOAD_IMAGES`, `APPROVE_IMAGES`, etc.
  - Booking: `VIEW_SESSIONS`, `CONFIRM_BOOKING`, etc.
  - Invoicing: `CREATE_INVOICES`, `RECORD_PAYMENT`, etc.
  - AI: `TRAIN_AI_MODEL`, `GENERATE_AI_PORTRAITS`, etc.
- **Traits**: `HasContextualPermissions` (adds contextual permission methods to User)
- **Middleware**: `CheckContextualPermission` for route protection
- **Policies**: OrganizationPolicy (others live in domain packages)

#### Filament Integration
- Permission matrix (interactive grid)
- Role management UI
- Permission management UI

**What it does NOT contain**: Domain models (Gallery, Invoice, Session, etc.)
**Integration pattern**: Domain packages import `ProPhoto\Access\Permissions` constants

---

## Vertical Domain Packages (Detailed)

### prophoto-gallery
**Purpose**: Gallery presentation and management
**Dependencies**: `prophoto-contracts`, `prophoto-access`
**Status**: ✅ Active

**Owns**:
- **Models**: Gallery, Image, ImageVersion
- **Migrations**: galleries, images, image_versions tables
- **Policies**: GalleryPolicy (authorization logic)
- **Controllers**: Gallery display, upload, sharing
- **Events**: GalleryCreated, ImageApproved

**Upstream Dependencies**: None (gallery is the foundation)

**Downstream Consumers**:
- prophoto-booking references Gallery
- prophoto-ai references Gallery
- prophoto-ingest transforms to Gallery
- prophoto-interactions interacts with Image
- prophoto-notifications references Gallery

---

### prophoto-booking
**Purpose**: Photo session scheduling and booking
**Dependencies**: `prophoto-contracts`, `prophoto-access`, **prophoto-gallery** ⬆️
**Status**: ✅ Active

**Owns**:
- **Models**: Session (photo sessions), BookingRequest
- **Migrations**: photo_sessions, booking_requests tables
- **Policies**: SessionPolicy
- **Controllers**: Booking workflow, calendar management
- **Integrations**: Google Calendar sync hooks

**Upstream Dependencies**:
```php
// Session → Gallery relationship
public function gallery(): HasOne
{
    return $this->hasOne(Gallery::class);
}
```

**Why this is allowed**: Sessions create galleries in the real workflow (booking → gallery creation).

---

### prophoto-invoicing
**Purpose**: Invoice generation and payment tracking
**Dependencies**: `prophoto-contracts`, `prophoto-access`, **prophoto-booking**, **prophoto-gallery** ⬆️
**Status**: ✅ Active

**Owns**:
- **Models**: Invoice, InvoiceItem, CustomFee
- **Migrations**: invoices, invoice_items, custom_fees tables
- **Policies**: InvoicePolicy
- **Controllers**: Invoice CRUD, PDF generation
- **Services**: Stripe integration (when prophoto-payments implemented)

**Upstream Dependencies**:
- Invoice can reference Session (session-based pricing)
- Invoice can reference Gallery (gallery delivery unlock)

**Why this is allowed**: Invoicing happens AFTER booking/gallery in the real workflow.

---

### prophoto-ai
**Purpose**: AI portrait model training and generation
**Dependencies**: `prophoto-contracts`, `prophoto-access`, **prophoto-gallery** ⬆️
**Status**: ✅ Active

**Owns**:
- **Models**: AiGeneration, AiGenerationRequest, AiGeneratedPortrait
- **Migrations**: ai_generations, ai_generation_requests, ai_generated_portraits tables
- **Services**: Fine-tuning orchestration, generation queue

**Upstream Dependencies**:
```php
// AiGeneration → Gallery relationship
public function gallery(): BelongsTo
{
    return $this->belongsTo(Gallery::class);
}
```

**Why this is allowed**: AI training uses images from existing galleries.

**Business Rules**:
- Max 5 portrait generations per trained model
- Model expires after configurable period
- Training requires minimum number of approved images

---

### prophoto-interactions
**Purpose**: User interactions with images (ratings, comments, approvals)
**Dependencies**: `prophoto-contracts`, `prophoto-access`, **prophoto-gallery** ⬆️
**Status**: ✅ Active

**Owns**:
- **Models**: ImageInteraction
- **Migrations**: image_interactions table
- **Types**: rating, note, approval, download, edit_request

**Upstream Dependencies**:
```php
// ImageInteraction → Image relationship
public function image(): BelongsTo
{
    return $this->belongsTo(Image::class);
}
```

**Why this is allowed**: Interactions happen ON images from galleries.

---

### prophoto-ingest
**Purpose**: Professional photo upload and culling interface
**Dependencies**: `prophoto-contracts`, `prophoto-access`, **prophoto-gallery** ⬆️, `intervention/image`, `inertia-laravel`
**Status**: ✅ Active, sophisticated Adobe Bridge-style UI

**Owns**:
- **Models**: StagingImage (temporary staging area)
- **Migrations**: staging_images table
- **React UI**: Three-panel culling interface
- **Services**: EXIF extraction, derivative generation, batch processing

**Key Features**:
- Adobe Bridge-style interface (React + TypeScript + Inertia.js)
- Three-tier image sizing (thumbnail, preview, original)
- Chart-based metadata filtering (click ISO chart → select photos)
- Tag system
- Queue-based processing
- Configurable naming schemas

**Data Flow** (one-way transformation):
```
StagingImage (temp queue)
    ↓ (process and transform)
Image in Gallery (permanent)
    ↓ (cleanup)
StagingImage deleted
```

**Upstream Dependencies**:
- StagingImage can be assigned to Gallery
- Transformation creates Image records in Gallery

**Why this is allowed**: Ingest transforms INTO gallery (ingest → gallery pipeline).

---

### prophoto-notifications
**Purpose**: Email notification system
**Dependencies**: `prophoto-contracts`, `prophoto-access`, **prophoto-gallery**, **prophoto-booking**, **prophoto-invoicing** ⬆️
**Status**: ✅ Active

**Owns**:
- **Models**: Message
- **Migrations**: messages table
- **Services**: Template-based email dispatch

**Upstream Dependencies**:
- Message can reference Gallery (context)
- Message can reference Image (context)
- Message can reference Session (booking notifications)
- Message can reference Invoice (payment reminders)

**Why this is allowed**: Notifications are triggered BY events from other domains.

---

## Sandbox Application

**Purpose**: Disposable Laravel application for development and integration testing
**Location**: `/sandbox`

The sandbox:
- Consumes packages via Composer path repositories (symlinked)
- Provides a real Laravel environment for testing
- Runs integration tests across packages
- Can be destroyed and recreated via `./scripts/prophoto sandbox:fresh`

**composer.json pattern**:
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../prophoto-*",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "prophoto/contracts": "dev-main",
    "prophoto/access": "dev-main",
    "prophoto/gallery": "dev-main",
    "prophoto/booking": "dev-main",
    "prophoto/invoicing": "dev-main",
    "prophoto/ai": "dev-main",
    "prophoto/interactions": "dev-main",
    "prophoto/ingest": "dev-main",
    "prophoto/notifications": "dev-main",
    "prophoto/debug": "dev-main"
  }
}
```

---

## Data Flow Examples

### Photo Ingestion Flow
1. User uploads photos via ingest UI (**prophoto-ingest**)
2. IngestService creates StagingImage records
3. Queue job extracts EXIF metadata
4. Queue job generates derivatives (thumbnails, previews)
5. User culls/selects photos in ingest UI
6. Selected photos transformed to Image records in **Gallery** (**prophoto-gallery**)
7. StagingImage records deleted (cleanup)
8. Event emitted: `AssetIngested` (other packages can listen)

### Gallery Access Flow
1. User requests gallery view
2. GalleryPolicy checks permission (**prophoto-gallery**)
3. Policy references Permissions constants (**prophoto-access**)
4. Contextual permission checked (user + THIS gallery)
5. Gallery + Images fetched
6. ImageInteractions loaded (**prophoto-interactions**)
7. Access controls applied per image
8. View rendered

### Booking to Gallery Flow
1. Client requests booking (**prophoto-booking**)
2. Studio confirms booking → Session created
3. Event: `SessionConfirmed` emitted (**prophoto-contracts**)
4. Gallery package listener creates Gallery for Session (**prophoto-gallery**)
5. Magic link generated with contextual permissions (**prophoto-access**)
6. Client receives link via **prophoto-notifications**

### AI Portrait Generation Flow
1. Gallery has approved images (**prophoto-gallery**)
2. Studio initiates AI training (**prophoto-ai**)
3. AiGeneration record created, references Gallery
4. Training job processes gallery images
5. Model trained, portraits generated
6. AiGeneratedPortrait records created
7. Notification sent (**prophoto-notifications**)

---

## Storage Conventions

### Asset Storage (current - to be migrated to prophoto-core)
- Staging/temp: `storage/app/ingest-temp/{thumbs,previews}/`
- Finals: `storage/app/images/`

### Asset Storage (planned with prophoto-core)
- Originals: `{studio_id}/{org_id}/{gallery_id}/originals/{image_id}.{ext}`
- Derivatives: `{studio_id}/{org_id}/{gallery_id}/derivatives/{type}/{image_id}.jpg`

Path and URL resolution should come from storage/path contracts in `prophoto-contracts`, not hardcoded package-specific string paths.

### Database
- Each package owns its migrations
- Sandbox runs migrations from all packages
- No shared tables between domain packages
- Foreign keys across packages are allowed (upstream only)

---

## Development Workflow

1. Make changes in package source (`prophoto-*/src/`)
2. Build assets if needed (`cd prophoto-ingest && npm run build`)
3. Refresh sandbox (`./scripts/prophoto refresh`)
4. Test in sandbox or via package tests
5. Commit package changes

**Never**:
- Commit vendor/ or node_modules/ in packages
- Make business logic changes in sandbox
- Create circular dependencies between domain packages

---

## Testing Strategy

### Package-Level Tests
- PHPUnit/Pest via Orchestra Testbench
- Run: `cd prophoto-{package} && composer test`
- Mock external dependencies
- Test in isolation

### Integration Tests
- Run in sandbox with real database
- Test cross-package interactions via events
- Run: `cd sandbox && php artisan test`

### Unified Testing
- Run all tests: `./scripts/prophoto test`

---

## Architecture Principles

✅ **Vertical Slices**: Each domain owns its complete stack (models, controllers, policies, migrations)
✅ **Contract-Based**: All packages depend on `prophoto-contracts`, never each other directly (except upstream)
✅ **Intentional Upstream Dependencies**: Allowed when reflecting real user workflow
✅ **Event-Driven**: Packages integrate via events when no direct dependency needed
✅ **Event Immutability**: Event facts are versioned, never mutated
✅ **Storage Ownership**: One canonical asset identity owns originals/derivatives
✅ **Self-Registering**: Laravel auto-discovers service providers
✅ **RBAC Layer**: Single source of truth for permissions (prophoto-access)
✅ **Eloquent Reality**: Foreign keys and relationships across packages are embraced
✅ **Testable**: Each package independently testable with Testbench

**Philosophy**:
> We're building a **disciplined modular monolith**, not a distributed microservices architecture. Eloquent relationships exist. Foreign keys exist. We embrace Laravel's reality instead of fighting it.

**Avoid**:
- ❌ Circular dependencies
- ❌ Downstream → Upstream dependencies
- ❌ God packages
- ❌ Domain logic in sandbox

---

## Package Status Summary

**Foundation** (2 packages):
- ✅ prophoto-contracts
- ✅ prophoto-access (RBAC + temporary tenancy)

**Core Infrastructure** (1 package):
- 📝 prophoto-core (planned - will extract from access)

**Vertical Domain Packages** (7 packages):
- ✅ prophoto-gallery (Gallery, Image, ImageVersion)
- ✅ prophoto-booking (Session, BookingRequest) → depends on gallery
- ✅ prophoto-invoicing (Invoice, InvoiceItem, CustomFee) → depends on booking, gallery
- ✅ prophoto-ai (AiGeneration, requests, portraits) → depends on gallery
- ✅ prophoto-interactions (ImageInteraction) → depends on gallery
- ✅ prophoto-ingest (StagingImage + upload UI) → depends on gallery
- ✅ prophoto-notifications (Message) → depends on gallery, booking, invoicing

**Utilities** (1 package):
- ✅ prophoto-debug

**Total: 11 packages (10 exist, 1 planned)**

---

## Migration History

### Phase 1: Monolith (Initial)
prophoto-access contained all domain models (Gallery, Session, Invoice, AI, etc.) plus RBAC.

### Phase 2: Vertical Extraction (Current)
Each domain extracted to its own package:
- Domain migrations moved from `prophoto-access/database/migrations/` to respective packages
- Domain models moved from `prophoto-Access\Models\` to `prophoto-{package}\Models\`
- Policies moved from `prophoto-access/src/Policies/` to respective packages
- prophoto-access now contains ONLY RBAC + temporary tenancy

**Guardrail for Phase 2**: Access is allowed to host Studio/Organization temporarily, but only what's required for authorization checks. All other tenant/business data (settings, branding, documents, billing info) belongs in prophoto-core when it exists.

### Phase 3: Core Consolidation (Planned)
- Create prophoto-core package
- Move Studio/Organization from prophoto-access to prophoto-core
- Consolidate infrastructure concerns (settings, audit, security, notifications)
- prophoto-access becomes pure RBAC

**Result**: Clean separation of concerns, intentional dependencies, Laravel-realistic architecture.

---

## Future Considerations

### When to extract to microservices

Only extract a package to a separate service when:
- ✅ It has fundamentally different scaling needs
- ✅ It must be deployed independently
- ✅ It uses a different tech stack
- ✅ Team boundaries require it

**Don't extract prematurely.** The modular monolith works great for 99% of Laravel apps.

### When to create prophoto-core

Create it when:
- ✅ You have 3+ packages needing shared infrastructure
- ✅ Settings/audit/security features are actually built
- ✅ Moving Studio/Organization makes sense

**Don't create empty packages.** Build only what you need, when you need it.
