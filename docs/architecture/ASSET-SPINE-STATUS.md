# Asset Spine Status (One Page)
Date: March 9, 2026
Owner: ProPhoto platform

## Current Posture
Because there is no legacy production dataset yet, Asset Spine is now treated as the **canonical implementation path** (not only a migration side-path). Migration/backfill/read-switch scaffolding remains available for safety.

## Priority Progress
1. **Finalize `prophoto-assets` foundations**: implemented for v1.
   - Contracts/DTOs/events are present in `prophoto-contracts`.
   - `prophoto-assets` owns canonical tables/models/repository/storage/metadata services.
   - `AssetCreationService` persists asset identity, original storage link, and raw/normalized metadata.
2. **Ingest writes assets for accepted uploads**: implemented.
   - Ingest dual-write path persists asset + raw metadata + normalized metadata + derivatives.
   - Default is now asset-write enabled (`INGEST_ASSET_SPINE_DUAL_WRITE=true` default in config).
   - Added ingest -> gallery association sync that writes gallery images with `asset_id` when association targets a gallery.
3. **Gallery new records reference `asset_id` by design**: implemented.
   - `images.asset_id` migration exists and is applied.
   - Gallery upload path now creates canonical assets first (write-enabled by default) and stores `asset_id` on gallery images.
4. **Seed/dev fixtures for end-to-end exercise**: implemented.
   - Added command: `php artisan prophoto:seed-asset-spine-fixtures --count=3`
   - Command creates fixture studio/org/gallery and runs real ingest pipeline with sample JPEGs.
   - Fixture filenames are unique per run so each run can produce fresh asset/gallery records.
5. **Prove one full ingest -> asset -> gallery flow**: implemented and verified in sandbox.
   - Verified output showed `assets_added=3`, `ingest_images_added=3`, `gallery_images_added=3`.
   - Each fixture row produced linked IDs: `ingest_image_id`, `asset_id`, `gallery_image_id`.
   - Added automated test: `tests/Feature/AssetSpineVerticalSliceTest.php`.

## Key Commands
```bash
cd /Users/davepeloso/Sites/prophoto/sandbox
php artisan migrate --force
php artisan prophoto:seed-asset-spine-fixtures --count=3
php artisan test tests/Feature/AssetSpineVerticalSliceTest.php
php artisan prophoto-gallery:backfill-asset-ids --dry-run --only-null
```

## Flags (Canonical Defaults + Scaffolding)
- `INGEST_ASSET_SPINE_DUAL_WRITE` (default config: `true`)
- `INGEST_ASSET_SPINE_FAIL_OPEN` (configurable)
- `GALLERY_ASSET_SPINE_WRITE_ENABLED` (default config: `true`)
- `GALLERY_ASSET_SPINE_WRITE_FAIL_OPEN` (default config: `false`)
- `GALLERY_ASSET_SPINE_READ_SWITCH` (kept as read-side migration toggle)

## Remaining Work
1. Wire the vertical-slice test into CI/package checks.
2. Continue Phase 5 cleanup (remove duplicated legacy ownership once no longer needed).
3. Expand metadata extractor/normalizer implementations beyond pass-through baseline.

## New Hardening Docs
- `docs/architecture/ASSET-METADATA-HARDENING-PACK.md`
- `docs/architecture/NORMALIZED-METADATA-SCHEMA-v1.md`
- `docs/architecture/ASSET-OWNERSHIP-CLEANUP-MAP.md`
