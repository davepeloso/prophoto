# Normalized Metadata Schema v1
Date: March 9, 2026
Status: Frozen (build against this contract)

## Purpose
Canonical normalized metadata contract for Asset Spine. This defines payload shape, normalization behavior, and index projection rules for `NormalizedAssetMetadata`.

## Version Field Semantics
- `NormalizedAssetMetadata.schemaVersion` (top-level DTO field) = version of the metadata schema contract.
- `payload.normalization.schema_version` = schema version used during normalization of this specific record.
- In v1 writes, these values must match.

## Payload Shape
- `media_kind`: `image|video|pdf|other`
- `captured_at`: ISO8601 string or `null`
- `captured_at_source`: `exif|file_mtime|pdf_info|user_override|ingested_at|null`
- `timezone_source`: `embedded|inferred|unknown|null`
- `mime_type`: string or `null`
- `file_size`: `int|null`
- `dimensions.width`: `int|null`
- `dimensions.height`: `int|null`
- `dimensions.exif_orientation`: `int|null`
- `camera.make`: `string|null`
- `camera.model`: `string|null`
- `camera.lens_model`: `string|null`
- `exposure.iso`: `int|null`
- `exposure.shutter_speed_display`: `string|null`
- `exposure.shutter_speed_seconds`: `float|null`
- `exposure.aperture`: `float|null`
- `exposure.focal_length`: `float|null`
- `color_profile`: `string|null`
- `keywords`: `list<string>`
- `rating`: `int|null`
- `gps.lat`: `float|null`
- `gps.lng`: `float|null`
- `gps.is_redacted`: `bool`
- `document.page_count`: `int|null`
- `document.title`: `string|null`
- `document.author`: `string|null`
- `video.duration_seconds`: `float|null`
- `video.frame_rate`: `float|null`
- `video.codec`: `string|null`
- `source.extractor`: `string`
- `source.tool_version`: `string|null`
- `source.extracted_at`: ISO8601 string or `null`
- `source.source_record_kind`: `exif|xmp|iptc|pdfinfo|ffprobe|mixed|unknown`
- `normalization.schema_version`: `string`
- `normalization.normalized_at`: ISO8601 string or `null`
- `normalization.normalizer_version`: `string|null`

## Normalization Rules
- `rating` must be `int|null` and clamped to range `0..5`.
- `exposure.shutter_speed_seconds` is exposure duration in seconds (for example `0.004` for `1/250`), not milliseconds.
- `captured_at` fallback order:
  - `user_captured_at`
  - `DateTimeOriginal`
  - `CreateDate`
  - `MediaCreateDate`
  - `creation_date`
  - `date_taken`
  - `FileModifyDate`
  - `file_mtime`
  - `ingested_at`
- `captured_at_source` must map to the key selected by the fallback order.
- `timezone_source`:
  - `embedded` when raw value includes timezone offset or `Z`
  - `inferred` when parsed timestamp had no explicit timezone in raw source
  - `unknown|null` only when no timestamp was normalized
- `keywords` policy:
  - source-derived only (no user-authored tag augmentation in this field)
  - trim whitespace
  - de-duplicate case-insensitively
  - preserve first-seen order and first-seen casing
- `has_gps` semantics: coordinate presence only.
  - If both `gps.lat` and `gps.lng` are present then `index.has_gps=true`
  - this remains true even when `gps.is_redacted=true`
- `source.source_record_kind` must use enum values above; fallback is `unknown`.
  - `unknown` means metadata was produced by an extractor, but origin could not be confidently classified.
  - `unknown` is not a generic default for known sources; use a specific enum value whenever classification is possible.

## Index Projection Rules
Index is a denormalized projection derived from payload, not an independent source of truth.

Required index keys:
- `media_kind`
- `captured_at`
- `mime_type`
- `file_size`
- `width`
- `height`
- `exif_orientation`
- `iso`
- `camera_make`
- `camera_model`
- `lens`
- `rating`
- `color_profile`
- `page_count`
- `duration_seconds`
- `has_gps`

Rules:
- index nullability must mirror payload nullability where applicable.
- index must be rebuilt from normalized payload on re-normalization.
- index fields are derived-only and must never be written independently of payload normalization.
- schema changes require new schema version; do not mutate existing normalized rows in place across versions.
