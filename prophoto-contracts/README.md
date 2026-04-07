# ProPhoto Contracts

## Purpose

Shared kernel for the entire ProPhoto system. Defines all cross-package interfaces, DTOs, enums, events, and exceptions. Every package in the system depends on this package. This package depends on nothing. It is the only package that may be imported by every other package without creating a dependency violation.

## Core Loop Role

Contracts does not participate in the event loop directly — it **defines** it. The load-bearing events that power the core loop are declared here:

```
prophoto-ingest  →  SessionAssociationResolved        (Events/Ingest/)
prophoto-assets  →  AssetReadyV1                      (Events/Asset/)
prophoto-assets  →  AssetSessionContextAttached        (declared in prophoto-assets, not here)
prophoto-intelligence  →  AssetIntelligenceGenerated   (Events/Intelligence/)
```

If this package is removed, every cross-package contract, event, DTO, and enum ceases to exist. The entire system stops compiling.

## Responsibilities

- All cross-package event contracts (Ingest, Asset, Intelligence namespaces)
- All shared DTOs: AssetId, SessionContextSnapshot, IntelligenceRunContext, GeneratorResult, IngestRequest, and 16 others
- All shared enums: SessionAssignmentMode, SessionMatchConfidenceTier, RunStatus, AssetType, and 11 others
- All service interfaces: AssetRepositoryContract, IngestServiceContract, AssetIntelligenceGeneratorContract, and 11 others
- All shared exception types: AssetNotFoundException, MetadataReadFailedException, PermissionDeniedException

## Non-Responsibilities

- MUST NOT depend on any other package — zero composer dependencies on prophoto/* packages
- MUST NOT contain Eloquent models, migrations, or database-specific code
- MUST NOT contain controllers, routes, or service providers with business logic
- MUST NOT contain implementation details — only interfaces and data structures
- MUST NOT be treated as a dumping ground for convenience code

## Integration Points

- **Events listened to:** None (defines events, does not consume them)
- **Events emitted:** None (defines event classes, does not dispatch them)
- **Contracts depended on:** None (this IS the contracts package)
- **Depended on by:** Every package in the system

## Data Ownership

This package owns no tables and no persistent state. It defines the shapes of data that other packages own.

| Artifact | Count | Purpose |
|---|---|---|
| Interfaces | 14 | Service boundaries across packages |
| DTOs | 21 | Cross-package data transfer objects |
| Enums | 15 | Shared vocabulary and constants |
| Event contracts | 14 | Ingest (5), Asset (6), Intelligence (3) |
| Exceptions | 3 | Shared exception types |

## Notes

- Treat this package as slow-moving and stable. Breaking changes here ripple across the entire system.
- Events carry IDs, not models. Events are immutable. Events are versioned if changed. These rules are defined in SYSTEM.md.
- The SessionContextSnapshot DTO is the canonical way intelligence receives session context — intelligence MUST NOT query booking directly.
- This package has 8 test files covering enum coverage, DTO serialization, event contract shapes, and interface signatures.
