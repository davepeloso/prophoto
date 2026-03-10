# TODO (Triage)
Updated: March 10, 2026

This is the active cross-package backlog, organized by priority.

## P0 — Next Up (architecture-critical)
- [ ] Replace single-generator orchestrator wiring with registry/planner selection.
  - Current: [IntelligenceOrchestrator.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/Orchestration/IntelligenceOrchestrator.php) receives one `AssetIntelligenceGeneratorContract`.
  - Target: orchestrator chooses one or more generators from a registry using config + run planning.
- [ ] Introduce generator registry/planner to run enabled generators by policy instead of direct dual orchestration in provider.
  - Current: provider directly invokes label and embedding orchestrators on `AssetReady`.
  - Target: centralized planner decides run set and ordering.

## P1 — Hardening (non-blocking, should be done soon)
- [ ] Remove `AssetId::toInt()` assumptions in intelligence repository/services to support future non-integer asset IDs.
  - Current usage exists in [IntelligenceRunRepository.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/Repositories/IntelligenceRunRepository.php) and [IntelligencePersistenceService.php](/Users/davepeloso/Sites/prophoto/prophoto-intelligence/src/Orchestration/IntelligencePersistenceService.php).
- [ ] Tighten run lifecycle transition so `markCompleted()` only allows `running -> completed`.
  - Current implementation still allows `pending -> completed`.
- [ ] Replace weak `find(): ?object` repository return type with a typed run DTO (or typed internal model).
- [ ] Add explicit retry classification policy in code (`retryable` vs `non-retryable`) and test coverage.
- [ ] Extend transactional boundary plan for multi-output runs (labels + embeddings + future outputs) and terminal run finalization.
- [ ] Reduce duplication between label and embedding orchestrators by extracting shared run-execution helper/base flow.
  - Shared concerns to centralize: run creation, started event dispatch, run-context building, config-hash construction, failure handling/error-code mapping.

## P2 — Later (quality + scale)
- [ ] Remove demo/process marker labels (for example `asset_ready`) before production tagging behavior is introduced.
- [ ] Expand generator `meta` payload to useful diagnostics (`latency`, `provider`, `trace_id`, `applied_rule_set`).
- [ ] Re-evaluate label query strategy:
  - keep generator lineage via `run_id` join (current), or
  - denormalize generator fields on labels and add `(generator_type, label)` index.
- [ ] Evaluate adding `model_family` for embedding retrieval grouping.
- [ ] Add typed read-model(s) for “latest intelligence” query projection to avoid ad hoc query drift.
- [ ] Trim `resultTypes` from `AssetEmbeddingUpdated` payload (event name is already specific to embedding updates).

## Deferred Suggestions Captured (not yet implemented)
- [ ] Keep `AssetId::toInt()` migration-risk item on the board.
- [ ] Keep `markCompleted()` permissive transition tightening on the board.
- [ ] Keep `find(): ?object` typing improvement on the board.

## Recently Completed (for context)
- [x] `AssetIntelligenceRunStarted` no longer carries `resultTypes`.
- [x] Duplicate-run fallback query now includes `configuration_hash`.
- [x] Label-empty validity moved to orchestration; persistence now fails fast on empty labels.
- [x] Label persistence + run completion is wrapped in a transaction for the current labels-only flow.
- [x] Embedding thin vertical slice implemented (`AssetReady -> embedding run -> persist -> completed -> events`) with idempotency + malformed payload tests.
