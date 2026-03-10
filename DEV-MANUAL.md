# DEV-MANUAL

This file is a copy/paste command guide for this repo.

## 0) Pick a PHP binary once per terminal

`./scripts/prophoto` now auto-detects Herd PHP first, so this is mainly needed for manual `artisan` commands.

If `php artisan` fails with missing `mbstring`/`iconv`, use Herd PHP:

```bash
export PHP_BIN="/Users/davepeloso/Library/Application Support/Herd/bin/php"
```

If `php artisan` already works, use:

```bash
export PHP_BIN="php"
```

## 1) One-time setup

From repo root:

```bash
./scripts/prophoto bootstrap
```

What it does:
- Creates `sandbox/` if missing
- Adds local path repositories (`../prophoto-*`)
- Requires core local packages
  - Includes `prophoto/assets` in bootstrap package set
- Installs Composer dependencies
- Creates/updates `.env` defaults
- Creates SQLite DB file
- Generates app key if missing
- Installs dev dashboard on home page (`/`)
- Runs migrations
- Installs npm dependencies
- Builds sandbox assets
- Publishes package assets

## 2) Daily workflow

Fast refresh (recommended):

```bash
./scripts/prophoto sync
```

Open the sandbox dashboard:

```bash
open http://sandbox.test
```

Run diagnostics:

```bash
./scripts/prophoto doctor
```

Run tests:

```bash
./scripts/prophoto test
```

Clear Laravel caches quickly:

```bash
cd sandbox
"$PHP_BIN" artisan clear:all quick
```

Full cache clear:

```bash
cd sandbox
"$PHP_BIN" artisan clear:all full
```

Full cache clear + npm rebuild:

```bash
cd sandbox
"$PHP_BIN" artisan clear:all full --npm
```

## 3) Access + Filament bootstrap

Run this once after `bootstrap` to wire Access into Filament and User traits:

```bash
./scripts/prophoto access:bootstrap
```

Alias (same command):

```bash
./scripts/prophoto access bootstrap
```

Then create/update the admin user:

```bash
./scripts/prophoto access:user
```

What it does:
- Ensures `filament/filament` is installed
- Ensures `spatie/laravel-permission` is installed
- Installs Filament panel scaffolding (if missing)
- Publishes Spatie `permission-migrations`
- Registers `AccessPlugin` in Filament panel provider
- Re-applies the ProPhoto admin panel style overrides in `AdminPanelProvider.php` (keeps sidebar/theme changes after sandbox rebuilds)
- Adds `HasRoles` + `HasContextualPermissions` traits to `app/Models/User.php`
- Runs migrations
- Seeds Access roles/permissions

If you ever hit `no such table: permissions` while seeding:

```bash
cd sandbox
"$PHP_BIN" artisan vendor:publish --provider='Spatie\Permission\PermissionServiceProvider' --tag='permission-migrations' --force
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --class='ProPhoto\Access\Database\Seeders\RolesAndPermissionsSeeder' --force
```

## 4) When sandbox gets weird

Reinstall deps in current sandbox:

```bash
./scripts/prophoto sandbox:reset
```

Delete and recreate sandbox from scratch:

```bash
./scripts/prophoto sandbox:fresh
```

Delete and recreate sandbox from scratch (no prompt):

```bash
./scripts/prophoto sandbox:fresh --yes
```

## 5) Full rebuild (slow)

```bash
./scripts/prophoto rebuild
```

## 6) Safe preview mode (no changes)

```bash
./scripts/prophoto --dry-run bootstrap
./scripts/prophoto --dry-run sync
```

## 7) Auto-sync while you edit files

Start watcher (default 2-second poll interval):

```bash
./scripts/prophoto watch
```

Set a custom poll interval:

```bash
./scripts/prophoto watch --interval=3
```

Stop watcher:

```bash
# Press Ctrl+C
```

## 8) Start app manually from sandbox

```bash
cd sandbox
composer dev
```

## 9) Create Filament admin user (dev)

One command (recommended):

```bash
./scripts/prophoto access:user
```

Custom values:

```bash
./scripts/prophoto access:user --name='Your Name' --email='you@sandbox.test' --password='YourStrongPass123!' --role='studio_user'
```

Manual fallback:

```bash
cd sandbox
"$PHP_BIN" artisan make:filament-user --name='Sandbox Admin' --email='admin@sandbox.test' --password='Password123!' --no-interaction
USER_ID=$("$PHP_BIN" artisan tinker --execute="echo App\\Models\\User::where('email','admin@sandbox.test')->value('id');")
"$PHP_BIN" artisan permission:assign-role studio_user "$USER_ID" --no-interaction
```

Login:

```bash
open http://sandbox.test/admin/login
```

## 10) Quick command list

```bash
./scripts/prophoto --help
```

## 11) Phase 3 dual-write (Asset Spine)

Enable dual-write in sandbox:

```bash
cd sandbox
grep -q '^INGEST_ASSET_SPINE_DUAL_WRITE=' .env && sed -i '' 's/^INGEST_ASSET_SPINE_DUAL_WRITE=.*/INGEST_ASSET_SPINE_DUAL_WRITE=true/' .env || echo 'INGEST_ASSET_SPINE_DUAL_WRITE=true' >> .env
grep -q '^INGEST_ASSET_SPINE_FAIL_OPEN=' .env && sed -i '' 's/^INGEST_ASSET_SPINE_FAIL_OPEN=.*/INGEST_ASSET_SPINE_FAIL_OPEN=true/' .env || echo 'INGEST_ASSET_SPINE_FAIL_OPEN=true' >> .env
"$PHP_BIN" artisan config:clear
```

Disable dual-write:

```bash
cd sandbox
grep -q '^INGEST_ASSET_SPINE_DUAL_WRITE=' .env && sed -i '' 's/^INGEST_ASSET_SPINE_DUAL_WRITE=.*/INGEST_ASSET_SPINE_DUAL_WRITE=false/' .env || echo 'INGEST_ASSET_SPINE_DUAL_WRITE=false' >> .env
"$PHP_BIN" artisan config:clear
```

Watch dual-write telemetry in logs:

```bash
cd sandbox
tail -f storage/logs/laravel.log | rg 'asset_spine_dual_write'
```

## 12) Phase 4 gallery `asset_id` backfill + read switch

Run gallery migration and preview backfill:

```bash
cd sandbox
composer show prophoto/assets >/dev/null 2>&1 || composer require prophoto/assets:@dev --no-interaction
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan prophoto-gallery:backfill-asset-ids --dry-run --only-null
```

Run actual backfill:

```bash
cd sandbox
"$PHP_BIN" artisan prophoto-gallery:backfill-asset-ids --only-null
```

Enable gallery read-switch flag:

```bash
cd sandbox
grep -q '^GALLERY_ASSET_SPINE_READ_SWITCH=' .env && sed -i '' 's/^GALLERY_ASSET_SPINE_READ_SWITCH=.*/GALLERY_ASSET_SPINE_READ_SWITCH=true/' .env || echo 'GALLERY_ASSET_SPINE_READ_SWITCH=true' >> .env
"$PHP_BIN" artisan config:clear
```

Disable gallery read-switch flag:

```bash
cd sandbox
grep -q '^GALLERY_ASSET_SPINE_READ_SWITCH=' .env && sed -i '' 's/^GALLERY_ASSET_SPINE_READ_SWITCH=.*/GALLERY_ASSET_SPINE_READ_SWITCH=false/' .env || echo 'GALLERY_ASSET_SPINE_READ_SWITCH=false' >> .env
"$PHP_BIN" artisan config:clear
```

## 13) Canonical Asset Spine defaults (no legacy dataset assumption)

Enable canonical write/read defaults:

```bash
cd sandbox
grep -q '^INGEST_ASSET_SPINE_DUAL_WRITE=' .env && sed -i '' 's/^INGEST_ASSET_SPINE_DUAL_WRITE=.*/INGEST_ASSET_SPINE_DUAL_WRITE=true/' .env || echo 'INGEST_ASSET_SPINE_DUAL_WRITE=true' >> .env
grep -q '^INGEST_ASSET_SPINE_FAIL_OPEN=' .env && sed -i '' 's/^INGEST_ASSET_SPINE_FAIL_OPEN=.*/INGEST_ASSET_SPINE_FAIL_OPEN=false/' .env || echo 'INGEST_ASSET_SPINE_FAIL_OPEN=false' >> .env
grep -q '^GALLERY_ASSET_SPINE_WRITE_ENABLED=' .env && sed -i '' 's/^GALLERY_ASSET_SPINE_WRITE_ENABLED=.*/GALLERY_ASSET_SPINE_WRITE_ENABLED=true/' .env || echo 'GALLERY_ASSET_SPINE_WRITE_ENABLED=true' >> .env
grep -q '^GALLERY_ASSET_SPINE_WRITE_FAIL_OPEN=' .env && sed -i '' 's/^GALLERY_ASSET_SPINE_WRITE_FAIL_OPEN=.*/GALLERY_ASSET_SPINE_WRITE_FAIL_OPEN=false/' .env || echo 'GALLERY_ASSET_SPINE_WRITE_FAIL_OPEN=false' >> .env
grep -q '^GALLERY_ASSET_SPINE_READ_SWITCH=' .env && sed -i '' 's/^GALLERY_ASSET_SPINE_READ_SWITCH=.*/GALLERY_ASSET_SPINE_READ_SWITCH=true/' .env || echo 'GALLERY_ASSET_SPINE_READ_SWITCH=true' >> .env
"$PHP_BIN" artisan config:clear
```

## 14) Seed fixture data and prove vertical slice

Runs a full fixture flow: `ingest -> asset -> gallery(asset_id)`.

```bash
cd sandbox
"$PHP_BIN" artisan prophoto:seed-asset-spine-fixtures --count=3
```

Run the automated vertical-slice test:

```bash
cd sandbox
"$PHP_BIN" artisan test tests/Feature/AssetSpineVerticalSliceTest.php
```

## 15) Rebuild normalized metadata (schema evolution)

Preview without writes:

```bash
cd sandbox
"$PHP_BIN" artisan prophoto-assets:renormalize --dry-run
```

Run re-normalization:

```bash
cd sandbox
"$PHP_BIN" artisan prophoto-assets:renormalize
```
