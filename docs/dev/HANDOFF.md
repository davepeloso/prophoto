# Handoff Note (March 10, 2026)

## Current Status
- Asset Spine + Derived Intelligence foundations are in place.
- `prophoto-intelligence` now has two separate thin slices (not bundled):
  - Label run: `demo_tagging`
  - Embedding run: `demo_embedding`
- Both are triggered on `AssetReady` as separate runs with separate generator/model identity.
- Phase tracking is now centralized in:
  - [DERIVED-INTELLIGENCE-PHASE-NOTES.md](/Users/davepeloso/Sites/prophoto/docs/architecture/DERIVED-INTELLIGENCE-PHASE-NOTES.md)

## What Was Completed
- Added embedding generator:
  - [DemoEmbeddingGenerator.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/Generators/DemoEmbeddingGenerator.php)
- Added dedicated embedding orchestrator:
  - [IntelligenceEmbeddingOrchestrator.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/Orchestration/IntelligenceEmbeddingOrchestrator.php)
- Extended persistence for strict embedding writes + validation:
  - [IntelligencePersistenceService.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/Orchestration/IntelligencePersistenceService.php)
- Kept label and embedding runs separate and generator-scoped.
- Added embedding event path:
  - `AssetEmbeddingUpdated`
  - `AssetIntelligenceGenerated`
- Added/updated tests:
  - [IntelligenceEmbeddingVerticalSliceTest.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/tests/Feature/IntelligenceEmbeddingVerticalSliceTest.php)
  - [IntelligenceVerticalSliceTest.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/tests/Feature/IntelligenceVerticalSliceTest.php)
  - [IntelligenceRunRepositoryTest.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/tests/Feature/IntelligenceRunRepositoryTest.php)

## Validation (Latest Run)
- `cd /Users/davepeloso/Sites/prophoto/prophoto-intelligence && composer test`
- Result: `OK (15 tests, 70 assertions)`

## Important Decisions Captured
- `AssetIntelligenceRunStarted` has no `resultTypes`.
- Duplicate-active-run fallback query includes `configuration_hash`.
- Persistence is idempotent per run:
  - labels via `(run_id, label)` + `insertOrIgnore`
  - embeddings via `(asset_id, run_id)` + `insertOrIgnore`
- Strict validation for malformed embedding payloads (asset/run mismatch fails loudly).

## Deferred / Not Implemented Yet
- Keep `AssetId::toInt()` usage for now (future UUID/ULID migration risk).
- `markCompleted()` still allows `pending -> completed` (non-blocking for now; long-term target is `running -> completed` only).
- `IntelligenceRunRepository::find()` remains weakly typed (`?object`).
- `AssetEmbeddingUpdated` still includes `resultTypes` (consider trimming later).
- Label and embedding orchestrators currently duplicate shared run flow; needs consolidation helper/base service.

## Tomorrow: Where to Start
1. Review backlog:
   - [TODO.md](/Users/davepeloso/Sites/prophoto/TODO.md)
2. Re-run package tests:
   - `cd /Users/davepeloso/Sites/prophoto/prophoto-intelligence && composer test`
   - `cd /Users/davepeloso/Sites/prophoto/prophoto-contracts && composer test`
3. Next recommended task:
   - Introduce generator registry/planner so orchestrator selection is centralized (replace direct dual orchestration calls in service provider).

## Workspace Reminder
- Working tree is intentionally dirty/uncommitted across contracts, intelligence package, and docs.
- Check before continuing:
  - `cd /Users/davepeloso/Sites/prophoto && git status --short`
