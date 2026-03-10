# Intelligence Orchestrator
Date: March 10, 2026
Status: Architecture design (headless, no code)

## 1) Purpose and Boundary
The Intelligence Runner / Orchestrator coordinates derived-intelligence execution as a workflow over immutable run records.

It is responsible for:
- reacting to asset readiness
- planning what to run
- creating and transitioning run records
- building run context
- invoking generators
- coordinating persistence
- finalizing runs and emitting events

It is not:
- a generator
- a repository
- a UI feature
- a canonical metadata writer

Boundary rule:
- Asset Spine remains canonical.
- Intelligence output is downstream, versioned, and regenerable.
- No orchestrator step may mutate canonical normalized metadata.

## 2) Placement in `prophoto-intelligence`
The orchestrator lives in `prophoto-intelligence` as headless domain services.

Suggested package area:
- `src/Orchestration/IntelligenceOrchestrator.php`
- `src/Orchestration/IntelligenceExecutionService.php`
- `src/Orchestration/IntelligencePersistenceService.php`
- optional future split:
  - `IntelligenceRunPlanner.php`
  - `IntelligenceRetryPolicy.php`

## 3) Core Workflow
Canonical workflow:

```text
AssetReady
-> run planning
-> run creation
-> context building
-> generator execution
-> result persistence
-> run finalization
-> event emission
```

Event payload rule:
- `AssetIntelligenceRunStarted` does not include `result_types` because outputs are not known yet.
- `result_types` is emitted on completion events after persistence succeeds.

Trigger sources:
- asset ingestion completion (`AssetReady`)
- scheduled background processing
- manual reprocessing

## 4) Run Lifecycle States
Run states:
- `pending`
- `running`
- `completed`
- `failed`
- `cancelled`

State intent:
- `pending`: run exists, not started
- `running`: generator in progress
- `completed`: all configured outputs persisted
- `failed`: run could not complete
- `cancelled`: intentionally stopped/invalidated

## 5) Partial-Result Policy (v1)
v1 is strict:
- if a run is configured for multiple output families, all required outputs must persist for `completed`
- if any required output fails, run is `failed`
- per-output partial success tracking is deferred

## 6) Retry and Idempotency Rules
Retry is orchestrator-controlled, not generator-controlled.

Retry guidance:
- retryable: transient provider/network/queue failures
- non-retryable: unsupported asset, invalid context, malformed generator output, missing asset

Idempotency requirements:
- execution must be idempotent for the same `run_id`
- retries of the same run must not duplicate labels, embeddings, or completion events
- persistence must enforce run-scoped uniqueness

Concurrency rule:
- Only one active run is allowed per
  (`asset_id`, `generator_type`, `generator_version`, `model_name`, `model_version`).
- Run creation must enforce this with a uniqueness constraint or atomic check to prevent duplicate concurrent runs.

## 7) Latest-Read Strategy
Do not maintain a mutable “current intelligence” record in v1.

Read repositories compute “latest” at query time:
- latest successful run for asset (with optional generator/model filters)
- latest labels for asset from latest successful label-producing run
- latest embedding for model family from latest successful embedding-producing run

Historical rows remain queryable and immutable.

## 8) Generator Registry Concept
The orchestrator resolves generators via a registry, not hardcoded class references.

Concept:
- registry returns available `AssetIntelligenceGeneratorContract` implementations
- orchestrator filters enabled generators by config and applicability
- generator identity (`generator_type`, `generator_version`) is always explicit

Configuration hash usage:
- `configuration_hash` represents the effective generator configuration.
- It is derived from:
  - `generator_type`
  - `generator_version`
  - `model_name`
  - `model_version`
  - effective generator configuration parameters
- Runs with identical `configuration_hash` for the same asset may be skipped when a completed run already exists.

## 9) Suggested Service/Class Responsibilities
`IntelligenceOrchestrator`
- entry point for triggers
- planning decisions
- run creation/dispatch

`IntelligenceExecutionService`
- build/load canonical metadata context
- invoke one generator
- validate returned `GeneratorResult`

`IntelligencePersistenceService`
- persist labels and embeddings by `asset_id` + `run_id`
- enforce run-scoped invariants
- trigger run finalization hooks

`IntelligenceRunRepository` (or equivalent persistence abstraction)
- create pending runs
- mark running/completed/failed/cancelled
- record failure/cancellation context
- optional future: persist `trigger_source` (`asset_ready`, `manual_reprocess`, `scheduled_batch`, `migration`) for run provenance/debugging

## 10) v1 Thin Vertical Slice
v1 is proven when both flows work end-to-end:

Tagging path:
```text
AssetReady -> orchestrator -> tagging run -> labels persisted -> run completed -> AssetIntelligenceGenerated
```

Embedding path:
```text
AssetReady -> orchestrator -> embedding run -> embedding persisted -> run completed -> AssetEmbeddingUpdated
```

## 11) Guardrails / What Not To Do
Do not:
- allow generators to write directly to DB
- allow generators to emit domain events directly
- mutate canonical asset metadata from intelligence
- create a mutable “current intelligence” table in v1
- couple gallery correctness to intelligence availability
- build UI concerns into `prophoto-intelligence`
- modify Asset Spine contracts for orchestrator concerns

Keep v1 simple, deterministic, and auditable.
