# Sprint 4 — Proofing Pipeline: Modal UX + Constraint Enforcement
## Story Specifications (Net-New Work Only)

**Sprint dates:** April 14+, 2026
**Owning package:** `prophoto-gallery`
**Sprint goal:** The proofing viewer has a polished image action modal, the pipeline enforces max_approvals / max_pending caps, and the photographer's activity ledger has distinct icons per action type.
**Already done (from Sprint 3):** Story 4.4 (GalleryActivityLogger service) and most of Story 4.2 (approval API endpoints exist). Story 4.2 remains for constraint hardening only.

---

## Story 4.1 — Image Action Modal (Client Side)
**Points:** 7 | **Priority:** P0

### What exists today
The proofing viewer (`proofing.blade.php`) has:
- Inline approve/pending/clear buttons per image card
- Star ratings in the card footer
- A separate pending type picker modal
- A separate lightbox for full-image viewing
- All actions work via fetch() → ProofingActionController

### What's changing
Replace the **separate lightbox + inline buttons** with a **unified action modal**:
- Click image → modal opens: full image (left ~60%), action panel (right ~40%)
- Action panel consolidates all interactions into one place
- The old lightbox and inline buttons are removed

### Acceptance criteria

**Modal layout:**
- [ ] Click image card → modal opens with dark overlay
- [ ] Left panel: full-resolution image, ←/→ navigation arrows, image counter (X of Y)
- [ ] Right panel: action controls (see below)
- [ ] Keyboard: ←/→ navigate images, Esc closes modal
- [ ] Responsive: on mobile (< 768px), stack vertically — image top, panel bottom
- [ ] "Changes saved automatically" helper text at bottom of panel

**Action panel contents (top to bottom):**
- [ ] **Filename** — `image.filename` displayed as header
- [ ] **Status badge** — current state (Unapproved / Approved / Pending / Cleared)
- [ ] **Star rating** — 1–5 stars, clickable. Shown only if `modeConfig.ratings_enabled`. Uses existing `rate` endpoint.
- [ ] **"Mark as Approved"** button — green, full-width. Hidden when status = approved or approved_pending. Uses existing `approve` endpoint.
- [ ] **"Approved Pending →"** dropdown — amber, full-width. Shows pending types for this gallery. **Visually disabled + greyed** with tooltip "Approve first" when status ≠ approved. When clicked (if enabled), shows pending type picker inline (not a separate modal) with optional note textarea. Uses existing `pending` endpoint.
- [ ] **"Clear Selection"** button — gray/red-text, full-width. Available when status = approved or approved_pending. Uses existing `clear` endpoint.
- [ ] **Download button** — shown only when `canDownload` is true. Links directly to `image.fullUrl` with `download` attribute. (No server-side tracking yet — Sprint 7.)
- [ ] **Copy link** — copies `{window.location.origin}/g/{token}#image-{id}` to clipboard. Shows "Copied!" feedback for 2s.

**Sequential enforcement (visual):**
- [ ] When `modeConfig.pipeline_sequential` is true and image is NOT approved, the "Approved Pending →" dropdown is disabled, greyed out, with tooltip text "Approve this image first"
- [ ] When image IS approved, dropdown becomes interactive

**Locked state:**
- [ ] When `isLocked` is true, all action buttons are hidden. Status badge + rating shown read-only. "Selections submitted" message shown.

### Files to modify
- `resources/views/viewer/proofing.blade.php` — replace lightbox + inline buttons with modal
- `src/Http/Controllers/ProofingViewerController.php` — add `rating` field to `imageData` (currently missing — ratings are in the activity log but not passed to the view)

### Files NOT modified
- `ProofingActionController.php` — endpoints are unchanged
- `routes/web.php` — no new routes

### Testing
- Existing `ProofingViewerTest` tests continue to pass (they test HTTP responses, not DOM)
- No new PHP tests needed (this is a Blade/Alpine.js change)
- Manual browser testing required

---

## Story 4.2 — Approval API Constraint Hardening
**Points:** 2 (reduced from 5 — endpoints already exist)

### What exists today
- `approve`, `pending`, `clear`, `rate`, `submit` all work
- Sequential pipeline enforcement works (pending returns 422 if not approved)
- No max_approvals or max_pending enforcement

### What's changing
Add constraint checks to `approve` and `pending` endpoints.

### Acceptance criteria

**max_approvals enforcement:**
- [ ] Before approving, count existing `approved` + `approved_pending` states for this share
- [ ] If count >= `mode_config.max_approvals` (and max_approvals is not null), return 422: `{"error": "Maximum of {N} approvals reached.", "constraint": "max_approvals", "current": X, "max": N}`
- [ ] Clearing an image then re-approving should succeed (count goes down on clear)

**max_pending enforcement:**
- [ ] Before setting pending, count existing `approved_pending` states for this share
- [ ] If count >= `mode_config.max_pending` (and max_pending is not null), return 422: `{"error": "Maximum of {N} pending requests reached.", "constraint": "max_pending", "current": X, "max": N}`

**min_approvals on submit:**
- [ ] Before submitting, count approved + approved_pending
- [ ] If count < `mode_config.min_approvals` (and min_approvals is not null), return 422: `{"error": "At least {N} images must be approved before submitting.", "constraint": "min_approvals", "current": X, "min": N}`

### Files to modify
- `src/Http/Controllers/ProofingActionController.php` — add constraint checks to `approve()`, `pending()`, `submit()`

### Testing (Feature tests)
- [ ] `test_approve_blocked_at_max_approvals` — gallery with max_approvals=2, approve 2, try 3rd → 422
- [ ] `test_approve_allowed_after_clear_frees_slot` — approve 2, clear 1, approve new → 200
- [ ] `test_pending_blocked_at_max_pending` — gallery with max_pending=1, pending 1, try 2nd → 422
- [ ] `test_submit_blocked_below_min_approvals` — gallery with min_approvals=3, approve 2, submit → 422
- [ ] `test_submit_allowed_at_min_approvals` — approve 3, submit → 200
- [ ] `test_constraints_return_structured_error` — verify error JSON has `constraint`, `current`, `max`/`min` fields

---

## Story 4.3 — Activity Ledger Polish (Photographer View)
**Points:** 4 (reduced from 6 — relation manager already exists with working filters and sorting)

### What exists today
- `GalleryActivityRelationManager` — fully functional read-only table on EditGallery page
- Color-coded action_type badges, actor_type badges, email search, filters
- `AccessLogResource` — standalone Filament resource

### What's changing
Add distinct icons per action type, resolve image filename for context, improve the empty state.

### Acceptance criteria

**Icon column:**
- [ ] New icon column before action_type, using Filament's `IconColumn`
- [ ] Icon mapping:
  - `approved` → `heroicon-o-check-circle` (green)
  - `approved_pending` → `heroicon-o-wrench-screwdriver` (amber)
  - `cleared` → `heroicon-o-x-circle` (gray)
  - `rated` → `heroicon-o-star` (yellow)
  - `gallery_submitted` → `heroicon-o-lock-closed` (green)
  - `gallery_locked` → `heroicon-o-lock-closed` (red)
  - `identity_confirmed` → `heroicon-o-finger-print` (blue)
  - `share_created` → `heroicon-o-share` (blue)
  - `gallery_viewed` → `heroicon-o-eye` (gray)
  - `gallery_created` → `heroicon-o-plus-circle` (blue)
  - default → `heroicon-o-question-mark-circle` (gray)

**Image context:**
- [ ] `image_id` column displays the image's `original_filename` instead of the raw ID
- [ ] Uses eager loading on the relationship to avoid N+1 (or a subquery)

**Empty state:**
- [ ] Table shows: "No activity yet — share the gallery to get started" when empty

### Files to modify
- `src/Filament/Resources/GalleryResource/RelationManagers/GalleryActivityRelationManager.php`
- `src/Models/GalleryActivityLog.php` — add `image()` belongsTo relationship for eager loading

### Testing
- Existing `GalleryActivityLoggingTest` tests continue to pass
- No new tests needed (Filament UI rendering isn't tested at package level)

---

## Story 4.5 — Constraint UI Feedback
**Points:** 3

### What exists today
- Progress bar showing "X approved" and "Min: N" + total count
- No cap warnings, no disabled-at-cap behavior

### What's changing
The progress bar and action buttons react to constraint limits in real-time.

### Acceptance criteria

**Approved count display:**
- [ ] When `max_approvals` is set: counter shows "X / N approved" (not just "X approved")
- [ ] When `max_approvals` is null: counter shows "X approved" (no cap)
- [ ] Progress bar fills relative to max_approvals (if set) or total images (if not)

**Cap-reached behavior (max_approvals):**
- [ ] When approved count reaches max_approvals, the "Mark as Approved" button in the modal is disabled with text "Selection limit reached (N/N)"
- [ ] The API also returns 422 (Story 4.2), so this is a graceful UX layer
- [ ] If a clear frees a slot, the button re-enables immediately (Alpine reactive)

**Cap-reached behavior (max_pending):**
- [ ] When pending count reaches max_pending, the "Approved Pending →" dropdown in the modal is disabled with text "Pending limit reached"
- [ ] Same reactive re-enable on clear

**Min-approvals feedback on submit:**
- [ ] When approved count < min_approvals, the "Submit My Selections" button shows tooltip: "Select at least N more images"
- [ ] Button becomes active once min threshold is met

### Files to modify
- `resources/views/viewer/proofing.blade.php` — update Alpine.js component with constraint tracking

### Testing
- No new PHP tests (this is purely frontend logic backed by Story 4.2's API enforcement)
- Manual browser testing required

---

## Implementation Order

1. **Story 4.2** first — API constraint hardening + tests. Backend foundation.
2. **Story 4.3** — Activity ledger polish. Quick, independent.
3. **Story 4.1** — Image action modal. Largest piece, builds on working API.
4. **Story 4.5** — Constraint UI feedback. Layer on top of the modal from 4.1.

---

## Out of Scope (Sprint 4)
- Download tracking (Sprint 7)
- Comments (Sprint 8)
- OTP identity verification (future phase)
- Submission notification email (Sprint 6)
- Photographer dashboard (Sprint 5)
