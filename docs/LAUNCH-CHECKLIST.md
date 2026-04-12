# ProPhoto Phase 1 — Launch Readiness Checklist

**Version:** 1.0  
**Sprint:** 7 of 7  
**Date:** April 11, 2026  
**Owner:** Dave Peloso

Work through each section top to bottom before going live. Check off items as you complete them.

---

## 1. Database & Migrations

- [ ] Run all migrations on staging: `php artisan migrate --force`
- [ ] Confirm all 7 ingest migrations ran cleanly (check `migrations` table)
- [ ] Confirm Sprint 7 performance indexes exist: `SHOW INDEX FROM ingest_files` — verify `idx_ingest_files_session_status_culled` is present
- [ ] Confirm `images.ingest_session_id` column exists (Sprint 6 migration)
- [ ] Confirm `assets.metadata` is a JSON column (not TEXT) — required for `whereJsonContains`
- [ ] Run `php artisan migrate --force` on **production** only after staging is verified

---

## 2. Queue Configuration

- [ ] Queue driver set to `database` or `redis` in `.env` — **NOT** `sync` in production
- [ ] `php artisan queue:work --queue=default` running as a daemon (Supervisor or Herd process manager)
- [ ] `GenerateAssetThumbnail` job visible in queue when a session is confirmed (test with one real upload)
- [ ] Failed jobs table exists: `php artisan queue:failed-table && php artisan migrate`
- [ ] Verify retry logic: kill the worker mid-job, confirm it retries up to 3 times then lands in `failed_jobs`

---

## 3. Storage

- [ ] `local` disk configured in `config/filesystems.php` with a real path (not just `:memory:`)
- [ ] `storage/app/ingest/` directory writable by the web server user
- [ ] `storage/app/thumbnails/` directory writable (GenerateAssetThumbnail writes here)
- [ ] GD PHP extension enabled: `php -m | grep gd` — required for thumbnail generation
- [ ] Test: upload a real JPEG and confirm `thumbnails/{assetId}/thumb_400x300.jpg` appears

---

## 4. Google Calendar OAuth

- [ ] `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` set in `.env`
- [ ] OAuth redirect URI registered in Google Cloud Console matches your production domain
- [ ] `calendar.readonly` scope approved in Google Cloud Console (not just test mode)
- [ ] Test full OAuth flow: connect → disconnect → reconnect
- [ ] Verify tokens are encrypted at rest (`CalendarTokenService` uses `encrypt()`)

---

## 5. Authentication & Authorization

- [ ] All `/api/ingest/*` routes require `auth:sanctum` — verify a request without a token returns 401
- [ ] Session ownership is enforced: confirm a user cannot access another user's session (try with two test accounts)
- [ ] Sanctum tokens are scoped correctly in the host app

---

## 6. Run the Full Test Suite

```bash
cd prophoto-ingest
./vendor/bin/phpunit --testdox
```

Expected: all tests green across:
- `Unit/Services/UploadSessionServiceTest` (16 tests)
- `Unit/Services/UploadSessionServiceSprint3Test` (12 tests)  
- `Unit/Services/Calendar/CalendarMatcherServiceTest` (25 tests)
- `Unit/Sprint5/IngestSessionConfirmedEventTest` (6 tests)
- `Unit/Sprint5/IngestSessionConfirmedListenerTest` (6 tests)
- `Unit/Sprint6/GalleryContextProjectionListenerTest` (8 tests)
- `Feature/IngestControllerTest` (22 tests)

**Total: ~95 tests, 0 failures required to proceed.**

---

## 7. End-to-End Smoke Test (Manual)

Run this full workflow once on staging with real files before going live:

- [ ] Navigate to `/ingest`
- [ ] Drag and drop 5–10 real JPEG files onto the drop zone
- [ ] Confirm EXIF extraction completes (check aperture, ISO, camera shown)
- [ ] Confirm calendar matching runs (if Google Calendar connected)
- [ ] Select a calendar match (or skip)
- [ ] Observe file thumbnails rendering in gallery
- [ ] Apply tags to 2–3 images manually
- [ ] Cull one image (star it, mark it rejected)
- [ ] Click "Confirm" — verify the spinner appears and transitions to complete
- [ ] Check the gallery in prophoto-gallery — confirm new Image records exist
- [ ] Check the assets table — confirm Asset records were created for each non-culled file
- [ ] Check the thumbnails directory — confirm `thumb_400x300.jpg` files were generated
- [ ] Check intelligence runs — confirm `intelligence_runs` records exist for each asset

---

## 8. Performance Verification

Run these against staging with a 100-file batch:

- [ ] Metadata extraction (browser): < 5 seconds for 100 files
- [ ] `POST /api/ingest/match-calendar`: response < 3 seconds
- [ ] Gallery render after file registration: < 2 seconds
- [ ] `PATCH /api/ingest/sessions/{id}/files/batch` (100 files): < 500ms (bulk update, no N+1)
- [ ] Asset creation (100 files via queue): < 30 seconds total
- [ ] `GET /api/ingest/sessions/{id}/preview-status`: < 500ms per poll
- [ ] GalleryContextProjectionListener (100 assets → gallery): < 10 seconds

---

## 9. Error Handling Verification

- [ ] Upload a corrupt/unreadable file — confirm `STATUS_FAILED` recorded, session continues
- [ ] Kill the queue worker mid-thumbnail — confirm job retries, then fails gracefully
- [ ] Confirm a session without any uploaded files can still be confirmed (zero-asset edge case)
- [ ] Disconnect Google Calendar mid-session — confirm ingest continues without calendar data
- [ ] Attempt to confirm the same session twice — confirm 422 returned on second attempt

---

## 10. Monitoring (Post-Launch)

- [ ] Set up log aggregation for `prophoto-ingest` channel (Papertrail, Loggly, or Laravel Telescope)
- [ ] Alert on `IngestController: File upload failed` log entries
- [ ] Alert on `GenerateAssetThumbnail: job failed after all retries`
- [ ] Alert on `IngestSessionConfirmedListener: session processing failed`
- [ ] Dashboard: track daily `upload_sessions` confirmed count
- [ ] Dashboard: track `failed_jobs` queue depth

---

## Sign-Off

| Checkpoint | Status | Date |
|---|---|---|
| All migrations run on staging | ☐ | |
| Full test suite passing (95+ tests) | ☐ | |
| E2E smoke test completed | ☐ | |
| Performance benchmarks met | ☐ | |
| Production `.env` verified | ☐ | |
| Queue daemon running | ☐ | |
| **APPROVED TO LAUNCH** | ☐ | |

