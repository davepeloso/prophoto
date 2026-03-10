# Asset Spine Ownership Map

## Purpose
This document maps **current ownership** to **target ownership** for introducing `prophoto-assets` as the canonical media domain without breaking existing ingest/gallery behavior.

Source references:
- `/Users/davepeloso/Sites/prophoto/docs/#prophotoAssetSpine.md`
- `/Users/davepeloso/Sites/prophoto/SYSTEM.md`
- `/Users/davepeloso/Sites/prophoto/RULES.md`

## Canonical Ownership Summary
- Canonical media identity will move to `prophoto-assets`.
- `prophoto-ingest` remains intake/workflow and no longer acts as long-term media owner.
- `prophoto-gallery` remains curation/presentation and no longer acts as canonical storage/metadata owner.

## Responsibility Mapping

| Area | Current Owner | Future Owner | v1 (unchanged) | Later migration |
|---|---|---|---|---|
| Upload workflow, staging UX, culling | `prophoto-ingest` | `prophoto-ingest` | Keep all current staging UX and jobs | N/A |
| Temporary staging files (`ingest-temp`) | `prophoto-ingest` | `prophoto-ingest` (temporary), then reduced | Keep current temp lifecycle | Reduce staging responsibilities once asset write path is fully stable |
| Canonical original media identity | effectively split (`ingest` + `gallery`) | `prophoto-assets` | Do not break current `ProxyImage -> Image` promotion | Asset row becomes canonical identity at acceptance seam |
| Canonical storage ownership (original + derivatives) | split/hardcoded paths in ingest/gallery flow | `prophoto-assets` | Keep existing final path behavior | Route path resolution through asset contracts |
| Raw metadata persistence (source truth) | primarily ingest proxy/raw fields | `prophoto-assets` | Keep existing ingest metadata extraction and persistence | Move canonical raw metadata source to `asset_metadata_raw` |
| Normalized metadata persistence | ingest + gallery image metadata fields | `prophoto-assets` | Keep existing fields used by current UI | Move canonical normalized metadata source to `asset_metadata_normalized` |
| Gallery membership / curation | `prophoto-gallery` | `prophoto-gallery` | Keep current gallery/image behavior | Gallery records reference `asset_id` as canonical media linkage |
| Asset browse semantics (drive-like) | not centralized | `prophoto-assets` | Not introduced in existing UI yet | Add repository/browse APIs for downstream consumers |

## What Ingest Stops Owning
### Stop owning in migration (not immediately)
- Canonical final media identity
- Canonical raw/normalized metadata truth
- Canonical derivative registry
- Canonical storage/path conventions

### Continue owning
- Upload/session workflow
- Staging queue state
- Intake validation and acceptance decisions
- UI-oriented staging operations

## What Gallery Stops Owning
### Stop owning in migration (not immediately)
- Canonical media identity
- Canonical metadata source of truth
- Canonical derivative assumptions and path logic

### Continue owning
- Curation and ordering
- Collection/grouping/sharing semantics
- Presentation-oriented fields and interactions

## New `prophoto-assets` Responsibilities
- Canonical `Asset` identity for every accepted media object.
- Canonical original + derivatives inventory.
- Raw metadata persistence with provenance.
- Normalized metadata persistence with schema versioning.
- Contract-based browse/list/find interfaces for downstream packages.
- Asset lifecycle events in `prophoto-contracts`.

## Non-Goals in v1 Introduction
- No Asset repository UI (Filament/Inertia).
- No immediate rewrite of gallery model behavior.
- No one-shot storage migration.
- No big-bang replacement of current ingest flow.

## Enforcement Notes
This mapping is constrained by `RULES.md`:
- Single table ownership
- Storage ownership
- Domain events immutability/versioning
- UI boundary (headless foundational package)
