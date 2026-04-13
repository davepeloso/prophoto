# ProPhoto Phase 2: Proofing System — Epic Breakdown & Sprint Planning

**Status:** 🟡 Planning
**Version:** 2.0
**Created:** April 12, 2026
**Updated:** April 12, 2026 — Full rewrite incorporating gallery type system, identity
gate, proofing pipeline, and gallery activity ledger
**Owner:** Dave Peloso
**Based on:** PRD-phase-2-proofing-system.md v2.0, RBAC-phase-2-backlog.md
**Estimated duration:** 8 sprints × 2 weeks = 16 weeks

---

## Sprint Progress Summary

| Sprint | Focus | Status | Points |
|--------|-------|--------|--------|
| Sprint 1 | RBAC Wiring + Foundation | 🔲 Planned | 18 |
| Sprint 2 | Gallery Type System + Image Selection UI | 🔲 Planned | 24 |
| Sprint 3 | Presentation Gallery Viewer + Proofing Identity Gate | 🔲 Planned | 22 |
| Sprint 4 | Proofing Pipeline — Approval States + Ledger | 🔲 Planned | 25 |
| Sprint 5 | Submission Flow + Photographer Approval Dashboard | 🔲 Planned | 22 |
| Sprint 6 | Notifications + Access Control + E2E Smoke Test | 🔲 Planned | 20 |
| Sprint 7 | Versioning + Edit Requests + Download | 🔲 Planned | 22 |
| Sprint 8 | Comments + Deep Link + AI Consent + Duplicate | 🔲 Planned | 16 |

**Total planned: 169 points**

---

## Executive Summary

Phase 2 introduces two gallery types — **Presentation** (view only, public-safe, no
identity) and **Proofing** (attributed workspace, sequential approval pipeline, activity
ledger) — and the infrastructure to support both.

The sprint order follows three rules:

1. **Foundation before features** — Sprint 1 wires RBAC before any gallery UI is built.
2. **Data model before UI** — Sprint 2 establishes the gallery type system and new schema
   before any client-facing surface is built.
3. **Presentation before Proofing** — Sprint 3 ships the simpler Presentation viewer
   first, then adds the identity gate and full pipeline on top.

**Hard dependency:** Phase 1 confirmed assets must be in the system. The gallery image
selection UI in Sprint 2 pulls from confirmed `UploadSession → Asset` records.

---

## All Locked Decisions (reference for all sprints)

| Decision | Answer |
|---|---|
| Q1: Approval UI | Modal, sequential enforcement — Retouch disabled until Approved first |
| Q2: Downloads | Per-gallery photographer choice — watermarked by default |
| Q3: AI consent | Boolean `images.ai_consent_given` |
| Q4: Gallery URL | `/g/{token}` on main domain |
| Q5: Share token | `GalleryShare.token` is canonical |
| Q6: Pending types | Per-gallery, seeded from studio master list |
| Q7: Identity gate | Open link + email on first access, no OTP in Phase 2 |
| Q8: Gallery types | Proofing (pipeline, identity) vs Presentation (view only, no identity) |
| Q9: Approval constraints | min_approvals, max_approvals, max_pending — all nullable, all independent |
| Q10: Templates | Layout + default pipeline config. Mode always overridable. |

---

## Sprint 1 — RBAC Wiring + Foundation
**Dates:** Weeks 1–2
**Sprint Goal:** Roles, permissions, and identity infrastructure are live. Every
subsequent sprint can rely on policy checks, contextual grants, and the gallery
type system working correctly.
**Capacity:** 18 points

---

### Story 1.1 — Wire RBAC into Host App
**Points:** 5 | **Priority:** P0

**Acceptance Criteria:**
- [ ] `App\Models\User` uses `HasRoles`, `HasApiTokens`, `HasContextualPermissions`
- [ ] `RolesAndPermissionsSeeder` runs cleanly — no duplicate errors
- [ ] `studio_user`, `client_user`, `guest_user` seeded with correct permission sets
- [ ] Sandbox user `dave@example.com` assigned `studio_user` role
- [ ] `AccessPlugin::make()` registered — Roles, Permissions, Permission Matrix visible in Filament
- [ ] Smoke test: `$user->hasRole('studio_user')` → true; `$user->hasPermissionTo('can_upload_images')` → true

---

### Story 1.2 — Add Phase 2 RBAC Permissions
**Points:** 3 | **Priority:** P0

**Acceptance Criteria:**
- [ ] `can_version_images`, `can_consent_ai_use`, `can_duplicate_images` added to `Permissions.php`
- [ ] All 3 added to `RolesAndPermissionsSeeder` with correct role assignments
- [ ] `SYSTEM_ADMIN` stub added to `UserRole` enum (no seeding — comment only)
- [ ] Deferred permissions from RBAC-phase-2-backlog.md added as constants with `// RBAC Phase 2` comment

---

### Story 1.3 — Studio Pending Type Templates (Master List)
**Points:** 4 | **Priority:** P0

**As a** photographer
**I want** a studio-level list of pending types that seed into new galleries
**So that** I don't have to re-enter "Retouch", "Background Swap" for every gallery

**Acceptance Criteria:**
- [ ] `studio_pending_type_templates` migration and model created in `prophoto-gallery`
- [ ] `RolesAndPermissionsSeeder` (or new `StudioSeeder`) seeds default types:
  Retouch, Background Swap, Awaiting Second Approval, Colour Correction
- [ ] Filament resource: photographer can add/edit/delete studio pending types
- [ ] `is_default = true` types are auto-added to `gallery_pending_types` on gallery creation

---

### Story 1.4 — Update Sandbox for Phase 2
**Points:** 3 | **Priority:** P0

**Acceptance Criteria:**
- [ ] `client@example.com` created with `client_user` role + org association
- [ ] `subject@example.com` created with `guest_user` role + contextual grants on seeded gallery
- [ ] All three user tokens printed at end of seeder
- [ ] `create-sandbox.sh` destroy-and-recreate runs clean

---

### Story 1.5 — RBAC Smoke Tests
**Points:** 3 | **Priority:** P0

**Acceptance Criteria:**
- [ ] `GalleryPolicyTest` — studio_user can upload, client/guest cannot without contextual grant
- [ ] `HasContextualPermissionsTest` — grant + check + expiry round-trip
- [ ] `CheckContextualPermissionMiddlewareTest` — 403 without grant, 200 with grant

---

## Sprint 2 — Gallery Type System + Image Selection UI
**Dates:** Weeks 3–4
**Sprint Goal:** Gallery type (Proofing vs Presentation) and pipeline configuration exist
in the data model and Filament UI. A photographer can create either type of gallery and
select images from confirmed sessions.
**Capacity:** 24 points

---

### Story 2.1 — Gallery Type + Mode Config Migration
**Points:** 4 | **Priority:** P0

**As a** developer
**I want** the gallery type system in the database
**So that** all Phase 2 features have a stable data foundation

**Acceptance Criteria:**
- [ ] `galleries.type` enum column added: `proofing | presentation`
- [ ] `galleries.mode_config` JSON column added (nullable)
- [ ] `gallery_pending_types` table created and model added
- [ ] `image_approval_states` table created with correct indexes
- [ ] `gallery_activity_log` table created with correct indexes
- [ ] `gallery_shares` extended with identity gate columns and pipeline override columns
- [ ] `studio_pending_type_templates` (from Sprint 1.3) confirmed present
- [ ] All migrations run cleanly in sandbox with existing data

---

### Story 2.2 — Gallery Creation Form (Filament)
**Points:** 6 | **Priority:** P0

**As a** photographer
**I want** a gallery creation form that prompts me for type and pipeline configuration
**So that** each gallery is correctly set up for its purpose before I share it

**Acceptance Criteria:**
- [ ] Gallery creation form has two-step flow:
  1. Pick template (visual picker matching Gallerie screenshot — Portrait, Editorial, etc.)
  2. Template pre-fills pipeline config; photographer can override
- [ ] Template picker pre-fills: layout, `mode_config` defaults, `type` default
  (Portrait → proofing, Architectural → presentation, etc. — per PRD template table)
- [ ] Proofing config fields shown only when `type = proofing`:
  min_approvals, max_approvals, max_pending (all nullable), ratings_enabled
- [ ] Pending types: checklist of studio master types, each toggleable per-gallery;
  photographer can add custom types inline
- [ ] `type` is always editable after creation from gallery settings
- [ ] Acceptance: Create a Portrait gallery → pipeline config pre-filled; Create an
  Architectural gallery → type defaults to presentation, no pipeline fields shown

---

### Story 2.3 — Asset → Gallery Association Model
**Points:** 4 | **Priority:** P0

**Acceptance Criteria:**
- [ ] `Gallery::images()` returns `Image` records with eager-loaded `asset`
- [ ] `Image::asset()` returns parent `Asset`
- [ ] Single Asset can appear in multiple Galleries (confirmed by existing `asset_id` FK)
- [ ] `Gallery::updateCounts()` correctly reflects image_count after add/remove
- [ ] Unit tests: attach, detach, count accuracy

---

### Story 2.4 — Session → Gallery Image Selection UI
**Points:** 6 | **Priority:** P0

**As a** photographer
**I want** to browse confirmed session assets and select which ones go into a gallery
**So that** I can curate before sharing

**Acceptance Criteria:**
- [ ] "Add images from session" Filament action opens thumbnail grid of session assets
- [ ] Select/deselect with checkboxes; already-added images show checkmark overlay
- [ ] "Add selected" saves associations; gallery image_count updates immediately
- [ ] Thumbnails served from `asset_derivatives` (type = thumbnail); placeholder if missing
- [ ] Policy: `can_upload_images` — studio_user only

---

### Story 2.5 — Gallery Image Management (Add/Delete/Reorder)
**Points:** 4 | **Priority:** P0

**Acceptance Criteria:**
- [ ] Remove image: soft-delete Image record (asset preserved)
- [ ] Add more images from same or different confirmed session
- [ ] Image order persisted (`images.sort_order`)
- [ ] `image_count` stays accurate after all operations
- [ ] Policy checks enforced on all operations

---

## Sprint 3 — Presentation Viewer + Proofing Identity Gate
**Dates:** Weeks 5–6
**Sprint Goal:** A Presentation Gallery link works for public viewing. A Proofing Gallery
link presents the email identity gate before showing any images. Both viewers are
mobile-responsive.
**Capacity:** 22 points

---

### Story 3.1 — Share Link Generation
**Points:** 4 | **Priority:** P0

**As a** photographer
**I want** to generate a share link for any gallery
**So that** my client or the public can access it

**Acceptance Criteria:**
- [ ] "Generate share link" creates a `GalleryShare` record with unique token
- [ ] URL format: `/g/{token}`
- [ ] For Proofing galleries: `PermissionContext` records created for the standard
  proofing permission set (view, approve, rate, comment, download, request_edits)
  scoped to this gallery — to be confirmed by identity on first access
- [ ] For Presentation galleries: no `PermissionContext` needed — no interactions
- [ ] Link copyable with one click from gallery detail page
- [ ] Multiple share links per gallery supported
- [ ] Policy: `can_share_gallery` — studio_user

---

### Story 3.2 — Presentation Gallery Viewer
**Points:** 5 | **Priority:** P0

**As a** member of the public
**I want** to open a gallery link and view images beautifully
**So that** I can see the photographer's work without any friction

**Acceptance Criteria:**
- [ ] `/g/{token}` for a Presentation gallery: no gate, no email, images load immediately
- [ ] Gallery displays: studio name, gallery name, image grid (web-res thumbnails)
- [ ] Lightbox on image click — full-screen, keyboard/swipe navigation
- [ ] No approval, no rating, no comment, no download UI elements whatsoever
- [ ] No ProPhoto internal details in page source (no IDs, no model names)
- [ ] Mobile-first: tested on iPhone Safari — grid, lightbox, swipe all functional
- [ ] Expired token → "This gallery is no longer available"
- [ ] Invalid token → 404

---

### Story 3.3 — Proofing Gallery Identity Gate
**Points:** 6 | **Priority:** P0

**As a** subject or client
**I want** to identify myself before interacting with a proofing gallery
**So that** my actions are attributed to me in the activity ledger

**Acceptance Criteria:**
- [ ] `/g/{token}` for a Proofing gallery: shows identity gate before any images
- [ ] Gate UI: gallery name, photographer name, single email input, "View Gallery" button
- [ ] On submit: email stored as `gallery_shares.confirmed_email`; `identity_confirmed_at` set
- [ ] Signed cookie set — subsequent visits from same browser skip gate
- [ ] New browser/device: re-enter email — if matches `confirmed_email`, admitted
  immediately; if different email, creates a new confirmation record (same share token
  can have multiple identities in Phase 2 — photographer sees all in ledger)
- [ ] Passcode gate (if `access_code` set on gallery) shown after identity gate
- [ ] Mobile-responsive gate UI

---

### Story 3.4 — Proofing Gallery Viewer (Post-Gate)
**Points:** 5 | **Priority:** P0

**As a** subject who has confirmed their identity
**I want** to see all gallery images with interaction controls available
**So that** I can begin the proofing process

**Acceptance Criteria:**
- [ ] Image grid with thumbnails
- [ ] Each image shows current approval state badge if set (Approved / Approved Pending)
- [ ] Clicking an image opens the action modal (see Story 4.1 for modal detail)
- [ ] Sticky header: gallery name + "X of Y images selected" counter
- [ ] "Submit my selections" button (inactive until min_approvals met)
- [ ] Ratings visible on each image card if `ratings_enabled = true`
- [ ] Mobile-responsive

---

### Story 3.5 — Gallery Access Logging
**Points:** 2 | **Priority:** P1

**Acceptance Criteria:**
- [ ] `gallery_access_logs` record on every `/g/{token}` load (IP, user_agent, confirmed_email if set)
- [ ] Filament gallery detail shows "Last viewed: {time ago}" or "Not yet viewed"
- [ ] First-view notification queued (Sprint 6 delivers the email)

---

## Sprint 4 — Proofing Pipeline: Approval States + Activity Ledger
**Dates:** Weeks 7–8
**Sprint Goal:** The sequential approval pipeline works. Every action is written to the
gallery activity ledger. The photographer can see attributed actions in real time.
**Capacity:** 25 points

---

### Story 4.1 — Image Action Modal (Client Side)
**Points:** 7 | **Priority:** P0

**As a** subject
**I want** a modal that shows my options for each image
**So that** I can approve, rate, or request a retouch clearly

**Acceptance Criteria:**
- [ ] Click image → modal opens with: full image (left), action panel (right)
- [ ] Action panel shows:
  - Star rating (1–5, free — always available if ratings_enabled)
  - **"Mark as Approved"** button (always available when image is Unapproved)
  - **"Approved Pending →"** dropdown (DISABLED and visually greyed until image is Approved)
    - Shows available pending types for this gallery
  - **"Clear Selection"** button (red, resets to Unapproved — available until submitted)
  - Download button (if `can_download_images` in their permission set)
  - Share (copy deep link)
  - Add Comment
- [ ] Sequential enforcement: pending options are non-interactive until Approved state is set
- [ ] Helper text: "Changes are saved automatically"
- [ ] "Save and Close" button
- [ ] State persisted immediately on each action via API call

**Technical Notes:**
- This modal replaces and significantly improves on the Gallerie modal
- Pending type dropdown is populated from `gallery_pending_types` for this gallery
- All actions write to `image_approval_states` and `gallery_activity_log`

---

### Story 4.2 — Image Approval State API
**Points:** 5 | **Priority:** P0

**As a** developer
**I want** API endpoints backing the approval modal
**So that** all state changes are persisted and attributed correctly

**Acceptance Criteria:**
- [ ] `POST /g/{token}/images/{image}/approve` — sets status = approved
- [ ] `POST /g/{token}/images/{image}/pending` — sets status = approved_pending + pending_type_id
  → returns 422 if image is not currently Approved (sequential enforcement)
- [ ] `POST /g/{token}/images/{image}/clear` — sets status = cleared
- [ ] `POST /g/{token}/images/{image}/rate` — stores ImageInteraction TYPE_RATING
- [ ] All endpoints: verify share token is valid + identity confirmed
- [ ] All endpoints: write corresponding entry to `gallery_activity_log`
- [ ] All endpoints: check max_approvals / max_pending constraints — return 422 with
  clear message if cap exceeded
- [ ] Feature tests for all endpoints covering: happy path, sequential violation,
  cap exceeded, expired token, unconfirmed identity

---

### Story 4.3 — Gallery Activity Ledger (Photographer View)
**Points:** 6 | **Priority:** P0

**As a** photographer
**I want** a live activity ledger on every Proofing Gallery
**So that** I can see exactly who did what and when without chasing anyone

**Acceptance Criteria:**
- [ ] Filament gallery detail page has "Activity" tab
- [ ] Ledger shows chronological list: timestamp | actor email + name | action | subject (filename)
- [ ] Action types displayed with distinct icons/colours:
  ✅ Approved | 🔧 Retouch Requested | 📁 Version Uploaded | ➕ Added |
  🗑 Deleted | 💬 Commented | ★ Rated | 🔒 Submitted | 🔒 Locked
- [ ] Ledger visible to photographer always
- [ ] Ledger visible to authenticated gallery participants (all share token holders for this gallery)
- [ ] Empty state: "No activity yet — share the gallery to get started"
- [ ] Ledger is read-only — no editing or deletion

---

### Story 4.4 — Activity Log Writer Service
**Points:** 4 | **Priority:** P0

**As a** developer
**I want** a single service responsible for writing all ledger entries
**So that** logging is consistent and never missed

**Acceptance Criteria:**
- [ ] `GalleryActivityLogger::log(Gallery $gallery, string $actionType, array $context)` service
- [ ] Service called from all approval API endpoints, version upload, image add/delete,
  comment creation, download
- [ ] `actor_email` resolved from: authenticated studio user (their email) or confirmed
  gallery share identity
- [ ] Unit tests for each action type logging correctly

---

### Story 4.5 — Approved Count + Constraint UI Feedback
**Points:** 3 | **Priority:** P0

**Acceptance Criteria:**
- [ ] Sticky counter in client viewer: "X of Y images approved"
- [ ] If `max_approvals` set: counter shows "X of max N approved" and Approve button
  disabled once cap reached with message "You've reached your selection limit"
- [ ] If `max_pending` set: pending dropdown disabled once cap reached
- [ ] Counter updates immediately on each approval action (no page refresh)

---

## Sprint 5 — Submission Flow + Photographer Approval Dashboard
**Dates:** Weeks 9–10
**Sprint Goal:** Client can submit their final selection. Photographer has a dashboard
showing all gallery states at a glance.
**Capacity:** 22 points

---

### Story 5.1 — Final Selection Submission
**Points:** 5 | **Priority:** P0

**As a** subject
**I want** to submit my final selection with a single confirmation
**So that** my photographer receives a clear, unambiguous signal

**Acceptance Criteria:**
- [ ] "Submit my selections" button active once `min_approvals` is met (or ≥1 if no minimum)
- [ ] Confirmation modal: "You've selected X images (Y pending retouch). Submit to [photographer]?"
- [ ] On confirm: `GalleryShare.submitted_at` set; `Gallery.approved_count` updated;
  `gallery_activity_log` entry written (action_type = 'gallery_submitted')
- [ ] Post-submit: thank-you screen — "Your selections have been sent to [photographer name]"
- [ ] After submission: all approval toggles locked (read-only) for this share token
- [ ] Notification event fired (Sprint 6 delivers the email)
- [ ] If min_approvals not met: button shows "Select at least {N} more images" tooltip

---

### Story 5.2 — Proofing Dashboard (Gallery List)
**Points:** 7 | **Priority:** P0

**As a** photographer
**I want** a single dashboard showing all galleries and their current state
**So that** I can see what needs follow-up without clicking into each gallery

**Acceptance Criteria:**
- [ ] Filament "Proofing" nav page: table of all galleries
- [ ] Columns: Type badge | Subject | Session date | Share status | Last viewed |
  Approved/Total | Pending Retouch count | Submitted? | Actions
- [ ] Status badges: Draft → Shared → Viewed → Submitted → Locked
  (Presentation galleries show: Draft → Live)
- [ ] Row actions: View approved | Send reminder | Lock | Extend expiry
- [ ] Filters: All / Proofing / Presentation / Active / Submitted / Archived
- [ ] Sort: last activity, submission date, subject name
- [ ] Empty state: "No galleries yet — create one to get started"

---

### Story 5.3 — Photographer: Approved Image View
**Points:** 4 | **Priority:** P0

**As a** photographer
**I want** to see exactly which images each subject approved, with their pending requests
**So that** I know what to deliver and what to retouch

**Acceptance Criteria:**
- [ ] Gallery detail "Approvals" tab: grid of images filtered by approval state
- [ ] Tabs or filters: All | Approved | Approved Pending | Unapproved
- [ ] Each image shows: approval badge, pending type if set, pending note, actor email
- [ ] "Awaiting submission" shown if no submission yet
- [ ] Multiple subjects: each subject's approvals shown distinctly (grouped by actor_email)

---

### Story 5.4 — Gallery Lock
**Points:** 3 | **Priority:** P1

**Acceptance Criteria:**
- [ ] Photographer can manually lock a gallery from Filament
- [ ] Lock sets `gallery_shares.submitted_at` equivalent lock field; all share tokens
  for this gallery become read-only
- [ ] Locked indicator shown on dashboard and gallery detail
- [ ] `gallery_activity_log` entry written (action_type = 'gallery_locked')

---

### Story 5.5 — Gallery Expiry
**Points:** 3 | **Priority:** P1

**Acceptance Criteria:**
- [ ] Photographer sets expiry on share link from gallery detail
- [ ] Expired link → "This gallery link has expired" page
- [ ] Photographer can extend expiry from dashboard
- [ ] Dashboard shows expiry per gallery with warning when < 48 hours remaining

---

## Sprint 6 — Notifications + Access Control + E2E Smoke Test
**Dates:** Weeks 11–12
**Sprint Goal:** The right people get the right emails. The full proofing cycle is
verified end-to-end in the sandbox.
**Capacity:** 20 points

---

### Story 6.1 — Approval Submitted Notification (Photographer)
**Points:** 4 | **Priority:** P0

**Acceptance Criteria:**
- [ ] Email within 60 seconds of `GalleryShare.submitted_at` being set
- [ ] Contents: gallery name, subject name, actor email, approved count, pending retouch count,
  direct link to Approvals tab
- [ ] No email if approved_count = 0
- [ ] Tracked in `prophoto-notifications` messages table

---

### Story 6.2 — First View Notification (Photographer)
**Points:** 3 | **Priority:** P1

**Acceptance Criteria:**
- [ ] Email on first `gallery_access_logs` entry per share token
- [ ] Deduplication: fires once per share token
- [ ] Contents: gallery name, actor email, timestamp, dashboard link

---

### Story 6.3 — Reminder Email (Photographer Triggered)
**Points:** 4 | **Priority:** P1

**Acceptance Criteria:**
- [ ] "Send reminder" action on dashboard row triggers email to share recipient
- [ ] Default body: gallery name, link, "Still waiting on your selections"
- [ ] Photographer can edit message before sending
- [ ] Disabled on galleries already submitted
- [ ] Tracked in messages table

---

### Story 6.4 — Optional Gallery Passcode
**Points:** 3 | **Priority:** P0

**Acceptance Criteria:**
- [ ] Photographer sets `access_code` on gallery from Filament
- [ ] For Presentation galleries: passcode gate shown before images load
- [ ] For Proofing galleries: passcode gate shown after identity gate
- [ ] Correct code → session stores unlock, no re-prompt on refresh
- [ ] Incorrect code → error + re-prompt

---

### Story 6.5 — Full E2E Smoke Test
**Points:** 6 | **Priority:** P0

**Acceptance Criteria:**
- [ ] Documented sandbox curl/manual test script covering full cycle:
  1. Create session → confirm → assets exist (Phase 1 ✅)
  2. Create Proofing gallery (Portrait template) → pipeline config saved
  3. Select 5 images from session → gallery shows 5
  4. Generate share link → GalleryShare + PermissionContext records created
  5. Open link as subject → identity gate shown → enter email → gallery loads
  6. Approve 3 images → 2 marked Approved Pending Retouch → ledger updated
  7. Submit → photographer dashboard shows "Submitted, 3 approved, 2 pending retouch"
  8. Approval notification queued
  9. Create Presentation gallery → share link opens directly, no gate, no modal
- [ ] All 9 steps pass in sandbox
- [ ] HTTP feature tests for gallery type routing (Presentation vs Proofing gate)

---

## Sprint 7 — P1 Capabilities: Versioning + Edit Requests + Download
**Dates:** Weeks 13–14
**Sprint Goal:** Photographers can replace images with retouched versions. Subjects can
flag images for editing. Clients can download web-resolution images.
**Capacity:** 22 points

---

### Story 7.1 — Image Replace (Version Upload)
**Points:** 7 | **Priority:** P1

**As a** photographer
**I want** to replace a gallery image with a retouched version
**So that** my client always sees the latest edit

**Acceptance Criteria:**
- [ ] "Replace image" action in Filament uploads new file → new Asset record (version + 1)
- [ ] Gallery Image.asset_id updated to new Asset
- [ ] Prior version preserved in `image_versions` chain
- [ ] Client viewer shows new version on refresh; version history photographer-only
- [ ] `gallery_activity_log` entry: action_type = 'version_uploaded', metadata includes v_from, v_to
- [ ] Policy: `can_version_images`

---

### Story 7.2 — Image Metadata Update
**Points:** 3 | **Priority:** P1

**Acceptance Criteria:**
- [ ] Inline edit for caption + tags on gallery image in Filament
- [ ] Saved to Image record + image_tags pivot
- [ ] Policy: `can_version_images`

---

### Story 7.3 — Edit Request Flow (Pending Retouch from Subject)
**Points:** 5 | **Priority:** P1

**As a** subject
**I want** to flag an approved image for retouching with a note
**So that** my photographer knows what to fix

**Acceptance Criteria:**
- [ ] In action modal: "Approved Pending →" dropdown → select "Retouch" → text field appears
- [ ] Text field: "Describe what you'd like changed" (max 500 chars)
- [ ] Stored as `image_approval_states` with status = approved_pending,
  pending_type = Retouch, pending_note = text
- [ ] `gallery_activity_log` entry written
- [ ] Photographer dashboard shows "Retouch" count column
- [ ] Photographer gallery detail shows retouch note alongside image with distinct badge
- [ ] Policy: `can_request_edits` (guest_user with contextual grant)

---

### Story 7.4 — Web-Resolution Download
**Points:** 7 | **Priority:** P1

**Acceptance Criteria:**
- [ ] "Download" in action modal downloads asset_derivatives web-res or watermarked JPEG
  (watermarked by default per gallery config; photographer can disable per-gallery)
- [ ] If no derivative exists: trigger GenerateAssetDerivative job → "Preparing, try again shortly"
- [ ] Original full-resolution returns 403
- [ ] `gallery_activity_log` entry: action_type = 'download'
- [ ] `galleries.download_count` incremented
- [ ] Policy: `can_download_images`

---

## Sprint 8 — P1 Polish: Comments + Deep Link + AI Consent + Duplicate
**Dates:** Weeks 15–16
**Sprint Goal:** Round out the client interaction surface. Ship AI consent foundation
for Phase 4. Complete all 10 image capabilities.
**Capacity:** 16 points

---

### Story 8.1 — Client Comments on Images
**Points:** 5 | **Priority:** P1

**Acceptance Criteria:**
- [ ] "Add Comment" in action modal → text input → saved as GalleryComment
  linked to image_id + gallery_share_id
- [ ] Photographer sees comments in gallery detail view alongside image
- [ ] `gallery_activity_log` entry: action_type = 'comment_added'
- [ ] Policy: `can_comment_on_images`

---

### Story 8.2 — Image Deep Link
**Points:** 2 | **Priority:** P1

**Acceptance Criteria:**
- [ ] "Share" in action modal copies `/g/{token}#image-{id}` to clipboard
- [ ] Opening URL scrolls gallery to image + opens lightbox
- [ ] Works on mobile

---

### Story 8.3 — AI Consent Toggle
**Points:** 4 | **Priority:** P1

**Acceptance Criteria:**
- [ ] AI consent toggle shown in action modal only if `gallery.ai_enabled = true`
- [ ] Default: `images.ai_consent_given = false` (opt-in)
- [ ] Toggle saves boolean to `images.ai_consent_given`
- [ ] Photographer sees consent status per image in gallery detail
- [ ] `gallery_activity_log` entry when consent changes
- [ ] Policy: `can_consent_ai_use`

---

### Story 8.4 — Image Duplicate Across Galleries
**Points:** 5 | **Priority:** P1

**Acceptance Criteria:**
- [ ] "Copy to gallery" action in Filament gallery detail → gallery picker modal
- [ ] Creates new Image record with same `asset_id` — no new file stored
- [ ] Source gallery unaffected; destination `image_count` incremented
- [ ] `gallery_activity_log` entry in destination gallery
- [ ] Policy: `can_duplicate_images`

---

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Identity gate kills adoption | Medium | High | Email only — no OTP, no password, no account. Re-entry on new device is 5 seconds. |
| Subjects request retouches on every image | High | Medium | max_pending cap per gallery. Retouch options hidden until Approved — friction is intentional. |
| Multiple subjects create conflicting approvals | Medium | Medium | Each share token has its own approval state. Photographer sees all actors in ledger. No merge conflict. |
| Ledger grows large on busy galleries | Low | Low | Index on (gallery_id, created_at). Paginate in UI. No functional impact. |
| Presentation gallery used as proofing gallery by mistake | Medium | Low | Type is visible and editable in gallery settings. Template defaults steer correctly. |
| magic_link_token vs GalleryShare.token confusion | Low | High | Decision locked: GalleryShare.token is canonical. magic_link_token deprecated for proofing shares. |

---

## Dependencies Map

```
Sprint 1 (RBAC + Foundation)
    └── Sprint 2 (Gallery Type System + Image Selection)
        └── Sprint 3 (Viewers + Identity Gate)
            ├── Sprint 4 (Pipeline + Ledger)
            │   └── Sprint 5 (Submission + Dashboard)
            │       └── Sprint 6 (Notifications + E2E)
            │
            └── Sprint 7 (Versioning + Download) ─── can start after Sprint 3
                └── Sprint 8 (Comments + AI Consent) ── can start after Sprint 3
```

Sprints 7 and 8 branch from Sprint 3. If Sprints 4–6 run long,
Sprints 7–8 can proceed in parallel without blocking.

---

## Definition of Done (All Sprints)

- [ ] Code reviewed and merged
- [ ] Feature tests passing
- [ ] Policy/permission checks covered in tests
- [ ] No N+1 queries introduced
- [ ] `gallery_activity_log` written for every user-attributed action
- [ ] Filament UI: photographer views mobile-responsive
- [ ] Client viewer: tested on iPhone Safari (real device)
- [ ] Sandbox seeder updated if new tables added
- [ ] `data-models.md` and `api-contracts.md` updated for new endpoints/tables
