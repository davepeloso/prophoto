# Upload Recognition Moment (v1)

## 1. Purpose
Define the upload-time recognition moment where `prophoto-ingest` evaluates an uploaded batch as a likely session context and surfaces deterministic next-step guidance before any system mutation occurs.

This slice is workflow-first (not a gallery app behavior) and exists to reduce operator friction at ingest time while preserving architecture boundaries and manual control.

## 2. User moment
Photographer uploads a batch.

System evaluates the batch and responds with recognition guidance such as:

"Looks like the Alma Mater Footwear Lifestyle shoot. Want to continue?"

Recognition is immediate workflow guidance, not an automatic write path.

## 3. Why it belongs in prophoto-ingest (v1)
`prophoto-ingest` owns upload-time matching and association detection.

This moment happens at ingest-time, is batch-scoped, and depends on ingest-owned recognition logic.

It does not belong in:
- `prophoto-booking` (owns session truth, not upload recognition flow)
- `prophoto-assets` (owns canonical media truth, not ingest-time detection)
- `prophoto-intelligence` (owns derived outputs, downstream of ingest/assets event loop)

## 4. Scope (v1)
In scope:
- Batch-level recognition during upload flow
- Confidence-scored candidate detection
- Explicit ambiguity handling
- Fixed, deterministic next-action suggestions
- Zero automatic mutation

Out of scope in v1:
- Per-asset recognition UX
- Automatic assignment or reassignment
- Any write that changes booking, ingest effective assignment, assets, or intelligence state
- Provider-specific calendar reconciliation logic beyond consumed snapshot inputs

## 5. Inputs
### Uploaded batch
- Batch identity and ingest context
- Batch-level file set under evaluation

### Normalized metadata snapshot
- Capture-layer snapshot for the batch (timestamps, location signals, import hints, and other normalized evidence)
- Treated as read-only recognition input

### Optional session context snapshot
- Intent-layer snapshot (session window, location hints, title/job/session context, reconciliation state)
- Optional by design
- Passed into ingest as a snapshot; no direct booking query from this slice

Input policy:
- Metadata-only recognition must work
- Calendar/session-context-only recognition may contribute when metadata is weak
- Combined metadata + session context should increase confidence when signals align

## 6. Recognition behavior
- Batch-level, not per-asset
- Detection, not decision
- No silent mutation
- Candidate ordering must be deterministic for identical inputs
- Calendar context is optional, not required
- Confidence:
- Tier: high-confidence or low-confidence
- Score: optional numeric value used for ranking

Detection rule:
- The system identifies likely session candidates and confidence tier/score
- The system does not execute assignment, override, or downstream state changes

## 7. Recognition outcomes
### High-confidence match
- One recognized session candidate is shown as likely match
- Confidence is shown as tier (high-confidence or low-confidence) plus optional score
- Fixed next actions are shown
- No action is executed automatically

### No high-confidence match
- Candidate help is still shown
- Up to 3 low-confidence candidates are shown
- Low-confidence state is explicitly labeled
- User must explicitly choose an action or dismiss
- No mutation occurs automatically

### No viable candidates
- Recognition result still returns with no primary candidate
- No candidate list is shown
- Fixed next actions are still presented

## 8. Fixed next actions (v1)
Always present these fixed workflow actions after recognition evaluation:
- `Cull now`
- `Continue to delivery`
- `Review match / session context`

Rules:
- Action set is fixed in v1
- System does not auto-select or auto-run an action
- Actions represent user navigation or workflow direction
- They do not execute system mutations at the recognition step

## 9. Output (product-level, not code)
Recognition moment produces a product-visible result containing:
- Recognized session candidate (nullable when none is high-confidence)
- At most one primary (high-confidence) candidate is ever surfaced
- Confidence:
- Tier: high-confidence or low-confidence
- Score: optional numeric value used for ranking
- Suggested next actions (fixed v1 set)
- Low-confidence candidates (max 3)

Output constraints:
- Confidence and ambiguity are always explicit
- Candidate ordering must be deterministic for identical inputs
- Low-confidence candidates are clearly labeled as low confidence

## 10. Boundaries (what this does NOT do)
This slice does not:
- Persist effective session assignment
- Perform manual override writes
- Mutate canonical asset truth
- Mutate booking/session truth
- Trigger intelligence execution as a side effect of recognition display
- Bypass ingest association workflow gates

Recognition display must remain pre-mutation guidance only.

## 11. Dependencies on other packages
- `prophoto-contracts`: shared snapshot/event/enum boundary types used by ingest-facing integration surfaces
- `prophoto-booking`: optional session context source, consumed only as passed snapshot context
- `prophoto-assets`: no canonical mutation dependency for this recognition moment
- `prophoto-intelligence`: no direct dependency for v1 recognition display path

Dependency rule:
- Keep cross-package integration contract/event-driven and snapshot-based
- Do not introduce direct booking-table queries from this slice

## 12. Future contracts/events (high-level only, no definitions)
Potential future boundary artifacts after v1 product behavior is stable:
- Upload batch recognition evaluated event/fact
- Recognition suggestion presented event/fact
- User recognition action selected event/fact
- Recognition dismissed event/fact

Future boundary guidance:
- Events represent historical facts
- Recognition events should remain detection/suggestion facts, not implicit mutation commands

## 13. Guardrails / what not to do
- Do not turn recognition into automatic assignment
- Do not bypass manual lock and override rules elsewhere in ingest
- Do not convert this into per-asset classifier behavior in v1
- Do not require calendar to function
- Do not treat calendar as canonical truth
- Do not treat metadata as decision authority by itself when ambiguity is high
- Do not add cross-package writes for convenience
- Do not introduce silent fallthrough actions

## 14. Minimal v1 recommendation
Ship the smallest deterministic slice:
- Evaluate uploaded batch against metadata snapshot plus optional session context snapshot
- Produce explicit primary confidence outcome
- Show fixed next actions
- Show low-confidence help (max 3 candidates) when no high-confidence match exists
- Require explicit user action or dismiss path
- Execute no automatic mutation

## 15. Success criteria
- Upload flow consistently surfaces a recognition moment before mutation
- High-confidence scenario always shows recognized session + fixed next actions + no auto-execution
- No-high-confidence scenario always shows candidate help with up to 3 low-confidence candidates
- Low-confidence candidates are always clearly labeled
- User must explicitly choose or dismiss before any downstream mutation path is entered
- No direct booking query is introduced; context is snapshot-driven
- Package boundaries remain intact (ingest owns recognition)

## 16. Non-goals
- Automatic session assignment at recognition time
- Automatic execution of culling, delivery, or review actions
- Per-asset recognition UX in v1
- Calendar-provider-specific optimization work
- New DTO/migration/event definitions in this slice
- Replacing existing ingest assignment/decision workflows
