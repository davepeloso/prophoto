# Session Matching Strategy
Date: March 13, 2026
Status: Architecture design (no code/migrations yet)

## 1) Purpose and Boundary
Define how ProPhoto infers and proposes likely `session_id` assignment by combining booking/session operational context with media/import context.

Boundary rules:
- Session matching is a context-association decision, not media ownership.
- Matching must not mutate canonical asset ownership/state in `prophoto-assets`.
- Matching may produce:
  - a proposal for operator review, or
  - an explicit association record between an `asset_id` (or ingest item) and `session_id`.
- `prophoto-booking` remains source of truth for session context.
- `prophoto-assets` remains source of truth for canonical media identity/metadata.

## 2) Inputs Used for Matching
Matching should use weighted evidence from both operational context and media/import context.

### A) Capture timestamps
- Asset capture datetime (and timezone normalization) compared against session planned window.
- Distance from window center/start/end.
- Out-of-window offsets (minutes early/late).

### B) GPS/location
- Asset GPS compared with session location coordinates or geocoded address centroid.
- Distance bucket (same venue/nearby/far).
- Presence/absence of reliable GPS signal.

### C) Session time windows
- Planned start/end from `session_time_windows`.
- Session status gating (`confirmed`, `in_progress` preferred).
- Sequence-aware day context (earlier/later sessions).

### D) Travel/setup/teardown buffers
- Include configured buffers to build effective matching windows.
- Differentiate:
  - in core window
  - in buffer-only window
  - fully outside buffer

### E) Upload/import batch context
- Batch import time and source.
- Folder/card naming hints.
- Existing operator-selected batch label or ingest mode.
- Batch-level consistency (multiple assets in batch matching same session).

### F) Calendar title/location hints
- Calendar title keywords matched against booking/session names.
- Event location snapshot text matched against asset/import hints.
- Reconciliation/sync state influence (stale/conflict lowers confidence).

### G) Booking/job type hints
- Booking `job_type` and session `session_type` matched against import/source hints.
- Day-level shoot pattern hints (for example ceremony + portraits sequence).

## 3) Confidence Model
Use a weighted confidence score in range `0.00 - 1.00` plus evidence tags.

Evidence families (recommended):
- temporal evidence (time window + buffers)
- spatial evidence (GPS/address proximity)
- semantic evidence (title/job/import naming hints)
- operational evidence (session status, calendar reconciliation state)
- batch coherence evidence (other assets in same batch)

Confidence tiers:
- High confidence: `>= 0.85`
  - strong temporal match + strong spatial or strong batch coherence
  - no hard conflict signals
- Medium confidence: `0.55 - 0.84`
  - plausible candidate with partial evidence or mild conflicts
- Low confidence: `< 0.55`
  - weak/ambiguous signals or strong conflict signals

Hard downgrades:
- Session cancelled/no_show.
- Calendar link in `conflict` or severe `sync_error` with stale snapshots.
- Capture time significantly outside effective window and buffers.

## 4) Matching Outcomes
Exactly one primary outcome per decision:

- `auto-assign`
  - allowed only for high-confidence matches and when no close competing candidate exists.
- `propose_for_review`
  - default for medium confidence, or high confidence with meaningful contention.
- `no_match`
  - low confidence or no candidate passes minimum viability threshold.

Operational behavior:
- Auto-assign should still emit audit evidence and remain reversible.
- Proposal flow should include top candidates with reason codes.
- No-match should preserve explanation for later operator action.

## 5) Ranking Logic When Multiple Sessions Are Candidates
Use ranked candidate scoring per asset (or per ingest item) and resolve ties deterministically.

Recommended rank components:
- Time proximity score (highest weight).
- Buffer class preference: core window > setup/travel/teardown buffer > outside.
- Location proximity score.
- Session status priority: `in_progress`/`confirmed` > `tentative` > terminal states.
- Semantic hint score from title/location/import metadata.
- Batch coherence score (candidate consistency within same ingest batch).
- Calendar health modifier (reconciliation/sync state).

Tie-break rules (in order):
1. Higher total confidence score.
2. Candidate in core window over buffer-only window.
3. Smaller time delta to planned start.
4. Smaller location distance.
5. Lower `sequence_index` distance from batch median capture time trend.
6. Stable deterministic fallback (for example lexical sort by `session_id`) for reproducibility.

## 6) Conflict / Stale-Calendar Handling
Calendar data is strong context, not absolute truth.

Required handling:
- If calendar link is stale/conflict, keep candidate generation but apply confidence penalty.
- If session fields were manually overridden in booking, prefer booking operational truth over raw provider snapshot.
- If provider sync is broken, matching can continue with reduced trust using local session window/location.
- Mark affected decisions with reconciliation flags for operator visibility.

Do not:
- silently overwrite booking session data from calendar feed during matching.
- block all matching solely due to provider outage when local session context exists.

## 7) Manual Override Behavior
Manual operator decisions are first-class and must take precedence over automated suggestions.

Rules:
- Manual assignment overrides automated proposal/assignment for the target scope.
- Manual unassignment is allowed and should prevent immediate auto-reassignment loops.
- Re-match on new evidence should respect manual lock/hold policy until explicitly released.
- UI/API should capture operator reason code (optional text in v1, required reason enum recommended later).

Suggested override states:
- `manual_assigned`
- `manual_unassigned`
- `auto_assigned`
- `proposed`

## 8) Audit / Provenance Requirements for Assignment Decisions
Every assignment/proposal/no-match decision should be auditable.

Minimum audit payload:
- decision id
- decision type (`auto_assign|propose|no_match|manual_override`)
- subject type (`ingest_item|asset`)
- subject identifier (ID value for that subject type)
- selected `session_id` (nullable for no-match)
- ranked candidate list with scores
- confidence tier + numeric score
- evidence summary:
  - time deltas
  - buffer class
  - location distance bucket/value
  - key semantic matches
  - calendar reconciliation/sync flags
- algorithm version / strategy version
- trigger source (`ingest_batch`, `manual_reprocess`, etc.)
- actor (`system` or user id)
- timestamps

Subject-identifier lifecycle rule:
- Before canonical asset creation, decisions should target ingest/staged subject IDs.
- After canonical asset creation, decisions should target canonical `asset_id`.
- If both phases occur for the same media, preserve lineage linking earlier ingest-subject decisions to the resulting `asset_id`.

Immutability rule:
- Decision records are append-only historical facts.
- Corrections and overrides append new records; do not rewrite prior decision history.

## 9) Minimal v1 Recommendation
Keep v1 narrow and practical.

v1 should include:
- session-candidate generation using:
  - capture timestamp
  - session window + buffers
  - session location (when available)
  - basic batch/import naming hints
- three confidence tiers (`high|medium|low`)
- three outcomes (`auto-assign|propose|no-match`)
- deterministic top-N candidate ranking
- manual override support
- append-only decision audit logging

v1 should not include:
- ML/LLM-based matching classifiers
- cross-day itinerary optimization
- complex multi-session segmentation of a single asset stream
- provider-specific custom matching engines

## 10) Guardrails / What Not To Do
- Do not write booking/session truth into canonical asset-owned tables.
- Do not make matching decisions opaque; always retain evidence and score provenance.
- Do not auto-assign on weak confidence.
- Do not ignore manual overrides.
- Do not treat calendar provider data as always-correct.
- Do not couple matching logic to one calendar vendor.
- Do not require booking package to own media associations.
- Do not bypass contracts/events with peer concrete model coupling.

## Recommended Persistence Pattern
Persist matching decisions in an explicitly owned association/audit structure outside canonical asset-owned tables.

Recommended v1 pattern:
- Keep canonical asset tables unchanged.
- Use dedicated session-assignment persistence owned by the package that owns ingest/session-matching workflow state (recommended: `prophoto-ingest` in v1), for example:
  - `asset_session_assignments` (current effective association, reversible)
  - `asset_session_assignment_decisions` (append-only decision/audit history)
  - `asset_session_match_candidates` (optional, short-retention candidate details)

Effective-association rule:
- The current effective session association is derived from the latest non-superseded assignment decision for the subject, unless a manual lock is active.
- `proposed` decisions never become effective association by themselves.
- Manual lock states (`manual_assigned`, `manual_unassigned`) take precedence over subsequent automated decisions until explicitly released or superseded by another manual action.

Ownership/boundary rationale:
- `prophoto-booking` owns session truth, not media associations.
- `prophoto-assets` owns canonical media truth, not booking context truth.
- Matching is a downstream reconciliation concern; association records should reference `asset_id` and `session_id` without redefining either package's ownership boundary.

Integration pattern:
- Expose read/write behavior through contracts/events in `prophoto-contracts`.
- Use stable IDs (`asset_id`, `session_id`) at boundaries.
- Emit immutable events for assignment changes and manual overrides.
