# Asset Ownership Cleanup Map
Date: March 9, 2026
Status: Planning guide for Phase 5 (slice-by-slice)

## Objective
Remove duplicated ownership assumptions only after canonical Asset Spine behavior is stable and boring.

## Ingest: Current vs Target
Current ingest responsibilities still present:
- Staging/proxy intake workflow
- Final file placement logic for ingest image records
- Metadata extraction during intake
- Dual-write into asset tables

Target ingest responsibilities:
- Intake/staging orchestration only
- Metadata extraction orchestration (producer role)
- Emit/forward canonical asset writes through Asset Spine
- No long-term canonical media ownership

Can be removed later from ingest:
- Canonical storage/path assumptions that belong to assets
- Canonical metadata ownership assumptions after full cutover

## Gallery: Current vs Target
Current gallery responsibilities still present:
- Gallery image records and presentation concerns
- Some legacy file/path fallback fields
- New `asset_id` reference path enabled for writes

Target gallery responsibilities:
- Curation and presentation layer
- References canonical asset identity via `asset_id`
- Reads metadata/storage via asset-backed contracts/resources
- No canonical media identity ownership

Can be removed later from gallery:
- Legacy media-owner assumptions (`file_path` as source of truth)
- Duplicated metadata ownership that mirrors assets
- Legacy path logic where asset-backed read is complete

## Assets: Protected Core Ownership
Assets package owns:
- Canonical media identity (`assets`)
- Raw metadata truth (`asset_metadata_raw`)
- Normalized schema projections (`asset_metadata_normalized`)
- Derivative inventory (`asset_derivatives`)
- Re-normalization workflow

Must remain out of scope:
- Filament/UI concerns
- Gallery/business workflow concerns
- Ingest session UX concerns

## Remove-Now vs Wait
Safe now:
- Tighten tests and docs around metadata and event contracts
- Keep backfill/read-switch tooling in place
- Normalize new writes through asset-first paths

Wait until later:
- Deleting migration/backfill scaffolding
- Dropping all legacy fallback fields
- Broad destructive cleanup across ingest/gallery in one PR

## Cleanup Gate Criteria
Only begin destructive cleanup when:
1. Multiple vertical slices pass repeatedly (image heavy/sparse, PDF, video placeholder).
2. Strict-mode failure tests pass and are stable in CI.
3. No active feature paths bypass canonical asset creation.
4. Gallery reads are fully asset-backed for targeted domains.
