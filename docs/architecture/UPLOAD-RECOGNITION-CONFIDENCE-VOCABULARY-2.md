**Notebook Option**: Unify the confidence vocabulary in v1

 Recommended v1 Decision: Remain Ingest-Local
For the v1 upload-recognition slice, the confidence vocabulary should remain local to prophoto-ingest.
Rationale: The Development-process.md for this slice specifically mandates "no DTOs yet" and "do not assume prophoto-contracts changes are required" during the architecture boundary phase. Keeping it local allows the team to refine the "User Moment" before committing to a system-wide contract.
8. Future Unification Path
If unification is chosen later, the following would need to change:
Contract Expansion: prophoto-contracts would need a new RecognitionResult DTO.
Shared Vocabulary: The SessionMatchConfidenceTier enum would be renamed or aliased to a more generic ConfidenceTier applicable to both recognition and matching.
Service Refactor: The SessionMatchingService would need to be split into a pure ScoringEngine (shared) and separate Assignment and Recognition handlers.
9. Guardrails / What Not To Do
Do Not Add Events: Do not create new domain events for recognition; keep it as a service-level return within the ingest package.
Do Not Mutate Assets: Recognition must never write to prophoto-assets tables; it is a suggestion, not a fact.
Do Not Query Booking Directly: Even for local recognition, follow the isolation rule—consume Session data as snapshots, never via direct DB queries across boundaries.
Do Not Broaden Scope: Stay focused on the upload-recognition moment; do not refactor the SessionAssociationWriteService at this stage.

