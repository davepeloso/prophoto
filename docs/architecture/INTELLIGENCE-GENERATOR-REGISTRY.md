# Intelligence Generator Registry and Planner
Date: March 12, 2026
Status: Design proposal (architecture only, no code)

## 1) Purpose and Problem Statement
The current thin-slice setup proved the run model, persistence model, and event model, but it does not scale cleanly because orchestration is still one-orchestrator-per-generator with one injected generator per flow.

That pattern is now too narrow because:
- one asset may need multiple intelligence runs from one trigger
- generator applicability differs by media kind and readiness
- enable/disable policy is not centralized
- skip decisions (already-completed, already-active) are not planned in one place

The registry/planner solves this by separating concerns:
- registry answers: what generators exist (via descriptors) and how to resolve an implementation when execution is needed
- planner answers: what should run now for this asset/config/context
- orchestrator answers: execute planned runs safely and persist results

Why introduce this now:
- two generator families already exist (`demo_tagging`, `demo_embedding`)
- the next additions (OCR, quality scores, other derived families) will multiply orchestration branches quickly
- centralized planning is required to keep run creation deterministic and auditable

Concrete examples:
- one image asset may require both tagging and embedding runs
- a PDF asset may require tagging and OCR, but not embedding in v1
- a generator should be skipped when a matching completed run already exists for the same effective configuration

## 2) Architecture Placement
This layer lives inside `prophoto-intelligence` and remains headless domain logic.

Placement and relationship:
- `IntelligenceEntryOrchestrator` receives the trigger (for example `AssetReady`)
- `IntelligenceEntryOrchestrator` fetches existing run summaries from `IntelligenceRunRepository`
- `IntelligencePlanner` is a pure decision engine that evaluates registry + config + asset context + `existingRunSummaries` and returns run intents
- `IntelligenceGeneratorRegistry` exposes generator descriptors for planning and resolves generator implementations for execution
- `IntelligenceRunRepository` creates/claims runs and enforces active-run concurrency constraints
- `IntelligenceExecutionService` executes one resolved generator for one created run
- `IntelligencePersistenceService` validates and writes outputs

Logical flow:
1. Trigger arrives.
2. Orchestrator fetches existing run summaries via repository.
3. Planner returns planned and skipped intents.
4. Orchestrator creates/claims each planned run via repository.
5. Orchestrator executes and persists per run.
6. Orchestrator transitions lifecycle and emits events.

This preserves headless boundaries:
- no UI coupling
- no Asset Spine mutation
- no dependency from `prophoto-assets` to `prophoto-intelligence`

## 3) Registry Responsibilities
The generator registry is responsible for generator discovery, descriptor exposure, and on-demand resolution, not orchestration.

Responsibilities:
- discover/register available generators
- expose lightweight descriptors for planning without constructing generator implementations
- expose descriptors by `generator_type`
- return all descriptors
- resolve a concrete generator implementation by `generator_type` only when orchestrator executes a planned run
- support filtering by declared capability family or type

Descriptor shape (conceptual):
- `generator_type`
- `generator_version`
- `supported_media_kinds`
- `produces_outputs` (for example `labels`, `embeddings`, `scores`)
- `default_model_name`
- `default_model_version`

Registry API concept:
- `descriptors()`
- `resolve(generator_type)`

Not responsible for:
- creating runs
- persisting outputs
- lifecycle transitions
- retry policy
- event emission
- planner skip decisions

## 4) Planner Responsibilities
The planner determines what should run now and what should be skipped, but does not execute generators.

Responsibilities:
- inspect asset context and canonical metadata
- evaluate generator applicability by media/readiness rules
- evaluate config enable/disable rules
- evaluate skip conditions using provided `existingRunSummaries`
- return a deterministic list of run intents and skip intents

Planner purity rule:
- planner does not query repositories or databases
- planner consumes only input arguments and returns decisions
- planner behavior must be unit-testable without database setup

Planner inputs:
- `asset_id`
- canonical metadata/context
- registry descriptors snapshot
- intelligence configuration
- `existingRunSummaries` (completed and active), fetched by orchestrator
- trigger source and run scope defaults

Planner outputs:
- planned run intents (ready for run creation/execution)
- skipped intents with explicit reasons

Non-responsibilities:
- direct provider/model execution
- repository/database queries
- DB writes
- event dispatch

## 5) Proposed Planner Output Model
A planner output should be explicit enough for orchestrator execution and audit logging.

Run intent fields:
- `asset_id`
- `generator_type`
- `generator_version`
- `model_name`
- `model_version`
- `configuration_hash`
- `run_scope`
- `trigger_source`
- `required_outputs`
- `decision` (`planned` or `skipped`)
- `skip_reason` (nullable)

Definitions:
- Registered generator: available in registry.
- Applicable generator: descriptor-supported for this asset context/media kind.
- Planned run: applicable and enabled, with no blocking skip condition.
- Skipped run: evaluated by planner and not scheduled now, with reason recorded.

`required_outputs` rule:
- planner does not invent outputs
- `required_outputs` is sourced from descriptor `produces_outputs` for the selected generator

Decision reason rule:
- planner returns exactly one outcome per descriptor: `planned` or `skipped`
- `skip_reason` is used only when `decision=skipped`
- planned runs do not require a separate reason in v1

## 6) Applicability Rules
Applicability must be explicit and deterministic, based on generator policy + asset context.

Examples:
- tagging generator: applies to image assets (and optionally PDFs if declared)
- embedding generator (v1): applies to image assets only
- OCR generator (future): applies to PDF and image assets
- unsupported media kinds: skipped cleanly, not treated as failures

Planner checks should answer:
- does this generator apply to this asset/media context?
- is this generator enabled by config?
- does a matching completed run already exist (within `existingRunSummaries`)?
- is an active run already in progress (within `existingRunSummaries`)?

Applicability belongs to planning policy, not ad hoc branching in orchestrator internals.

## 7) Skip Logic
Skip logic is planner-owned and should run in a stable order so decisions are explainable.

Suggested skip reasons:
- `disabled_by_config`
- `unsupported_media_kind`
- `asset_not_ready`
- `matching_completed_run_exists`
- `active_run_exists`

Guidance:
- planner computes intent-level skip decisions before execution
- orchestrator does not reinterpret skip policy; it executes only planned intents
- orchestrator supplies `existingRunSummaries`; planner does not fetch them
- generators do not decide whether runs should exist
- skip reasons should be loggable for observability/debugging

Configuration-change behavior:
- v1 handles this implicitly: if no matching completed run exists for the current `configuration_hash`, planner returns `decision=planned`

## 8) Configuration Model
Configuration should be declarative and hashable.

Configuration concepts:
- global intelligence enabled flag
- per-generator enabled flag
- `generator_version`
- `model_name`
- `model_version`
- generator-specific options (for example thresholds, prompt version, output controls)
- future operational knobs (queue, priority, retry class)

`configuration_hash` should be derived from the effective run-defining configuration, including:
- generator identity/version
- model identity/version
- outputs from descriptor `produces_outputs` (materialized into intent `required_outputs`)
- effective generator options

Hash guidance:
- use normalized deterministic serialization (stable key ordering) so equal configuration yields equal hash
- exclude runtime-only values (timestamps, transient IDs)

## 9) Suggested Contracts / Internal Interfaces
Most of this layer should remain internal to `prophoto-intelligence` because it is orchestration policy, not cross-package API.

Suggested internal interfaces/classes:
- `IntelligenceGeneratorRegistry`
- `GeneratorDescriptor`
- `IntelligencePlanner`
- `PlannedIntelligenceRun` (or equivalent intent DTO)
- `GeneratorApplicabilityPolicy`
- `PlannerDecisionReason` (enum/value object for skip reasons)

Interface boundary note:
- `IntelligencePlanner` should accept plain inputs (including `existingRunSummaries`) and must not depend on `IntelligenceRunRepository`.

Package boundary guidance:
- keep planner/registry interfaces internal to `prophoto-intelligence`
- keep generator execution contract in `prophoto-contracts` (`AssetIntelligenceGeneratorContract`)
- keep domain events in `prophoto-contracts`
- avoid exporting planner internals as cross-package contracts until another package truly needs them

## 10) Orchestrator Evolution Path
This should be an incremental evolution, not a rewrite.

Current:
- separate thin-slice orchestrators (`demo_tagging`, `demo_embedding`)
- provider wiring decides which orchestrators run

Target:
- single entry orchestrator handles the trigger
- orchestrator fetches existing run summaries
- planner computes intents
- registry provides descriptors for planning, then resolves implementations for execution
- repository creates/claims runs
- execution/persistence happen per planned run

Incremental path:
1. Add registry and planner services alongside existing orchestrators.
2. Introduce a single entry orchestration path behind current trigger handling.
3. Keep existing execution/persistence services and run repository.
4. Decommission per-generator orchestrators after parity tests pass.

## 11) Minimal v1 Registry/Planner Recommendation
Build the smallest useful system first.

Minimal v1:
- static registry with two generators:
  - `demo_tagging`
  - `demo_embedding`
- each generator is represented by a descriptor (`generator_type`, `generator_version`, `supported_media_kinds`, `produces_outputs`, default model identity)
- planner evaluates only:
  - media kind support
  - enabled/disabled config
  - matching completed-run skip logic (same `configuration_hash`)
  - active-run skip logic
- orchestrator prefetches run summaries and passes them into planner as `existingRunSummaries`
- planner returns planned/skipped intents
- orchestrator executes planned intents using existing execution/persistence services

Do not add dynamic plugin loading, rule DSLs, or workflow engines in v1.

## 12) Guardrails / What Not To Do
Avoid these mistakes:
- hardcoding generator selection deep inside orchestrator execution branches
- putting provider SDK logic inside planner
- allowing generators to decide whether runs should exist
- introducing mutable “current intelligence” write models
- coupling gallery behavior directly to planner internals
- making planner write to DB directly
- overbuilding a generic workflow engine before real complexity demands it

Preserve existing invariants:
- Asset Spine is canonical and immutable from intelligence
- intelligence outputs are append-only and regenerable
- latest/current remains query-time
- `prophoto-assets` stays independent of intelligence
- `prophoto-intelligence` remains headless

## Recommended First Implementation Step
Implement an internal static `IntelligenceGeneratorRegistry` that exposes descriptor metadata via `descriptors()` and execution resolution via `resolve(generator_type)`, then implement a pure `IntelligencePlanner` that consumes descriptors + `existingRunSummaries` to produce intents whose `required_outputs` come from descriptor `produces_outputs`.
