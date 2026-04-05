# ProPhoto Architecture Index

> **Purpose**: Single source of truth for which architecture documents are authoritative, how they group into workstreams, and which context set to load for a given task.

---

## Authoritative Global Docs

These apply to **every** workstream. Always load them.

| # | File | Location | Scope |
|---|------|----------|-------|
| 1 | `RULES.md` | `/RULES.md` | Platform-wide constraints, conventions, and non-negotiables |
| 2 | `SYSTEM.md` | `/SYSTEM.md` | High-level system architecture, domain boundaries, tech stack |

---

## Booking / Ingest / Session-Association Docs

Load **Global (1–2) + these (3–7)** when working on booking, media ingest, or session matching.

| # | File | Location |
|---|------|----------|
| 3 | `BOOKING-OPERATIONAL-CONTEXT.md` | `docs/architecture/` |
| 4 | `BOOKING-DATA-MODEL.md` | `docs/architecture/` |
| 5 | `SESSION-MATCHING-STRATEGY.md` | `docs/architecture/` |
| 6 | `INGEST-SESSION-ASSOCIATION-DATA-MODEL.md` | `docs/architecture/` |
| 7 | `INGEST-SESSION-ASSOCIATION-IMPLEMENTATION-CHECKLIST.md` | `docs/architecture/` |

---

## Intelligence Docs

Load **Global (1–2) + these (8–13)** when working on derived intelligence, generators, or intelligence orchestration.

| # | File | Location |
|---|------|----------|
| 8 | `DERIVED-INTELLIGENCE-LAYER.md` | `docs/architecture/` |
| 9 | `INTELLIGENCE-ORCHESTRATOR.md` | `docs/architecture/` |
| 10 | `INTELLIGENCE-RUN-DATA-MODEL.md` | `docs/architecture/` |
| 11 | `INTELLIGENCE-GENERATOR-REGISTRY.md` | `docs/architecture/` |
| 12 | `INTELLIGENCE-SESSION-CONTEXT-INTEGRATION.md` | `docs/architecture/` |
| 13 | `DERIVED-INTELLIGENCE-IMPLEMENTATION-CHECKLIST.md` | `docs/architecture/` |

---

## Context Loading Rules

| Task touches | Load docs |
|-------------|-----------|
| Booking / ingest / session matching only | **1–7** |
| Intelligence only | **1–2 + 8–13** |
| Both ingest/session context AND intelligence | **1–13** (full set) |

---

## Deprecated / Superseded Docs

| File | Status | Superseded by |
|------|--------|---------------|
| *(none yet)* | — | — |

> When a doc is retired, move it to a `docs/architecture/_deprecated/` folder and record it here with the date and replacement doc.

---

*Last updated: 2026-04-04*
