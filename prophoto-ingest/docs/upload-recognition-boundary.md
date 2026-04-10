# Upload Recognition Boundary (v1)

## 1. Purpose

Define the architecture boundary for Upload Recognition Moment (v1) inside `prophoto-ingest`, aligned to `prophoto-ingest/docs/upload-recognition-moment.md`, `docs/PROJECT-CORE.md`, and `docs/Development-process.md`.

This boundary locks recognition as:

- workflow-first
- batch-level
- detection-only
- pre-mutation

It does not change the core loop (`Ingest -> SessionAssociationResolved -> Asset -> Intelligence`) and does not introduce cross-package mutation.

## 2. Boundary of the v1 recognition result

The v1 recognition result is an ingest-owned, product-visible detection outcome produced during upload flow before any system mutation.

Boundary rules:

- It is not an assignment decision.
- It does not persist effective association.
- It does not mutate ingest, booking, assets, or intelligence state.
- It exists to guide explicit user direction only.

## 3. Proposed recognition result structure (product/architecture level, not PHP)

Recognition produces a product-visible result containing:

- `primary_candidate`: nullable recognized session candidate; at most one candidate; present only when high-confidence exists.
- `confidence`: Tier is `high-confidence` or `low-confidence`; Score is optional numeric value used for ranking.
- `low_confidence_candidates`: ordered candidate list; maximum 3; each labeled `low-confidence`.
- `suggested_next_actions`: fixed v1 actions are `Cull now`, `Continue to delivery`, `Review match / session context`.
- `recognition_outcome`: `high-confidence-match` | `low-confidence-candidates` | `no-viable-candidates`.
- Recognition result is explicitly pre-mutation and does not imply any system state change.

## 4. Confidence and ambiguity model for v1

Confidence model:

- Tier: `high-confidence` or `low-confidence`.
- Score: optional numeric value used for ranking.

Ambiguity model:

- Ambiguity is explicit in `recognition_outcome`.
- `low-confidence-candidates` means candidate help is available but no primary high-confidence match exists.
- `no-viable-candidates` means no candidate help list is shown.

No thresholds or scoring algorithm details are defined in this boundary.

## 5. Primary candidate vs low-confidence candidate handling

- At most one primary candidate is surfaced.
- Primary candidate requires `high-confidence` tier.
- If primary candidate exists, show primary candidate and fixed next actions, and do not execute automatic mutation.
- If no high-confidence match exists, show up to 3 low-confidence candidates, clearly label them low-confidence, require explicit user choice or dismiss, and do not execute automatic mutation.
- If no viable candidates exist, return recognition result with no primary candidate, show no candidate list, and still show fixed next actions.

## 6. Ingest-owned service boundary

`prophoto-ingest` owns the upload recognition boundary service responsibility in v1.

Ingest boundary responsibilities:

- accept batch-level recognition inputs
- evaluate recognition deterministically
- for identical inputs, recognition must produce identical outputs
- produce recognition result only

Ingest boundary non-responsibilities at this step:

- no assignment persistence
- no override persistence
- no downstream mutation triggers

## 7. Trigger point in the ingest lifecycle

Recognition is triggered at batch upload time after batch intake is complete and normalized metadata snapshot is available, and before any mutation path is entered.

Lifecycle position in v1:

- after batch intake is complete and normalized metadata snapshot is available
- with optional session context snapshot when available
- before any association decision persistence
- before any assignment-changing event emission

## 8. Package ownership

### What `prophoto-ingest` owns

- batch-level upload recognition evaluation
- recognition result production
- conservative pre-mutation detection boundary

### What `prophoto-booking` does not own here

- upload recognition execution
- recognition result ownership
- direct control of ingest recognition flow

### What `prophoto-assets` does not own here

- upload-time recognition detection
- recognition result ownership
- any mutation driven by recognition moment

### What `prophoto-intelligence` does not own here

- upload-time recognition detection
- recognition result ownership
- recognition-time orchestration triggers

## 9. Session context input boundary

Session context is an optional input to recognition and must arrive as snapshot context.

Boundary rules:

- no direct booking query from recognition logic
- snapshot is read-only input
- recognition must still operate when snapshot is absent
- metadata and calendar intent context coexist when both are available
- calendar context remains optional in v1

## 10. Event decision

### Option A: Keep recognition internal to ingest in v1

Pros:

- matches conservative pre-mutation boundary
- avoids exposing non-decision signals as cross-package contracts too early
- preserves existing core loop without adding event surface

Cons:

- recognition observability remains local to ingest in v1

### Option B: Emit a new recognition event in v1

Pros:

- enables immediate cross-package observability of recognition outcomes

Cons:

- externalizes a pre-mutation detection signal that is not a decision
- increases contract/event surface before stability is proven
- creates avoidable coupling risk for a slice intended to remain conservative

### Preferred v1 recommendation

- Does v1 need a new event: no.
- Why not: recognition is a pre-mutation detection step with no system decision or state change, so internal ingest ownership is the correct boundary.
- If yes in the future: only when cross-package consumers require recognition outcomes before mutation decisions, and only after boundary stability is proven.
- Preferred path for v1: keep recognition internal to `prophoto-ingest`.

## 11. Non-responsibilities / what stays unwired in v1

- no automatic assignment
- no automatic execution of next actions
- no per-asset recognition behavior
- no direct booking reads from recognition logic
- no new contracts/events required for v1 boundary
- no intelligence trigger changes from recognition outcome
- no asset truth mutation
- no booking truth mutation

## 12. Minimal v1 recommendation

Keep v1 to a deterministic, batch-level, no-mutation recognition boundary in ingest that consumes normalized metadata plus optional session snapshot input and produces fixed next-action guidance.

## 13. Open questions, if any

- No blocking architecture-boundary questions for v1.
- Future reassessment point: introduce a new event only if recognition outcomes require cross-package consumption before mutation decisions are made.
