# Sprint 6 — Download Tracking, Gallery View Notifications & Future Backlog

**Sprint dates:** April 14+, 2026 (follows Sprint 5)
**Primary package:** `prophoto-gallery` (download enforcement, events, activity logging)
**Secondary package:** `prophoto-notifications` (listeners, email, Message records, Filament bell)
**Read-only dependencies:** `prophoto-assets`, `prophoto-contracts`, `prophoto-access`
**Implementation order:** 6.1 → 6.2 → 6.3 → 6.5
**Skipped:** 6.4 (download limit approaching notification — deferred, see Out of Scope)

---

## Context

Sprint 5 established the notification pattern: gallery emits event → notifications listens → sends email + creates Message record + optional Filament bell. Sprint 6 applies this same pattern to two new triggers (image download, gallery viewed) while also closing the gap on download enforcement — the schema fields exist (`can_download`, `max_downloads`, `download_count` on `GalleryShare`; `download_count` on `Gallery`) but are never incremented or enforced.

### Why downloads stay in prophoto-gallery (not a separate package)

The `SYSTEM.md` lists `downloads` as `ARCHIVED/FUTURE`, but the data ownership analysis is clear:
- `can_download` permission lives on `GalleryShare` (gallery-owned table)
- `max_downloads` and `download_count` live on `GalleryShare` (gallery-owned table)
- `download_count` lives on `Gallery` (gallery-owned table)
- `gallery_activity_log` already has `download` in its action type vocabulary
- `gallery_access_logs` already tracks download actions via `ImageController`

Creating a separate downloads package would require reading and writing gallery tables, violating the Database Ownership rule. Downloads are a gallery capability, not a separate domain. The notification side (telling the photographer someone downloaded) correctly belongs in `prophoto-notifications`.

---

## Story 6.1 — Public Download Endpoint + Enforcement (5 pts, P0)

### Goal
Create a share-scoped download endpoint that clients can use from the gallery viewer. Enforce `can_download`, `max_downloads`, and increment counters. Currently, only the authenticated API endpoint (`ImageController::download()`) exists — there is no public download route.

### What to build

**Controller** — `ProPhoto\Gallery\Http\Controllers\DownloadController`
- Location: `prophoto-gallery/src/Http/Controllers/DownloadController.php`
- Single method: `download(Request $request, string $token, int $imageId)`
- Resolves share via token (same pattern as `GalleryViewerController`)
- Validates: share is valid (not expired/revoked), `can_download` is true, image belongs to gallery
- Enforces `max_downloads` if set (returns 403 with clear message when limit reached)
- Increments `GalleryShare.download_count` and `Gallery.download_count` atomically
- Logs to `gallery_activity_log` via `GalleryActivityLogger::log()` with action type `download`
- Logs to `gallery_access_logs` with `ACTION_DOWNLOAD`
- Dispatches `ImageDownloaded` event (Story 6.2)
- Returns redirect to the resolved image URL (same approach as `ImageController::download()`)

**Route** — `GET /g/{token}/download/{image}`
- Added to `prophoto-gallery/routes/web.php` alongside existing share routes
- No auth middleware (public, share-scoped)
- Rate limited: 60 per minute per IP (Laravel's built-in throttle)

**GalleryShare model updates:**
- Add `incrementDownloadCount()` method — atomic increment of `download_count`
- Add `canDownload()` method — checks `can_download` flag AND `max_downloads` limit
- Rename existing `hasReachedMaxViews()` to `hasReachedMaxDownloads()` (it already checks download_count, the name is misleading)

**Gallery model updates:**
- Add `incrementDownloadCount()` method — atomic increment of `download_count`

### Acceptance criteria
- [ ] `GET /g/{token}/download/{image}` returns the image file (redirect to resolved URL)
- [ ] Returns 403 if `can_download` is false on the share
- [ ] Returns 403 if `max_downloads` reached, with message indicating limit
- [ ] Returns 404 if image doesn't belong to the gallery
- [ ] Returns 410 if share is expired or revoked
- [ ] `GalleryShare.download_count` increments on each successful download
- [ ] `Gallery.download_count` increments on each successful download
- [ ] Activity log entry created with action type `download`, image_id, and share metadata
- [ ] Access log entry created with `ACTION_DOWNLOAD`
- [ ] Existing authenticated `ImageController::download()` continues to work unchanged

### Files to create/modify
- CREATE: `prophoto-gallery/src/Http/Controllers/DownloadController.php`
- MODIFY: `prophoto-gallery/routes/web.php` (add download route)
- MODIFY: `prophoto-gallery/src/Models/GalleryShare.php` (add canDownload(), incrementDownloadCount(), rename hasReachedMaxViews)
- MODIFY: `prophoto-gallery/src/Models/Gallery.php` (add incrementDownloadCount())
- CREATE: `prophoto-gallery/tests/Feature/DownloadControllerTest.php`

### Testing notes
- Test with `can_download = true` and `can_download = false`
- Test `max_downloads` enforcement (set to 2, download twice, verify 3rd is blocked)
- Test expired share returns 410
- Test image-not-in-gallery returns 404
- Verify both counters increment atomically (no race conditions with DB::increment)

---

## Story 6.2 — ImageDownloaded Event + Notification (5 pts, P0)

### Goal
Dispatch an event when a client downloads an image, and notify the photographer via email + Message record + Filament bell. Follows the exact pattern established by `GallerySubmitted` / `HandleGallerySubmitted` in Sprint 5.

### What to build

**Event class** — `ProPhoto\Gallery\Events\ImageDownloaded`
- Location: `prophoto-gallery/src/Events/ImageDownloaded.php`
- Properties: `galleryId`, `galleryShareId`, `studioId`, `galleryName`, `imageId`, `imageFilename`, `downloadedByEmail`, `shareDownloadCount`, `shareMaxDownloads`, `galleryDownloadCount`, `downloadedAt`, `sharedByUserId`
- Same convention as `GallerySubmitted`: carries IDs and data, not models
- Dispatched from `DownloadController::download()` after successful download

**Listener** — `ProPhoto\Notifications\Listeners\HandleImageDownloaded`
- Location: `prophoto-notifications/src/Listeners/HandleImageDownloaded.php`
- Follows `HandleGallerySubmitted` template exactly:
  1. Resolve recipient (share creator → fallback to studio admin)
  2. Send `DownloadNotificationMail`
  3. Create `Message` record for audit trail
  4. Send Filament database notification (with `class_exists` guard)

**Mailable** — `ProPhoto\Notifications\Mail\DownloadNotificationMail`
- Location: `prophoto-notifications/src/Mail/DownloadNotificationMail.php`
- Subject: "Image Downloaded: {gallery name}"
- Data: gallery name, image filename, who downloaded (client email), download count for this share, max downloads (if set), timestamp
- View: `prophoto-notifications::emails.image-downloaded`
- CTA: "View in Dashboard" linking to gallery edit page

**Email view** — `prophoto-notifications/resources/views/emails/image-downloaded.blade.php`
- Clean layout matching `proofing-submitted.blade.php` style
- Shows: gallery name, image filename, who downloaded, download count ("3 of 10" or "3 downloads" if no limit), timestamp
- CTA button: "View in Dashboard"
- Footer: "You're receiving this because a client downloaded an image from your gallery"

**Listener registration** — `NotificationsServiceProvider::boot()`
- `Event::listen(ImageDownloaded::class, HandleImageDownloaded::class)`

### Acceptance criteria
- [ ] `ImageDownloaded` event dispatched on every successful download
- [ ] Event carries all data needed (no additional DB queries in listener)
- [ ] Email sent to share creator (or studio admin fallback)
- [ ] Email shows gallery name, image filename, client email, download stats
- [ ] `Message` record created for audit trail
- [ ] Filament notification sent (with graceful degradation if Filament not installed)
- [ ] Event NOT dispatched if download is blocked (permission denied, limit reached)

### Files to create/modify
- CREATE: `prophoto-gallery/src/Events/ImageDownloaded.php`
- MODIFY: `prophoto-gallery/src/Http/Controllers/DownloadController.php` (dispatch event — from 6.1)
- CREATE: `prophoto-notifications/src/Listeners/HandleImageDownloaded.php`
- CREATE: `prophoto-notifications/src/Mail/DownloadNotificationMail.php`
- CREATE: `prophoto-notifications/resources/views/emails/image-downloaded.blade.php`
- MODIFY: `prophoto-notifications/src/NotificationsServiceProvider.php` (register listener)
- CREATE: `prophoto-notifications/tests/Feature/DownloadNotificationTest.php`

### Design decision: individual vs. batched notifications
For Sprint 6, each download sends one notification. This is fine for typical usage (photographers share galleries with 1–3 clients). If bulk download notifications become noisy in the future, Story 6.5 (deferred) can add digest/batching. Don't over-engineer now.

---

## Story 6.3 — GalleryViewed Event + Notification (3 pts, P1)

### Goal
Notify the photographer when a client first views their gallery. This closes the feedback loop — the photographer knows the client received the link and actually looked at it.

### What to build

**Event class** — `ProPhoto\Gallery\Events\GalleryViewed`
- Location: `prophoto-gallery/src/Events/GalleryViewed.php`
- Properties: `galleryId`, `galleryShareId`, `studioId`, `galleryName`, `viewedByEmail`, `viewCount`, `viewedAt`, `sharedByUserId`
- `viewCount` is the share's `access_count` after incrementing

**Dispatch point** — `GalleryViewerController::show()`
- After the share is resolved and access is logged
- Only dispatched when `access_count` reaches specific thresholds: 1 (first view), 5, 10, 25, 50 — NOT on every single page load
- This prevents notification spam while still alerting on meaningful milestones
- Threshold list stored as a class constant on the controller for easy tuning

**Listener** — `ProPhoto\Notifications\Listeners\HandleGalleryViewed`
- Location: `prophoto-notifications/src/Listeners/HandleGalleryViewed.php`
- Same pattern as `HandleGallerySubmitted`
- Email subject varies: "Gallery Viewed: {name}" (first view) vs. "Gallery Milestone: {name} — {count} views" (subsequent thresholds)

**Mailable** — `ProPhoto\Notifications\Mail\GalleryViewedMail`
- Location: `prophoto-notifications/src/Mail/GalleryViewedMail.php`
- Subject: dynamic based on view count (first view vs. milestone)
- Data: gallery name, viewer email, view count, timestamp, dashboard URL
- View: `prophoto-notifications::emails.gallery-viewed`

**Email view** — `prophoto-notifications/resources/views/emails/gallery-viewed.blade.php`
- First view: "Great news — {email} just viewed {gallery name} for the first time!"
- Milestone: "{gallery name} has been viewed {count} times by {email}"
- CTA: "View in Dashboard"

### Acceptance criteria
- [ ] `GalleryViewed` event dispatched on first view (access_count = 1)
- [ ] Event dispatched on milestone thresholds (5, 10, 25, 50)
- [ ] Event NOT dispatched on every page load (only thresholds)
- [ ] Email sent with appropriate subject (first view vs. milestone)
- [ ] `Message` record created
- [ ] Filament notification sent (with graceful degradation)
- [ ] Activity log entry created with action type `gallery_viewed`

### Files to create/modify
- CREATE: `prophoto-gallery/src/Events/GalleryViewed.php`
- MODIFY: `prophoto-gallery/src/Http/Controllers/GalleryViewerController.php` (dispatch event at thresholds)
- CREATE: `prophoto-notifications/src/Listeners/HandleGalleryViewed.php`
- CREATE: `prophoto-notifications/src/Mail/GalleryViewedMail.php`
- CREATE: `prophoto-notifications/resources/views/emails/gallery-viewed.blade.php`
- MODIFY: `prophoto-notifications/src/NotificationsServiceProvider.php` (register listener)
- CREATE: `prophoto-notifications/tests/Feature/GalleryViewedNotificationTest.php`

### Testing notes
- Test first view dispatches event
- Test second view does NOT dispatch event
- Test 5th view dispatches event
- Use `Event::fake()` in gallery tests to verify dispatch without running listener

---

## Story 6.4 — Download Limit Approaching Notification (2 pts, P2)

### Goal
Warn the photographer when a share is nearing its download limit so they can extend it if needed. This is a proactive notification — better UX than the client hitting a hard wall.

### What to build

**Event class** — `ProPhoto\Gallery\Events\DownloadLimitApproaching`
- Location: `prophoto-gallery/src/Events/DownloadLimitApproaching.php`
- Properties: `galleryId`, `galleryShareId`, `studioId`, `galleryName`, `shareEmail`, `downloadCount`, `maxDownloads`, `remainingDownloads`, `sharedByUserId`
- Dispatched from `DownloadController::download()` when remaining downloads ≤ 20% of max (minimum 1 remaining)

**Listener** — `ProPhoto\Notifications\Listeners\HandleDownloadLimitApproaching`
- Same pattern, simpler email
- Only sends once per share (check: if a Message already exists for this share + subject pattern, skip)
- This deduplication prevents repeated "limit approaching" emails

**Mailable** — `ProPhoto\Notifications\Mail\DownloadLimitApproachingMail`
- Subject: "Download Limit Warning: {gallery name}"
- Body: "{email} has used {count} of {max} downloads for {gallery name}. {remaining} downloads remaining."
- CTA: "Manage Share Settings" (link to gallery edit page)

### Acceptance criteria
- [ ] Notification sent when remaining downloads ≤ 20% of max_downloads
- [ ] NOT sent if max_downloads is null (unlimited)
- [ ] Sent only once per share (deduplicated via Message record check)
- [ ] Email shows current count, max, and remaining
- [ ] Message record created
- [ ] Filament notification sent

### Files to create/modify
- CREATE: `prophoto-gallery/src/Events/DownloadLimitApproaching.php`
- MODIFY: `prophoto-gallery/src/Http/Controllers/DownloadController.php` (dispatch when threshold hit)
- CREATE: `prophoto-notifications/src/Listeners/HandleDownloadLimitApproaching.php`
- CREATE: `prophoto-notifications/src/Mail/DownloadLimitApproachingMail.php`
- CREATE: `prophoto-notifications/resources/views/emails/download-limit-approaching.blade.php`
- MODIFY: `prophoto-notifications/src/NotificationsServiceProvider.php` (register listener)

---

## Story 6.5 — Filament Download Stats on Gallery Edit Page (3 pts, P2)

### Goal
Show download statistics directly on the gallery edit page in Filament so the photographer can see download activity without leaving the admin panel.

### What to build

**Info section** on the gallery edit page — a read-only stats panel showing:
- Total gallery downloads (from `Gallery.download_count`)
- Per-share breakdown: share email, download count, max downloads, last download time
- Per-image download counts (from activity log aggregation)

**Implementation** — add a new section to `GalleryResource::form()` or as a custom Filament widget on the edit page
- Query `gallery_activity_log` where `action_type = 'download'` grouped by `image_id`
- Query `gallery_shares` for per-share download counts
- Display as a clean table with Filament components

### Acceptance criteria
- [ ] Gallery edit page shows total download count
- [ ] Per-share download breakdown visible (email, count, limit, last download)
- [ ] Per-image download counts shown
- [ ] Scoped to current gallery (no cross-gallery data leakage)
- [ ] Empty state when no downloads exist

### Files to create/modify
- MODIFY: `prophoto-gallery/src/Filament/Resources/GalleryResource.php` (add download stats section)
- Or CREATE: `prophoto-gallery/src/Filament/Widgets/GalleryDownloadStatsWidget.php` (if widget approach is cleaner)

---

## Out of Scope for Sprint 6

- **Download limit approaching notification (was Story 6.4)** — deferred. Download counting is active but limiting downloads on a digital product adds friction without clear value for a solo studio. The `max_downloads` enforcement exists in DownloadController if ever needed; the notification can be added later.
- **Bulk/zip download** — downloading multiple images as a zip archive (significant infrastructure)
- **Download notification digest** — batching multiple download notifications into one email
- **Watermarked downloads** — applying watermarks to downloaded images
- **Resolution selection** — letting clients choose download resolution (needs derivatives infrastructure)
- **Download expiry** — time-limited download links (pre-signed URLs)
- **Notification preferences** — opt-in/opt-out per notification type (deferred from Sprint 5 too)
- **Client communication** — photographer replying to client from notification (see Future Backlog below)
- **SMS/push** — email only for now
- **Template branding** — plain email layout, no studio-customizable templates

---

## Architecture Notes

### Package boundaries (same as Sprint 5)
- **prophoto-gallery** emits events (`ImageDownloaded`, `GalleryViewed`, `DownloadLimitApproaching`) and owns all download data
- **prophoto-notifications** listens to events, sends emails, creates Message records, sends Filament notifications
- **prophoto-contracts** is NOT modified
- **prophoto-assets** is NOT touched

### Event naming convention
All gallery events follow the pattern: `ProPhoto\Gallery\Events\{PastTenseAction}`
- `GallerySubmitted` (Sprint 5)
- `ImageDownloaded` (Sprint 6)
- `GalleryViewed` (Sprint 6)
- `DownloadLimitApproaching` (Sprint 6 — exception: present tense because it describes a threshold state)

### Listener template
All listeners in `prophoto-notifications` follow the `HandleGallerySubmitted` pattern:
1. Resolve recipient (share creator → studio admin fallback)
2. Send mailable
3. Create Message record
4. Send Filament notification (with 3-layer guard: class_exists, method_exists, try/catch)
5. Log results

### Download counter atomicity
Use `DB::table()->where()->increment()` or Eloquent's `increment()` for both `GalleryShare.download_count` and `Gallery.download_count`. This prevents race conditions if two downloads happen simultaneously.

---

## Future Features Backlog

Features identified but deliberately deferred. Organized by domain area.

### Downloads (prophoto-gallery)
- **Bulk/zip download** — download all approved images (or full gallery) as a single zip
- **Watermarked downloads** — apply studio watermark before serving (needs image processing pipeline)
- **Resolution selection** — offer original, web-optimized, and thumbnail download options (depends on derivatives in prophoto-assets)
- **Download expiry** — pre-signed URLs with time limits for download links
- **Download analytics dashboard** — trends, popular images, peak download times (separate Filament page or widget)

### Notifications (prophoto-notifications)
- **Notification preferences** — per-user opt-in/opt-out for each notification type
- **Notification digest** — daily/weekly summary email instead of individual notifications
- **SMS notifications** — text message alerts for high-priority events
- **Push notifications** — browser push via service worker
- **Template branding** — studio-customizable email templates with logo, colors, fonts
- **Booking notifications** — reminders, confirmations, follow-ups (requires prophoto-booking integration)
- **Ingest notifications** — upload complete, processing finished, session matched (requires prophoto-ingest integration)

### Client Communication (prophoto-notifications + prophoto-gallery)
- **Reply-to-client** — photographer responds to a submission or download notification, client receives email
- **In-gallery messaging** — chat-like communication within the gallery viewer
- **Feedback requests** — photographer sends targeted question to client about specific images
- **Delivery confirmation** — client acknowledges receipt of final gallery

### Intelligence (prophoto-intelligence)
- **Download prediction** — predict which images a client is likely to download based on approval patterns
- **Gallery engagement scoring** — score client engagement across views, approvals, downloads
- **Smart notification timing** — send notifications at times when photographer is most likely to read them

### Gallery (prophoto-gallery)
- **Gallery expiry** — auto-archive galleries after a configurable period
- **Gallery duplication** — clone a gallery as a template for similar shoots
- **Gallery comparison** — side-by-side before/after views
- **Client self-service** — client re-opens submitted gallery to change selections (within a window)

### Access & Security (prophoto-access)
- **IP-based download restriction** — enforce `ip_whitelist` on GalleryShare for downloads
- **Two-factor share access** — require email + SMS code to view gallery
- **Audit dashboard** — who accessed what, when, from where (aggregate across all shares)
- **GDPR data export** — export all data associated with a client email

---

*Last updated: 2026-04-14 — Sprint 6 planning, follows Sprint 5 completion*
