# ProPhoto Project Documentation

Complete reference for the ProPhoto Laravel monorepo architecture, design patterns, and implementation details.

**Generated:** 2026-04-10  
**Scan Level:** Deep Scan  
**Project Type:** Modular Monolith (11 Laravel Packages)

## Quick Navigation

### Core Documentation
- **[Project Overview](./project-overview.md)** - Strategic architecture, core principles, technology stack
- **[Source Tree Analysis](./source-tree-analysis.md)** - Directory structure, file organization, critical paths
- **[Component Inventory](./component-inventory.md)** - All services, repositories, models, and events
- **[API Contracts](./api-contracts.md)** - Event contracts, interface definitions, DTOs
- **[Data Models](./data-models.md)** - Database schema, relationships, migrations

### Package-Specific Documentation
- **[prophoto-contracts](./architecture/PACKAGE-contracts.md)** - Shared interfaces, DTOs, enums
- **[prophoto-access](./architecture/PACKAGE-access.md)** - RBAC, permissions, authorization
- **[prophoto-assets](./architecture/PACKAGE-assets.md)** - Media asset repository, metadata, storage
- **[prophoto-gallery](./architecture/PACKAGE-gallery.md)** - Gallery management, templates, sharing
- **[prophoto-booking](./architecture/PACKAGE-booking.md)** - Booking workflow, session management
- **[prophoto-ingest](./architecture/PACKAGE-ingest.md)** - Upload ingestion, session matching
- **[prophoto-intelligence](./architecture/PACKAGE-intelligence.md)** - Derived intelligence, embeddings
- **[prophoto-interactions](./architecture/PACKAGE-interactions.md)** - Image ratings, comments
- **[prophoto-ai](./architecture/PACKAGE-ai.md)** - AI orchestration, model training
- **[prophoto-invoicing](./architecture/PACKAGE-invoicing.md)** - Invoice generation and tracking
- **[prophoto-notifications](./architecture/PACKAGE-notifications.md)** - Email notifications system

### Development Guides
- **[Development Guide](./development-guide.md)** - Setup, testing, deployment
- **[Architecture Decisions](./architecture/ARCHITECTURE-INDEX.md)** - Documented design decisions

## System at a Glance

### Technology Stack
- **Framework:** Laravel 11.0+ with Illuminate components
- **Language:** PHP 8.2+
- **Database:** Eloquent ORM (migrations included per package)
- **Testing:** PHPUnit 11.0, Pest 2.0
- **Architecture Pattern:** Modular monolith with event-driven communication

### Package Organization (11 Packages)

| Category | Packages |
|----------|----------|
| **Foundation** | prophoto-contracts (interfaces, DTOs, enums) |
| **Access & Auth** | prophoto-access (RBAC, Spatie permissions) |
| **Media** | prophoto-assets (canonical media repository), prophoto-gallery (gallery UX) |
| **Booking & Sessions** | prophoto-booking (workflow engine), prophoto-ingest (upload processing) |
| **Intelligence** | prophoto-intelligence (derived runs), prophoto-ai (model training) |
| **Features** | prophoto-interactions (ratings/comments), prophoto-invoicing (billing) |
| **Infrastructure** | prophoto-notifications (email system) |

### Communication Patterns
- **Event-driven:** Packages communicate via Laravel event dispatch/listen
- **Dependency injection:** Service providers bind contracts to implementations
- **Contract-based:** All cross-package communication through prophoto-contracts interfaces
- **Async processing:** Queue support for long-running tasks (ingest, intelligence)

### Key Integration Points
1. **Asset Pipeline:** Upload → Ingest → Session Matching → Asset Creation → Intelligence Generation
2. **Gallery Workflow:** Session Created → Assets Ingested → Gallery Generated → Shared
3. **Access Control:** Studio/Organization-scoped access via RBAC (Spatie permissions)

## Recent Changes (Phase 1 Analysis)

This documentation represents a deep scan of the codebase as of April 10, 2026. All 11 packages have been analyzed:

- Architecture patterns documented
- Service bindings and dependencies mapped
- Event flows and listeners identified
- Data models and migrations catalogued
- Test coverage assessed
- Integration points discovered

For detailed findings per package, see package-specific documentation above.

## Key Findings

### Architecture Strengths
- ✅ Clean separation of concerns via Laravel packages
- ✅ Event-driven communication prevents tight coupling
- ✅ Contracts layer enables swappable implementations
- ✅ Clear dependency direction (contracts ← all packages)

### Test Coverage Assessment
- **assets, contracts, ingest, intelligence:** Solid test coverage (8-13 files)
- **access, ai, gallery, interactions, invoicing, notifications:** No tests yet (gap)
- **booking:** No tests yet (critical gap for workflow logic)

### Known Gaps vs. Documentation
- Some ServiceProviders lack event listener registration (e.g., ingest, intelligence)
- Test coverage inconsistent across packages
- Some packages have complex logic without test files yet

## How to Use This Documentation

1. **Understanding the system:** Start with [Project Overview](./project-overview.md)
2. **Finding code:** Use [Source Tree Analysis](./source-tree-analysis.md) to locate files
3. **Understanding integration:** Check [Component Inventory](./component-inventory.md) for cross-package references
4. **API integration:** Reference [API Contracts](./api-contracts.md) for event definitions
5. **Database work:** See [Data Models](./data-models.md) for schema

For implementation details, see package-specific docs and architecture decision files in the `/docs/architecture/` directory.
