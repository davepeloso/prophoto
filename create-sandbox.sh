#!/usr/bin/env bash
# =============================================================================
# ProPhoto Sandbox Creator
# =============================================================================
#
# Creates a disposable Laravel host app that wires all ProPhoto packages
# together via composer path repositories. Use it for smoke testing,
# E2E testing, or integration work. Throw it away any time.
#
# Usage:
#   ./create-sandbox.sh              # creates prophoto-app in current dir
#   ./create-sandbox.sh my-app-name  # creates prophoto-app/my-app-name
#
# Requirements:
#   - composer  (in PATH or via Laravel Herd)
#   - php 8.2+  (via Laravel Herd is fine)
#   - All prophoto-* packages as siblings of this script
#
# Layout assumed:
#   prophoto/
#   ├── create-sandbox.sh         ← this script
#   ├── prophoto-ingest/
#   ├── prophoto-gallery/
#   ├── prophoto-assets/
#   ├── prophoto-intelligence/
#   ├── prophoto-booking/
#   ├── prophoto-access/
#   └── prophoto-app/             ← created by this script
#
# =============================================================================

set -euo pipefail

# ─── Config ──────────────────────────────────────────────────────────────────

APP_NAME="${1:-prophoto-app}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$SCRIPT_DIR/$APP_NAME"

# Packages to wire in (order matters for migrations — access first for users table)
PACKAGES=(
  "prophoto-contracts"
  "prophoto-access"
  "prophoto-assets"
  "prophoto-booking"
  "prophoto-gallery"
  "prophoto-intelligence"
  "prophoto-ingest"
  "prophoto-interactions"
  "prophoto-notifications"
  "prophoto-ai"
)

# ─── Colours ─────────────────────────────────────────────────────────────────

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
NC='\033[0m' # No Color

step()  { echo -e "\n${CYAN}▶ $1${NC}"; }
ok()    { echo -e "${GREEN}✔ $1${NC}"; }
warn()  { echo -e "${YELLOW}⚠ $1${NC}"; }
die()   { echo -e "${RED}✘ $1${NC}"; exit 1; }

# ─── Pre-flight checks ───────────────────────────────────────────────────────

step "Pre-flight checks"

command -v php >/dev/null 2>&1 || die "php not found. Is Laravel Herd running?"
command -v composer >/dev/null 2>&1 || die "composer not found."

PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
ok "PHP $PHP_VERSION found"
ok "composer found"

if [[ -d "$APP_DIR" ]]; then
  warn "Directory $APP_DIR already exists."
  read -r -p "Delete it and start fresh? [y/N] " confirm
  if [[ "$confirm" =~ ^[Yy]$ ]]; then
    rm -rf "$APP_DIR"
    ok "Removed existing $APP_DIR"
  else
    die "Aborted."
  fi
fi

# Verify packages exist
for pkg in "${PACKAGES[@]}"; do
  PKG_PATH="$SCRIPT_DIR/$pkg"
  if [[ ! -d "$PKG_PATH" ]]; then
    warn "Package not found, skipping: $PKG_PATH"
  fi
done

# ─── Create Laravel app ──────────────────────────────────────────────────────

step "Creating Laravel application: $APP_NAME (Laravel 12)"

# Install Laravel 12 skeleton — ProPhoto packages support ^11.0|^12.0 but not Laravel 13 yet
# We use the last Laravel 12 compatible skeleton version
composer create-project --prefer-dist laravel/laravel "$APP_DIR" "^12.0" --no-interaction -q

ok "Laravel 12 skeleton created at $APP_DIR"

# ─── Configure SQLite database ───────────────────────────────────────────────

step "Configuring SQLite database"

touch "$APP_DIR/database/database.sqlite"

# Patch .env — ensure SQLite is the DB driver regardless of Laravel skeleton version
ENV_FILE="$APP_DIR/.env"

# Strip all DB_* lines (handles both MySQL-default and SQLite-default skeletons)
grep -v "^DB_" "$ENV_FILE" > "$ENV_FILE.tmp"

# Write a clean SQLite block
cat >> "$ENV_FILE.tmp" << EOF

DB_CONNECTION=sqlite
DB_DATABASE=$APP_DIR/database/database.sqlite
EOF

mv "$ENV_FILE.tmp" "$ENV_FILE"

# Patch APP_NAME and APP_URL
sed -i.bak "s/APP_NAME=.*/APP_NAME=\"ProPhoto Sandbox\"/" "$ENV_FILE"
sed -i.bak "s#APP_URL=.*#APP_URL=http://$APP_NAME.test#" "$ENV_FILE"
rm -f "$ENV_FILE.bak"

ok "SQLite configured (database/database.sqlite)"

# ─── Add package path repositories to composer.json ─────────────────────────

step "Wiring ProPhoto packages via composer path repositories"

cd "$APP_DIR"

# Build the path repositories JSON block
REPOS_JSON=""
REQUIRE_ARGS=()

for pkg in "${PACKAGES[@]}"; do
  PKG_PATH="$SCRIPT_DIR/$pkg"

  if [[ ! -d "$PKG_PATH" ]]; then
    warn "Skipping missing package: $pkg"
    continue
  fi

  # Get the composer package name from the package's composer.json
  PKG_NAME=$(php -r "echo json_decode(file_get_contents('$PKG_PATH/composer.json'))->name;")

  if [[ -z "$PKG_NAME" ]]; then
    warn "Could not read composer name from $pkg/composer.json — skipping"
    continue
  fi

  # Accumulate repository entries (we'll add them all at once)
  composer config "repositories.$pkg" \
    '{"type":"path","url":"'"$PKG_PATH"'","options":{"symlink":true}}' \
    --no-interaction

  REQUIRE_ARGS+=("$PKG_NAME:@dev")

  ok "Path repo registered: $PKG_NAME → ../$pkg"
done

# ─── Require all packages ────────────────────────────────────────────────────

step "Installing Laravel Sanctum (required for auth:sanctum middleware)"

composer require laravel/sanctum --no-interaction --no-progress --quiet
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" \
  --force --no-interaction >/dev/null 2>&1

# Patch User model with all required traits:
#   HasApiTokens       — Sanctum token generation (createToken())
#   HasRoles           — Spatie role assignment (assignRole(), hasRole())
#   HasContextualPermissions — ProPhoto gallery-scoped permission checks
USER_MODEL="$APP_DIR/app/Models/User.php"

# Add use imports after the Notifiable import
sed -i.bak "s/use Illuminate\\\\Notifications\\\\Notifiable;/use Illuminate\\\\Notifications\\\\Notifiable;\nuse Laravel\\\\Sanctum\\\\HasApiTokens;\nuse Spatie\\\\Permission\\\\Traits\\\\HasRoles;\nuse ProPhoto\\\\Access\\\\Traits\\\\HasContextualPermissions;/" "$USER_MODEL"

# Add all traits to the use statement
sed -i.bak "s/use HasFactory, Notifiable;/use HasApiTokens, HasFactory, HasRoles, HasContextualPermissions, Notifiable;/" "$USER_MODEL"

rm -f "$USER_MODEL.bak"
ok "Sanctum installed + User model patched with HasApiTokens, HasRoles, HasContextualPermissions"

# ─── Install Filament ───────────────────────────────────────────────────────

step "Installing Filament v4 (admin panel)"

composer require filament/filament:"^4.0" --with-all-dependencies --no-interaction --no-progress --quiet

# Create the admin panel provider
mkdir -p "$APP_DIR/app/Providers/Filament"
cat > "$APP_DIR/app/Providers/Filament/AdminPanelProvider.php" << 'PANEL_PHP'
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ProPhoto\Gallery\Filament\GalleryPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => \Filament\Support\Colors\Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->plugins([
                GalleryPlugin::make(),
            ])
            ->databaseNotifications()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
PANEL_PHP

ok "Filament v4 installed + AdminPanelProvider created with GalleryPlugin + databaseNotifications"

# Register the panel provider in bootstrap/providers.php
PROVIDERS_FILE="$APP_DIR/bootstrap/providers.php"
sed -i.bak "s/return \[/return [\n    App\\\\Providers\\\\Filament\\\\AdminPanelProvider::class,/" "$PROVIDERS_FILE"
rm -f "$PROVIDERS_FILE.bak"
ok "AdminPanelProvider registered in bootstrap/providers.php"

# Create Filament resource/page/widget directories so discovery doesn't warn
mkdir -p "$APP_DIR/app/Filament/Resources"
mkdir -p "$APP_DIR/app/Filament/Pages"
mkdir -p "$APP_DIR/app/Filament/Widgets"

step "Requiring all packages (symlinked, no copy)"

if [[ ${#REQUIRE_ARGS[@]} -gt 0 ]]; then
  # --with-all-dependencies allows composer to resolve transitive deps freely.
  # Without it, composer refuses to install if any package constrains a dep
  # that the Laravel skeleton already locked to a different version.
  if ! composer require "${REQUIRE_ARGS[@]}" \
      --with-all-dependencies \
      --no-interaction \
      --no-progress; then
    die "composer require failed — see output above for details"
  fi
  ok "All packages installed"
else
  warn "No packages found to require — check your directory layout"
fi

# ─── Publish + run migrations ────────────────────────────────────────────────

step "Publishing and running migrations from all packages"

# Spatie laravel-permission ships its own migrations (permissions, roles,
# model_has_permissions, etc.) inside the vendor package. Unlike our own
# packages which use loadMigrationsFrom() in their ServiceProviders, Spatie
# requires an explicit vendor:publish to copy migrations into database/migrations/
# before artisan migrate can see them.
#
# Laravel 12 skeleton already includes personal_access_tokens — we do NOT
# publish Sanctum migrations separately (would create a duplicate and crash).
php artisan vendor:publish \
  --provider="Spatie\Permission\PermissionServiceProvider" \
  --force --no-interaction >/dev/null 2>&1
ok "Spatie permission migrations published"

# Filament databaseNotifications() requires Laravel's notifications table.
# This creates the migration in database/migrations/ so artisan migrate picks it up.
php artisan make:notifications-table --no-interaction >/dev/null 2>&1 || true
ok "Notifications table migration created"

php artisan migrate --force --no-interaction

ok "Migrations complete"

# ─── Testing infrastructure ─────────────────────────────────────────────────

step "Installing Orchestra Testbench for package integration tests"

composer require orchestra/testbench:"^10.0" \
  --dev \
  --with-all-dependencies \
  --no-interaction \
  --no-progress --quiet

ok "Orchestra Testbench installed"

# Register package test suites in phpunit.xml so `php artisan test --testsuite=AI`
# (and future package suites) work from the sandbox app with the full framework.
# Uses PHP to safely manipulate XML without fragile sed replacements.
PHPUNIT_XML="$APP_DIR/phpunit.xml"
php -r "
\$xml = new DOMDocument();
\$xml->preserveWhiteSpace = false;
\$xml->formatOutput = true;
\$xml->load('$PHPUNIT_XML');

\$testsuites = \$xml->getElementsByTagName('testsuites')->item(0);

// Add AI package test suite
\$suite = \$xml->createElement('testsuite');
\$suite->setAttribute('name', 'AI');
\$dir = \$xml->createElement('directory', '../prophoto-ai/tests');
\$suite->appendChild(\$dir);
\$testsuites->appendChild(\$suite);

\$xml->save('$PHPUNIT_XML');
"

ok "Package test suites registered in phpunit.xml"

# ─── Queue + Cache config ────────────────────────────────────────────────────

step "Configuring queue and cache for local use"

# Use sync queue so jobs fire immediately without needing a worker
sed -i.bak "s/QUEUE_CONNECTION=database/QUEUE_CONNECTION=sync/" "$ENV_FILE"
# Also handle if it's set to redis or anything else
grep -q "^QUEUE_CONNECTION" "$ENV_FILE" \
  || echo "QUEUE_CONNECTION=sync" >> "$ENV_FILE"

# Use file cache driver
sed -i.bak "s/CACHE_STORE=database/CACHE_STORE=file/" "$ENV_FILE"
grep -q "^CACHE_STORE" "$ENV_FILE" \
  || echo "CACHE_STORE=file" >> "$ENV_FILE"

rm -f "$ENV_FILE.bak"

ok "Queue: sync | Cache: file"

# ─── Seed dummy data ─────────────────────────────────────────────────────────

step "Seeding dummy data (studio, user, sample upload session)"

# Copy the seeder script into the app then run it
SEEDER_SRC="$SCRIPT_DIR/sandbox-seeder.php"
SEEDER_DEST="$APP_DIR/database/seeders/SandboxSeeder.php"

if [[ -f "$SEEDER_SRC" ]]; then
  cp "$SEEDER_SRC" "$SEEDER_DEST"
  php artisan db:seed --class=SandboxSeeder --force --no-interaction
  ok "Dummy data seeded"
else
  warn "sandbox-seeder.php not found next to create-sandbox.sh — skipping seed"
  warn "Run ./seed-sandbox.sh from inside $APP_DIR to seed later"
fi

# ─── Generate app key ────────────────────────────────────────────────────────

php artisan key:generate --force --no-interaction >/dev/null
ok "App key generated"

# ─── Storage link ────────────────────────────────────────────────────────────

php artisan storage:link --force --no-interaction >/dev/null 2>&1 || true
ok "Storage link created"

# ─── Publish Filament assets ────────────────────────────────────────────────

step "Publishing Filament assets (CSS/JS)"

php artisan filament:assets --no-interaction >/dev/null 2>&1
ok "Filament assets published"

# ─── Print summary ───────────────────────────────────────────────────────────

echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ProPhoto Sandbox Ready!${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════${NC}"
echo ""
echo -e "  ${CYAN}Location:${NC}  $APP_DIR"
echo -e "  ${CYAN}URL:${NC}       http://$APP_NAME.test  (add to Herd if needed)"
echo -e "  ${CYAN}Database:${NC}  $APP_DIR/database/database.sqlite"
echo -e "  ${CYAN}Queue:${NC}     sync (jobs fire immediately)"
echo ""
echo -e "  ${CYAN}To open in Herd:${NC}"
echo -e "    herd link $APP_NAME   (from inside $APP_DIR)"
echo ""
echo -e "  ${CYAN}To tear down:${NC}"
echo -e "    rm -rf $APP_DIR"
echo ""
echo -e "  ${CYAN}To re-seed:${NC}"
echo -e "    cd $APP_DIR && php artisan db:seed --class=SandboxSeeder --force"
echo ""
echo -e "  ${CYAN}To run package tests:${NC}"
echo -e "    cd $APP_DIR && php artisan test --testsuite=AI"
echo ""
echo -e "  ${YELLOW}Note: packages are symlinked — edits to ../prophoto-*${NC}"
echo -e "  ${YELLOW}are reflected immediately, no re-install needed.${NC}"
echo ""
