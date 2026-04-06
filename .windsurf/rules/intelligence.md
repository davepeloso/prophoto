---
trigger: glob
globs: prophoto-intelligence/**
---

# Intelligence Rules

- Before making changes, confirm this package owns the concern. If not, stop and ask
- Intelligence is derived-only (no mutation of assets)
- Must use SessionContextSnapshot for session-aware logic
- Do not query booking directly
- Planner must be pure (no DB access)
- Orchestrator handles execution boundaries

Trigger sources:

- asset_ready
- asset_session_context