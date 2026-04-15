# Phase 5 — Submission Notifications & Photographer Dashboard
## Sprint Retrospective & Context Preservation

**Sprint date:** April 14, 2026
**Primary package:** `prophoto-notifications` (new work — first real implementation)
**Secondary package:** `prophoto-gallery` (event dispatch, widget, model relationship)
**Read-only dependencies:** `prophoto-assets`, `prophoto-contracts`, `prophoto-access`
**Contracts modified:** None
**Assets modified:** None
**Status:** Complete — 61 gallery tests (190 assertions) + 9 notification tests (10 assertions), all passing

---

## What Was Built

### Story 5.1 — GallerySubmitted Event + Listener Wiring (3 pts)
Plumbing to connect the gallery submit action to the notification system.

- **`GallerySubmitted` event** — `prophoto-gallery/src/Events/GallerySubmitted.php`
  - Uses `Dispatchable` trait. Readonly constructor with: `galleryId`, `galleryShareId`, `studioId`, `galleryName`, `submittedByEmail`, `approvedCount`, `pendingCount`, `totalImages`, `submittedAt`, `sharedByUserId`.
  - Carries all data needed by listeners — no additional DB queries required downstream.
  - Lives in prophoto-gallery (not contracts) because only one consumer exists. Promote to contracts if a second package needs it.

- **Dispatch point** — `ProofingActionController::submit()` after the share is locked and activity is logged. Stats (approvedCount, pendingCount, totalImages) are gathered once and shared between the activity log and the event dispatch.

- **`HandleGallerySubmitted` listener** — `prophoto-notifications/src/Listeners/HandleGallerySubmitted.php`
  - Registered in `NotificationsServiceProvider::boot()` via `Event::listen()`.

- **Dependency added** — `prophoto/gallery` added to `prophoto-notifications/composer.json` so it can import the event class.

### Story 5.2 — Submission Email Notification (5 pts)
The actual email sent to the photographer when a client submits.

- **`ProofingSubmittedMail` mailable** — `prophoto-notifications/src/Mail/ProofingSubmittedMail.php`
  - Subject: "Proofing Submitted: {gallery name}"
  - View: `prophoto-notifications::emails.proofing-submitted`
  - Data: gallery name, client email, approved/pending/total counts, submitted timestamp, dashboard URL

- **Email view** — `prophoto-notifications/resources/views/emails/proofing-submitted.blade.php`
  - Clean, professional HTML email with inline styles (no external CSS dependencies)
  - Shows: gallery name, who submitted, approval stats table, submitted timestamp
  - CTA button: "View in Dashboard" linking to Filament gallery edit page
  - Footer: "You're receiving this because a client submitted proofing selections"

- **Recipient resolution** — `HandleGallerySubmitted::resolveRecipient()`
  - Priority 1: The user who created the share link (`shared_by_user_id` on GalleryShare)
  - Priority 2: First user with matching `studio_id`
  - Returns null if no valid recipient → logs warning, skips silently

- **Message audit trail** — creates a `Message` record in the notifications package for every email sent. Subject and body summarize the submission.

- **Service provider updates** — `loadViewsFrom()` with namespace `prophoto-notifications`, publishable views.

### Story 5.3 — Filament Dashboard Widget (4 pts)
Lightweight table widget showing recent proofing submissions.

- **`RecentSubmissionsWidget`** — `prophoto-gallery/src/Filament/Widgets/RecentSubmissionsWidget.php`
  - Extends Filament `TableWidget`, queries `gallery_shares` where `submitted_at IS NOT NULL` and `is_locked = true`
  - Columns: Gallery name (linked to edit page), Submitted By (client email), When (relative time + absolute in description), Selections (approved/total with check icon)
  - Paginated (5/10), full column span, sort position 1
  - Empty state: "No submissions yet — share a proofing gallery to get started"

- **`GalleryShare::approvalStates()` relationship** — new `HasMany` to `ImageApprovalState` via `gallery_share_id`. Used by the widget to calculate approval counts.

- **`GalleryPlugin` registration** — widget registered via `$panel->widgets()` with a toggle: `->submissionsWidget(false)` to disable.

### Story 5.4 — In-App Notification Bell (3 pts)
Filament database notification sent alongside the email.

- **Implementation** — added `sendFilamentNotification()` to `HandleGallerySubmitted`
  - Uses `Filament\Notifications\Notification::make()->sendToDatabase($recipient)`
  - Title: "Proofing Submitted", body includes client email + gallery name + stats
  - Action: "View Gallery" links to Filament edit page, marks notification as read
  - Icon: `heroicon-o-check-circle` in success color

- **Graceful degradation** — three layers of protection:
  1. `class_exists()` guard — skips if Filament notifications package not installed
  2. `method_exists($recipient, 'notify')` guard — skips if user model doesn't support notifications
  3. `try/catch` — any Filament error is logged as warning, never breaks the email flow

---

## Architecture Decisions

1. **Event in gallery, not contracts** — `GallerySubmitted` lives in `prophoto-gallery` because only `prophoto-notifications` consumes it. If intelligence or another package needs it later, promote to `prophoto-contracts`. Premature abstraction avoided.

2. **Notifications depends on gallery** — `prophoto-notifications` has `prophoto/gallery` in its `composer.json`. This is acceptable — notifications is a downstream consumer that reacts to gallery events. The dependency is one-directional.

3. **Widget in gallery, not notifications** — The `RecentSubmissionsWidget` queries `gallery_shares` and `galleries` — tables owned by `prophoto-gallery`. Notifications handles delivery, not display. Ownership boundary respected.

4. **Filament as optional** — The notification bell is guarded by `class_exists()`. The email and Message record are always sent. This keeps the notifications package testable without Filament as a dependency.

5. **Stats gathered once in controller** — `approvedCount`, `pendingCount`, and `totalImages` are calculated once in `ProofingActionController::submit()` and passed to both the activity logger and the event. No duplicated queries.

6. **Dashboard URL is string-built, not route-generated** — `buildDashboardUrl()` constructs the Filament URL from `config('app.url')` + a known path pattern. This avoids importing Filament's route helpers into the notifications package.

---

## Files Created

| File | Package | Story |
|------|---------|-------|
| `src/Events/GallerySubmitted.php` | prophoto-gallery | 5.1 |
| `src/Listeners/HandleGallerySubmitted.php` | prophoto-notifications | 5.1, 5.2, 5.4 |
| `src/Mail/ProofingSubmittedMail.php` | prophoto-notifications | 5.2 |
| `resources/views/emails/proofing-submitted.blade.php` | prophoto-notifications | 5.2 |
| `src/Filament/Widgets/RecentSubmissionsWidget.php` | prophoto-gallery | 5.3 |
| `tests/TestCase.php` | prophoto-notifications | 5.2 |
| `tests/Feature/ProofingSubmittedNotificationTest.php` | prophoto-notifications | 5.2, 5.4 |
| `phpunit.xml` | prophoto-notifications | 5.2 |

## Files Modified

| File | Package | Story | Change |
|------|---------|-------|--------|
| `src/Http/Controllers/ProofingActionController.php` | prophoto-gallery | 5.1 | Import + dispatch GallerySubmitted after submit |
| `src/NotificationsServiceProvider.php` | prophoto-notifications | 5.1, 5.2 | Event listener registration, view loading |
| `composer.json` | prophoto-notifications | 5.1 | Added `prophoto/gallery` dependency |
| `src/Models/GalleryShare.php` | prophoto-gallery | 5.3 | Added `approvalStates()` HasMany relationship |
| `src/Filament/GalleryPlugin.php` | prophoto-gallery | 5.3 | Widget registration + toggle |

---

## Test Coverage

**prophoto-gallery:** 61 tests, 190 assertions (unchanged — event dispatch is additive, no listener runs in gallery tests)

**prophoto-notifications:** 9 tests, 10 assertions (new test file)
- `test_email_sent_to_share_creator` — email delivered to the user who created the share link
- `test_email_falls_back_to_studio_user_when_no_share_creator` — fallback recipient resolution
- `test_no_email_sent_when_no_recipient_found` — graceful skip + warning log
- `test_email_contains_correct_subject` — subject line matches gallery name
- `test_email_has_correct_data` — mailable carries correct counts and client email
- `test_message_record_created_on_send` — Message audit trail written to DB
- `test_no_message_created_when_no_recipient` — no Message when no recipient
- `test_email_contains_dashboard_url` — URL built correctly from app config
- `test_filament_notification_skipped_gracefully_when_not_installed` — no crash without Filament

---

## Sandbox Seeder Status

No seeder changes needed for Sprint 5. The existing sandbox data exercises all new features:
- The seeded gallery has `submitted_at = null` and `is_locked = false` — the proofing flow is ready to test
- If you submit via Postman (POST /g/{token}/submit), the event fires and the listener runs
- The dashboard widget will show the submission once it exists
- Email delivery can be tested with Mailtrap, Mailpit, or Laravel's log driver

---

## Filament v4 Migration (Post-Sprint 5)

After Sprint 5 stories were complete, the sandbox was rebuilt with Filament v4 for the first time. This exposed a wave of namespace and API changes that required fixes across all gallery Filament code. The migration is now complete and documented in `Filament-Namespace-Issue.md`.

**Summary of changes:**
- All actions moved from `Filament\Tables\Actions\*` to `Filament\Actions\*` (7 files)
- Layout components moved from `Filament\Forms\Components\*` to `Filament\Schemas\Components\*` (2 files)
- `Get`/`Set` closures moved to `Filament\Schemas\Components\Utilities\*` (1 file)
- `$navigationGroup` type changed to `\UnitEnum|string|null`, `$navigationIcon`/`$icon` to `string|\BackedEnum|null` (5 files)
- `BadgeColumn` replaced with `TextColumn::make()->badge()` (2 files)
- `IconColumnSize::Medium` enum replaced with string `'md'` (1 file)
- `getTableQuery()` override replaced with `->modifyQueryUsing()` (1 file)
- Return type `\Filament\Forms\Components\Component` changed to `\Filament\Schemas\Components\Component` (1 file)
- `form()` signature updated to accept `\Filament\Schemas\Schema` (2 files)
- Missing `GalleryActivityRelationManager` import fixed (latent bug exposed by v4's stricter Livewire resolution)
- `create-sandbox.sh` updated: `filament/filament:"^4.0"`, `php artisan filament:assets`, `php artisan make:notifications-table`

**Sandbox is fully operational** — Filament admin panel renders correctly, all resources load, activity log and image management work, proofing viewer functions end-to-end.

---

## Known Debt & Deferred Items

- **Notification preferences** — no opt-in/opt-out per notification type. All studio users with matching studio_id are candidates.
- **Rate limiting** — no throttling. One submit = one email. Fine for now.
- **Template branding** — plain HTML email, no studio-customizable templates
- **SMS/push** — email only
- **Reply-to-client** — photographer can't respond to the client from the notification
- **Download tracking notifications** — deferred
- **Booking/ingest notifications** — deferred (Sprint 5 is gallery-only)
- **Real-time WebSocket** — Filament polling is sufficient
- **Sophisticated dashboard** — charts, trends, revenue tracking all deferred
- **Filament `databaseNotifications()` panel config** — the host app's panel provider needs `->databaseNotifications()` enabled for Story 5.4's bell icon to appear. This is a one-line config change in the host app.

---

## What Sprint 6 Needs to Know

1. **`GallerySubmitted` is the event pattern** — if adding more notification triggers (e.g., gallery viewed, image downloaded), follow the same pattern: event in the originating package, listener in prophoto-notifications, email + Message record + optional Filament notification.
2. **`HandleGallerySubmitted` is the template** — recipient resolution, email send, Message creation, Filament notification with graceful degradation. Copy this pattern for new listener types.
3. **The widget queries `gallery_shares`** — if you need to scope by studio, join through `galleries.studio_id`. The widget doesn't currently filter by studio because Filament's tenant scoping isn't wired yet.
4. **`prophoto-notifications` now has a test suite** — 9 tests using Orchestra Testbench with minimal stub tables (users, studios, galleries, images). The test infrastructure is self-contained — no need to load gallery or asset providers.
5. **Filament is optional** — the notification bell only works when Filament is installed with `databaseNotifications()` enabled. The email always works.
6. **`prophoto-contracts` remains untouched** — no new interfaces, DTOs, or events.
7. **`prophoto-assets` remains untouched** — the read-only boundary is preserved.
8. **Filament v4 namespaces are fully documented** — read `Filament-Namespace-Issue.md` before writing ANY Filament code. Actions, layout components, utility classes, and property types all changed. The checklist at the bottom of that doc is the quickest reference.
9. **The sandbox builds cleanly** — `./create-sandbox.sh` produces a working app with Filament v4, all 9 packages, Filament assets, and the notifications table. If it breaks after code changes, the most likely cause is a v3-style Filament namespace.
