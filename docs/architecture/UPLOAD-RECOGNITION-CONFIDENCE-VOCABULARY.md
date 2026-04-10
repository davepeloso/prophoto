# Upload Recognition Confidence Vocabulary (Architecture Note)

## 1. Purpose and Boundary

Determine whether upload-recognition confidence in `prophoto-ingest` should reuse the existing session-matching confidence vocabulary or remain ingest-local for v1.

This decision affects only the confidence tier vocabulary used in the approved Upload Recognition Moment slice. It does not cover persistence models, event contracts, or service redesigns.

Scope:
- Upload recognition confidence tier vocabulary only
- Ingest-local recognition service boundary
- Alignment with existing session-matching confidence semantics

Out of scope:
- Database schema changes
- Event contract modifications
- Write service redesigns
- Cross-package contract changes

## 2. Current Recognition Vocabulary

The Upload Recognition Moment implementation defines:

```php
protected const TIER_HIGH_CONFIDENCE = 'high-confidence';
protected const TIER_LOW_CONFIDENCE = 'low-confidence';
```

Recognition outcome statuses:
- `high-confidence-match`
- `low-confidence-candidates`
- `no-viable-candidates`

Recognition semantics:
- **Detection-focused**: "Does this batch look like a specific session?"
- **Pre-mutation**: Guidance only, no assignment decision
- **User-facing**: Shown to operator as workflow guidance
- **Binary confidence**: Either high-confidence (primary candidate) or low-confidence (candidate help)

## 3. Current Matching/Assignment Confidence Vocabulary

The existing session-matching system uses `SessionMatchConfidenceTier` enum:

```php
enum SessionMatchConfidenceTier: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
}
```

Matching/assignment semantics:
- **Decision-focused**: "Should we assign this asset to this session?"
- **Mutation-coupled**: Leads to assignment decisions and persistence
- **System-facing**: Used for automated assignment thresholds
- **Three-tier confidence**: High/Medium/Low with numeric scoring

## 4. Whether These Vocabularies Represent the Same Concept

**Partial overlap, different purposes:**

Similar aspects:
- Both assess similarity between uploaded content and session contexts
- Both use confidence to indicate strength of match
- Both operate on similar metadata and calendar signals

Different aspects:
- **Recognition**: "Does this look like X?" (detection)
- **Matching**: "Should we assign to X?" (decision)
- **Recognition**: Binary user guidance (high vs low confidence)
- **Matching**: Three-tier automated decision support (high/medium/low)
- **Recognition**: Pre-mutation, workflow guidance
- **Matching**: Post-mutation, assignment authority

## 5. Risks of Unifying Them Too Early

### Architectural Drift
- Blurs the boundary between detection and decision semantics
- May force recognition to adopt assignment-focused vocabulary
- Could create confusion about whether recognition implies assignment intent

### Premature Coupling
- Recognition becomes dependent on matching/assignment evolution
- Changes to assignment confidence may break recognition semantics
- Limits ability to evolve recognition vocabulary independently

### Semantic Mismatch
- Recognition's binary confidence doesn't map cleanly to three-tier assignment confidence
- User-facing "high-confidence" vs system-facing "HIGH" may create confusion
- Recognition's "low-confidence" is not the same as assignment's "LOW"

### Implementation Complexity
- Requires immediate enum extension or modification
- May need to update existing matching services to accommodate recognition semantics
- Introduces cross-package coordination before recognition stability is proven

## 6. Risks of Leaving Them Separate

### Vocabulary Fragmentation
- Two different confidence systems in the same domain space
- Potential confusion about which confidence vocabulary to use when
- Documentation and onboarding complexity

### Future Integration Cost
- Later unification may require more extensive refactoring
- Existing recognition consumers may need migration
- Test coverage duplication during transition

### Inconsistent User Experience
- Different confidence terminology across similar workflows
- Potential for user confusion if confidence semantics diverge significantly

### Maintenance Overhead
- Two separate confidence evolution paths
- Duplicate validation and testing logic
- Increased cognitive load for developers

## 7. Recommended v1 Decision

**Keep recognition vocabulary ingest-local for v1.**

**Rationale:**
1. **Boundary Preservation**: Recognition is detection-only, pre-mutation guidance. Matching is decision-focused, mutation-coupled. Different purposes deserve different vocabularies.
2. **Conservative Evolution**: Allow recognition to prove its value and semantics before coupling to assignment vocabulary.
3. **Semantic Clarity**: "high-confidence" (recognition) and "HIGH" (assignment) can coexist with clear documentation about their different purposes.
4. **Independence**: Recognition can evolve its confidence model based on user feedback without affecting assignment stability.

**Implementation approach:**
- Keep `TIER_HIGH_CONFIDENCE` and `TIER_LOW_CONFIDENCE` constants in `BatchUploadRecognitionService`
- Document the distinction clearly in service comments
- Add architecture note explaining the vocabulary separation
- Review unification in v2 based on recognition usage patterns

## 8. What Would Need to Change Later If Unification Is Chosen

If recognition and assignment confidence vocabularies should be unified in a future version:

### Enum Extension
```php
enum SessionMatchConfidenceTier: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
    // Potential addition for recognition alignment
    case HIGH_CONFIDENCE = 'high-confidence';  // If recognition needs distinct vocabulary
    case LOW_CONFIDENCE = 'low-confidence';    // If recognition needs distinct vocabulary
}
```

### Service Updates
- `BatchUploadRecognitionService` would import and use `SessionMatchConfidenceTier`
- Update all confidence tier references from string constants to enum cases
- Update tests to expect enum values instead of strings

### Documentation Alignment
- Update recognition documentation to reference shared confidence vocabulary
- Clarify semantic differences between recognition and assignment use of same tiers
- Update architectural decision records

### Migration Path
- Add enum support while maintaining string constants for backward compatibility
- Gradually migrate recognition outputs to use enum values
- Remove string constants after migration is complete

## 9. Guardrails / What Not to Do

### Do Not
- Rush unification before recognition proves stable value
- Modify existing assignment confidence enum to accommodate recognition prematurely
- Assume recognition and assignment confidence represent identical concepts
- Create cross-package dependencies for vocabulary sharing
- Redesign persistence models to accommodate vocabulary unification
- Add new events to communicate recognition confidence across packages

### Do
- Maintain clear documentation about vocabulary separation
- Monitor recognition usage patterns to inform future unification decisions
- Keep recognition vocabulary ingest-local and conservative
- Review vocabulary alignment as part of v2 planning
- Preserve the semantic distinction between detection and decision confidence

### Review Triggers for Future Unification
- Recognition shows consistent usage patterns across multiple workflows
- User feedback indicates confusion about vocabulary differences
- Recognition confidence model evolves to need three-tier semantics
- Cross-package consumers need recognition confidence before assignment decisions

---

**Decision Authority**: Architecture note for v1 implementation guidance. Future unification should be revisited during v2 planning based on real usage and stability evidence.
