# Phase 6 — Download Tracking, Gallery View Notifications & Future Backlog
## Sprint Retrospective & Context Preservation

**Sprint date:** April 14–15, 2026
**Primary package:** `prophoto-gallery` (download endpoint, events, model updates)
**Secondary package:** `prophoto-notifications` (listeners, email, Message records, Filament bell)
**Read-only dependencies:** `prophoto-assets`, `prophoto-contracts`, `prophoto-access`
**Contracts modified:** None
**Assets modified:** None
**Status:** Complete — Stories 6.1 + 6.2 + 6.3 + 6.5 done, Story 6.4 skipped. 88 gallery tests (225 assertions) + 28 notification tests (31 assertions), all passing

---

## What Was Built

### Story 6.1 — Public Download Endpoint + Enforcement (5 pts)
Public download route with full permission enforcement and counter tracking.

- **`DownloadController`** — `prophoto-gallery/src/Http/Controllers/DownloadController.php`
  - `GET /g/{token}/download/{image}` — public, share-scoped, rate-limited (60/min)
  - Resolves share via token (same pattern as GalleryViewerController)
  - Enforces `can_download` flag (403 if false)
  - Enforces `max_downloads` limit via `hasReachedMaxDownloads()` (403 if reached)
  - Validates image belongs to gallery (404 if not)
  - Returns 410 for expired/revoked shares
  - Increments both `GalleryShare.download_count` and `Gallery.download_count` atomically
  - Logs to `gallery_activity_log` via `GalleryActivityLogger::log()` with action type `download`
  - Logs to `gallery_access_logs` with `ACTION_DOWNLOAD`
  - Dispatches `ImageDownloaded` event (consumed by Story 6.2)
  - Redirects to `$image->resolved_url`

- **`ImageDownloaded` event** — `prophoto-gallery/src/Events/ImageDownloaded.php`
  - Readonly constructor with: `galleryId`, `galleryShareId`, `studioId`, `galleryName`, `imageId`, `imageFilename`, `downloadedByEmail`, `shareDownloadCount`, `shareMaxDownloads`, `galleryDownloadCount`, `downloadedAt`, `sharedByUserId`
  - Same convention as `GallerySubmitted`: carries IDs and data, not models

- **`GalleryShare` model updates:**
  - `canDownload()` — checks `can_download` flag AND `max_downloads` limit
  - `incrementDownloadCount()` — atomic DB increment
  - `hasReachedMaxDownloads()` — renamed from `hasReachedMaxViews()` (misleading name)

- **`Gallery` model update:**
  - `incrementDownloadCount()` — atomic DB increment

- **Route:** `GET g/{token}/download/{image}` added to `web.php` with `throttle:60,1` middleware

### Story 6.2 — Download Notification (5 pts)
Email + audit trail + Filament bell on every image download. Follows HandleGallerySubmitted template exactly.

- **`HandleImageDownloaded` listener** — `prophoto-notifications/src/Listeners/HandleImageDownloaded.php`
  - Registered in `NotificationsServiceProvider::boot()` via `Event::listen()`
  - Same 4-step pattern: resolve recipient → send email → create Message → Filament bell
  - Recipient resolution: share creator → studio admin fallback (identical to HandleGallerySubmitted)
  - Filament notification: `heroicon-o-arrow-down-tray` icon, info color, "View Gallery" action
  - 3-layer Filament guard: `class_exists`, `method_exists`, `try/catch`

- **`DownloadNotificationMail` mailable** — `prophoto-notifications/src/Mail/DownloadNotificationMail.php`
  - Subject: "Image Downloaded: {gallery name}"
  - Data: gallery name, image filename, client email, share download count, max downloads (nullable), gallery total, timestamp, dashboard URL

- **Email view** — `prophoto-notifications/resources/views/emails/image-downloaded.blade.php`
  - Same design as `proofing-submitted.blade.php` (clean card layout, inline styles)
  - Download stats: "3 of 10" (with limit) or "3 downloads" (unlimited)
  - Gallery total count shown separately
  - CTA: "View in Dashboard" linking to Filament gallery edit page

- **Service provider:** `ImageDownloaded → HandleImageDownloaded` registered alongside existing `GallerySubmitted` listener

### Story 6.4 — SKIPPED
Download limit approaching notification deferred — counting is active but limiting downloads on a digital product adds friction without clear value. The enforcement plumbing exists if ever needed.

### Story 6.3 — GalleryViewed Event + Threshold Notifications (3 pts)
Notifies the photographer when a client views their gallery at milestone thresholds.

- **`GalleryViewed` event** — `prophoto-gallery/src/Events/GalleryViewed.php`
  - Readonly constructor with: `galleryId`, `galleryShareId`, `studioId`, `galleryName`, `viewedByEmail`, `viewCount`, `viewedAt`, `sharedByUserId`
  - NOT dispatched on every page load — only at milestone thresholds

- **Dispatch point** — `GalleryViewerController::show()`
  - After `incrementViewCount()` and activity logging
  - Thresholds defined as class constant: `[1, 5, 10, 25, 50]`
  - Uses `in_array($share->access_count, self::VIEW_NOTIFICATION_THRESHOLDS, true)` for strict check
  - Falls back to `shared_with_email` if `confirmed_email` is null (presentation galleries skip identity gate)

- **`HandleGalleryViewed` listener** — `prophoto-notifications/src/Listeners/HandleGalleryViewed.php`
  - Same 4-step pattern as HandleGallerySubmitted
  - Dynamic subject: "Gallery Viewed: {name}" (first view) vs. "Gallery Milestone: {name} — {count} views"
  - Dynamic Filament icon: `heroicon-o-eye` (success) for first view, `heroicon-o-chart-bar` (info) for milestones

- **`GalleryViewedMail` mailable** — `prophoto-notifications/src/Mail/GalleryViewedMail.php`
  - Exposes `isFirstView` boolean for conditional rendering in the email template
  - Subject computed dynamically based on view count

- **Email view** — `prophoto-notifications/resources/views/emails/gallery-viewed.blade.php`
  - First view: "Great news — {email} just viewed {gallery} for the first time!"
  - Milestone: "{gallery} has been viewed {count} times by {email}"
  - Same card layout as other notification emails

- **Service provider:** `GalleryViewed → HandleGalleryViewed` registered

---

## Architecture Decisions

1. **Downloads stay in prophoto-gallery** — `can_download`, `max_downloads`, `download_count` all live on `GalleryShare` (gallery-owned table). A separate downloads package would violate Database Ownership. Downloads are a gallery capability, not a separate domain.

2. **Event in gallery, listener in notifications** — Same pattern as Sprint 5. `ImageDownloaded` lives in `prophoto-gallery`, `HandleImageDownloaded` lives in `prophoto-notifications`. One-directional dependency.

3. **Atomic counters** — Both `GalleryShare.download_count` and `Gallery.download_count` use Eloquent's `increment()` method which translates to `UPDATE ... SET column = column + 1`. No race conditions.

4. **Dual logging** — Downloads write to both `gallery_activity_log` (append-only ledger, single write path via GalleryActivityLogger) AND `gallery_access_logs` (access tracking). This matches how gallery views are logged.

5. **Rate limiting on route** — `throttle:60,1` middleware on the download route prevents abuse. This is in addition to `max_downloads` enforcement on the share.

6. **Image URL resolution** — Controller uses `$image->resolved_url` which checks asset spine → imagekit_url → legacy file_path. Test images use `imagekit_url` since the test schema doesn't include the legacy `file_path` column.

---

## Files Created

| File | Package | Story |
|------|---------|-------|
| `src/Events/ImageDownloaded.php` | prophoto-gallery | 6.1 |
| `src/Http/Controllers/DownloadController.php` | prophoto-gallery | 6.1 |
| `tests/Feature/DownloadControllerTest.php` | prophoto-gallery | 6.1 |
| `src/Listeners/HandleImageDownloaded.php` | prophoto-notifications | 6.2 |
| `src/Mail/DownloadNotificationMail.php` | prophoto-notifications | 6.2 |
| `resources/views/emails/image-downloaded.blade.php` | prophoto-notifications | 6.2 |
| `tests/Feature/DownloadNotificationTest.php` | prophoto-notifications | 6.2 |
| `src/Events/GalleryViewed.php` | prophoto-gallery | 6.3 |
| `tests/Feature/GalleryViewedEventTest.php` | prophoto-gallery | 6.3 |
| `src/Listeners/HandleGalleryViewed.php` | prophoto-notifications | 6.3 |
| `src/Mail/GalleryViewedMail.php` | prophoto-notifications | 6.3 |
| `resources/views/emails/gallery-viewed.blade.php` | prophoto-notifications | 6.3 |
| `tests/Feature/GalleryViewedNotificationTest.php` | prophoto-notifications | 6.3 |
| `src/Filament/Widgets/GalleryDownloadStatsWidget.php` | prophoto-gallery | 6.5 |

## Files Modified

| File | Package | Story | Change |
|------|---------|-------|--------|
| `src/Models/GalleryShare.php` | prophoto-gallery | 6.1 | Added `canDownload()`, `incrementDownloadCount()`, renamed `hasReachedMaxViews` → `hasReachedMaxDownloads` |
| `src/Models/Gallery.php` | prophoto-gallery | 6.1 | Added `incrementDownloadCount()` |
| `routes/web.php` | prophoto-gallery | 6.1 | Added download route with throttle |
| `src/NotificationsServiceProvider.php` | prophoto-notifications | 6.2, 6.3 | Registered `ImageDownloaded` and `GalleryViewed` listeners |
| `src/Http/Controllers/GalleryViewerController.php` | prophoto-gallery | 6.3 | Added GalleryViewed import, threshold constant, dispatch after access tracking |
| `src/Filament/Resources/GalleryResource/Pages/EditGallery.php` | prophoto-gallery | 6.5 | Added getFooterWidgets + getFooterWidgetData for download stats widget |
| `src/Filament/GalleryPlugin.php` | prophoto-gallery | 6.5 | Added hasDownloadStatsWidget toggle + downloadStatsWidget() + hasDownloadStats() |

---

## Test Coverage

**prophoto-gallery:** 88 tests, 225 assertions (27 new)
- Download controller (21 tests): successful redirect, counter increments (share + gallery), activity log, access log, event dispatch, permission denied (can_download=false, limit reached), invalid shares (expired, revoked, bad token, wrong gallery), model helpers (canDownload, hasReachedMaxDownloads, incrementDownloadCount)
- Gallery viewed event (6 tests): dispatched on first view, not dispatched on 2nd view, dispatched on 5th/10th thresholds, not dispatched on non-threshold, event carries correct data

**prophoto-notifications:** 28 tests, 31 assertions (19 new)
- Download notification (10 tests): email to share creator, fallback to studio user, no recipient handling, subject validation, data validation (with/without limit), Message audit trail, dashboard URL, Filament graceful degradation
- Gallery viewed notification (9 tests): first view email, milestone email, dynamic subjects, no recipient handling, Message records (first view + milestone), data validation, Filament graceful degradation

---

### Story 6.5 — Filament Download Stats Widget (3 pts)
Per-share download breakdown widget on the gallery edit page.

- **`GalleryDownloadStatsWidget`** — `prophoto-gallery/src/Filament/Widgets/GalleryDownloadStatsWidget.php`
  - Extends `Filament\Widgets\TableWidget`
  - Scoped to current gallery via `$galleryId` public property (passed from EditGallery)
  - Queries `GalleryShare` where `download_count > 0`, sorted by download count desc
  - Columns: Client (confirmed_email with fallback), Downloads (bold, info color), Last Activity (since + full date description), Views (access_count)
  - Aggregate description via `getTableDescription()`: "{total} downloads · {clients} clients · {unique} unique images"
  - Top images query against `gallery_activity_log` for unique image count
  - Pagination: 5/10, default 5
  - Empty state: "No downloads yet" with arrow-down-tray icon

- **`EditGallery`** — modified to add `getFooterWidgets()` + `getFooterWidgetData()`
  - Passes `galleryId => $this->record->getKey()` to widget
  - Respects `GalleryPlugin::get()->hasDownloadStats()` toggle (with try/catch for plugin-not-registered)

- **`GalleryPlugin`** — added `$hasDownloadStatsWidget` toggle (default: true)
  - `downloadStatsWidget(bool $condition)` fluent setter
  - `hasDownloadStats()` public getter used by EditGallery

## What Sprint 6 Still Needs

- Nothing — Sprint 6 complete (Stories 6.1 + 6.2 + 6.3 + 6.5, Story 6.4 skipped)

---

## What Sprint 7+ Needs to Know

1. **`ImageDownloaded` is the second event pattern** — follows `GallerySubmitted` exactly. For new triggers, copy `HandleImageDownloaded` as the listener template.
2. **`DownloadController` is the public download pattern** — resolves share via token, validates, enforces limits, logs, dispatches. For future download features (bulk, watermark), extend this controller.
3. **Test images need `imagekit_url`** — the test database schema doesn't include legacy `file_path`. Use `imagekit_url` in test helpers so `resolved_url` returns non-null.
4. **`hasReachedMaxViews()` was renamed** to `hasReachedMaxDownloads()`. If any code referenced the old name, update it.
5. **Dual logging is intentional** — downloads go to both `gallery_activity_log` and `gallery_access_logs`. The activity log is the append-only ledger (single write path), the access log is for analytics queries.
6. **prophoto-contracts remains untouched** — no new interfaces, DTOs, or events.
7. **prophoto-assets remains untouched** — the read-only boundary is preserved.

---

8. **`GalleryDownloadStatsWidget` is page-scoped** — registered via `EditGallery::getFooterWidgets()`, not on the panel. Receives `galleryId` from `getFooterWidgetData()`. Controlled by `GalleryPlugin::hasDownloadStats()` toggle.

---

*Last updated: 2026-04-15 — Sprint 6 complete (Stories 6.1 + 6.2 + 6.3 + 6.5)*
