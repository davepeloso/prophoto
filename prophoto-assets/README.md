# ProPhoto Assets

## Purpose

Canonical media repository for the ProPhoto system. Owns asset identity, original/derivative storage linkage, raw and normalized metadata persistence, and asset-side session context projections. This is the single source of truth for "what is this asset and what do we know about it." Other packages reference assets by ID — they do not own or mutate asset records.

## Core Loop Role

Assets is **position 2** in the core event loop. It attaches canonical truth to decisions made by ingest.

```
  prophoto-ingest  ──(SessionAssociationResolved)──►  ► prophoto-assets
► prophoto-assets  ──(AssetSessionContextAttached)──►  prophoto-intelligence
► prophoto-assets  ──(AssetReadyV1)──────────────────►  prophoto-intelligence
```

When `SessionAssociationResolved` arrives, the `HandleSessionAssociationResolved` listener creates an `AssetSessionContext` record (the asset-side projection of the ingest decision) and emits `AssetSessionContextAttached`. Separately, when an asset completes its metadata pipeline, `AssetReadyV1` is emitted.

If this package is removed, there is no canonical asset truth, no session context projection, and neither `AssetSessionContextAttached` nor `AssetReadyV1` is ever emitted. Intelligence has nothing to act on.

## Responsibilities

- Asset model (canonical asset identity — one row per ingested media file)
- AssetDerivative model (thumbnails, previews, web-optimized versions linked to originals)
- AssetMetadataRaw model (verbatim metadata extracted from files)
- AssetMetadataNormalized model (standardized metadata derived from raw records)
- AssetSessionContext model (asset-side projection of ingest session-association decisions)
- HandleSessionAssociationResolved listener (consumes ingest decision, creates session context, emits AssetSessionContextAttached)
- AssetServiceProvider: registers 7 contract bindings (AssetRepositoryContract, AssetStorageContract, AssetPathResolverContract, SignedUrlGeneratorContract, AssetMetadataExtractorContract, AssetMetadataNormalizerContract, AssetMetadataRepositoryContract), registers AssetCreationService, registers event listener, registers RenormalizeAssetsMetadataCommand
- `php artisan prophoto-assets:renormalize` command for rebuilding normalized metadata from raw records

## Non-Responsibilities

- MUST NOT perform session matching — that is prophoto-ingest
- MUST NOT perform intelligence operations — that is prophoto-intelligence
- MUST NOT mutate booking data — that is prophoto-booking
- MUST NOT own gallery presentation logic — that is prophoto-gallery
- MUST NOT make matching decisions — it receives decisions via `SessionAssociationResolved` and projects them, nothing more

## Integration Points

- **Events listened to:** `SessionAssociationResolved` (from prophoto-contracts, dispatched by ingest)
- **Events emitted:** `AssetSessionContextAttached` (defined here in `Events/`), `AssetReadyV1` (defined in prophoto-contracts)
- **Contracts depended on:** `prophoto/contracts` (DTOs, enums, event classes, service interfaces)
- **Consumed by:** prophoto-intelligence (listens to both emitted events), prophoto-gallery (references Asset model via Image.asset_id)

## Data Ownership

| Table | Model | Purpose |
|---|---|---|
| `assets` | Asset | Canonical asset identity — one row per ingested media file |
| `asset_derivatives` | AssetDerivative | Derived versions (thumb, preview, web) linked to originals |
| `asset_metadata_raw` | AssetMetadataRaw | Verbatim metadata as extracted from the file |
| `asset_metadata_normalized` | AssetMetadataNormalized | Standardized metadata derived from raw records |
| `asset_session_contexts` | AssetSessionContext | Asset-side projection of session-association decisions from ingest |

## Notes

- This is a headless package — no Filament resources, no Inertia/SPA UI, no gallery curation logic
- Other packages reference assets by ID only. Only this package may create, update, or delete asset records.
- AssetSessionContext is a projection, not a decision — the decision lives in prophoto-ingest's assignment tables. If the ingest decision changes (supersession), a new SessionAssociationResolved event will arrive and the projection will be updated.
- Metadata has a two-stage pipeline: raw extraction → normalization. The renormalize command allows reprocessing normalized metadata without re-extracting from files.
- ServiceProvider: `ProPhoto\Assets\AssetServiceProvider` (auto-discovered)
