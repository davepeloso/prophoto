# Agent Context Loading Guide — ProPhoto

> **Purpose**: This document tells any AI coding agent exactly what to read, in what order, before writing a single line of code. It exists because ProPhoto is a disciplined, event-driven modular monolith — not a blank canvas. Skipping the foundational docs leads to duplicate packages, broken ownership boundaries, and parallel implementations that ignore the existing spine.

---

## The Problem This Solves

When an agent starts Sprint 3+ work (intelligence orchestration, new generators, session-context features, or any cross-package behavior), it will be tempted to:

- Scaffold new services that already exist in another package
- Skip the contracts package and hardcode DTOs inline
- Query booking directly from intelligence (forbidden)
- Treat `prophoto-assets` as "just another model" instead of the canonical media spine
- Invent new event patterns instead of using the ones defined in `prophoto-contracts`
- Create mutable state where append-only history is required

**Every one of these has happened before.** This guide prevents it.

---

## Mandatory Pre-Flight: Read Before You Code

### Tier 1 — Always Read (Every Task, No Exceptions)

These define the laws of the system. If code contradicts these, the code is wrong.

| Order | File | Why |
|-------|------|-----|
| 1 | `RULES.md` | Hard constraints: dependency law, database ownership, integration style, metadata spine rule, storage ownership, domain events |
| 2 | `SYSTEM.md` | Core event loop, package roles, dependency rules, anti-patterns. **This is the center of the entire system.** |
| 3 | `CONTRACTS-PACKAGE-README.md` | The shared kernel — 14 interfaces, 21 DTOs, 15 enums, 14 event contracts. If you need a type, it's probably already here. **Check before inventing.** |
| 4 | `AGENTS.md` | Package mental model, coding style, what to avoid, how to approach a task, response rules |

**Time to read**: ~10 minutes. **Cost of skipping**: rebuilding the wrong thing for hours.

### Tier 2 — Read Based on What You're Touching

Use the Architecture Index (`ARCHITECTURE-ARCHITECTURE-INDEX.md`) to determine which docs to load:

| Task touches | Load these (in addition to Tier 1) |
|-------------|-------------------------------------|
| Booking / ingest / session matching | `ARCHITECTURE-BOOKING-OPERATIONAL-CONTEXT.md`, `ARCHITECTURE-BOOKING-DATA-MODEL.md`, `SESSION-MATCHING-STRATEGY.md`, plus ingest association docs |
| Intelligence / generators / orchestration | `INTELLIGENCE-ACTIVATION-ROADMAP.md` (start here — current state + build order), `ARCHITECTURE-DERIVED-INTELLIGENCE-LAYER.md`, `ARCHITECTURE-INTELLIGENCE-ORCHESTRATOR.md`, `ARCHITECTURE-INTELLIGENCE-RUN-DATA-MODEL.md`, `ARCHITECTURE-INTELLIGENCE-GENERATOR-REGISTRY.md`, `ARCHITECTURE-INTELLIGENCE-SESSION-CONTEXT-INTEGRATION.md`, `ARCHITECTURE-DERIVED-INTELLIGENCE-IMPLEMENTATION-CHECKLIST.md` |
| Asset spine / metadata / canonical truth | `ARCHITECTURE-ASSET-SPINE.md`, `ARCHITECTURE-ASSET-SPINE-STATUS.md`, `ARCHITECTURE-ASSET-METADATA-HARDENING-PACK.md`, `ARCHITECTURE-NORMALIZED-METADATA-SCHEMA-v1.md` |
| Gallery / proofing / presentation / image management | `GALLERY-PACKAGE-README.md`, `PACKAGE-CLASSIFICATION.md`, `ASSET-CONTRACTS-PATCH.md`, `PHASE-2-GALLERY-NOTES.md`, `PHASE-3-GALLERY-NOTES.md`, `PHASE-4-GALLERY-NOTES.md`, `PHASE-5-NOTIFICATIONS-NOTES.md`, `PHASE-6-NOTIFICATIONS-NOTES.md`, `ARCHITECTURE-PIPELINE-VERIFICATION.md`. Also read `GalleryRepositoryContract` in `prophoto-contracts`, the `Image` model, `ImageApprovalState` model, `ProofingActionController`, `DownloadController`, and `GalleryActivityLogger` service. |
| Notifications / email delivery / in-app alerts | `PHASE-5-NOTIFICATIONS-NOTES.md`, `PHASE-6-NOTIFICATIONS-NOTES.md`, `ARCHITECTURE-PIPELINE-VERIFICATION.md`. Also read `HandleGallerySubmitted` listener, `HandleImageDownloaded` listener, `HandleGalleryViewed` listener, `ProofingSubmittedMail` mailable, `Message` model, and `NotificationsServiceProvider`. |
| Downloads / download tracking / download stats | `PHASE-6-NOTIFICATIONS-NOTES.md`. Also read `DownloadController`, `GalleryShare` model (`canDownload`, `incrementDownloadCount`, `hasReachedMaxDownloads`), `Gallery` model (`incrementDownloadCount`), `ImageDownloaded` event, and `GalleryDownloadStatsWidget`. |
| Both ingest/session AND intelligence | **All of the above** (docs 1–13 from the Architecture Index) |

| Sandbox / host app / Filament / integration testing | `SANDBOX-SETUP-GUIDE.md`, `Filament-Namespace-Issue.md`. Also read `create-sandbox.sh` and `SandboxSeeder.php` if modifying the sandbox. |
| Any Filament resource, action, widget, or relation manager | `Filament-Namespace-Issue.md` — **MANDATORY**. Filament v4 moved actions, layout components, utility classes, and property types. Every namespace mapping is documented here. Writing Filament code without reading this doc WILL produce runtime errors. |

### Tier 3 — Read When Modifying a Specific Package

Before changing any package, read its README:

| Package | README |
|---------|--------|
| `prophoto-assets` | `ASSETS-PACKAGE-README.md` + the 9-part deep dive (`ASSETS PACKAGE-01` through `09`) |
| `prophoto-contracts` | `CONTRACTS-PACKAGE-README.md` |
| `prophoto-ingest` | `INGEST-PACKAGE-README.md` |
| `prophoto-intelligence` | `INTELLEGENCE-PACKAGE-README.md` |
| `prophoto-booking` | `BOOKING-PACKAGE-README.md` |
| `prophoto-gallery` | `GALLERY-PACKAGE-README.md` |
| `prophoto-notifications` | `PHASE-5-NOTIFICATIONS-NOTES.md` (README exists but is minimal — phase notes are authoritative) |
| `prophoto-access` | `ACCESS-PACKAGE-README.md` |

---

## The Core Event Loop — Memorize This

This is the spine. Every feature flows through it:

```
prophoto-ingest (decides)
  → emits SessionAssociationResolved

prophoto-assets (attaches canonical truth)
  → consumes SessionAssociationResolved
  → emits AssetSessionContextAttached
  → emits AssetReadyV1

prophoto-intelligence (derives outputs)
  → consumes AssetSessionContextAttached
  → consumes AssetReadyV1
```

**Meaning**: Ingest decides → Assets attach truth → Intelligence derives.

If your code breaks this flow, it is wrong. Full stop.

---

## Non-Negotiable Rules (Quick Reference)

These are extracted from RULES.md and SYSTEM.md for fast reference. The source docs are authoritative.

1. **Intelligence MUST NOT query booking directly** — use `SessionContextSnapshot` DTO
2. **Only the owning package mutates its data** — no cross-package writes
3. **One table = one owner** — no cross-package migrations
4. **Events are immutable, versioned, carry IDs not models** — events live in `prophoto-contracts`
5. **Contracts package has zero domain dependencies** — it defines shapes, not behavior
6. **Foundational packages are headless** — no UI in assets, contracts, or intelligence
7. **Append-only history where architecture says so** — decision history is never rewritten
8. **Manual locks block automated supersession** — never silently bypass
9. **No legacy patterns in new code** — `_archive/prophoto-ingest-legacy` is reference only
10. **Check contracts before inventing** — the DTO/enum/event probably already exists

---

## Sprint 3+ Specific Warnings

### If you're working on Intelligence (Phases 3–7):

- The package scaffold already exists. Do not create a new one.
- The planner, registry, orchestrator, and run repository are already implemented.
- Entry listener routing is in place. Check `IntelligenceServiceProvider` before adding routes.
- Result validation enforces both required outputs and no unexpected output families.
- Read `ARCHITECTURE-DERIVED-INTELLIGENCE-PHASE-NOTES.md` to see what's landed vs. what's open.

### If you're working on Session Context:

- `SessionContextSnapshot` is the canonical DTO for passing session info to intelligence.
- The asset-session trigger path already passes a real snapshot into orchestration.
- `asset_session_contexts` is the asset-side projection table — it already exists.
- Session matching is deterministic (not ML). Don't introduce ML matching.

### If you're adding a new Generator:

- Generators register through the `IntelligenceGeneratorRegistry`.
- Generators must implement `AssetIntelligenceGeneratorContract` from `prophoto-contracts`.
- Generators must never invoke peer generators directly.
- Generator descriptors declare `produces_outputs`. Capability metadata (`produces_capabilities`, `requires_capabilities`) is deferred — do not implement yet.

### If you're touching Assets:

- The Asset Spine is fully functional end-to-end. Do not re-scaffold.
- Ingest dual-write is the default path (`INGEST_ASSET_SPINE_DUAL_WRITE=true`).
- Raw metadata is immutable source truth. Normalized metadata is schema-versioned.
- Read the 9-part deep dive before making structural changes.

### If you're working on Gallery (Sprint 5+):

- `prophoto-gallery` reads from `prophoto-assets` but **never writes to it**. This is a one-directional downstream → upstream relationship, explicitly allowed by RULES.md Rule 2.
- `prophoto-contracts` was **not modified** in Sprint 3 or 4. No new interfaces, DTOs, or events were needed.
- `Image::asset()` is the Eloquent `belongsTo` crossing the package boundary. `Gallery::imagesWithAssets()` eager-loads `asset.derivatives` to avoid N+1.
- `Image::thumbnail()` resolves the best derivative (prefers `thumbnail`, falls back to `preview`). `Image::resolvedThumbnailUrl()` has a three-tier fallback: asset derivative → legacy ImageKit → legacy local path.
- `GalleryRepositoryContract` (in `prophoto-contracts`) is the write seam for attaching assets to galleries. The `EloquentGalleryRepository` implementation is bound in `GalleryServiceProvider`. Cross-package consumers call `attachAsset(GalleryId, AssetId)` — never create `Image` rows directly.
- Assets link to sessions through `asset_session_contexts` (join table in `prophoto-assets`), not a column on `assets`. Query that table to find session assets.
- The gallery has two types: `proofing` (full pipeline with identity gate, approval workflow, activity ledger) and `presentation` (view-only, no pipeline). `mode_config` JSON holds per-gallery pipeline settings for proofing galleries; it's `null` for presentation.
- `GalleryTestServiceProvider` exists in `tests/` as a slim provider that loads migrations, views, web routes, and config without the `IngestSessionConfirmed` listener. TestCase includes a deterministic `APP_KEY` (required by web middleware).
- **Sprint 3 delivered the full viewer layer:** Share link resolution (`GET /g/{token}`), presentation viewer (Tailwind CDN + Alpine.js lightbox), identity gate (email confirmation), proofing viewer (approve/pending/clear/rate/submit actions), and admin-facing activity log interfaces.
- **Sprint 4 delivered modal UX + constraint enforcement:** Unified image action modal (replaces separate lightbox + inline buttons), server-side constraint checks (max_approvals, max_pending, min_approvals with structured 422 responses), activity ledger Filament polish (icons, filenames, empty states), and client-side constraint UI feedback (disabled buttons, progress labels, submit gating).
- **Sprint 5 delivered submission notifications:** `GallerySubmitted` event dispatched from `ProofingActionController::submit()`, `HandleGallerySubmitted` listener in prophoto-notifications sends email + creates Message record + optional Filament database notification. `RecentSubmissionsWidget` on the Filament dashboard shows recent submissions. `GalleryShare::approvalStates()` relationship added.
- **Public web routes** are in `routes/web.php` wrapped in `middleware(['web'])`. Token-based access — no auth required. See `PHASE-3-GALLERY-NOTES.md` for the full route map.
- **`ImageApprovalState`** model tracks per-image, per-share approval status. Uses `updateOrCreate` keyed on `(gallery_id, image_id, gallery_share_id)`. Status constants: `unapproved`, `approved`, `approved_pending`, `cleared`.
- **`GalleryActivityLogger::log()`** is the single write path for the append-only activity ledger. All viewer actions (identity confirmed, image approved, etc.) go through this service.
- **Ratings live in the activity ledger** — stored as `metadata.rating` on `action_type='rated'` rows. Not on `ImageApprovalState`. `ProofingViewerController` queries the latest rating per image from the log.
- **Constraint enforcement pattern** — server returns structured 422: `{error, constraint, current, max/min}`. Client-side Alpine.js computed properties disable buttons and show explanatory text. The exclusion clause `where('image_id', '!=', ...)` prevents re-approval of an already-approved image from counting against itself.
- **`ProofingActionController::resolveContext()`** is the standard pattern for validating a share token — checks existence, validity, identity confirmation, and lock status.
- **Unified modal is the interaction center** — all image actions (approve, pending, clear, rate, download, copy link) happen in the right-side panel. New actions should be added here.
- **Blade limitation:** Cannot use `fn()` arrow functions inside `@json()`. Always pre-build Alpine.js data in the controller.
- **Dual logging:** `GalleryAccessLog` (per-share analytics) and `GalleryActivityLogger` (audit ledger) serve different purposes — this is intentional, not a bug.
- **Sprint 6 delivered download tracking + view notifications:** `DownloadController` handles `GET /g/{token}/download/{image}` with full permission enforcement (`can_download`, `max_downloads`), atomic counter increments on both `GalleryShare` and `Gallery`, dual logging (activity + access), and dispatches `ImageDownloaded` event. `GalleryViewed` event fires at milestone thresholds `[1, 5, 10, 25, 50]` — not on every page load. `GalleryDownloadStatsWidget` shows per-share download breakdown on the gallery edit page (registered via `EditGallery::getFooterWidgets()`, gated by `GalleryPlugin::hasDownloadStats()`).
- **Story 6.4 was deliberately skipped** — download limit approaching notification deferred. The enforcement plumbing (`max_downloads`, `hasReachedMaxDownloads()`) exists but rate-limiting downloads on a digital product adds friction without clear value for a sole proprietor studio.
- **Downloads stay in prophoto-gallery** — `can_download`, `max_downloads`, `download_count` all live on `GalleryShare` (gallery-owned table). A separate downloads package would violate Database Ownership.
- **Three notification listeners now exist:** `HandleGallerySubmitted` (Sprint 5), `HandleImageDownloaded` (Sprint 6), `HandleGalleryViewed` (Sprint 6). All follow the same 4-step pattern: resolve recipient → send email → create Message → optional Filament bell.

### If you're writing ANY Filament code (Resources, Actions, Widgets, RelationManagers):

- **READ `Filament-Namespace-Issue.md` FIRST** — this is not optional. Filament v4 moved dozens of classes to new namespaces. Every mapping is documented there.
- **Actions live in `Filament\Actions\*`** — NOT `Filament\Tables\Actions\*`. This includes Action, EditAction, DeleteAction, BulkAction, BulkActionGroup, CreateAction. The old namespace does not exist in v4.
- **Layout components live in `Filament\Schemas\Components\*`** — Section, Grid, Wizard, Wizard\Step, Placeholder, Hidden, Repeater all moved. Input components (TextInput, Select, Toggle, etc.) stayed in `Filament\Forms\Components\*`.
- **`Get` and `Set` closures** are now `Filament\Schemas\Components\Utilities\Get` and `Set` — not `Filament\Forms\Get`.
- **Property types on Resources/RelationManagers** — `$navigationGroup` is `\UnitEnum|string|null`, `$navigationIcon` and `$icon` are `string|\BackedEnum|null`. Using `?string` will cause a fatal error.
- **`BadgeColumn` is removed** — use `TextColumn::make()->badge()` instead.
- **`IconColumnSize` enum is removed** — use string sizes: `'sm'`, `'md'`, `'lg'`.
- **`getTableQuery()` on RelationManagers returns null in v4** — use `->modifyQueryUsing()` in the `table()` method instead.
- **Return types** — if a helper method returns a layout component, use `\Filament\Schemas\Components\Component` not `\Filament\Forms\Components\Component`.
- **`form()` method signature** — v4 parent expects `Form|\Filament\Schemas\Schema $form): \Filament\Schemas\Schema`.
- **After installing Filament**, run `php artisan filament:assets` to publish CSS/JS. Without this, the admin panel renders unstyled.
- **`databaseNotifications()`** requires Laravel's `notifications` table — create it with `php artisan make:notifications-table` before migrating.

### If you're working on Notifications (Sprint 6+):

- `prophoto-notifications` is a **downstream consumer** — it listens to events from other packages but never mutates their state.
- **Event pattern:** events live in the originating package (e.g., `GallerySubmitted` in prophoto-gallery). Notifications listens via `Event::listen()` in its service provider.
- **Listener pattern:** `HandleGallerySubmitted` is the template — resolve recipient, send email, create Message record, send optional Filament notification. Copy this pattern for new triggers.
- **Recipient resolution:** Priority: share creator (`shared_by_user_id`) → first studio user (`studio_id`). Future: add notification preferences per user.
- **Filament is optional:** The bell icon requires Filament's `databaseNotifications()` panel config. The `class_exists()` guard ensures the listener never crashes without Filament.
- **Message model** is the audit trail — every notification sent creates a `Message` record. The model has `studio_id`, `recipient_user_id`, `gallery_id`, `image_id`, `subject`, `body`, `read_at`.
- **No contracts touched** — notifications doesn't define or modify shared interfaces.

---

## Before Proposing Any New Slice

Always define (from AGENTS.md):

1. **Package owner** — which package owns this?
2. **Input boundary** — what data enters and from where?
3. **Persistence boundary** — what tables are written and who owns them?
4. **Event boundary** — what events are emitted/consumed?
5. **Tests to add** — what behavior gets locked?
6. **Explicitly out of scope** — what are you NOT doing?

---

## Current Implementation Status (as of April 2026)

| Component | Status |
|-----------|--------|
| Contracts (DTOs, events, enums, interfaces) | Complete |
| Asset Spine (canonical media ownership) | Fully functional, end-to-end verified |
| Ingest (session matching, decisions, association) | Fully implemented |
| Intelligence Phase 1–2 (contracts + events) | Complete |
| Intelligence Phase 3 (package scaffold) | In progress (config not formalized) |
| Intelligence Phase 4 (migrations) | Complete |
| Intelligence Phase 5 (services/repositories) | In progress (open items remain) |
| Intelligence Phase 6 (test plan) | In progress |
| Intelligence Phase 7 (acceptance criteria) | In progress |
| Gallery Sprint 1 (gallery type system, mode_config, pending types) | Complete |
| Gallery Sprint 2 (asset association, image selection, image management) | Complete |
| Gallery Sprint 3 (share links, viewers, identity gate, proofing pipeline, activity logging) | Complete — 55 tests, 157 assertions |
| Gallery Sprint 4 (modal UX, constraint enforcement, activity ledger polish, constraint UI) | Complete — 61 tests, 190 assertions |
| Gallery Sprint 5 (GallerySubmitted event, dashboard widget, GalleryShare relationship) | Complete — 61 tests, 190 assertions |
| Gallery Sprint 6 (download endpoint, GalleryViewed event, download stats widget) | Complete — 88 tests, 225 assertions |
| Notifications Sprint 5 (submission email, Message audit trail, Filament bell) | Complete — 9 tests, 10 assertions |
| Notifications Sprint 6 (download + gallery viewed emails, Message records, Filament bells) | Complete — 28 tests, 31 assertions |
| `EloquentGalleryRepository` implements `GalleryRepositoryContract` | Complete — bound in `GalleryServiceProvider` |
| Gallery package-level test suite (Orchestra Testbench) | 88 tests across 8 feature test files |
| Notifications package-level test suite (Orchestra Testbench) | 28 tests across 3 feature test files |
| Filament v4 compatibility (all gallery Filament code) | Complete — all namespace migrations applied, sandbox builds cleanly |
| Sandbox (`create-sandbox.sh`) | Complete — Filament v4, notifications table, asset publishing, all 9 packages |
| 8 empty scaffolds (audit, downloads, etc.) | To be archived |

---

## How to Use This Document

### For Windsurf / Cursor / Claude Code:
Add to your workspace rules or system prompt:
```
Before any code task, read AGENT-CONTEXT-LOADING-GUIDE.md in full, then load Tier 1 docs. Load Tier 2/3 based on what the task touches.
```

### For CLAUDE.md:
Add this line:
```
Always read AGENT-CONTEXT-LOADING-GUIDE.md before starting implementation work.
```

### For manual prompting:
Paste this at the start of any sprint session:
```
Read these files before writing code:
1. AGENT-CONTEXT-LOADING-GUIDE.md
2. RULES.md
3. SYSTEM.md
4. CONTRACTS-PACKAGE-README.md
5. AGENTS.md
Then load the relevant Tier 2 docs based on the task.
```

---

*This document is authoritative for agent onboarding. If agent behavior contradicts the docs listed here, the agent is wrong — not the docs.*

*Last updated: 2026-04-15 — Sprint 6 complete (download tracking, view notifications, download stats widget), Filament v4 migration complete, sandbox fully operational*
