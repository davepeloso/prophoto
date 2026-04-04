# Ingest Session Association Implementation Checklist
Date: March 13, 2026
Status: Implementation checklist (no code/migrations yet)

## Scope and Constraints
- Governing docs: `RULES.md`, `SYSTEM.md`, `BOOKING-OPERATIONAL-CONTEXT.md`, `BOOKING-DATA-MODEL.md`, `SESSION-MATCHING-STRATEGY.md`, `INGEST-SESSION-ASSOCIATION-DATA-MODEL.md`.
- v1 owner for association persistence: `prophoto-ingest`.
- Keep boundaries strict:
  - no canonical media ownership changes in `prophoto-assets`
  - no session truth ownership changes in `prophoto-booking`
  - contracts/events for cross-package boundaries via `prophoto-contracts`

## Phase 1 — Contracts and Events
### Contracts/DTOs/Enums in `prophoto-contracts`
- [ ] Add enum `SessionAssignmentDecisionType` (`auto_assign|propose|no_match|manual_assign|manual_unassign`).
  - Belongs in `prophoto-contracts`: yes
  - Needed in v1: yes (shared decision semantics across ingest + consumers)
  - Defer: no
- [ ] Add enum `SessionMatchConfidenceTier` (`high|medium|low`).
  - Belongs: yes
  - Needed in v1: yes (consistent thresholds and payload semantics)
  - Defer: no
- [ ] Add enum `SessionAssociationSubjectType` (`ingest_item|asset`).
  - Belongs: yes
  - Needed in v1: yes (subject lifecycle boundary)
  - Defer: no
- [ ] Add enum `SessionAssignmentMode` (`auto|manual`).
  - Belongs: yes
  - Needed in v1: yes (effective association semantics)
  - Defer: no
- [ ] Add enum `SessionAssociationLockState` (`none|manual_assigned_lock|manual_unassigned_lock`).
  - Belongs: yes
  - Needed in v1: yes (manual precedence)
  - Defer: no
- [ ] Add DTO for assignment decision payload (subject identity, selected session, confidence, evidence envelope, trigger source).
  - Belongs: yes
  - Needed in v1: yes (stable boundary between engine and persistence/event layer)
  - Defer: no
- [ ] Add DTO for matching candidate item (rank, score, disqualifier/reason codes).
  - Belongs: yes
  - Needed in v1: optional (required only if candidate detail is exposed beyond ingest internals)
  - Defer: yes if candidate storage is deferred

### Immutable events in `prophoto-contracts`
- [ ] Add `SessionAutoAssignmentApplied`.
  - Needed in v1: yes
- [ ] Add `SessionMatchProposalCreated`.
  - Needed in v1: yes
- [ ] Add `SessionManualAssignmentApplied`.
  - Needed in v1: yes
- [ ] Add `SessionManualUnassignmentApplied`.
  - Needed in v1: yes
- [ ] Lock event payload shape with tests (stable IDs only, no model payloads; breaking changes require versioned events).
  - Needed in v1: yes

## Phase 2 — Migrations
### Required v1 migrations in `prophoto-ingest`
- [ ] Create migration: `asset_session_assignment_decisions` (first).
  - Must include append-only decision history semantics.
  - Must support subject lifecycle (`subject_type`, `subject_id`, typed IDs).
  - Must support idempotency and supersession references.
- [ ] Create migration: `asset_session_assignments` (second).
  - Must support current effective reversible state.
  - Must reference source decision.
  - Must support manual lock precedence fields and supersession chain.

### Optional migration (defer by default)
- [ ] `asset_session_match_candidates`:
  - v1 default: defer
  - enable only if operator/reporting needs require queryable per-candidate history beyond JSON payload

### Migration quality gates
- [ ] Ownership boundaries respected (`prophoto-ingest` owns these tables only).
- [ ] Foreign key direction correct (references upstream `sessions.id` and optional `assets.id`; no downstream coupling).
- [ ] Uniqueness constraints implemented (one current row per subject, idempotency controls).
- [ ] Append-only history preserved (no destructive rewrite semantics for decisions).
- [ ] Manual lock precedence fields and states implemented.
- [ ] Subject identity alignment enforced (`subject_id` matches typed ID per `subject_type`).

## Phase 3 — Persistence / Repository Layer
### Required responsibilities in `prophoto-ingest`
- [ ] `SessionAssignmentDecisionRepository`
  - Append-only decision writes
  - Decision history reads
  - Supersession linkage
- [ ] `SessionAssignmentRepository`
  - Current-effective row writes via supersession
  - Current assignment lookup by subject
  - No in-place semantic mutation
- [ ] `EffectiveSessionAssociationResolver`
  - Deterministic effective-state resolution
  - Manual lock precedence enforcement
- [ ] `SessionAssociationWriteService` (transaction boundary)
  - Persist decision
  - Apply/skip effective assignment based on decision type and lock state
  - Emit events after persistence
- [ ] `SubjectIdentityLifecycleService`
  - Pre-/post-canonical identity handling
  - Ingest-item to asset lineage continuity

### Split-of-concern rules
- [ ] Repositories do not score/rank candidates.
- [ ] Matching engine does not directly mutate tables.
- [ ] All writes go through lock-aware persistence services.

## Phase 4 — Matching Engine
### Minimal v1 engine
- [ ] Candidate generation from session context (time windows, buffers, location, calendar hints, batch context, job/session hints).
- [ ] Confidence scoring (`high|medium|low` + numeric score).
- [ ] Deterministic ranking of candidates.
- [ ] Decision classification:
  - `auto-assign`
  - `propose`
  - `no-match`
- [ ] Evidence payload construction for audit trail.

### Explicit v1 deferrals
- [ ] Defer ML/LLM-based matching.
- [ ] Defer complex segmentation (multi-stream split logic).
- [ ] Defer provider-specific matching engines.
- [ ] Defer cross-day itinerary optimization.

## Phase 5 — Manual Override Flow
- [ ] Implement manual assign flow:
  - append `manual_assign` decision
  - create superseding effective assignment row
  - set `manual_assigned_lock`
- [ ] Implement manual unassign flow:
  - append `manual_unassign` decision
  - create superseding effective unassigned row
  - set `manual_unassigned_lock`
- [ ] Implement explicit unlock flow:
  - append decision with `lock_effect=unlock`
  - clear manual lock only
  - unlock does not assign by itself
- [ ] Reprocessing behavior:
  - while lock active, automated decisions cannot supersede effective row
  - after unlock, automated/manual decisions may become effective normally

## Phase 6 — Thin Vertical Slice
### First end-to-end slice
- [ ] Fixture setup (minimum):
  - one ingest subject (`ingest_item`)
  - two candidate sessions with distinct windows
  - optional location data for one session
- [ ] Run matching once:
  - generate candidates
  - classify decision
  - persist one decision row
  - persist one effective assignment row for `auto-assign`, or proposal-only decision for `propose`
  - emit correct immutable event
- [ ] Minimum assertions:
  - decision history row exists and is immutable
  - effective association resolution is deterministic
  - subject identity fields are aligned (`subject_type`, `subject_id`, typed ID)
  - manual-lock rules not violated
- [ ] Rollback/reversibility expectation:
  - supersession creates new effective rows
  - prior rows remain historical

## Phase 7 — Acceptance Criteria
- [ ] Automated decision history is append-only.
- [ ] Effective association resolution is deterministic.
- [ ] Manual override supersedes automated state.
- [ ] Manual lock blocks automated supersession until explicit unlock.
- [ ] Unlock is lock-release only (no implicit assignment).
- [ ] Repeated idempotent decision writes do not create duplicate effective assignment rows.
- [ ] Canonical asset tables remain unchanged.
- [ ] Session truth remains owned by booking.
- [ ] Ingest owns reconciliation/association tables only.
- [ ] Tests cover:
  - `auto-assign`
  - `propose`
  - `no-match`
  - `manual_assign`
  - `manual_unassign`
  - `unlock` behavior

## Recommended Event Surface
- [ ] `SessionAutoAssignmentApplied`
  - emitted after persisted auto-assignment becomes effective
- [ ] `SessionMatchProposalCreated`
  - emitted after persisted proposal decision (no effective assignment change required)
- [ ] `SessionManualAssignmentApplied`
  - emitted after persisted manual assignment + lock
- [ ] `SessionManualUnassignmentApplied`
  - emitted after persisted manual unassignment + lock
- [ ] Event invariants:
  - immutable payloads
  - stable IDs (`decision_id`, `assignment_id` where applicable, `subject_type`, `subject_id`, optional typed IDs)
  - no model payloads

## A) Recommended Build Order
1. Finalize contract enums/DTOs/events in `prophoto-contracts`.
2. Add migration for `asset_session_assignment_decisions`.
3. Add migration for `asset_session_assignments`.
4. Implement repositories + lock-aware write service.
5. Implement minimal matching engine and wire to persistence services.
6. Implement manual override + unlock flows.
7. Add thin vertical slice tests and acceptance tests.
8. Evaluate need for `asset_session_match_candidates`; keep deferred unless required.

## B) Explicit Deferrals
- `asset_session_match_candidates` table (default defer in v1).
- ML/LLM scoring and advanced model-driven matching.
- Provider-specific heuristic engines.
- Cross-day optimization and complex session segmentation.
- Cross-package UI/admin workflows beyond existing ingest/operator surfaces.

## C) Risk Notes
- Risk: ownership drift into canonical asset tables.
  - Mitigation: keep all association writes in ingest-owned tables only.
- Risk: manual lock rules bypassed by automated reprocessing.
  - Mitigation: centralize writes in lock-aware service; add explicit lock tests.
- Risk: decision history mutation.
  - Mitigation: append-only repository API and immutability tests.
- Risk: identity drift between `subject_id` and typed IDs.
  - Mitigation: DB/application constraints enforcing alignment rules.
- Risk: overbuilding candidate persistence too early.
  - Mitigation: defer candidate table by default; use decision payload JSON in v1.

## Recommended First Coding Step
Implement Phase 1 in `prophoto-contracts`: add the five shared enums plus the four immutable assignment/proposal event classes with payload-shape lock tests before any ingest migration work begins.
