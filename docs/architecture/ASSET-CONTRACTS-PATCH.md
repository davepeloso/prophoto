# Asset Contracts Patch Proposal (`prophoto-contracts`)

## Goal
Define and stabilize cross-package asset/media interfaces before implementation-heavy changes.

This proposal is additive-first and follows `RULES.md`:
- shared interfaces/DTOs/events in `prophoto-contracts`
- immutable/versioned event policy
- no UI coupling

## Status Legend
- `Exists`: already present in `prophoto-contracts`
- `Adjust`: exists but needs follow-up refinement
- `Add`: not present yet
- `Added`: introduced by this patch set

## 1) Storage / Path Contracts

### `AssetStorageContract` — **Exists (Adjust)**
Current status:
- Exists with original/derivative put/get/delete methods.

Suggested refinements:
- Confirm create/upsert lifecycle support expectation from ingest dual-write.
- Confirm stream type semantics (`resource|string|StreamInterface`) for portability.
- Confirm error contract (exceptions vs nullable returns) for fail-open dual-write.

### `AssetPathResolverContract` — **Exists**
Current status:
- Exists with original/derivative/logical path resolvers.

### `SignedUrlGeneratorContract` — **Exists**
Current status:
- Exists for storage key and derivative URL signing.

## 2) Asset Repository / Browse Contracts

### `AssetRepositoryContract` — **Exists (Adjust)**
Current status:
- Exists with `find`, `list`, `browse`.

Suggested refinements:
- Add explicit write contract for dual-write seam (for example `upsertFromIngest(...)` or equivalent command contract).
- Clarify dedupe identity policy (checksum + tenant scope) at contract boundary.

## 3) Metadata Contracts

### `AssetMetadataExtractorContract` — **Exists**
### `AssetMetadataNormalizerContract` — **Exists**
### `AssetMetadataRepositoryContract` — **Exists**

Suggested refinements:
- Clarify provenance minimum required fields.
- Clarify normalized schema version expectations.
- Clarify retrieval behavior when only raw or only normalized data exists.

## 4) DTOs / Enums

### DTOs — **Exists**
- `AssetId`
- `AssetRecord`
- `StoredObjectRef`
- `RawMetadataBundle`
- `NormalizedAssetMetadata`
- `MetadataProvenance`
- `AssetMetadataSnapshot`
- browse/query DTOs (`AssetQuery`, `BrowseOptions`, `BrowseEntry`, `BrowseResult`)

### Enums — **Exists**
- `DerivativeType`
- `MetadataScope`

Suggested refinements:
- Ensure `AssetType` vocabulary supports image/video/pdf/other lifecycle consistently.
- Confirm whether `DerivativeType` naming should align with existing ingest names (`thumbnail` vs `thumb`).

## 5) Event Contracts

### Existing Asset Events — **Exists**
- `AssetCreated`
- `AssetStored`
- `AssetMetadataExtracted`
- `AssetMetadataNormalized`
- `AssetDerivativesGenerated`

### Additive Event Needed — **Added**
- `AssetReadyV1` (versioned additive event)
  - Emitted when canonical asset has minimum ready state for downstream consumption.
  - Must carry stable identifiers only (asset ID + tenant scope + timestamp + readiness summary fields).

## Event Immutability and Versioning Policy
- Do not mutate existing event payloads after introduction.
- If event payload/shape changes, add a new versioned event type.
- Keep prior versions available until consumers migrate.
- Event payloads must carry stable identifiers, not hydrated Eloquent models.

## Proposed PR Slice (Contracts-only)
1. `AssetReadyV1` event contract (added).
2. Add notes/tests for event immutability/versioning expectations.
3. Add follow-up TODOs for `AssetRepositoryContract` write-path refinement needed by dual-write.
4. Keep changes additive and backward-compatible.
