# ProPhoto Image Spine v3
Canonical Architecture + Dependency Governance Spec

This document defines the **canonical image lifecycle spine** for the ProPhoto system.

It exists to:

- make package boundaries explicit
- prevent accidental coupling between packages
- define event and workflow orchestration
- standardize asset lifecycle terminology

The graph is intentionally **non-hierarchical**. Entities connect across bounded contexts.

---

# Core Concepts

The **canonical lifecycle spine**:

StagingImage → Image → Gallery → Interactions → Delivery → AI reuse

Supporting contexts:

- Storage
- Tenancy
- Access
- Audit
- Booking
- AI generation

---

# Node Metadata Schema

Each node includes governance metadata.

| Field | Meaning |
|-----|-----|
| ownerPackage | Package responsible for lifecycle |
| boundedContext | Domain boundary |
| aggregateRoot | Controls lifecycle mutations |
| persistenceModel | Backed by database table |
| publicContract | Whether other packages may rely on it |
| mutability | Mutable or append/immutable |

---

# Nodes

| Node | Package | Context | Aggregate | Persistence | Contract | Mutability |
|-----|-----|-----|-----|-----|-----|-----|
| StagingImage | prophoto-staging | ingest | yes | yes | internal | mutable |
| IngestJob | prophoto-staging | ingest | no | no | internal | mutable |
| AssetIngested | prophoto-contracts | contracts | no | no | public | immutable |
| Image | prophoto-gallery | gallery | yes | yes | public | mutable |
| Gallery | prophoto-gallery | gallery | yes | yes | public | mutable |
| MetadataProfile | prophoto-gallery | gallery | no | yes | public | mutable |
| OriginalFile | prophoto-core | storage | no | no | internal | immutable |
| DerivativeSet | prophoto-core | storage | no | no | public | mutable |
| StoragePath | prophoto-core | storage | no | no | public | immutable |
| Organization | prophoto-core | tenancy | yes | yes | public | mutable |
| Studio | prophoto-core | tenancy | yes | yes | public | mutable |
| Subject | prophoto-clients | identity | yes | yes | public | mutable |
| Session | prophoto-booking | booking | yes | yes | public | mutable |
| ImageInteraction | prophoto-interactions | review | yes | yes | public | immutable |
| Approval | prophoto-interactions | review | no | yes | public | immutable |
| Rating | prophoto-interactions | review | no | yes | public | immutable |
| PermissionCheck | prophoto-access | access | no | no | public | immutable |
| MagicLink | prophoto-access | access | yes | yes | public | mutable |
| DownloadArchive | prophoto-downloads | delivery | yes | yes | public | mutable |
| DeliveryJob | prophoto-downloads | delivery | no | no | internal | mutable |
| ArchiveBuilt | prophoto-contracts | contracts | no | no | public | immutable |
| AuditEvent | prophoto-audit | audit | yes | yes | public | immutable |
| AIGeneration | prophoto-ai | ai | yes | yes | public | mutable |
| GeneratedPortrait | prophoto-ai | ai | no | yes | public | immutable |

---

# Edge Metadata Schema

| Field | Meaning |
|-----|-----|
| edgeClass | Type of relationship |
| timing | sync / async |
| strength | hard / soft dependency |
| direction | read / write / read-write |

---

# Edge Classes

| Class | Meaning |
|-----|-----|
| Eloquent relationship | database model link |
| domain event | event-driven package communication |
| policy/auth dependency | authorization check |
| storage/path concern | filesystem abstraction |
| async workflow/job | queued orchestration |

---

# Core Edges

### Ingest

| From | To | Class | Timing | Strength | Direction |
|-----|-----|-----|-----|-----|-----|
| IngestJob | StagingImage | async workflow | async | hard | write |
| IngestJob | MetadataProfile | async workflow | async | hard | write |
| IngestJob | DerivativeSet | async workflow | async | hard | write |
| StagingImage | Image | async workflow | async | hard | write |
| IngestJob | AssetIngested | domain event | async | soft | write |

---

### Gallery Spine

| From | To | Class | Timing | Strength | Direction |
|-----|-----|-----|-----|-----|-----|
| Image | Gallery | Eloquent | sync | hard | write |
| Image | MetadataProfile | Eloquent | sync | hard | write |
| Image | OriginalFile | storage concern | sync | hard | read-write |
| Image | DerivativeSet | storage concern | sync | hard | read-write |

---

### Tenancy

| From | To | Class | Timing | Strength | Direction |
|-----|-----|-----|-----|-----|-----|
| Studio | Organization | Eloquent | sync | hard | write |
| Organization | Gallery | Eloquent | sync | hard | write |

---

### Interactions

| From | To | Class | Timing | Strength | Direction |
|-----|-----|-----|-----|-----|-----|
| ImageInteraction | Image | Eloquent | sync | hard | write |
| ImageInteraction | Gallery | Eloquent | sync | hard | write |
| Approval | Image | Eloquent | sync | hard | write |
| Rating | Image | Eloquent | sync | hard | write |

---

### Access

| From | To | Class | Timing | Strength | Direction |
|-----|-----|-----|-----|-----|-----|
| PermissionCheck | Gallery | policy | sync | hard | read |
| PermissionCheck | Image | policy | sync | hard | read |
| MagicLink | Gallery | policy | sync | hard | read |

---

### Delivery

| From | To | Class | Timing | Strength | Direction |
|-----|-----|-----|-----|-----|-----|
| DeliveryJob | DownloadArchive | async workflow | async | hard | write |
| DeliveryJob | DerivativeSet | async workflow | async | hard | read |
| DeliveryJob | ArchiveBuilt | domain event | async | soft | write |
| DownloadArchive | Image | Eloquent | sync | hard | read |

---

### AI Reuse

| From | To | Class | Timing | Strength | Direction |
|-----|-----|-----|-----|-----|-----|
| Gallery | AIGeneration | async workflow | async | soft | read |
| Image | AIGeneration | async workflow | async | soft | read |
| Approval | AIGeneration | policy | sync | hard | read |
| AIGeneration | GeneratedPortrait | async workflow | async | hard | write |

---

# Architectural Rules

### 1. Aggregate Roots Own Mutations
Only aggregate roots may mutate lifecycle state.

Examples:

- Image
- Gallery
- Organization
- Session
- DownloadArchive

---

### 2. Cross-Package Writes Use Events

Packages must not directly mutate other package aggregates.

Use events:

- AssetIngested
- ArchiveBuilt

---

### 3. Hard Sync Dependencies Must Be Rare

Hard sync dependencies create coupling.

Allowed examples:

- Image → Gallery
- ImageInteraction → Image

---

### 4. Soft Async Dependencies Are Preferred

Preferred communication:

- Domain events
- Async jobs
- Read-only queries

---

### 5. Storage Is Centralized

Filesystem concerns belong in:
