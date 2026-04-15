# Sandbox Setup Guide — ProPhoto

> **Purpose**: Documents how the ProPhoto sandbox (host app) is created, what it contains, and how to maintain it. This is essential reading for any agent that needs to modify the sandbox script, add packages, or debug integration issues.

---

## What the Sandbox Is

ProPhoto is a modular monolith — the actual product code lives in ~9 independent packages (`prophoto-gallery`, `prophoto-assets`, `prophoto-notifications`, etc.). The sandbox (`prophoto-app`) is a disposable Laravel 12 host application that wires them all together via composer path repositories (symlinked, not copied).

The sandbox exists for smoke testing, integration work, and visual verification. It is NOT the production deployment target — it's a developer tool.

---

## How It's Created

**Script:** `create-sandbox.sh` (root of the `prophoto/` directory)

```bash
./create-sandbox.sh              # creates prophoto-app/
./create-sandbox.sh my-app-name  # custom directory name
```

**Requirements:** PHP 8.2+ and Composer (Laravel Herd provides both).

**What the script does, in order:**

1. Creates a fresh Laravel 12 skeleton
2. Configures SQLite database (`database/database.sqlite`)
3. Patches `.env` (APP_NAME, APP_URL, DB_CONNECTION=sqlite, QUEUE_CONNECTION=sync, CACHE_STORE=file)
4. Registers all ProPhoto packages as composer path repositories (symlinked)
5. Installs Laravel Sanctum + patches User model with `HasApiTokens`, `HasRoles`, `HasContextualPermissions`
6. Installs Filament v4 + creates `AdminPanelProvider` with GalleryPlugin and database notifications
7. Registers the panel provider in `bootstrap/providers.php`
8. Runs `composer require` for all packages
9. Publishes Spatie permission migrations + creates notifications table migration + runs all migrations
10. Seeds dummy data via `SandboxSeeder`
11. Generates app key + storage link
12. Publishes Filament assets (CSS/JS) via `php artisan filament:assets`

---

## Packages Wired In

These are registered as path repositories and composer-required (order matters for migrations):

| Package | Composer Name | What It Does |
|---------|--------------|--------------|
| `prophoto-contracts` | `prophoto/contracts` | Shared interfaces, DTOs, enums, events |
| `prophoto-access` | `prophoto/access` | Studios, users, roles, permissions |
| `prophoto-assets` | `prophoto/assets` | Asset Spine — canonical media ownership |
| `prophoto-booking` | `prophoto/booking` | Booking sessions, operational context |
| `prophoto-gallery` | `prophoto/gallery` | Galleries, proofing, shares, viewers |
| `prophoto-intelligence` | `prophoto/intelligence` | Derived intelligence, generators |
| `prophoto-ingest` | `prophoto/ingest` | Upload sessions, file processing, session matching |
| `prophoto-interactions` | `prophoto/interactions` | Image interactions (favorites, tags, etc.) |
| `prophoto-notifications` | `prophoto/notifications` | Email notifications, Message audit trail |

**If you add a new package**, add it to the `PACKAGES` array in `create-sandbox.sh`. Order matters — packages with FK dependencies must come after the packages that own the referenced tables.

---

## Filament Admin Panel

**URL:** `http://prophoto-app.test/admin` (or whatever APP_NAME you used)

**Login:** `dave@example.com` / `password` (from SandboxSeeder)

**Panel provider:** `app/Providers/Filament/AdminPanelProvider.php` — created by the sandbox script, NOT checked into git.

**What's registered:**
- `GalleryPlugin::make()` — brings in GalleryResource, PendingTypeTemplateResource, AccessLogResource, and RecentSubmissionsWidget
- `->databaseNotifications()` — enables the notification bell icon (Story 5.4)
- `->login()` — provides the `/admin/login` page
- Filament discovery directories: `app/Filament/{Resources,Pages,Widgets}`

**Important:** Filament is installed by the sandbox script, not by any package. Packages that provide Filament resources (like `prophoto-gallery/src/Filament/`) guard their code with class_exists checks or simply don't register if Filament isn't present. This means:
- Package tests run without Filament (via Orchestra Testbench)
- Filament features only activate when the host app has Filament installed
- The `AdminPanelProvider` is the single place where plugins are registered

---

## SandboxSeeder

**Location:** `prophoto-app/database/seeders/SandboxSeeder.php`

The seeder creates a complete but minimal dataset for testing:

- **Studio:** "Peloso Photography"
- **User:** dave@example.com / password (with studio_id, HasRoles, HasContextualPermissions)
- **Gallery:** "April 2026 Shoot" (proofing type, mode_config with min_approvals=5, ratings_enabled, pipeline_sequential)
- **Images:** 2 images (image1 approved + rated 5 stars, image2 unapproved)
- **Share:** Confirmed identity (subject@example.com), not submitted, can_download=true
- **Activity log:** 6 entries (gallery_created, share_created, identity_confirmed, gallery_viewed, image_approved, image_rated)
- **Approval states:** 1 approved (image1), 1 unapproved (image2)
- **Access logs:** 2 entries
- **Upload session:** STATUS_UPLOADING with 3 pre-registered files (2 completed, 1 pending)

**Postman collection:** The seeder also generates `postman-collection.json` with all API and web requests organized into folders.

**To re-seed:** `cd prophoto-app && php artisan migrate:fresh --seed --seeder=SandboxSeeder --force`

**To seed without destroying:** `cd prophoto-app && php artisan db:seed --class=SandboxSeeder --force` (will fail on unique constraints if data exists)

---

## Postman Variables

The seeder sets these collection-level variables in the generated Postman collection:

| Variable | Value | Used By |
|----------|-------|---------|
| `PROPHOTO_API_BASE_URL` | `http://prophoto-app.test/api` | API routes |
| `PROPHOTO_APP_URL` | `http://prophoto-app.test` | Web routes (gallery viewer) |
| `AUTH_TOKEN` | Sanctum token for dave@example.com | Authorization header |
| `STUDIO_ID` | Seeded studio ID | Studio-scoped requests |
| `GALLERY_ID` | Seeded gallery ID | Gallery requests |
| `SHARE_TOKEN` | Deterministic share token | Viewer/proofing requests |
| `IMAGE_1_ID` | First seeded image | Approve/rate requests |
| `IMAGE_2_ID` | Second seeded image | Approve requests |
| `CSRF_TOKEN` | Empty (must be extracted from web session) | POST to web routes |

---

## Common Operations

### Recreate from scratch
```bash
cd ~/Sites/prophoto
./create-sandbox.sh
```

### Re-seed only (keep schema)
```bash
cd ~/Sites/prophoto/prophoto-app
php artisan migrate:fresh --seed --seeder=SandboxSeeder --force
```

### Add a new package to the sandbox
1. Add the package directory name to the `PACKAGES` array in `create-sandbox.sh`
2. If it has Filament resources, register its plugin in the `AdminPanelProvider` section of the script
3. Recreate the sandbox or manually run `composer require prophoto/new-package:@dev` in the existing app

### Test the proofing flow end-to-end
1. Open `http://prophoto-app.test/g/{SHARE_TOKEN}` in a browser
2. The identity gate is already confirmed — you'll see the proofing viewer directly
3. Approve images, rate them, submit
4. Check the Filament admin at `/admin` for the submission notification

### Debug Filament issues
- Panel provider is at `app/Providers/Filament/AdminPanelProvider.php`
- Check `bootstrap/providers.php` includes the panel provider
- Run `php artisan filament:info` to see registered resources and widgets
- Run `php artisan route:list --name=filament` to see Filament routes
- If admin panel renders without CSS: run `php artisan filament:assets`
- If "no such table: notifications" error: run `php artisan make:notifications-table && php artisan migrate`

### Filament v4 namespace issues
If you see "Class not found" errors after rebuilding, read `docs/Filament-Namespace-Issue.md`. The most common causes:
- Actions imported from `Filament\Tables\Actions\*` instead of `Filament\Actions\*`
- Layout components imported from `Filament\Forms\Components\*` instead of `Filament\Schemas\Components\*`
- Property types using `?string` instead of `\UnitEnum|string|null` or `string|\BackedEnum|null`

---

## Architecture Notes

- **Packages are symlinked** — edits to `../prophoto-gallery/src/` are reflected immediately in the sandbox. No re-install needed.
- **The sandbox is disposable** — delete and recreate any time. Never commit important code to the sandbox app itself.
- **SQLite for simplicity** — no MySQL/Postgres setup needed. Fine for development and smoke testing.
- **Sync queue** — jobs fire immediately without a worker process. Fine for testing.
- **Herd integration** — `herd link prophoto-app` makes it available at `http://prophoto-app.test`.

---

*Last updated: 2026-04-14 — Sprint 5, Filament v4 + notifications table + asset publishing, all 9 packages wired*
