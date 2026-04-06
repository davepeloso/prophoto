---
trigger: glob
globs: prophoto-assets/**
---

# Assets Rules

- Before making changes, confirm this package owns the concern. If not, stop and ask
- Assets own canonical media state
- May store projections (like asset_session_contexts)
- Must not own ingest decision logic
- Must not query booking directly

Event flow:

- consumes SessionAssociationResolved
- emits AssetSessionContextAttached