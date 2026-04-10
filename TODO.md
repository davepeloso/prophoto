# TODO

## prophoto-ingest/src/Services/BatchUploadRecognitionService.php
- Extract ranking logic into a dedicated value object or strategy if it grows (keeps service slimmer).
- Consider making thresholds explicit inputs (instead of config lookups) for easier deterministic testing.
- Consider adding an ingest-local logging/debug hook for inspecting candidate ranking during development.
- Consider separating candidate generation concerns from candidate presentation concerns if ingest UI needs diverge.
- Consider replacing raw string constants for outcome/tier with ingest-local enums once behavior is stable.
- Consider centralizing the `3` candidate limit as a class constant beside `FIXED_NEXT_ACTIONS`.
- Consider centralizing outcome/tier strings and candidate-limit `3` once behavior stabilizes.
- TODO: verify deterministic tie-break ordering in code, not just comments/assertions.
- TODO: verify metadata-only path does not accidentally degrade into an empty-snapshot early.
- Consider replacing raw outcome/tier strings with ingest-local enums once the behavior is stable.
- Consider moving ranking sort keys into named helpers/constants if tie-break logic grows.
- TODO: verify whether fallback label `Session {$sessionId}` is acceptable when `session_id` is empty; current output could become `Session `.
- Consider making `MAX_LOW_CONFIDENCE_CANDIDATES` and outcome/tier constants public only if tests or consumers need shared access; otherwise keep them protected/internal.
- TODO: verify whether `assertValidSessionContextSnapshots()` should also reject associative arrays if the contract is truly a list.
- Consider adding a dedicated helper for building the top-level `confidence` block now that invariants are explicit.
- TODO: verify whether `scoreCandidates()` can ever return malformed candidate rows; if yes, add defensive validation before ranking.
- TODO: add/verify a unit test that the top-ranked candidate is included in `low_confidence_candidates` for the low-confidence outcome.

## prophoto-ingest/src/Services/IngestItemContextBuilder.php
- TODO: verify no derived/matching fields are present in returned snapshot (for example no best_match, candidate lists, or inferred decisions).
- TODO: verify session_context_snapshot is passed through without filtering, re-indexing, sorting, or shape mutation.
- TODO: ensure buildInputSnapshots() does not silently default null session context to [] unless that behavior is explicitly required by downstream consumers.
- TODO: verify buildMetadataSnapshot() does not depend on any matching/recognition services.
- TODO: ensure no implicit coupling to IngestItemSessionMatchingFlowService (no fields that only exist for downstream matching convenience).
- Consider making buildInputSnapshots() the single public entry point and deprecating other builder methods if redundancy exists.
- Add explicit return shape docblock describing metadata_snapshot, session_context_snapshot, and nullability guarantees.
- Consider formalizing a ContextSnapshot structure once multiple consumers rely on it.
- Consider adding explicit docblock text: "This is a pure input snapshot builder. No decision logic allowed."

## prophoto-ingest/tests/Unit/BatchUploadRecognitionServiceTest.php
- Consider extracting the anonymous generator/scoring stubs into small private helpers to reduce duplication across targeted edge-case tests.
- TODO: consider adding one test that proves stable secondary ordering when scores tie (same score, different session_id/buffer/distance inputs).
