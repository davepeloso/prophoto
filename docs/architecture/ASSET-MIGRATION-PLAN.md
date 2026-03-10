# Asset Spine Migration Plan (Non-Breaking)

## Objective
Introduce `prophoto-assets` as the canonical media + metadata spine with phased, reversible changes.

## Transition Seam (Exact Insertion Point)
Current flow:
- `upload -> staging -> metadata -> derivatives -> gallery image -> cleanup staging`

Target flow:
- `upload -> staging acceptance -> asset created -> raw metadata -> normalized metadata -> derivatives -> gallery association -> cleanup staging`

Seam location in current code:
- `prophoto-ingest/src/Services/IngestProcessor.php::process()` after acceptance/promotion context is available.

## Phased Rollout and Gates

### Phase 1 (docs/contracts planning)
Gate:
- Ownership map approved.
- Contract/event patch approved.
- No runtime behavior changes.

### Phase 2 (`prophoto-assets` headless scaffold)
Gate:
- Package loads in sandbox.
- Migrations run cleanly.
- Package tests pass.
- No ingest/gallery behavior changes.

### Phase 3 (ingest dual-write)
Gate:
- Feature flag added for dual-write.
- Existing ingest -> gallery promotion remains primary path.
- Asset creation/metadata/derivative registry executes as additive side path.
- Fail-open behavior confirmed (asset write failures do not break legacy ingest promotion initially).

### Phase 4 (gallery reads asset references)
Gate:
- `asset_id` linkage added and backfilled for gallery image records.
- Read path supports asset-backed metadata/storage resolution.
- Regression tests confirm gallery behavior parity.

### Phase 5 (de-dup ownership cleanup)
Gate:
- Asset-backed read/write stable in production-like testing.
- Remove duplicated metadata/path responsibilities from ingest/gallery.
- Destructive cleanup deferred until backfill and rollback windows close.

## Table Introduction Order (Additive First)
1. `assets`
2. `asset_metadata_raw`
3. `asset_metadata_normalized`
4. `asset_derivatives`
5. Later: gallery-side `asset_id` linkage/backfill migration(s)

Rules:
- Additive migrations first.
- Backfill before read-switch.
- Destructive/column-removal migrations last.

## Dual-Write Strategy (Phase 3)
At staging acceptance:
- Continue current `ProxyImage -> Image` promotion unchanged.
- In parallel (flag-gated):
  - Create/update canonical `assets` record.
  - Persist raw metadata to `asset_metadata_raw`.
  - Persist normalized metadata to `asset_metadata_normalized`.
  - Register derivative entries in `asset_derivatives`.
  - Emit additive asset lifecycle events.

Requirements:
- Idempotent writes keyed by stable identity (checksum and/or deterministic key policy).
- Fail-open during initial rollout (log and continue legacy path on asset failures).
- Structured telemetry for dual-write success/failure rates.

## Rollback Strategy
Immediate rollback lever:
- Disable dual-write feature flag.

Operational behavior on rollback:
- Legacy ingest->gallery promotion continues without asset-side writes.
- Existing additive asset records remain inert until re-enabled.

Migration safety:
- Because table introduction is additive-first, rollback does not require destructive schema operations.
- Read-path cutover in gallery occurs only after successful backfill and verification.

## Compatibility and Risk Notes
- No UI introduction in foundational package.
- No forced storage path migration in early phases.
- No mandatory rewrite of gallery models in dual-write phase.
- Event payload changes must use versioned/additive event types; existing events remain intact.

## Acceptance Criteria
- Existing ingest flow remains functional before and during dual-write.
- Asset tables can be populated without breaking legacy gallery behaviors.
- Gallery can switch to `asset_id` references behind controlled rollout.
- Duplicate ownership can be removed only after parity validation.
