---
trigger: glob
globs: prophoto-ingest/**
---

# Ingest Rules

- Before making changes, confirm this package owns the concern. If not, stop and ask
- Decisions are append-only
- Effective assignment rows supersede, never mutate meaning
- Manual locks MUST block automated assignment
- Matching is deterministic (not ML)
- SessionAssociationResolved is the only outbound event

Do not:

- write into asset tables
- query booking directly in matching