# Ingest Session Association Data Model
Date: March 13, 2026
Status: Frozen pre-migration v1 data model (no code/migrations yet)

## Purpose
Define the concrete persistence model for session-matching outputs and current effective session association in a way that preserves package ownership boundaries.

Core v1 rules:
- Owning package is `prophoto-ingest`.
- Session matching outputs are association decisions, not media ownership.
- Canonical media ownership in `prophoto-assets` is unchanged.
- Session truth in `prophoto-booking` is unchanged.
- v1 stays practical: deterministic, auditable, reversible.

## Ownership and Boundary
- Owning package for all structures in this document: `prophoto-ingest`
- Upstream session context owner: `prophoto-booking` (`sessions.id`)
- Upstream media owner: `prophoto-assets` (`assets.id`)
- Cross-package rule: no migration in this phase mutates canonical asset-owned table schemas
- Integration rule: reference upstream by stable IDs (`session_id`, `asset_id`) and contracts/events

---

## Effective Association Resolution (v1)
Current effective association must be explicit and deterministic.

Resolution rules:
1. Effective association is determined from the latest non-superseded row in `asset_session_assignments` for a subject.
2. `proposed` outcomes do not become effective assignments.
3. If manual lock is active, automated decisions cannot replace the current effective state.
4. Manual actions (`manual_assign`, `manual_unassign`) supersede automated effective states.
5. Reversal is modeled by superseding prior effective rows, not destructive rewrites.
6. Unlocking does not itself assign a session; it only removes manual lock so later decisions can become effective.

---

## Subject Identity Lifecycle (before and after canonical asset creation)
Session matching may run at multiple lifecycle points.

Identity rules:
- Pre-canonicalization: subject is ingest-owned (`subject_type=ingest_item`).
- Post-canonicalization: subject is canonical (`subject_type=asset`).
- Lineage continuity is required: when canonical `asset_id` becomes available, a linking/superseding decision must preserve traceability from ingest-subject decisions to asset-subject decisions.
- Data model supports both IDs on decisions for lineage (`ingest_item_id` and `asset_id`), while `subject_type` + `subject_id` identifies the authoritative subject at decision time.
- Alignment rule:
  - when `subject_type=ingest_item`, `subject_id` must equal serialized `ingest_item_id`
  - when `subject_type=asset`, `subject_id` must equal serialized `asset_id`

---

## 1) `asset_session_assignments`
### Purpose
Store the current effective, reversible session association state for a subject (`ingest_item` or `asset`).

### Owning Package
`prophoto-ingest`

### Columns (v1)
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | ULID / bigint PK | Assignment row identifier |
| `subject_type` | yes | enum/string | `ingest_item|asset` |
| `subject_id` | yes | string | Subject identifier matching `subject_type` |
| `ingest_item_id` | nullable | FK-compatible ID | Required when `subject_type=ingest_item` |
| `asset_id` | nullable | FK-compatible ID (`assets.id`) | Required when `subject_type=asset` |
| `session_id` | nullable | FK-compatible ID (`sessions.id`) | Required when `effective_state=assigned` |
| `effective_state` | yes | enum/string | `assigned|unassigned` |
| `assignment_mode` | yes | enum/string | `auto|manual` |
| `manual_lock_state` | yes | enum/string | `none|manual_assigned_lock|manual_unassigned_lock` |
| `source_decision_id` | yes | FK to `asset_session_assignment_decisions.id` | Decision that made this row effective |
| `confidence_tier` | nullable | enum/string | `high|medium|low` (usually null for manual) |
| `confidence_score` | nullable | decimal | Usually null for manual |
| `reason_code` | nullable | string | Optional concise reason |
| `became_effective_at` | yes | timestamp | Effective-start timestamp |
| `superseded_at` | nullable | timestamp | Null while current |
| `superseded_by_assignment_id` | nullable | FK self-reference | Next effective row pointer |
| `created_at` | yes | timestamp | Row creation |
| `updated_at` | yes | timestamp | Supersession/maintenance updates |

### Required vs Nullable Rules
- `subject_type=ingest_item` -> `ingest_item_id` required, `asset_id` nullable.
- `subject_type=asset` -> `asset_id` required, `ingest_item_id` nullable (may be present for lineage if desired).
- `effective_state=assigned` -> `session_id` required.
- `effective_state=unassigned` -> `session_id` nullable.
- `subject_id` must match the typed ID selected by `subject_type`.

### Relationship Direction
- Downstream/association table in `prophoto-ingest`.
- References upstream IDs:
  - `session_id` -> `prophoto-booking.sessions.id`
  - `asset_id` -> `prophoto-assets.assets.id` (when subject is canonical)
- References local decision history:
  - `source_decision_id` -> `asset_session_assignment_decisions.id`

### Mutability Rules
- Treat rows as effective snapshots.
- Do not rewrite effective meaning in-place.
- On change, supersede current row (`superseded_at`) and insert a new current row.
- Physical deletion is not allowed for historical rows.

### Uniqueness Constraints
- PK on `id`
- One current row per subject: unique (`subject_type`, `subject_id`) where `superseded_at IS NULL`
- Optional sanity uniqueness: unique (`source_decision_id`) in current rows where appropriate
- Constraint/validation enforcing `subject_type` and ID column consistency
- Constraint/validation enforcing `subject_id` serialization alignment with the typed ID column

### Manual Override Handling
- Manual assign creates a new row:
  - `effective_state=assigned`
  - `assignment_mode=manual`
  - `manual_lock_state=manual_assigned_lock`
- Manual unassign creates a new row:
  - `effective_state=unassigned`
  - `assignment_mode=manual`
  - `manual_lock_state=manual_unassigned_lock`
- While manual lock is active, automated rows must not supersede current row.

### Subject Identity Handling
- Supports both `ingest_item` and `asset` subjects.
- Pre-canonical rows use ingest subject identity.
- Post-canonical rows use asset subject identity; lineage preserved via `source_decision_id` chain and decision-level dual IDs.

---

## 2) `asset_session_assignment_decisions`
### Purpose
Store append-only decision/audit history for all matching outcomes and manual overrides.

### Owning Package
`prophoto-ingest`

### Columns (v1)
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | ULID / bigint PK | Decision identifier |
| `decision_type` | yes | enum/string | `auto_assign|propose|no_match|manual_assign|manual_unassign` |
| `subject_type` | yes | enum/string | `ingest_item|asset` |
| `subject_id` | yes | string | Subject identifier at decision time |
| `ingest_item_id` | nullable | FK-compatible ID | Pre-canonical or lineage reference |
| `asset_id` | nullable | FK-compatible ID (`assets.id`) | Post-canonical or lineage reference |
| `selected_session_id` | nullable | FK-compatible ID (`sessions.id`) | Null for `no_match`/manual unassign |
| `confidence_tier` | nullable | enum/string | `high|medium|low` (usually null for manual) |
| `confidence_score` | nullable | decimal | Numeric confidence value |
| `algorithm_version` | yes | string | Strategy/version fingerprint |
| `trigger_source` | yes | enum/string | `ingest_batch|post_canonicalization|manual_override|manual_reprocess|api` |
| `ranked_candidates_payload` | nullable | json | Top-N candidates (if candidate table not used) |
| `evidence_payload` | yes | json | Matching evidence/provenance details |
| `calendar_context_state` | nullable | enum/string | Optional summary (`normal|stale|conflict|sync_error`) |
| `manual_override_reason_code` | nullable | string | For manual decisions |
| `manual_override_note` | nullable | text | Operator note |
| `lock_effect` | yes | enum/string | `none|lock_assigned|lock_unassigned|unlock` |
| `supersedes_decision_id` | nullable | FK self-reference | Prior decision superseded by this one |
| `idempotency_key` | nullable | string | Deduplication key for retried writes |
| `actor_type` | yes | enum/string | `system|user` |
| `actor_id` | nullable | string | User ID when `actor_type=user` |
| `created_at` | yes | timestamp | Decision timestamp |

### Required vs Nullable Rules
- `selected_session_id` required for `auto_assign`, `propose`, `manual_assign`.
- `selected_session_id` nullable for `no_match`, `manual_unassign`.
- At least one subject reference must be populated consistent with `subject_type`.
- `subject_id` must match the typed ID selected by `subject_type`.
- `evidence_payload` required for all automated decisions; manual decisions may use minimal evidence but still require provenance envelope.

### Relationship Direction
- Local append-only history table in `prophoto-ingest`.
- May reference upstream IDs (`sessions.id`, `assets.id`) but does not own them.
- Upstream for `asset_session_assignments.source_decision_id`.

### Mutability Rules
- Append-only: no updates to decision meaning after insert.
- Corrections are new decisions with `supersedes_decision_id`.
- No hard deletes in normal operation.

### Uniqueness Constraints
- PK on `id`
- Optional unique on non-null `idempotency_key`
- Optional uniqueness guard for repeated identical manual actions can be application-level in v1

### Manual Override Handling
- Manual operations are explicit decision types:
  - `manual_assign`
  - `manual_unassign`
- Manual decisions set `lock_effect` and drive locked assignment rows.
- Manual decisions should supersede prior effective automated decisions via assignment supersession flow.
- `lock_effect=unlock` is lock-release only; it does not by itself assign or unassign a session.

### Subject Identity Handling
- Pre-canonical decisions use ingest subject identity.
- Post-canonical decisions use asset subject identity.
- For continuity, post-canonical decisions should include `ingest_item_id` when lineage is known.

---

## 3) Optional `asset_session_match_candidates`
### Purpose
Store normalized candidate ranking rows per decision when operator UX/debugging needs structured queryable candidate history beyond JSON payloads.

### v1 Justification
Optional in v1.

Use this table only if one or more are true:
- operator workflows need server-side filtering/sorting over candidate history
- analytics require cross-decision candidate comparisons
- JSON payload size/performance becomes a concern

If not needed, keep candidate detail in `asset_session_assignment_decisions.ranked_candidates_payload` only.

### Owning Package
`prophoto-ingest`

### Columns (v1, optional)
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | ULID / bigint PK | Candidate row identifier |
| `decision_id` | yes | FK to `asset_session_assignment_decisions.id` | Parent decision |
| `rank_position` | yes | integer | 1-based candidate rank |
| `candidate_session_id` | yes | FK-compatible ID (`sessions.id`) | Candidate session |
| `candidate_score` | yes | decimal | Numeric score |
| `confidence_tier` | nullable | enum/string | `high|medium|low` |
| `buffer_class` | nullable | enum/string | `core|buffer|outside` |
| `time_delta_minutes` | nullable | integer | Absolute/planned delta |
| `distance_meters` | nullable | integer | Spatial distance where available |
| `disqualifier_code` | nullable | string | Why candidate lost or downgraded |
| `is_selected` | yes | boolean | Selected candidate marker |
| `created_at` | yes | timestamp | Row creation |

### Required vs Nullable Rules
- `decision_id`, `rank_position`, `candidate_session_id`, `candidate_score`, `is_selected` required.
- Evidence detail columns are nullable because signals may be unavailable.

### Relationship Direction
- Child table of `asset_session_assignment_decisions`.
- References upstream `sessions.id` by ID only.

### Mutability Rules
- Append-only with parent decision.
- No updates after insertion except controlled data-fix operations.

### Uniqueness Constraints
- PK on `id`
- Unique (`decision_id`, `rank_position`)
- Unique (`decision_id`, `candidate_session_id`)
- Optional partial unique (`decision_id`) where `is_selected=true`

### Manual Override Handling
- Manual decisions may omit candidate rows entirely.
- If inserted for manual decisions, candidate rows are informational only and do not affect lock precedence.

### Subject Identity Handling
- Inherits subject identity lifecycle from parent decision via `decision_id`.

---

## Practical v1 Recommendation
- Implement:
  - `asset_session_assignments`
  - `asset_session_assignment_decisions`
- Defer `asset_session_match_candidates` unless operator/reporting needs prove necessary.
- Keep ranking detail in decision JSON payload initially for speed.

---

## Guardrails
- Do not alter canonical asset-owned table schemas to store booking/session truth.
- Do not move session truth ownership into ingest or assets.
- Do not let `prophoto-booking` own media association tables.
- Do not bypass manual lock precedence with automated writes.
- Do not drop audit history to "fix" current state.

## Recommended Event Surface
Events should be immutable and defined in `prophoto-contracts`. Payloads should use stable IDs and avoid full model payloads.

### 1) Auto-assignment event
Suggested name: `SessionAutoAssignmentApplied`

Minimum payload:
- `assignment_id`
- `decision_id`
- `subject_type`
- `subject_id`
- `asset_id` (nullable pre-canonical)
- `ingest_item_id` (nullable post-canonical)
- `session_id`
- `confidence_tier`
- `confidence_score`
- `algorithm_version`
- `occurred_at`

### 2) Proposal creation event
Suggested name: `SessionMatchProposalCreated`

Minimum payload:
- `decision_id`
- `subject_type`
- `subject_id`
- `asset_id` (nullable)
- `ingest_item_id` (nullable)
- `top_candidate_session_id` (nullable)
- `candidate_count`
- `confidence_tier`
- `confidence_score`
- `algorithm_version`
- `occurred_at`

### 3) Manual assignment event
Suggested name: `SessionManualAssignmentApplied`

Minimum payload:
- `assignment_id`
- `decision_id`
- `subject_type`
- `subject_id`
- `asset_id` (nullable)
- `ingest_item_id` (nullable)
- `session_id`
- `manual_override_reason_code` (nullable)
- `actor_id`
- `lock_state` (`manual_assigned_lock`)
- `occurred_at`

### 4) Manual unassignment event
Suggested name: `SessionManualUnassignmentApplied`

Minimum payload:
- `assignment_id`
- `decision_id`
- `subject_type`
- `subject_id`
- `asset_id` (nullable)
- `ingest_item_id` (nullable)
- `manual_override_reason_code` (nullable)
- `actor_id`
- `lock_state` (`manual_unassigned_lock`)
- `occurred_at`

Event rules:
- Events are append-only historical facts.
- If payload shape changes incompatibly, publish versioned events.
- Emit events from persisted decisions/assignments, never from transient scoring paths.
