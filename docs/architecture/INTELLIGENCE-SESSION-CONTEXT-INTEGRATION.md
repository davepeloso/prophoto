# Intelligence Session Context Integration
Date: April 4, 2026
Status: Architecture design (no code)

## 1) Purpose and Boundary
Define how `prophoto-intelligence` can consume booking/session operational context to improve planning and generator applicability while preserving package ownership boundaries.

Boundary rules:
- Intelligence consumes session context; it does not own it.
- Session truth remains owned by `prophoto-booking`.
- Canonical media truth remains owned by `prophoto-assets`.
- Planner remains pure (no DB/repository calls inside planner).
- `prophoto-intelligence` must not query booking tables directly.
- Integration must occur through contracts/DTOs/events and ID-based references.

## 2) Why Session Context Improves Intelligence Quality
Session context improves generator relevance and reduces low-value runs by adding operational intent:
- session type narrows expected semantics (for example wedding ceremony vs corporate headshot).
- job type gives stronger priors for labels/scores.
- location/time-window context can improve applicability and filtering.
- explicit session linkage reduces ambiguity for downstream retrieval and ranking.

Practical impact:
- fewer unnecessary runs
- better model/generator selection
- better skip decisions
- better quality of generated labels/scores for the real shoot context

## 3) Where Session Context Enters the Current Intelligence Flow
Current flow:
`AssetReady -> EntryOrchestrator -> Planner -> Run creation -> RunContext build -> Generator execute`

Session context should enter at two points:
1. Before planning:
   - Orchestrator receives a pre-resolved session snapshot from a contract-based provider.
   - Planner uses snapshot only as input data.
2. Before execution:
   - Orchestrator enriches `IntelligenceRunContext` with the same snapshot used by planner.

Important:
- The orchestrator (or upstream caller) resolves session context.
- Planner and generators never query booking storage directly.

## 4) Proposed `SessionContextSnapshot` Concept
Introduce a small, immutable, transport-safe snapshot DTO (in `prophoto-contracts`) for planning/execution input.

Conceptual fields:
- `asset_id`
- `session_id` (nullable when none)
- `booking_id` (nullable umbrella context)
- `session_status`
- `session_type` (nullable)
- `job_type` (nullable)
- `session_timezone` (nullable)
- `session_window_start` / `session_window_end` (nullable)
- `location_hint` (nullable, compact text or normalized summary)
- `association_source` (`auto|manual|proposal|none`)
- `association_confidence_tier` (nullable)
- `context_reliability` (`high|medium|low|none`)
- `manual_lock_state` (`none|manual_assigned_lock|manual_unassigned_lock`)
- `snapshot_version`
- `snapshot_captured_at`

Design intent:
- immutable input envelope for one planning/execution cycle
- minimal operational context only (no booking ownership transfer)
- safe to persist in run metadata for provenance

Reliability baseline guidance:
- manual assigned session -> `high`
- auto-assigned with strong match -> `medium`
- calendar-derived with stale/conflict signals -> `low`
- no session context -> `none`

Normalization and versioning rules:
- snapshot must be normalized before planner input:
  - timestamps normalized to UTC
  - timezone values normalized to consistent IANA identifiers
  - location represented as normalized structured hints (not raw provider strings only)
- breaking snapshot shape/semantic changes require `snapshot_version` bump
- planner must evaluate `snapshot_version` explicitly when behavior differs by version

## 5) How `IntelligencePlanner` Inputs Should Evolve
Evolve planner input shape to include optional session snapshot:
- existing: `asset_id`, canonical metadata, descriptors, config, existing run summaries, trigger source, run scope
- new: `sessionContextSnapshot` (nullable)

Planner purity remains unchanged:
- planner accepts snapshot as an argument
- planner does not fetch session state itself
- planner decisions remain deterministic from explicit inputs

If snapshot is missing:
- generators that do not require session context can still plan normally
- generators that require session context should return skipped intents with explicit reason

## 6) How `GeneratorDescriptor` Should Evolve
Extend descriptor metadata with session-context applicability controls:

- `requires_session_context: bool`
  - `true`: planner must skip when snapshot missing or unusable
  - `false`: planner may run without session context

- `preferred_session_types: list<string>`
  - applicability preference list (for scoring/skip policy)
  - empty means no session-type preference

- `preferred_job_types: list<string>`
  - booking/job preference list
  - empty means no job-type preference

Descriptor policy guidance:
- keep these as planner metadata, not generator runtime branching directives
- planner uses them to classify `planned|skipped` and reason codes

## 7) How `IntelligenceRunContext` Should Be Enriched Safely
Enrich run context with session snapshot in a boundary-safe way:
- add optional structured session snapshot field (preferred), or
- add namespaced `metadataContext['session_context']` payload in v1 if contract DTO expansion is deferred

Safety requirements:
- context enrichment must be read-only at generator runtime
- snapshot should be recorded exactly as planned input (for reproducibility)
- no direct booking-table access from generators
- no mutation of session truth from intelligence execution
- one run uses one fixed snapshot; snapshot must not change during run execution

## 8) Example Context-Aware Generator Types
Examples that benefit from session context:
- `event_scene_tagging`
  - uses session/job type priors for label weighting
- `deliverable_readiness_scoring`
  - weights outputs based on session type expectations
- `style_consistency_scoring`
  - compares asset style against session-specific shoot intent
- `venue_semantic_enrichment`
  - uses location/session hints to improve contextual labels

Non-context-required examples:
- generic embeddings for semantic retrieval can remain session-agnostic in v1

## 9) New Applicability / Skip Rules
Add planner decision reasons for session-context-aware planning:
- `session_context_required_but_missing`
- `session_context_locked_unassigned`
- `session_context_incompatible_session_type`
- `session_context_incompatible_job_type`
- `session_context_reliability_too_low`

Policy guidance:
- hard skip:
  - `requires_session_context=true` and snapshot missing
  - `requires_session_context=true` and manual lock indicates intentionally unassigned context
  - `requires_session_context=true` and `context_reliability=low|none`
- soft preference:
  - preferred type mismatch may skip or may plan with downgraded priority based on config policy
  - `requires_session_context=false` and low reliability should remain plannable, using lowered priority/weight or reduced-context mode
- existing skip logic still applies (`disabled_by_config`, `unsupported_media_kind`, active/matching run checks)

## 10) Guardrails / What Not To Do
- Do not make `prophoto-intelligence` query booking tables directly.
- Do not persist or mutate booking truth inside intelligence-owned tables as source of truth.
- Do not treat session snapshot as canonical; it is execution context only.
- Do not break planner purity by adding repository calls.
- Do not bypass `prophoto-contracts` for cross-package session context transport.
- Do not block all intelligence runs when session context is absent unless descriptor explicitly requires it.
- Do not mutate canonical asset metadata using session-context-driven intelligence logic.

## 11) Minimal v1 Recommendation
Keep v1 narrow and low-risk:
- add optional `SessionContextSnapshot` input path via contract boundary
- extend planner to accept optional snapshot
- extend descriptors with:
  - `requires_session_context`
  - `preferred_session_types`
  - `preferred_job_types`
- include `context_reliability` in snapshot and enforce required-context reliability gate
- add only minimal new skip reasons needed for strict required-context behavior
- start with one context-aware generator family and keep existing generators unchanged

v1 deferrals:
- no direct intelligence-owned session caches
- no complex multi-session dependency graphs
- no provider-specific booking adapters inside intelligence
- no attempt to make intelligence authoritative for session assignment
