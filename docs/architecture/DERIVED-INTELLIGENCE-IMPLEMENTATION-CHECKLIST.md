# Derived Intelligence Implementation Checklist
Date: March 10, 2026
Status: Planning checklist (no code changes yet)

## Scope and Guardrails
- Asset Spine remains canonical (`prophoto-assets` is source of truth).
- Derived intelligence is regenerable, versioned, and non-canonical.
- `prophoto-assets` must not depend on `prophoto-intelligence`.
- Contracts/events live in `prophoto-contracts`.
- `prophoto-intelligence` remains headless (no UI components).

## Phase 1 — Contracts in `prophoto-contracts`
- [ ] Add `AssetIntelligenceGeneratorContract`
- [ ] Add `AssetLabelRepositoryContract`
- [ ] Add `AssetEmbeddingRepositoryContract`
- [ ] Add DTOs needed for contract boundaries (run context, label result, embedding result, latest-query filters)
- [ ] Add enum/value objects for run status and run scope (`pending|running|completed|failed|cancelled`, `single_asset|batch|reindex|migration`)
- [ ] Add contract-level tests for DTO shape and serialization compatibility

## Phase 2 — Events in `prophoto-contracts`
- [ ] Add `AssetIntelligenceRunStarted`
- [ ] Add `AssetIntelligenceGenerated`
- [ ] Add `AssetEmbeddingUpdated`
- [ ] Ensure payloads include stable IDs and generator/model identity:
  - `asset_id`, `run_id`, `generator_type`, `generator_version`, `model_name`, `model_version`
  - `result_types` for generation-complete event
- [ ] Add event shape lock tests (constructor/signature/required fields)
- [ ] Document immutability/versioning rule (breaking changes require new versioned event)

## Phase 3 — Package Scaffold (`prophoto-intelligence`)
- [ ] Create package skeleton and service provider
- [ ] Register package config (generator toggles, default model settings, queue settings)
- [ ] Add headless domain services (no controllers/pages/Filament/Inertia)
- [ ] Bind contract implementations in service provider
- [ ] Add package test harness (unit + feature)

## Phase 4 — Migrations and Data Ownership (`prophoto-intelligence`)
- [ ] Add migration: `intelligence_runs`
  - required columns: `asset_id`, `generator_type`, `generator_version`, `model_name`, `model_version`, `run_scope`, `run_status`, timing fields, `configuration_hash`
  - indexes: `asset_id`, `(asset_id, created_at)`, `(generator_type, model_name, model_version)`, `run_status`
- [ ] Add migration: `asset_labels`
  - required columns: `asset_id`, `run_id`, `label`, `confidence`
  - unique/integrity: enforce run-level idempotency (`run_id + label` uniqueness in v1)
  - indexes: `asset_id`, `run_id`, `(asset_id, label)`
- [ ] Add migration: `asset_embeddings`
  - required columns: `asset_id`, `run_id`, `embedding_vector`, `vector_dimensions`
  - unique/integrity: one embedding per asset per run (`run_id` uniqueness in v1)
  - indexes: `asset_id`, `run_id`, model/run lookup via join to `intelligence_runs`
- [ ] Enforce FK relationships to `intelligence_runs` and canonical asset references
- [ ] Confirm no migration touches canonical asset tables or normalized metadata tables
- [ ] Optional future field (not v1): `parent_run_id` for run lineage chaining (for example embedding run -> clustering run)

## Phase 5 — Service and Repository Responsibilities
- [ ] Implement run orchestration service:
  - validate canonical asset exists before run creation
  - create run (`pending`)
  - transition `pending -> running -> completed|failed|cancelled`
  - record failure context and cancellation reason
- [ ] Implement generator execution service:
  - load canonical input context by `asset_id`
  - invoke configured generator(s)
  - produce run-scoped outputs
  - ensure generator execution is idempotent for the same `run_id`
- [ ] Implement persistence services:
  - write labels tied to `run_id`
  - write embedding tied to `run_id`
  - enforce v1 partial-result policy (multi-output run fails if any configured output fails)
- [ ] Implement read repositories behind contracts:
  - latest successful run for asset
  - latest labels for asset (optionally scoped by generator/model)
  - latest embedding for model family
- [ ] Emit events at lifecycle points (`RunStarted`, `Generated`, `EmbeddingUpdated`)

## Phase 6 — Minimal Vertical Slice Test Plan
- [ ] Contract tests (`prophoto-contracts`)
  - DTO and event payload shape locks
  - enum coverage for run states/scope
- [ ] Package tests (`prophoto-intelligence`)
  - run lifecycle transitions, including `cancelled`
  - failure behavior and retry semantics
  - partial-result policy enforcement
- [ ] Integration tests (sandbox)
  - `AssetReady` trigger creates intelligence run
  - successful tag + embedding generation persists rows and emits events
  - failed multi-output run records `failed` and does not mark `completed`
  - latest-read queries return correct run-scoped results without mutating history
  - reprocessing (new model/generator/config) creates new runs and preserves old rows

## Phase 7 — v1 Implemented Acceptance Criteria
- [ ] AI tag generation works end-to-end for at least one asset flow
- [ ] Embedding generation works end-to-end for at least one asset flow
- [ ] `intelligence_runs` records run lineage with generator/model identity and status
- [ ] Event contracts are emitted with immutable, stable payloads including `asset_id`
- [ ] Latest-read contract methods return expected results without direct table querying by consumers
- [ ] Reprocessing creates new runs/results (no historical overwrite)
- [ ] Canonical metadata remains unchanged by intelligence operations
- [ ] No UI surface introduced in `prophoto-intelligence`

## Out of Scope for v1
- Face recognition
- OCR text extraction
- Ranking/recommendation engines
- Aesthetic scoring
- Clustering/similarity orchestration
