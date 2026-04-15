# ProPhoto Assets Package Deep-Dive Documentation

## Overview

This is an exhaustive deep-dive analysis of the `prophoto-assets` package, the canonical media asset repository for ProPhoto. The package implements the Asset Spine pattern and owns canonical media truth for the entire system.

**Package Classification**: Core Package (part of event loop)  
**Files Analyzed**: 38 files  
**Lines of Code**: ~1,200 LOC  
**Generated**: 2026-04-10

## Architecture Position

```
prophoto-ingest -> SessionAssociationResolved -> prophoto-assets -> AssetSessionContextAttached -> prophoto-intelligence
```

The assets package sits at the center of the ProPhoto event loop:
- **Consumes**: `SessionAssociationResolved` events from ingest
- **Emits**: `AssetSessionContextAttached`, `AssetReadyV1` events
- **Owns**: Canonical asset truth, metadata spine, storage contracts

## Documentation Structure

This deep-dive includes:

1. **[File Inventory](./01-file-inventory.md)** - Complete catalog of all 38 files with signatures
2. **[Dependency Map](./02-dependency-map.md)** - Import/export relationships and data flow
3. **[Event Contracts](./03-event-contracts.md)** - Event consumption and emission patterns
4. **[Data Model](./04-data-model.md)** - Complete schema, relationships, and migration sequence
5. **[Service Layer](./05-service-layer.md)** - Service responsibilities and implementation patterns
6. **[Integration Points](./06-integration-points.md)** - Cross-package connections and boundaries
7. **[Test Coverage](./07-test-coverage.md)** - Testing patterns and coverage analysis
8. **[Gap Analysis](./08-gap-analysis.md)** - Alignment with SYSTEM.md/RULES.md
9. **[Code Patterns](./09-code-patterns.md)** - Repository, listener, and service patterns

## Key Findings

### Architecture Compliance
- **Event-driven boundaries**: Properly implemented via contracts
- **Package ownership**: Clear asset ownership with no cross-package mutation
- **Metadata spine**: Correctly preserves raw and normalized metadata
- **Storage contracts**: Well-abstracted storage layer

### Implementation Strengths
- Clean separation between raw and normalized metadata
- Robust event-driven session context attachment
- Comprehensive metadata normalization pipeline
- Proper use of Laravel patterns and Eloquent relationships

### Areas for Attention
- Asset type resolution could be more extensible
- Metadata normalizer is quite large (457 lines) - could be refactored
- Some service methods have high complexity (AssetCreationService)

## Package Statistics

| Category | Count |
|----------|-------|
| Models | 5 |
| Services | 6 |
| Repositories | 1 |
| Listeners | 1 |
| Console Commands | 1 |
| Events | 1 |
| Migrations | 6 |
| Tests | 8 |
| Contracts | 0 (uses prophoto-contracts) |

## Quick Reference

### Core Models
- `Asset` - Canonical asset record
- `AssetDerivative` - Processed derivatives (thumbnails, etc.)
- `AssetMetadataRaw` - Immutable raw metadata
- `AssetMetadataNormalized` - Queryable normalized metadata
- `AssetSessionContext` - Session association projection

### Key Services
- `AssetCreationService` - End-to-end asset creation pipeline
- `AssetRegistrar` - Simple asset registration
- `EloquentAssetMetadataRepository` - Metadata persistence
- `LaravelAssetStorage` - Storage abstraction
- `PassThroughAssetMetadataNormalizer` - Metadata normalization
- `NullAssetMetadataExtractor` - No-op metadata extractor

### Event Flow
1. Ingest emits `SessionAssociationResolved`
2. Asset listener persists session context
3. Asset emits `AssetSessionContextAttached`
4. Intelligence consumes context for derived work

---

*This documentation represents a complete exhaustive analysis of the prophoto-assets package as of 2026-04-10.*
