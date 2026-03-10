# Asset Metadata Hardening Pack (v1)
Date: March 9, 2026
Status: Frozen v1 contract

## 1) Contract Lock (Spine Core)
The following are now treated as canonical contract surfaces for downstream packages:

- Asset creation: `AssetCreationService::createFromFile(...)`
- Raw metadata persistence: `AssetMetadataRepositoryContract::storeRaw(...)` (append-only records)
- Normalized metadata persistence: `AssetMetadataRepositoryContract::storeNormalized(...)` (schema-version keyed projection)
- Derivative registration: ingest dual-write path + `AssetDerivativesGenerated` event
- Gallery ownership model: new gallery image records must reference `images.asset_id`
- Event contracts (immutable payload shapes):
  - `AssetCreated`
  - `AssetStored`
  - `AssetMetadataExtracted`
  - `AssetMetadataNormalized`
  - `AssetDerivativesGenerated`
  - `AssetReadyV1`

Policy: event payloads are immutable. Shape changes require additive versioned events (for example `AssetReadyV2`), not in-place mutation.

## 2) Normalized Metadata Schema v1
Normalized payload (DTO: `NormalizedAssetMetadata.payload`) is now canonicalized into this shape.
Authoritative spec file: `docs/architecture/NORMALIZED-METADATA-SCHEMA-v1.md`.

Version fields are intentionally distinct:
- `NormalizedAssetMetadata.schemaVersion` (top-level DTO field) = metadata schema contract version.
- `payload.normalization.schema_version` = schema version used during normalization of this specific record.
- In v1 writes, these values must match.

- `media_kind`: `image|video|pdf|other`
- `captured_at`: ISO8601 timestamp or `null`
- `captured_at_source`: `exif|file_mtime|pdf_info|user_override|ingested_at|null`
- `timezone_source`: `embedded|inferred|unknown|null`
- `mime_type`: string or `null`
- `file_size`: integer or `null`
- `dimensions`:
  - `width`: integer or `null`
  - `height`: integer or `null`
  - `exif_orientation`: integer or `null`
- `camera`:
  - `make`: string or `null`
  - `model`: string or `null`
  - `lens_model`: string or `null`
- `exposure`:
  - `iso`: integer or `null`
  - `shutter_speed_display`: string or `null`
  - `shutter_speed_seconds`: float or `null`
    - Represents exposure duration in seconds (for example `0.004` for `1/250`), not milliseconds.
  - `aperture`: float or `null`
  - `focal_length`: float or `null`
- `color_profile`: string or `null`
- `keywords`: list of strings
- `rating`: `int|null` (`0..5`; `null` means absent)
- `gps`:
  - `lat`: float or `null`
  - `lng`: float or `null`
  - `is_redacted`: boolean
- `document`:
  - `page_count`: integer or `null`
  - `title`: string or `null`
  - `author`: string or `null`
- `video`:
  - `duration_seconds`: float or `null`
  - `frame_rate`: float or `null`
  - `codec`: string or `null`
- `source`:
  - `extractor`: string
  - `tool_version`: string or `null`
  - `extracted_at`: ISO8601 timestamp or `null`
  - `source_record_kind`: `exif|xmp|iptc|pdfinfo|ffprobe|mixed|unknown`
    - `unknown` means extractor output exists but origin could not be confidently classified.
    - Do not use `unknown` when a concrete enum value can be derived.
- `normalization`:
  - `schema_version`: string
  - `normalized_at`: ISO8601 timestamp or `null`
  - `normalizer_version`: string or `null`

## 3) Raw vs Normalized Field Map
Examples of canonical mapping:

- Image-heavy EXIF:
  - `DateTimeOriginal` -> `captured_at`
  - source key used -> `captured_at_source`
  - timezone in source date string -> `timezone_source=embedded` else `inferred`
  - `ImageWidth`/`ExifImageWidth` -> `dimensions.width`
  - `ImageHeight`/`ExifImageHeight` -> `dimensions.height`
  - `Orientation` -> `dimensions.exif_orientation`
  - `Make` -> `camera.make`
  - `Model` -> `camera.model`
  - `LensModel` -> `camera.lens_model`
  - `ISO`/`ISOSpeedRatings` -> `exposure.iso`
  - `ExposureTime` -> `exposure.shutter_speed_display` + `exposure.shutter_speed_seconds`
  - `FNumber` -> `exposure.aperture`
  - `FocalLength` -> `exposure.focal_length`
  - `Keywords`/`Subject` -> `keywords`
- PDF:
  - `PageCount` -> `document.page_count`
  - `Title` -> `document.title`
  - `Author` -> `document.author`
  - `MIMEType=application/pdf` -> `media_kind=pdf`
- Video placeholder:
  - `Duration` -> `video.duration_seconds`
  - `VideoFrameRate` -> `video.frame_rate`
  - `CompressorName`/`VideoCodec` -> `video.codec`
  - `MIMEType` starting with `video/` -> `media_kind=video`

## 4) Provenance Model
Provenance contract (`MetadataProvenance`) is required on writes:

- `source`: extractor/producer identity (`exiftool`, `pdfinfo`, `ffprobe`, `renormalizer`, etc.)
- `toolVersion`: extractor version when available
- `recordedAt`: write timestamp
- `context`: bounded map for workflow metadata (`proxy_uuid`, command name, raw record id, etc.)

Raw records keep extraction truth and provenance per write. Normalized records keep projection provenance per schema version.

Keyword normalization policy (`payload.keywords`):
- source-derived only (not user-curated tagging)
- trim whitespace
- preserve first-seen casing
- de-duplicate case-insensitively
- preserve first-seen order

`has_gps` semantics:
- `index.has_gps=true` whenever normalized coordinates (`gps.lat` and `gps.lng`) are present
- independent of `gps.is_redacted` (redaction is a presentation/export concern, not coordinate presence)

## 5) Re-normalization Strategy
Schema evolution path is now explicit:

1. Introduce new schema version (for example `v2`).
2. Deploy updated normalizer logic.
3. Run `php artisan prophoto-assets:renormalize` (optionally scoped by `--asset-id` / `--limit` / `--dry-run`).
4. Keep prior normalized rows by schema version for traceability.
5. Consumers switch reads to the target schema behind flags/cutover steps.

## 6) Failure Behavior (Strict Mode)
Current defaults are strict for canonical writes:

- Ingest dual-write enabled.
- Fail-open disabled by default for ingest asset writes.
- Gallery asset write path enabled.
- Gallery fail-open disabled by default.

Behavior target:
- Fail clearly on canonical write failure.
- Avoid hidden partial-success states.
- Keep telemetry/log context on failures for triage.

## 7) Initial Indexed Fields
Current physical indexed columns in `asset_metadata_normalized`:

- `camera_make`
- `iso`
- `media_kind`
- `captured_at`
- `mime_type`
- `rating`
- `has_gps`
- (`asset_id`, `schema_version`) composite

Persisted denormalized columns currently include:
- `camera_make`, `camera_model`, `lens`, `iso`, `width`, `height`
- `exif_orientation`, `color_profile`, `page_count`, `duration_seconds`
- `file_size`, `captured_at`, `mime_type`, `media_kind`, `has_gps`

Invariant:
- Index columns are derived from normalized payload and must never be written independently of payload normalization.

## 8) Enforcement Test Matrix
Implemented now:

- Normalizer schema tests:
  - EXIF-heavy image payload
  - sparse/weird payload
  - PDF payload
  - video placeholder payload
- Metadata lifecycle tests:
  - raw append-only behavior
  - normalized schema-versioned behavior
  - re-write behavior for same schema version
- Event contract shape lock tests:
  - constructor signatures for all asset lifecycle events
- Re-normalization command test:
  - builds normalized rows from latest raw metadata

Next enforcement additions:
- ingest strict-mode failure tests (asset write, metadata normalize, derivative register)
- idempotent ingest re-run tests
- gallery invalid/missing `asset_id` guard tests

## 9) Asset Metadata Mutability Rules
- Raw metadata is immutable (append-only records in `asset_metadata_raw`).
- Normalized metadata is replaceable only within the same `schema_version` record.
- Consumers must not mutate normalized payloads directly.
- All normalized payload/index updates must pass through the normalizer write path.

Why:
- Direct DB mutations (for example updating JSON payload fields in-place) bypass normalization rules and can break payload/index contract guarantees.
