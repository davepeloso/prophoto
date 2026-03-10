# prophoto-assets Proposal (Content Repository)

## Purpose
`prophoto-assets` is the canonical Asset layer for ProPhoto.

It owns file identity, storage linkage, metadata persistence, metadata enrichment lifecycle, and drive-like browse semantics behind contracts. It does not ship UI.

Ingest creates assets and writes bytes. Galleries and other packages reference assets.

## Boundaries
### Owns
- Asset identity and storage linkage for every uploaded file (image, video, PDF, other)
- Derivative inventory (thumb, preview, web, etc.)
- Storage abstraction and path conventions via contracts
- Logical path/prefix fields for drive-like browse
- Raw metadata persistence (source truth)
- Normalized metadata persistence (canonical query shape)
- Metadata provenance and enrichment lifecycle

### Explicitly does not own
- Upload UI/workflow (`prophoto-ingest`)
- Gallery membership/curation/sharing (`prophoto-gallery`)
- Authorization policy decisions (consuming app + `prophoto-access`)
- Filament resources, pages, panels, or UI assets

## Dependency Rules
- This package must comply with [`RULES.md`](../../../RULES.md) as the authoritative constraint set.
- `prophoto-assets` depends on `prophoto-contracts` and framework/core packages only.
- `prophoto-assets` must not depend on domain packages (`prophoto-ingest`, `prophoto-gallery`, etc.).
- Domain packages may depend on `prophoto-assets`.
- `prophoto-contracts` remains dependency-free from domain packages.

## Contracts (define in `prophoto-contracts` first)
Names only in this proposal; signatures finalized in contracts package.

### Storage and path contracts
- `AssetStorageContract`
- `AssetPathResolverContract`
- `SignedUrlGeneratorContract` (optional split)

### Asset querying and browse contracts
- `AssetRepositoryContract`

### Metadata contracts
- `AssetMetadataExtractorContract`
- `AssetMetadataNormalizerContract`
- `AssetMetadataRepositoryContract`

## Data Model (package-owned tables)
### `assets`
Canonical spine record for each original file.

Core fields:
- `id`
- `studio_id`
- `type` (`image|video|pdf|other`)
- `original_filename`
- `mime_type`
- `bytes`
- `checksum_sha256`
- `storage_driver`
- `storage_key_original`
- `logical_path` (drive-like prefix)
- `captured_at` (if metadata provides it)
- `ingested_at`
- `status` (`pending|ready|failed|quarantined`)

### `asset_derivatives`
Derivative inventory.

Core fields:
- `asset_id`
- `type` (`thumb|preview|web|...`)
- `storage_key`
- `mime_type`
- `bytes`
- `width`
- `height`
- `created_at`

### `asset_metadata_raw`
Raw extracted truth, preserved as-is.

Core fields:
- `asset_id`
- `source` (`exiftool|pdfinfo|ffprobe|...`)
- `tool_version`
- `extracted_at`
- `payload` (JSON, optional compression)
- `hash` (payload integrity)

### `asset_metadata_normalized`
Canonical normalized snapshot.

Core fields:
- `asset_id`
- `schema_version`
- `normalized_at`
- `payload` (JSON)
- optional denormalized filter columns (for query speed):
  - `camera_make`
  - `lens`
  - `iso`
  - `width`
  - `height`

### Key modeling decision
Keep raw and normalized metadata in separate tables so normalization can evolve without losing original extraction truth.

## Event Model
Asynchronous enrichment lifecycle events:
- `AssetCreated`
- `AssetStored`
- `AssetMetadataExtracted`
- `AssetMetadataNormalized`
- `AssetDerivativesGenerated`

Consumers should depend on event contracts, not implementation details.

## Operational Notes
- Extractor implementation can start with ExifTool for images behind `AssetMetadataExtractorContract`.
- PDF/video extractors can be added later via the same contract.
- Package outputs are services, queries, URLs/streams, and metadata payloads for use by UI, API, and CLI callers.

## Initial Rollout Sequence
1. Add contracts and DTO/event contracts in `prophoto-contracts`.
2. Scaffold `prophoto-assets` package with migrations + repositories + service implementations.
3. Make `prophoto-ingest` create assets and publish lifecycle events.
4. Make `prophoto-gallery` reference assets through contracts/repository queries.

## Open Decisions
- Final enum vocabulary for asset type and derivative type (reuse existing enums vs new assets-specific enums).
- Signed URL behavior ownership (`AssetStorageContract` vs dedicated `SignedUrlGeneratorContract`).
- Metadata retention policy for very large raw payloads (compression + archival).
