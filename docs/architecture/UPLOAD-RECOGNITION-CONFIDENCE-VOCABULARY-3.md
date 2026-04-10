# Upload Recognition Confidence Vocabulary v2 (Architecture Note)

Date: April 9, 2026
Status: Architecture note — no code, no DTOs, no migrations
Supersedes: `UPLOAD-RECOGNITION-CONFIDENCE-VOCABULARY.md` (v1)
Owning slice: Upload Recognition Moment (`prophoto-ingest`)

---

## 1. Purpose and Boundary

Determine whether upload-recognition confidence in `prophoto-ingest` should reuse the existing session-matching confidence vocabulary (`SessionMatchConfidenceTier` enum in `prophoto-contracts`) or remain ingest-local for v1.

This is a vocabulary-alignment decision only. It does not cover persistence refactors, new events, write-service redesigns, or changes to `prophoto-contracts`.

### In scope

- The confidence tier vocabulary used by `BatchUploadRecognitionService`
- Whether that vocabulary should reference `SessionMatchConfidenceTier` or stay as local string constants
- Semantic analysis of recognition vs. matching confidence

### Out of scope

- Database schema changes
- Event contract additions or modifications
- Write-service redesigns (`SessionAssociationWriteService`, etc.)
- `prophoto-contracts` enum modifications
- Persistence model changes to `asset_session_assignments` or `asset_session_assignment_decisions`
- Cross-package mutation patterns

---

## 2. Current Recognition Vocabulary

`BatchUploadRecognitionService` defines two confidence tiers as protected string constants:

```php
protected const TIER_HIGH_CONFIDENCE = 'high-confidence';
protected const TIER_LOW_CONFIDENCE = 'low-confidence';
```

Three outcome statuses:

```php
protected const OUTCOME_HIGH_CONFIDENCE_MATCH = 'high-confidence-match';
protected const OUTCOME_LOW_CONFIDENCE_CANDIDATES = 'low-confidence-candidates';
protected const OUTCOME_NO_VIABLE_CANDIDATES = 'no-viable-candidates';
```

Recognition semantics:

- **Detection-focused**: "Does this batch look like a specific session?"
- **Pre-mutation**: Output is guidance data for ingest flow/UI; no persisted assignment or state change
- **User-facing**: Shown to operator as workflow guidance
- **Binary confidence**: High-confidence (single primary candidate) or low-confidence (up to 3 candidates for review)
- **Threshold-driven**: Uses the same `auto_assign_threshold` (0.85) and `proposal_threshold` (0.55) config values that the matching pipeline uses, but interprets them for detection display rather than assignment decisions

---

## 3. Current Matching/Assignment Confidence Vocabulary

The matching pipeline uses `SessionMatchConfidenceTier`, a backed enum in `prophoto-contracts`:

```php
// prophoto-contracts/src/Enums/SessionMatchConfidenceTier.php
enum SessionMatchConfidenceTier: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
}
```

This enum is consumed across the matching and assignment stack:

- `SessionMatchScoringService` — classifies candidate confidence tiers
- `SessionMatchDecisionClassifier` — uses tiers to drive decision outcomes (`auto_assign`, `propose`, `no_match`)
- `IngestItemSessionMatchingFlowService` — hydrates tier from matching results
- `SessionAssociationWriteService` — persists tier to `asset_session_assignments` and `asset_session_assignment_decisions`
- `SessionAssignmentRepository` / `SessionAssignmentDecisionRepository` — stores `->value` string to database columns
- `SessionAssociationResolved` event — carries tier on the event payload

Matching semantics:

- **Decision-focused**: "Should we assign this asset to this session?"
- **Mutation-coupled**: Drives persisted assignment decisions and audit history
- **System-facing**: Used for automated assignment thresholds and decision classification
- **Three-tier confidence**: HIGH / MEDIUM / LOW with numeric scoring (0.00–1.00)

---

## 4. Whether These Vocabularies Represent the Same Concept

**They do not.** Partial signal overlap does not make them the same concept.

### What overlaps

- Both assess similarity between uploaded content and session contexts
- Both consume the same scoring infrastructure (`SessionMatchScoringService`, `SessionMatchCandidateGenerator`)
- Both are driven by the same threshold configuration values
- Both operate on normalized metadata + session context snapshots

### What differs

| Dimension | Recognition | Matching/Assignment |
|-----------|-------------|---------------------|
| Question answered | "Does this look like X?" | "Should we assign to X?" |
| Tier count | 2 (high / low) | 3 (high / medium / low) |
| Tier values | `'high-confidence'` / `'low-confidence'` | `'high'` / `'medium'` / `'low'` |
| Mutation effect | None — guidance only | Drives persisted assignments |
| Consumer | Operator UI / ingest flow | Assignment pipeline, audit history, events |
| Lifecycle position | Pre-mutation detection | Post-detection decision |
| Type system | String constants (class-local) | Backed enum (contract-level) |

The recognition vocabulary answers a detection question with a binary signal. The matching vocabulary answers an assignment question with a three-tier decision framework. These are different stages in the core loop (`Ingest → SessionAssociationResolved → Asset → Intelligence`) and collapsing them conflates detection with decision.

---

## 5. Risks of Unifying Them Too Early

### Semantic contamination

Recognition's binary detection signal (`high-confidence` / `low-confidence`) does not map cleanly onto matching's three-tier decision framework. Forcing recognition into `SessionMatchConfidenceTier` would require either:

- Ignoring `MEDIUM` in recognition (wasting an enum case, creating dead-code confusion)
- Inventing a recognition meaning for `MEDIUM` that does not exist today (premature complexity)

### Coupling recognition evolution to assignment evolution

`SessionMatchConfidenceTier` is consumed by write services, repositories, events, and tests across the matching pipeline. Any change to the enum to accommodate recognition semantics would ripple through all of those consumers. Recognition is new and its confidence model may need to evolve based on operator feedback — coupling it to a stable, widely-consumed enum removes that freedom.

### Boundary violation

`BatchUploadRecognitionService` is explicitly documented as "detection-only, pre-mutation, and non-canonical." Importing a contract-level enum that is semantically tied to mutation-coupled assignment decisions blurs that boundary.

### Premature contract dependency

Recognition does not currently require `prophoto-contracts`. Importing `SessionMatchConfidenceTier` would create a new dependency path from a detection service to the shared contract layer for vocabulary alone — before recognition's vocabulary is proven stable.

---

## 6. Risks of Leaving Them Separate

### Vocabulary fragmentation

Two confidence systems coexist in `prophoto-ingest`. A developer working on the matching pipeline may encounter `'high-confidence'` strings in recognition and assume they are related to `SessionMatchConfidenceTier::HIGH` — they are not, and nothing in the code makes this explicit beyond a class-level docblock.

### Threshold drift

Both systems currently use `auto_assign_threshold` (0.85) and `proposal_threshold` (0.55) from the same config namespace. If recognition evolves its thresholds independently without documenting the divergence, the two systems could silently disagree about what "high confidence" means for the same score.

### Future integration cost

If recognition eventually feeds into the assignment pipeline (e.g., recognition results become inputs to matching decisions), a later unification would require migrating recognition output consumers from string constants to enum values and reconciling the binary/three-tier difference.

### Documentation burden

The distinction between recognition confidence and matching confidence must be actively documented and maintained. Without this, onboarding complexity increases.

---

## 7. Recommended v1 Decision

**Keep recognition vocabulary ingest-local. Do not import `SessionMatchConfidenceTier`.**

Rationale, in priority order:

1. **Different concepts deserve different types.** Detection confidence and assignment confidence answer different questions at different lifecycle stages. A shared vocabulary implies shared semantics that do not exist.

2. **Recognition is unproven.** The Upload Recognition Moment is a new slice. Its confidence model should be free to evolve based on operator feedback without constraint from the stable, widely-consumed matching enum.

3. **No contract change is required.** The approved upload-recognition slice is ingest-local. Importing a contract-level enum for vocabulary alone is unnecessary coupling.

4. **Binary vs. three-tier is a real structural difference.** Recognition has no `MEDIUM` tier and should not be forced to invent one.

### What stays the same

- `TIER_HIGH_CONFIDENCE` and `TIER_LOW_CONFIDENCE` remain as protected string constants in `BatchUploadRecognitionService`
- Recognition outcome statuses remain as class-local constants
- Recognition continues to use the shared scoring infrastructure (`SessionMatchScoringService`, `SessionMatchCandidateGenerator`) without importing the matching confidence enum
- Threshold config values remain shared (both systems read from the same config keys)

### What to add

- A docblock on `BatchUploadRecognitionService` confidence constants explicitly noting the distinction from `SessionMatchConfidenceTier` and citing this architecture note
- This v2 note supersedes v1 in the architecture record

---

## 8. What Would Need to Change Later If Unification Is Chosen

If a future version decides to unify the vocabularies, the following changes would be required:

### a) Enum evolution (in `prophoto-contracts`)

Either extend `SessionMatchConfidenceTier` with recognition-specific cases or create a parent abstraction. Extension approach:

```
// Hypothetical — do not implement now
case HIGH = 'high';
case MEDIUM = 'medium';
case LOW = 'low';
case RECOGNITION_HIGH = 'recognition-high';  // detection-only
case RECOGNITION_LOW = 'recognition-low';    // detection-only
```

This would require updating every consumer that switches on the enum or validates its cases.

### b) Service migration

- `BatchUploadRecognitionService` would import and use the unified enum instead of string constants
- All tests asserting string constant values would need updating
- Recognition output shape (`confidence.tier`) would change from string to enum-backed value

### c) Consumer migration

- Any future UI or API consumer of recognition output that parses `'high-confidence'` / `'low-confidence'` strings would need updating
- Threshold interpretation documentation would need reconciling

### d) Decision: collapse or coexist within the enum

The hardest design question would be whether recognition cases and matching cases are separate enum members (coexist) or whether recognition adopts `HIGH` / `LOW` directly (collapse). Collapse forces recognition into matching semantics. Coexistence inflates the enum. Neither is free.

### e) Trigger for this work

Do not begin unification unless at least one of the following is true:

- Recognition output is consumed by the assignment pipeline as a direct input
- Operator feedback indicates confusion between recognition and matching confidence
- A third confidence vocabulary is emerging, creating a pattern that demands consolidation

---

## 9. Guardrails / What Not to Do

### Do not

- Import `SessionMatchConfidenceTier` into `BatchUploadRecognitionService` for v1
- Modify the `SessionMatchConfidenceTier` enum to accommodate recognition semantics
- Add new enum cases to `prophoto-contracts` for this slice
- Create new events to communicate recognition confidence across packages
- Redesign write services or persistence models as part of this vocabulary decision
- Assume that shared thresholds imply shared vocabulary — config reuse is intentional; type-level coupling is not
- Treat this note as authorization to broaden the recognition slice beyond its approved scope

### Do

- Document the vocabulary distinction in `BatchUploadRecognitionService` docblocks
- Keep recognition vocabulary as class-local protected constants
- Monitor for threshold drift between recognition and matching if either evolves independently
- Revisit this decision during v2 planning if recognition usage patterns indicate unification value
- Preserve the semantic boundary between detection (pre-mutation guidance) and decision (mutation-coupled assignment)

---

**Decision authority**: Architecture note for the approved upload-recognition slice in `prophoto-ingest`. Does not authorize contract changes, persistence refactors, new events, or write-service modifications. Future unification is deferred to v2 planning and requires evidence from real usage.
