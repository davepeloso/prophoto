# Sprint 5 — Submission Notifications & Photographer Dashboard

**Sprint dates:** April 14+, 2026
**Primary package:** `prophoto-notifications` (new work)
**Secondary package:** `prophoto-gallery` (event dispatch + dashboard widget)
**Read-only dependencies:** `prophoto-assets`, `prophoto-contracts`, `prophoto-access`
**Implementation order:** 5.1 → 5.2 → 5.3 → 5.4

---

## Context

Sprint 4 completed the proofing pipeline UX. A client can now view a gallery, approve/rate images, and submit their selections — but when they submit, nothing notifies the photographer. The photographer has to manually check Filament's activity log on each gallery. Sprint 5 closes this gap.

The `prophoto-notifications` package already exists with a `Message` model (studio-scoped, soft-deleted, with gallery/image FKs) and a service provider that loads migrations. Sprint 5 builds on this foundation.

---

## Story 5.1 — GallerySubmitted Event + Listener Wiring (3 pts, P0)

### Goal
Dispatch a proper Laravel event when a client submits proofing selections, so the notifications package can react to it without coupling to the gallery controller.

### What to build

**Event class** — `ProPhoto\Gallery\Events\GallerySubmitted`
- Location: `prophoto-gallery/src/Events/GallerySubmitted.php`
- Properties: `galleryId`, `galleryShareId`, `studioId`, `submittedByEmail`, `approvedCount`, `pendingCount`, `totalImages`, `submittedAt`
- Follows existing convention: events carry IDs, not models
- This lives in prophoto-gallery (not contracts) because it's a gallery-specific domain event that only the notifications package consumes. If more packages need it later, promote to contracts.

**Dispatch point** — `ProofingActionController::submit()`
- After the share is locked and the activity log entry is written, dispatch `GallerySubmitted`
- All data is already available at this point in the method

**Listener registration** — `NotificationsServiceProvider::boot()`
- `Event::listen(GallerySubmitted::class, HandleGallerySubmitted::class)`
- The listener lives in prophoto-notifications: `ProPhoto\Notifications\Listeners\HandleGallerySubmitted`

### Acceptance criteria
- [ ] `GallerySubmitted` event is dispatched on every successful submit
- [ ] Event carries all data needed for notification (no additional DB queries needed in listener)
- [ ] Listener is registered and fires (test with a simple log entry first)
- [ ] Existing submit tests still pass (event dispatch is additive)
- [ ] Event is NOT dispatched if submit fails (constraint violation, already locked, etc.)

### Files to create/modify
- CREATE: `prophoto-gallery/src/Events/GallerySubmitted.php`
- MODIFY: `prophoto-gallery/src/Http/Controllers/ProofingActionController.php` (add dispatch after lock)
- CREATE: `prophoto-notifications/src/Listeners/HandleGallerySubmitted.php` (stub — calls notification in 5.2)
- MODIFY: `prophoto-notifications/src/NotificationsServiceProvider.php` (register listener)

---

## Story 5.2 — Submission Email Notification (5 pts, P0)

### Goal
Send the photographer an email when a client submits their proofing selections. This is the single most impactful feature — it makes the proofing flow actually usable in production.

### What to build

**Mailable class** — `ProPhoto\Notifications\Mail\ProofingSubmittedMail`
- Location: `prophoto-notifications/src/Mail/ProofingSubmittedMail.php`
- Data: gallery name, client email, approved count, pending count, total images, submitted timestamp, link to gallery in Filament admin
- Uses a Blade view for the email body (simple, clean, no heavy template system yet)

**Email view** — `prophoto-notifications/resources/views/emails/proofing-submitted.blade.php`
- Clean, professional email layout
- Shows: gallery name, who submitted (client email), summary stats (X approved, Y pending, Z total), timestamp
- CTA button: "View in Dashboard" linking to the gallery's Filament edit page
- Footer: studio name, "You're receiving this because a client submitted proofing selections"

**Recipient resolution** — Who gets the email?
- The user who created the share link: `$share->createdBy` (the `shared_by_user_id` FK on `GalleryShare`)
- If that's null, fall back to the studio's first admin user
- Future: configurable notification preferences per user (deferred — too complex for Sprint 5)

**HandleGallerySubmitted listener** (from 5.1)
- Resolves the recipient user
- Loads the gallery name (single query)
- Sends the `ProofingSubmittedMail`
- Creates a `Message` record in the notifications package for in-app history

**Service provider updates**
- Load views from the package: `$this->loadViewsFrom(...)` with namespace `prophoto-notifications`
- Publish views for customization

### Acceptance criteria
- [ ] Email is sent when a client submits proofing selections
- [ ] Email contains gallery name, client email, approval stats, and Filament link
- [ ] A `Message` record is created in the `messages` table for audit trail
- [ ] If the share creator is null, falls back to studio admin
- [ ] Email is not sent if no valid recipient can be resolved (fail silently, log warning)
- [ ] Email rendering works with Laravel's mail testing (`Mail::fake()`)

### Files to create/modify
- CREATE: `prophoto-notifications/src/Mail/ProofingSubmittedMail.php`
- CREATE: `prophoto-notifications/resources/views/emails/proofing-submitted.blade.php`
- MODIFY: `prophoto-notifications/src/Listeners/HandleGallerySubmitted.php` (full implementation)
- MODIFY: `prophoto-notifications/src/NotificationsServiceProvider.php` (load views)
- CREATE: `prophoto-notifications/tests/Feature/ProofingSubmittedNotificationTest.php`

### Testing notes
- Use `Mail::fake()` + `Mail::assertSent()` to verify email dispatch
- Create a gallery + share + studio user in test setup
- Verify Message record is created alongside email send
- Test the fallback when `shared_by_user_id` is null

---

## Story 5.3 — Filament Dashboard Widget: Recent Submissions (4 pts, P1)

### Goal
A lightweight widget on the Filament dashboard showing recent proofing submissions across all galleries. The photographer sees this immediately after logging in — no need to click into each gallery.

### What to build

**Widget class** — `ProPhoto\Gallery\Filament\Widgets\RecentSubmissionsWidget`
- Location: `prophoto-gallery/src/Filament/Widgets/RecentSubmissionsWidget.php`
- Type: Filament `TableWidget` (shows a small table of recent submissions)
- Scoped to current studio (via `studio_id` on gallery)
- Shows the 10 most recent submissions

**Table columns:**
- Gallery name (linked to EditGallery page)
- Client email (`confirmed_email` from the share)
- Submitted at (relative time: "2 hours ago")
- Stats: "X approved / Y total" (from activity log metadata or share data)
- Status indicator: unread (new) vs. viewed

**Widget registration** — in `GalleryPlugin` (the existing Filament plugin for prophoto-gallery)

**Data source:**
- Query `gallery_shares` where `submitted_at IS NOT NULL`, ordered by `submitted_at DESC`, limit 10
- Join to `galleries` for name and `studio_id` scoping
- The `is_locked` flag confirms it's a real submission (not just a locked share)

### Acceptance criteria
- [ ] Widget appears on the Filament dashboard
- [ ] Shows up to 10 most recent submissions
- [ ] Each row shows gallery name, client email, time, and approval count
- [ ] Gallery name links to the EditGallery page
- [ ] Scoped to the current studio (multi-tenant safe)
- [ ] Empty state: "No submissions yet — share a gallery to get started"

### Files to create/modify
- CREATE: `prophoto-gallery/src/Filament/Widgets/RecentSubmissionsWidget.php`
- MODIFY: `prophoto-gallery/src/GalleryPlugin.php` (register widget)

### Design notes
- This is the "lightweight" version. A more sophisticated dashboard with charts, trends, and revenue tracking is deferred to a future sprint.
- The widget lives in prophoto-gallery (not notifications) because it queries gallery data. Notifications is for delivery, not display.

---

## Story 5.4 — In-App Notification Bell (3 pts, P2)

### Goal
Show a notification indicator in Filament's top bar so the photographer sees unread submission notifications without opening the dashboard widget.

### What to build

**Filament native notifications** — use Filament's built-in `Notification` system (database channel)
- When `HandleGallerySubmitted` fires, in addition to sending email, dispatch a Filament database notification to the recipient user
- Filament automatically shows a bell icon with unread count in the top bar

**Implementation:**
- In `HandleGallerySubmitted`, after sending the email:
  ```php
  Notification::make()
      ->title('Proofing Submitted')
      ->body("{$clientEmail} submitted selections for {$galleryName}")
      ->actions([
          Action::make('view')
              ->url(GalleryResource::getUrl('edit', ['record' => $galleryId]))
      ])
      ->sendToDatabase($recipientUser);
  ```
- This uses Filament's existing notification infrastructure — no custom UI needed

### Acceptance criteria
- [ ] Bell icon shows unread count in Filament top bar
- [ ] Clicking the bell shows the submission notification with gallery link
- [ ] Clicking the notification marks it as read
- [ ] Notification is sent alongside (not instead of) the email

### Files to modify
- MODIFY: `prophoto-notifications/src/Listeners/HandleGallerySubmitted.php` (add Filament notification)
- VERIFY: Filament's `notifications` table migration exists in the host app

### Dependencies
- Requires `filament/notifications` (included with Filament v4)
- Requires the `notifications` database table (Filament migration)

---

## Out of Scope for Sprint 5

- **Notification preferences** — no opt-in/opt-out per notification type yet
- **Rate limiting** — no throttling (one submit = one email, which is fine)
- **SMS/push notifications** — email only for now
- **Template branding** — plain email layout, no studio-customized templates
- **Reply-to-client flow** — photographer can't respond to the client from the notification
- **Download tracking notifications** — deferred
- **Booking/ingest notifications** — deferred (this sprint is gallery-only)
- **Real-time WebSocket** — Filament polling is sufficient for now
- **Sophisticated dashboard** — charts, trends, revenue tracking all deferred

---

## Architecture Notes

### Package boundaries
- **prophoto-gallery** emits the `GallerySubmitted` event and hosts the dashboard widget (it owns the data)
- **prophoto-notifications** listens to the event, sends email, and creates the Message record (it owns delivery)
- **prophoto-contracts** is NOT modified — the event lives in prophoto-gallery for now
- **prophoto-assets** is NOT touched

### Why the event lives in prophoto-gallery (not contracts)
Currently only one consumer (notifications) listens to `GallerySubmitted`. If a second package needs it (e.g., prophoto-intelligence for analytics), promote it to contracts at that point. Premature abstraction adds complexity without benefit.

### Message model role
The existing `Message` model in prophoto-notifications serves as an audit trail for sent notifications. Every email sent creates a corresponding Message record. This supports future features: in-app message history, resend capability, delivery tracking.

### Dashboard widget placement
The widget lives in prophoto-gallery (not notifications) because it queries `gallery_shares` and `galleries` — tables owned by gallery. The notifications package handles delivery, not display. This respects the ownership boundary.
