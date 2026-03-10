# ProPhoto Assets

Canonical media repository package for ProPhoto.

## Scope
- Canonical asset identity
- Original/derivative storage linkage
- Raw + normalized metadata persistence
- Asset browse/query contracts

## Non-goals
- No Filament resources
- No Inertia/SPA UI
- No gallery curation logic
- No ingest workflow UI

## Package Status
Active headless canonical media domain for Asset Spine.

## Metadata Maintenance
- Rebuild normalized metadata from latest raw records:
  - `php artisan prophoto-assets:renormalize`
  - `php artisan prophoto-assets:renormalize --dry-run`
