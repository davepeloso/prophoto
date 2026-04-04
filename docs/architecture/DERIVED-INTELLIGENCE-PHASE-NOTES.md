# Derived Intelligence Phase Notes
Updated: March 13, 2026

Purpose: maintain a running phase-by-phase implementation log for the Derived Intelligence Layer so planning, handoff, and review stay aligned.

## Usage Rule
- Keep this file updated at the end of each phase-sized change.
- For each phase, track: `status`, `what landed`, `what is still open`.
- Prefer short bullets and link to concrete files/tests.

## Phase 1 — Contracts in `prophoto-contracts`
Status: Complete

What landed:
- Intelligence generator/repository contracts are present.
- Intelligence DTOs and run enums are present.
- Contract/event shape tests exist in `prophoto-contracts/tests`.

Key references:
- [AssetIntelligenceGeneratorContract.php](/Users/davepeloso/Sites/prophoto/prophoto-contracts/src/Contracts/Intelligence/AssetIntelligenceGeneratorContract.php)
- [GeneratorResult.php](/Users/davepeloso/Sites/prophoto/prophoto-contracts/src/DTOs/GeneratorResult.php)
- [RunStatus.php](/Users/davepeloso/Sites/prophoto/prophoto-contracts/src/Enums/RunStatus.php)

Open items:
- None for this phase.

## Phase 2 — Events in `prophoto-contracts`
Status: Complete

What landed:
- Intelligence lifecycle events are present with generator/model identity payloads.
- Event contract shape tests are in place.

Key references:
- [AssetIntelligenceRunStarted.php](/Users/davepeloso/Sites/prophoto/prophoto-contracts/src/Events/Intelligence/AssetIntelligenceRunStarted.php)
- [AssetIntelligenceGenerated.php](/Users/davepeloso/Sites/prophoto/prophoto-contracts/src/Events/Intelligence/AssetIntelligenceGenerated.php)
- [AssetEmbeddingUpdated.php](/Users/davepeloso/Sites/prophoto/prophoto-contracts/src/Events/Intelligence/AssetEmbeddingUpdated.php)

Open items:
- None for this phase.

## Phase 3 — Package Scaffold (`prophoto-intelligence`)
Status: In Progress

What landed:
- Headless package services are implemented (orchestration, planning, registry, repository, generators).
- Package test harness is active (unit + feature).

Key references:
- [IntelligenceServiceProvider.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/IntelligenceServiceProvider.php)
- [IntelligencePlanner.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/Planning/IntelligencePlanner.php)
- [IntelligenceGeneratorRegistry.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/Registry/IntelligenceGeneratorRegistry.php)

Open items:
- Package config file/merge path is still not formalized (provider reads `prophoto-intelligence.*` keys without a package `config/*.php`).

## Phase 4 — Migrations and Data Ownership
Status: Complete

What landed:
- `intelligence_runs`, `asset_labels`, and `asset_embeddings` tables are present in `prophoto-intelligence`.
- Intelligence writes are isolated from canonical Asset Spine metadata.

Key references:
- [2026_03_10_000001_create_intelligence_runs_table.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/database/migrations/2026_03_10_000001_create_intelligence_runs_table.php)
- [2026_03_10_000002_create_asset_labels_table.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/database/migrations/2026_03_10_000002_create_asset_labels_table.php)
- [2026_03_10_000003_create_asset_embeddings_table.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/database/migrations/2026_03_10_000003_create_asset_embeddings_table.php)

Open items:
- None for this phase.

## Phase 5 — Services and Repository Responsibilities
Status: In Progress

What landed:
- Thin-slice label and embedding orchestrators are working.
- Entry orchestrator is implemented with planner + registry flow.
- Run repository has active-run protection and planner run summaries.
- Entry listener routing is in place with primary config key `intelligence.entry_orchestrator_enabled` (env/back-compat fallback available).
- Result validation now enforces both required outputs and no unexpected output families.

Key references:
- [IntelligenceEntryOrchestrator.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/Orchestration/IntelligenceEntryOrchestrator.php)
- [IntelligenceRunRepository.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/Repositories/IntelligenceRunRepository.php)
- [IntelligencePersistenceService.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/Orchestration/IntelligencePersistenceService.php)

Open items:
- Tighten lifecycle transition to `running -> completed` only.
- Remove integer-only `AssetId::toInt()` assumptions.
- Replace weak `find(): ?object` return with typed shape.
- Formal retry classification policy (`retryable` vs `non-retryable`).

## Phase 6 — Minimal Vertical Slice Test Plan
Status: In Progress

What landed:
- Unit tests for planner and registry.
- Feature tests for thin slices and entry orchestrator planning/execution behaviors.
- Feature tests for provider routing (entry path vs legacy fallback).

Key references:
- [IntelligencePlannerTest.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/tests/Unit/Planning/IntelligencePlannerTest.php)
- [IntelligenceGeneratorRegistryTest.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/tests/Unit/Registry/IntelligenceGeneratorRegistryTest.php)
- [IntelligenceEntryOrchestratorTest.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/tests/Feature/IntelligenceEntryOrchestratorTest.php)
- [IntelligenceServiceProviderRoutingTest.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/tests/Feature/IntelligenceServiceProviderRoutingTest.php)

Open items:
- Add broader cross-package integration coverage beyond package-local vertical slices.

## Phase 7 — v1 Implemented Acceptance Criteria
Status: In Progress

What landed:
- End-to-end tagging and embedding flows exist.
- Immutable run lineage with model/generator identity is persisted.
- Planner/registry foundation is in place for multi-generator expansion.

Open items:
- Finalize single entry orchestrator parity sign-off and legacy deprecation plan.
- Complete and document latest-read contract methods for consumers.
- Evolve descriptor/planner model to capability-aware dependencies:
  - descriptor metadata: `produces_capabilities`, `requires_capabilities`
  - planner builds dependency-aware execution order from capability prerequisites
  - hard rule: generators must never invoke peer generators directly

## Next Update Target
- After parity confirmation in integrated runtime: mark Phase 5 complete and define legacy orchestrator decommission plan.

## Deferred Design Note — Capability Metadata (Not Implemented Yet)
- Descriptor vNext target:
  - `produces_capabilities`
  - `requires_capabilities`
- Migration sequence:
  - Stage 1: keep current `produces_outputs` + flat planning.
  - Stage 2: add capability fields on descriptors (backward compatible, no runtime graph changes yet).
  - Stage 3: teach planner/orchestrator dependency-aware capability ordering.
- Hard rule remains unchanged:
  - no generator may directly invoke another generator.
- Mental model:
  - `produces_outputs` = persisted output family
  - `produces_capabilities` = semantic capability provided
  - `requires_capabilities` = prerequisite capabilities planner must satisfy
