# Sprint 7 — Gallery Polish & Client Experience
## Spec Document

**Sprint date:** April 2026
**Primary package:** `prophoto-gallery` (relation manager, actions, model updates, views)
**Secondary package:** `prophoto-notifications` (share event notifications — if time permits)
**Read-only dependencies:** `prophoto-assets`, `prophoto-contracts`, `prophoto-access`
**Contracts modified:** None
**Assets modified:** None

---

## Sprint Goal

Make the photographer's daily workflow smoother. Sprint 6 built the tracking plumbing (downloads, views, stats). Sprint 7 surfaces that data where it matters and fills the biggest UX gaps: share management, gallery lifecycle, gallery duplication, and client-facing message display.

---

## Stories

### Story 7.1 — Share Management Relation Manager (5 pts)
**The biggest admin UX gap.** Photographers can create share links but can't see, edit, or revoke them after creation. GalleryShare has 18+ fields — none visible in the admin after the initial modal.

**Deliverables:**
- `GalleryShareRelationManager` on `EditGallery` page
- Table columns: recipient email (confirmed_email ?? shared_with_email), permissions summary (icons for download/approve/comment), status badge (active/expired/revoked/submitted), access count, download count, last accessed, created date
- Row actions:
  - **Revoke** — sets `revoked_at` + `revoked_by_user_id`, logs to activity ledger
  - **Extend** — opens modal to update `expires_at` (only for non-revoked shares)
  - **Toggle Downloads** — flips `can_download` inline, logs to activity ledger
  - **Copy Link** — copies `url("/g/{share_token}")` to clipboard via Filament notification
- Header action: reuse existing `GenerateShareLinkAction` modal (already built)
- Empty state: "No shares yet — generate your first share link above"
- Sort: most recent first

**Existing infrastructure:**
- `GalleryShare` model is fully featured (all fields, casts, relationships, SoftDeletes)
- `GenerateShareLinkAction` already creates shares with a modal
- `GalleryActivityLogger::log()` is the standard write path for the activity ledger
- `GalleryImagesRelationManager` and `GalleryActivityRelationManager` are templates for the pattern
- `Filament-Namespace-Issue.md` has the v4 namespace mappings

**Technical notes:**
- Status badge logic: `revoked_at` present → revoked (danger). `expires_at` past → expired (warning). `submitted_at` present → submitted (success). Otherwise → active (info).
- Permissions summary: use small icon badges — `heroicon-o-arrow-down-tray` (download), `heroicon-o-check-circle` (approve), `heroicon-o-chat-bubble-left` (comment)
- Revoke action should soft-disable, not delete — the share and its tracking data remain for reporting
- Log revoke/extend/toggle actions to the activity ledger with `action_type: 'share_revoked'`, `'share_extended'`, `'share_permission_changed'`

**Tests:**
- Relation manager renders on edit page
- Revoke sets revoked_at and logs to activity ledger
- Extend updates expires_at
- Toggle download flips can_download and logs
- Status badge logic (active, expired, revoked, submitted)
- Empty state renders

---

### Story 7.2 — Gallery Archival & Lifecycle Management (3 pts)
**Clean up old galleries without losing data.** The `archived_at` column and `STATUS_ARCHIVED` constant already exist but aren't wired into the UI.

**Deliverables:**
- **Archive action** on gallery table (list view) — sets `status = archived`, `archived_at = now()`
- **Unarchive action** — reverses it (sets `status = active`, `archived_at = null`)
- **Status filter** on gallery list — "Active" (default), "Completed", "Archived", "All"
- **Visual treatment** — archived galleries show muted/gray row styling, archived badge
- **Archive action on edit page** header actions (alongside existing Delete)
- **Completed action** — sets `status = completed`, `completed_at = now()` (for galleries where the client has finished but photographer hasn't archived yet)

**Existing infrastructure:**
- `Gallery::STATUS_ACTIVE`, `STATUS_COMPLETED`, `STATUS_ARCHIVED` constants exist
- `archived_at` and `completed_at` are fillable datetime columns with casts
- Gallery list table already shows status column

**Technical notes:**
- Default filter should be "Active" — photographers shouldn't see archived clutter by default
- Archive is NOT delete — all data preserved, shares remain (but already expired/revoked presumably)
- No cascade to shares — archiving a gallery doesn't auto-revoke shares. If a share is still active on an archived gallery, the viewer still works (photographer's choice)
- Activity log: `action_type: 'gallery_archived'`, `'gallery_unarchived'`, `'gallery_completed'`

**Tests:**
- Archive sets status + archived_at
- Unarchive resets status + archived_at
- Complete sets status + completed_at
- Default filter shows only active galleries
- "All" filter shows everything
- Archived gallery row has muted styling class

---

### Story 7.3 — Client Message Display (2 pts)
**The `message` field on GalleryShare is created but never shown to the client.** This is a quick win that lets photographers personalize gallery links.

**Deliverables:**
- Display `$share->message` on the **presentation viewer** (above image grid, below gallery name)
- Display `$share->message` on the **proofing viewer** (same position)
- Display `$share->message` on the **identity gate** page (gives context before email confirmation)
- Styled as a subtle quote/message card — photographer's personal touch, not system chrome
- Graceful empty state — no message = no card rendered (not an empty box)

**Existing infrastructure:**
- `GalleryShare.message` is fillable, nullable text column
- `GenerateShareLinkAction` already collects `message` in the modal
- Blade views: `presentation-viewer.blade.php`, `proofing-viewer.blade.php`, `identity-gate.blade.php`
- Controllers already pass `$share` to views

**Technical notes:**
- XSS: use `{{ $share->message }}` (Blade auto-escapes), NOT `{!! !!}`
- Allow basic line breaks: `nl2br(e($share->message))` wrapped in `{!! !!}` is safe
- Keep styling consistent with existing gallery viewer design (Tailwind CDN)
- No markdown rendering — just plain text with line breaks

**Tests:**
- Presentation viewer shows message when present
- Presentation viewer hides message card when null
- Proofing viewer shows message when present
- Identity gate shows message when present
- Message is HTML-escaped (XSS test)

---

### Story 7.4 — Gallery Viewer Template System (5 pts)
**The templates are what the client sees — they're the product.** Currently the viewer is hardcoded: presentation galleries get one layout, proofing galleries get another. In practice, photographers always switch from the vanilla default to a style that fits the shoot. This story adds the template selection infrastructure and ships basic starter templates.

**Deliverables:**
- **Migration:** Add `viewer_template` column to `galleries` table (nullable string, defaults to `'default'`)
- **Template registry:** `ViewerTemplateRegistry` service — returns available templates per gallery type, each with: slug, name, description, preview description, supported types (proofing/presentation/both)
- **Starter templates (6 Blade files):**
  - `portrait` — Two-column tall cards. Intimate & warm. Best for headshots/portraits. (Both types)
  - `editorial` — Asymmetric, cinematic. Mixed aspect ratios. Hero image + smaller grid. (Both types)
  - `architectural` — Three-column landscape cards. Precise grid. (Both types)
  - `classic` — Balanced gallery grid with rating controls and image numbers. (Proofing only)
  - `profile` — Centered profile header with circular avatar + portfolio grid. (Both types)
  - `single-column` — Full-width vertical stack. Cinematic & editorial. (Both types)
- **Default template** (`default`) remains the existing presentation/proofing blade — no breaking change
- **Template picker on EditGallery** — visual radio-card selector (similar to current app's "Profile Choice Picker"), shows template name + description, filtered by gallery type
- **Dynamic view resolution** in `GalleryViewerController` — resolves blade path from `viewer_template` field: `viewer.{type}.{template}` with fallback to `viewer.{type}.default`
- **Google Fonts integration** — each template specifies its font stack via a `@push('fonts')` directive. Templates load fonts from Google Fonts CDN.

**Template structure convention:**
```
resources/views/viewer/
  presentation/
    default.blade.php      ← current presentation-viewer (renamed, not changed)
    portrait.blade.php
    editorial.blade.php
    architectural.blade.php
    profile.blade.php
    single-column.blade.php
  proofing/
    default.blade.php      ← current proofing-viewer (renamed, not changed)
    portrait.blade.php
    editorial.blade.php
    architectural.blade.php
    classic.blade.php
    profile.blade.php
    single-column.blade.php
  partials/
    _lightbox.blade.php    ← shared lightbox Alpine component
    _proofing-modal.blade.php  ← shared proofing action panel
    _fonts.blade.php       ← Google Fonts loader partial
```

**Existing infrastructure:**
- `presentation-viewer.blade.php` (~200 lines) — Tailwind CDN + Alpine.js, 3-col grid, lightbox modal
- `proofing-viewer.blade.php` (~607 lines) — Tailwind CDN + Alpine.js, 4-col grid, unified modal with action panel
- `gallery_templates` table exists (for creation defaults) — viewer templates are a SEPARATE concern
- Both viewers are self-contained single-file applications — good candidates for extracting shared partials

**Technical notes:**
- Templates MUST keep the same Alpine.js data contract — the image array, lightbox state, and proofing actions need consistent variable names so templates are swappable without breaking functionality
- Proofing templates get the full action panel (approve/cull/rate/download). Presentation templates get the simpler lightbox.
- The `classic` template is proofing-only because it shows image numbers and rating controls inline (not in a modal)
- New templates start basic (layout + fonts + grid structure). Micro-animations and polish are a future story — get the routing and switching working first.
- The `ViewerTemplateRegistry` is config-driven so future templates can be added without code changes (just drop a blade file + add to config)
- Shared partials (`_lightbox`, `_proofing-modal`) extract the common Alpine.js components so templates don't duplicate 200+ lines of modal logic

**Migration path:**
- Existing galleries with `viewer_template = null` resolve to `'default'` — zero breaking changes
- Current `presentation-viewer.blade.php` moves to `viewer/presentation/default.blade.php`
- Current `proofing-viewer.blade.php` moves to `viewer/proofing/default.blade.php`
- Old paths get a one-line `@include` redirect for backwards compatibility

**Tests:**
- Default template resolves when viewer_template is null
- Template picker shows only templates valid for gallery type
- Controller resolves correct blade path for each template
- Fallback to default when template blade doesn't exist
- ViewerTemplateRegistry returns correct templates per type
- Migration adds column with null default

**Future (NOT this story):**
- Template preview thumbnails in the picker (requires screenshot generation or static assets)
- Micro-animations per template (CSS transitions, scroll-triggered reveals)
- Custom color themes per template
- Image Browser package (`prophoto-browser`) — professional culling/editing tool with fast keybindings, separate from gallery viewer templates. This is its own package and sprint.

---

### Story 7.5 — Gallery Delivered Status + Client Notification (3 pts)
**Close the loop.** When a photographer marks a gallery as "delivered," the client should know their images are ready. This bridges the gallery lifecycle (7.2) with the notification system (Sprint 5–6).

**Deliverables:**
- **Deliver action** on gallery edit page header — sets `status = completed` (or new `STATUS_DELIVERED`?), `delivered_at = now()`
- Dispatches `GalleryDelivered` event from `prophoto-gallery`
- `HandleGalleryDelivered` listener in `prophoto-notifications`:
  - Sends email to ALL active shares (not just one) — every client who has access gets notified
  - Subject: "Your Gallery is Ready: {gallery name}"
  - Email body: photographer's message (from a modal field), link back to gallery viewer
  - Creates Message record per recipient
  - Optional Filament bell (same 3-layer guard pattern)
- Modal on the Deliver action: optional message field ("Your images are ready for download!")

**Existing infrastructure:**
- `delivered_at` column exists on Gallery, fillable and cast to datetime
- Notification listener pattern is proven (3 listeners already: submitted, downloaded, viewed)
- Email template pattern is proven (3 templates)

**Technical notes:**
- Use existing `STATUS_COMPLETED` rather than adding a new status — `delivered_at` being non-null IS the delivery signal
- Query active shares: `GalleryShare::where('gallery_id', $id)->whereNull('revoked_at')->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))`
- The delivery email goes to `shared_with_email` (original recipient), not `confirmed_email` — we want to reach the person the photographer shared with
- Log to activity ledger: `action_type: 'gallery_delivered'`

**Tests (gallery):**
- Deliver sets delivered_at
- GalleryDelivered event dispatched with correct data
- Activity log records delivery

**Tests (notifications):**
- Email sent to all active shares
- No email to revoked or expired shares
- Subject line correct
- Message field included in email
- Message record created per recipient
- Filament graceful degradation

---

## Implementation Order

```
7.1 Share Management RM  →  7.2 Gallery Lifecycle  →  7.3 Client Message  →  7.4 Viewer Templates  →  7.5 Delivery Notification
     (5 pts)                    (3 pts)                  (2 pts)               (5 pts)                   (3 pts)
```

**Total: 18 points**

- **7.1 first** because it's the biggest gap and 7.2's archive/complete actions benefit from seeing the share state
- **7.2 second** because lifecycle states underpin 7.5's delivery flow
- **7.3 third** because it's quick and validates the blade view editing pattern needed for 7.4
- **7.4 fourth** because it restructures blade views (easier after 7.3 has already touched them) and is the largest story
- **7.5 last** because it depends on 7.2 (lifecycle states) and follows the Sprint 5–6 notification pattern

---

## Architecture Decisions

1. **No new packages** — everything stays in `prophoto-gallery` and `prophoto-notifications`
2. **No contracts changes** — `GalleryDelivered` event lives in `prophoto-gallery`, not contracts (same pattern as `ImageDownloaded` and `GalleryViewed`)
3. **Activity ledger for all actions** — revoke, extend, toggle, archive, unarchive, complete, deliver all get logged via `GalleryActivityLogger::log()`
4. **Soft lifecycle states** — archive/complete/deliver don't cascade to child records. Shares, images, and activity logs are preserved.
5. **Client message is display-only** — no editing from the client side, no reply mechanism (that's a future feature)
6. **Viewer templates are blade files, not database records** — `gallery_templates` table exists for creation defaults (a separate concern). Viewer layout is a `viewer_template` string on the gallery, resolved to a blade path at render time. Adding a new template = drop a blade file + register in config.
7. **Shared Alpine.js contract across templates** — all templates consume the same data shape from the controller. Templates control layout/styling only, not behavior. The lightbox and proofing modal are extracted to partials that templates `@include`.
8. **Image Browser is a separate future package** — `prophoto-browser` will be a professional culling/editing tool with keybindings, distinct from viewer templates. Not part of this sprint or the gallery package.

---

## Files Expected

### New Files
| File | Package | Story |
|------|---------|-------|
| `src/Filament/Resources/GalleryResource/RelationManagers/GalleryShareRelationManager.php` | prophoto-gallery | 7.1 |
| `src/Events/GalleryDelivered.php` | prophoto-gallery | 7.5 |
| `tests/Feature/GalleryShareRelationManagerTest.php` | prophoto-gallery | 7.1 |
| `tests/Feature/GalleryLifecycleTest.php` | prophoto-gallery | 7.2 |
| `src/Services/ViewerTemplateRegistry.php` | prophoto-gallery | 7.4 |
| `database/migrations/xxxx_add_viewer_template_to_galleries.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/presentation/default.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/presentation/portrait.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/presentation/editorial.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/presentation/architectural.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/presentation/profile.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/presentation/single-column.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/proofing/default.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/proofing/portrait.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/proofing/editorial.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/proofing/architectural.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/proofing/classic.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/proofing/profile.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/proofing/single-column.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/partials/_lightbox.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/partials/_proofing-modal.blade.php` | prophoto-gallery | 7.4 |
| `resources/views/viewer/partials/_fonts.blade.php` | prophoto-gallery | 7.4 |
| `tests/Feature/ViewerTemplateTest.php` | prophoto-gallery | 7.4 |
| `src/Listeners/HandleGalleryDelivered.php` | prophoto-notifications | 7.5 |
| `src/Mail/GalleryDeliveredMail.php` | prophoto-notifications | 7.5 |
| `resources/views/emails/gallery-delivered.blade.php` | prophoto-notifications | 7.5 |
| `tests/Feature/GalleryDeliveredNotificationTest.php` | prophoto-notifications | 7.5 |

### Modified Files
| File | Package | Story | Change |
|------|---------|-------|--------|
| `src/Filament/Resources/GalleryResource.php` | prophoto-gallery | 7.1, 7.2, 7.4 | Add relation manager, table actions (archive, unarchive, duplicate), status filter |
| `src/Filament/Resources/GalleryResource/Pages/EditGallery.php` | prophoto-gallery | 7.2, 7.5 | Add archive/deliver header actions |
| `resources/views/presentation-viewer.blade.php` | prophoto-gallery | 7.3, 7.4 | Add message display (7.3), then move to `viewer/presentation/default.blade.php` (7.4) |
| `resources/views/proofing-viewer.blade.php` | prophoto-gallery | 7.3, 7.4 | Add message display (7.3), then move to `viewer/proofing/default.blade.php` (7.4) |
| `resources/views/identity-gate.blade.php` | prophoto-gallery | 7.3 | Add message display |
| `src/Http/Controllers/GalleryViewerController.php` | prophoto-gallery | 7.4 | Dynamic view resolution based on viewer_template |
| `src/Http/Controllers/PresentationViewerController.php` | prophoto-gallery | 7.4 | Accept template param, resolve blade path |
| `src/Http/Controllers/ProofingViewerController.php` | prophoto-gallery | 7.4 | Accept template param, resolve blade path |
| `config/gallery.php` | prophoto-gallery | 7.4 | Add viewer_templates config section |
| `src/NotificationsServiceProvider.php` | prophoto-notifications | 7.5 | Register GalleryDelivered listener |

---

## What This Sprint Does NOT Include

- Bulk/zip downloads (deferred — needs ext-zip dependency, client testing)
- Client reply/messaging (deferred — needs bidirectional communication design)
- Notification preferences (deferred — needs user settings table)
- Watermarked downloads (deferred — needs image processing pipeline)
- Auto-archive scheduled task (deferred — manual archive first, automation later)
- Template micro-animations (deferred — get layouts and switching working first, polish later)
- Template preview thumbnails in the picker (deferred — needs static assets or screenshot generation)
- Custom color themes per template (deferred — future customization story)
- Image Browser package (`prophoto-browser`) — professional culling/editing tool with fast keybindings, entirely separate from gallery viewer templates. Its own package and sprint.
- Gallery duplication (dropped — photographer workflow doesn't benefit from cloning galleries; templates are too distinct to be useful as copies)

---

*Last updated: 2026-04-15 — Sprint 7 planning, follows Sprint 6 completion*
