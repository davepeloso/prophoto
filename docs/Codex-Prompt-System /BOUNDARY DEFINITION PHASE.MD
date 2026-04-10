We are working in the ProPhoto monorepo.

This is an ARCHITECTURE BOUNDARY task.

## Source documents

- docs/PROJECT-CORE.md
- prophoto-<package>/docs/<feature>.md

## Rules

- No PHP
- No DTOs
- No migrations
- No UI hooks
- No tests
- Do NOT redesign the feature
- Do NOT assume new events are required

## Task
Define:

- recognition/result structure (conceptual, not code)
- owning package responsibilities
- service boundary
- trigger point in lifecycle
- whether this emits an event in v1 or remains internal
- what is explicitly NOT wired in v1

## Output

- Markdown only
- No commentary