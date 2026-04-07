# ProPhoto Ingest

## Purpose

Owns the ingest item lifecycle, session-to-asset matching pipeline, assignment decision persistence, and the effective association state between assets and sessions. This is the entry point of the core event loop — ingest decides which session an asset belongs to, persists that decision with full audit trail, and emits the event that triggers downstream processing.

## Core Loop Role

Ingest is **position 1** in the core event loop. It is the decision-maker.

```
► prophoto-ingest  ──(SessionAssociationResolved)──►  prophoto-assets
  prophoto-assets  ──(AssetSessionContextAttached)──►  prophoto-intelligence
  prophoto-assets  ──(AssetReadyV1)──────────────────►  prophoto-intelligence
```

If this package is removed, no session-to-asset matching occurs, no assignment decisions are persisted, and `SessionAssociationResolved` is never emitted. The assets package has nothing to react to. The entire downstream pipeline stops.

## Responsibilities

- IngestItem domain entity (ingest item lifecycle)
- Session matching pipeline: candidate generation → scoring → decision classification
- SessionMatchCandidateGenerator: filters sessions by status and time window, applies travel buffers
- SessionMatchScoringService: deterministic v1 scoring with time/location/semantic/operational weights
- SessionMatchDecisionClassifier: classifies scores into auto_assign, propose, or skip based on confidence tiers
- SessionAssociationWriteService: persists decisions with append-only audit trail, supersession chain, manual lock enforcement
- SessionAssignmentDecisionRepository: append-only decision persistence
- SessionAssignmentRepository: current assignment state with partial unique index
- IngestItemContextBuilder: builds context for matching from asset metadata and booking data
- IngestItemSessionMatchingFlowService: orchestrates the full matching flow
- SessionMatchingService: top-level matching coordination
- IngestServiceProvider: registers config, migrations

## Non-Responsibilities

- MUST NOT mutate asset records — assets package owns canonical asset truth
- MUST NOT perform intelligence operations — intelligence is a separate downstream concern
- MUST NOT mutate booking data — booking package owns session truth
- MUST NOT emit AssetSessionContextAttached or AssetReadyV1 — those belong to the assets package
- MUST NOT bypass the event system to push data directly into downstream packages

## Integration Points

- **Events emitted:** `SessionAssociationResolved` (defined in prophoto-contracts, dispatched after assignment decision is committed)
- **Events listened to:** None — ingest is triggered by the ingest flow, not by events from other packages
- **Contracts depended on:** `prophoto/contracts` (DTOs, enums, event classes), `prophoto/assets` (asset references), `prophoto/booking` (session data for matching)
- **Consumed by:** prophoto-assets (listens to `SessionAssociationResolved`)

## Data Ownership

| Table | Purpose |
|---|---|
| `asset_session_assignment_decisions` | Append-only decision audit trail. Every matching decision is recorded with scores, confidence tier, mode, lock state, and idempotency key. Rows are never updated or deleted. |
| `asset_session_assignments` | Current assignment state. Partial unique index on (subject_type, subject_id) WHERE superseded_at IS NULL ensures exactly one active assignment per subject. |

## Notes

- Decisions use an append-only pattern with supersession chains — old decisions get `superseded_at` and `superseded_by_assignment_id` when a new decision is made
- Manual locks (manual_assigned_lock, manual_unassigned_lock) block automated assignment changes — only explicit manual actions can override
- Events are dispatched only after the database transaction commits, never inside
- The matching pipeline is fully deterministic (v1): time weight=0.55, location=0.20, semantic=0.15, operational=0.10
- Confidence tiers: HIGH >= 0.85, MEDIUM >= 0.55, LOW < 0.55
- Travel buffer model uses separate `travel_buffer_before_minutes` and `travel_buffer_after_minutes` per BOOKING-DATA-MODEL.md
- ServiceProvider: `ProPhoto\Ingest\IngestServiceProvider` (auto-discovered)
- Key architecture docs: `INGEST-SESSION-ASSOCIATION-DATA-MODEL.md`, `SESSION-MATCHING-STRATEGY.md`
