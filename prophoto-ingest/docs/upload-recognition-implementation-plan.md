# Upload Recognition Implementation Plan (v1)

## 1. Purpose
Translate the approved Upload Recognition Moment (v1) product spec and architecture boundary into a deterministic implementation plan for `prophoto-ingest` only.

This plan keeps recognition:
- detection-only
- batch-level
- pre-mutation
- ingest-local in v1

## 2. What this implementation plan covers
This plan covers only `prophoto-ingest` work needed to:
- evaluate an uploaded batch for likely session context
- produce a recognition result for operator guidance
- surface that result in ingest UI at the correct lifecycle point
- verify deterministic behavior and no automatic mutation

This plan does not define cross-package integrations, new cross-package events, migrations, or downstream workflow execution.

## 3. Internal ownership in `prophoto-ingest`
`prophoto-ingest` owns:
- upload-time recognition evaluation
- recognition result assembly
- recognition trigger placement in ingest flow
- ingest-local delivery of recognition result to operator-facing UI state

`prophoto-ingest` does not delegate recognition ownership to booking, assets, or intelligence in v1.

## 4. Proposed internal service boundary
Likely service name:
- `BatchUploadRecognitionService` (ingest-local)

Service responsibility:
- accept batch-level recognition inputs
- evaluate candidates deterministically
- classify one outcome status
- produce one ingest-local recognition result

What it does not own:
- assignment decisions
- effective assignment persistence
- manual override persistence
- cross-package event emission
- booking, asset, or intelligence mutations
- direct booking queries

## 5. Trigger point in ingest flow
Where recognition should be called from:
- ingest upload flow orchestration immediately after batch intake completion

What inputs must exist first:
- uploaded batch intake context
- normalized metadata snapshot
- optional session context snapshot, if present

What must not have happened yet:
- no mutation path entered
- no assignment decision persistence
- no assignment-changing event emission
- no asset/booking/intelligence mutation

Deterministic trigger rule:
- call recognition after batch intake is complete and normalized metadata snapshot is available; include session context snapshot only if already available.

## 6. Code-level result shape
Use an ingest-local result structure (internal only) with these fields:
- `outcome_status`: `high-confidence-match` | `low-confidence-candidates` | `no-viable-candidates`
- `primary_candidate`: nullable object
- `primary_candidate.session_id`: nullable identifier
- `primary_candidate.display_label`: nullable string
- `confidence`: object
- `confidence.tier`: `high-confidence` | `low-confidence`
- `confidence.score`: nullable numeric value (ranking use only)
- `low_confidence_candidates`: ordered list (max 3)
- `low_confidence_candidates[].session_id`: identifier
- `low_confidence_candidates[].display_label`: string
- `low_confidence_candidates[].confidence_tier`: always `low-confidence`
- `low_confidence_candidates[].score`: optional numeric value
- `suggested_next_actions`: fixed ordered list
- `suggested_next_actions[]`: `Cull now` | `Continue to delivery` | `Review match / session context`

Result invariants:
- at most one primary candidate
- if `outcome_status=high-confidence-match`, primary candidate is present
- if `outcome_status=low-confidence-candidates`, primary candidate is null and candidate list has 1..3 items
- if `outcome_status=no-viable-candidates`, primary candidate is null and candidate list is empty
- for identical inputs, recognition must produce identical outputs
- recognition result is explicitly pre-mutation and does not imply any system state change

## 7. Minimal UI hook for v1
Ingest UI should receive the recognition result from the existing upload flow response/view-model in `prophoto-ingest`.

Minimal v1 behavior:
- render recognition from the ingest response/view-model
- show primary candidate when present
- show low-confidence candidate list when outcome is `low-confidence-candidates`
- show no candidate list when outcome is `no-viable-candidates`
- always show fixed next actions
- do not trigger downstream workflow wiring automatically from recognition display

UI boundary for v1:
- keep hook local to ingest upload surface
- no additional cross-package UI or workflow integration

## 8. Tests required
Unit tests (recognition logic):
- deterministic output for identical inputs
- `high-confidence-match` outcome with exactly one primary candidate
- `low-confidence-candidates` outcome with no primary and max 3 low-confidence candidates
- `no-viable-candidates` outcome with no primary and empty candidate list
- fixed next actions always present and ordered
- no automatic mutation side effects from recognition evaluation

Feature/integration tests (ingest flow placement):
- recognition executes only after batch intake completion and normalized metadata snapshot availability
- optional session context snapshot is accepted when present and not required when absent
- recognition executes before mutation path is entered
- ingest response/view-model includes recognition result for UI consumption
- recognition step does not persist assignment decisions or trigger assignment-changing events

Out of scope for tests in v1:
- cross-package consumer behavior
- downstream booking/assets/intelligence wiring

## 9. What stays unwired in v1
- no automatic assignment execution
- no automatic execution of suggested next actions
- no per-asset recognition flow
- no direct booking query from recognition logic
- no new cross-package events for recognition
- no asset mutation
- no booking mutation
- no intelligence wiring changes

## 10. Acceptance criteria
- recognition runs at upload flow boundary after batch intake completion and normalized metadata snapshot availability
- recognition accepts optional session context snapshot without requiring it
- recognition result follows approved outcome statuses and confidence semantics
- recognition returns fixed next actions for all outcomes
- recognition returns at most one primary candidate
- recognition remains pre-mutation with no automatic state changes
- ingest UI can render recognition from ingest-local response data only
- deterministic behavior is proven for identical inputs

## 11. Suggested implementation order
1. Lock ingest-local result contract shape and invariants for v1.
2. Implement ingest-local recognition service boundary (`BatchUploadRecognitionService`).
3. Wire trigger into upload flow at the approved pre-mutation point.
4. Expose recognition result through ingest upload response/view-model.
5. Add unit tests for outcome classification and deterministic behavior.
6. Add feature/integration tests for ingest flow placement and no-mutation behavior.
7. Confirm no cross-package events, mutations, or new wiring were introduced.
