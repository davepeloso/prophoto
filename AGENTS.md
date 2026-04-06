# ProPhoto Workspace Context for Windsurf

## What this repo is

ProPhoto is a multi-package Laravel ecosystem for photography operations. It is not a monolith with random feature folders. It is a package-oriented platform with strict ownership boundaries.

The core idea is:

- `prophoto-assets` owns canonical media truth
- `prophoto-booking` owns operational/session truth
- `prophoto-ingest` owns ingest-time reconciliation and asset/session association decisions
- `prophoto-intelligence` owns derived intelligence runs and outputs
- `prophoto-contracts` owns shared DTOs, enums, events, and cross-package contracts

This repo should be treated as a long-lived architecture, not a rapid prototype.

## Authoritative docs

Always read these first before making architectural decisions.

Global:

1. `RULES.md`
2. `SYSTEM.md`

Booking / ingest / session association:
3. `docs/architecture/BOOKING-OPERATIONAL-CONTEXT.md`
4. `docs/architecture/BOOKING-DATA-MODEL.md`
5. `docs/architecture/SESSION-MATCHING-STRATEGY.md`
6. `docs/architecture/INGEST-SESSION-ASSOCIATION-DATA-MODEL.md`
7. `docs/architecture/INGEST-SESSION-ASSOCIATION-IMPLEMENTATION-CHECKLIST.md`

Intelligence:
8. `docs/architecture/DERIVED-INTELLIGENCE-LAYER.md`
9. `docs/architecture/INTELLIGENCE-ORCHESTRATOR.md`
10. `docs/architecture/INTELLIGENCE-RUN-DATA-MODEL.md`
11. `docs/architecture/INTELLIGENCE-GENERATOR-REGISTRY.md`
12. `docs/architecture/INTELLIGENCE-SESSION-CONTEXT-INTEGRATION.md`
13. `docs/architecture/DERIVED-INTELLIGENCE-IMPLEMENTATION-CHECKLIST.md`

If a task touches both ingest/session context and intelligence, load all 1–13.

## Non-negotiable architecture rules

Do not violate these.

1. Intelligence must not mutate canonical asset truth.
2. Booking must not own media truth.
3. Assets must not own booking/session truth.
4. Ingest owns reconciliation and association state, not booking truth and not canonical asset truth.
5. Cross-package communication should prefer contracts/events, not direct concrete coupling.
6. Planner logic stays pure. No DB queries inside planners.
7. Listeners stay thin. Orchestrators/services do the real work.
8. Append-only history is preferred where architecture says so.
9. Manual overrides and manual locks must not be silently bypassed by automated flows.
10. Avoid “array soup” when a stable DTO/domain object should exist.

## Current architectural state

This is the current known-good direction.

### Assets

`prophoto-assets` is the Asset Spine and canonical media owner.

There is now an asset-side projection table for session context:

- `asset_session_contexts`

Assets consume ingest decisions through:

- `SessionAssociationResolved` event

Assets then emit an asset-domain event:

- `AssetSessionContextAttached`

That event should be treated as the asset-side signal for downstream consumers, especially intelligence.

### Booking

`prophoto-booking` is the operational context spine.

It owns:

- bookings
- sessions
- session time windows
- session locations
- calendar linkage

Booking does not own assets and does not own ingest association state.

### Ingest

`prophoto-ingest` is now the owner of:

- session assignment decisions
- effective asset/session association state
- ingest-time matching logic
- ingest item lifecycle entry into matching

Important tables:

- `asset_session_assignment_decisions`
- `asset_session_assignments`

Core services already established:

- `SessionAssignmentDecisionRepository`
- `SessionAssignmentRepository`
- `SessionAssociationWriteService`
- `SessionMatchCandidateGenerator`
- `SessionMatchScoringService`
- `SessionMatchDecisionClassifier`
- `SessionMatchingService`
- `IngestItemContextBuilder`
- `IngestItemSessionMatchingFlowService`

Key behavior:

- decision history is append-only
- effective assignment supersedes prior rows instead of rewriting meaning in place
- manual locks block automated supersession
- matching is deterministic, not ML-driven
- ingest emits `SessionAssociationResolved`
- assets consume that and emit `AssetSessionContextAttached`

### Intelligence

`prophoto-intelligence` owns derived runs and outputs.

Core architecture already established:

- generator registry
- pure planner
- entry orchestrator
- run repository
- execution service
- persistence service
- context-aware generators via `SessionContextSnapshot`

Important rule:
Session-aware intelligence must consume a `SessionContextSnapshot`, not query booking directly.

Important current behavior:
The asset-session trigger path now passes a real `SessionContextSnapshot` into intelligence orchestration.

## Package mental model

Use this when deciding where code belongs.

### `prophoto-contracts`

Put shared DTOs, enums, events, and contracts here only when they are truly cross-package.

Do not move package-internal policy objects here unless multiple packages really need them.

### `prophoto-assets`

Put canonical media ownership, asset projections, and asset-domain events here.

Good fit:

- `AssetSessionContextAttached`
- asset-side projections
- asset listeners reacting to ingest decisions

Bad fit:

- ingest reconciliation decisions
- booking ownership
- matching logic

### `prophoto-booking`

Put booking/session/calendar operational truth here.

Bad fit:

- media truth
- ingest matching decisions
- intelligence persistence

### `prophoto-ingest`

Put ingest item lifecycle, matching, assignment decisions, effective association rows, manual override logic here.

Bad fit:

- asset ownership
- booking ownership
- intelligence run logic

### `prophoto-intelligence`

Put planners, registries, orchestrators, generators, derived outputs, and run persistence here.

Bad fit:

- direct booking queries
- asset mutation
- ingest ownership

## Current coding style expectations

When modifying this repo:

- Prefer small, explicit services over huge manager classes.
- Prefer deterministic logic over clever magic.
- Prefer enum-backed semantics over string drift.
- Keep event payloads scalar/enum/DTO only.
- Use tests to lock behavior before broadening scope.
- Keep listeners thin.
- Keep repositories simple and honest.
- Keep service boundaries obvious.
- Do not silently broaden a slice beyond the requested scope.

## How to approach a task

For any non-trivial task:

1. Identify which package owns the concern.
2. Load the relevant authoritative docs first.
3. State assumptions briefly before changing code.
4. Make the smallest slice that satisfies the architecture.
5. Add or update focused tests.
6. Do not refactor unrelated areas opportunistically.

## What to avoid

Do not do these without explicit need:

- inventing a new ownership model
- moving logic into the wrong package “for convenience”
- adding UI before domain rules are stable
- using direct table access across package boundaries when a contract/event should be used
- bypassing manual lock rules
- replacing append-only history with mutable current-state hacks
- mixing old legacy ingest assumptions into new ingest code
- introducing AI/ML matching before deterministic behavior is fully locked

## Legacy warning

`_archive/prophoto-ingest-legacy` is archived reference material only.

It may contain useful UI ideas or implementation ideas, but it is not the architectural source of truth for the new ingest system.

Do not copy legacy patterns into the new ingest package unless they clearly fit the approved architecture.

## Current project state summary

This is the important system loop now in place:

Ingest item
-> matching
-> session assignment decision persisted
-> `SessionAssociationResolved`
-> asset listener persists asset session context
-> `AssetSessionContextAttached`
-> intelligence listener can trigger context-aware intelligence

Treat this as the platform spine.

## Response rules for Windsurf

When asked to make a change:

- first state which package owns it
- name the docs being followed
- say what files will be changed
- do not broaden scope
- keep architecture intact
- prefer correctness over cleverness

If there is ambiguity, prefer asking:
“Which package should own this?”
instead of making a cross-boundary shortcut.

## If proposing a new slice

Always define:

- package owner
- input boundary
- persistence boundary
- event boundary
- tests to add
- what is explicitly out of scope

## Immediate priority guidance

If no other direction is given, prioritize:

1. stabilizing package-level correctness
2. preserving package boundaries
3. event-driven integration between packages
4. deterministic workflows before UI
5. session-context-aware intelligence over generic blind automation