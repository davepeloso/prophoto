#!/bin/bash

# ProPhoto Debug Package Uninstaller
# Usage: ./uninstall.sh /path/to/laravel/app [--drop-tables] [--force]

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_DIR="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

show_help() {
    echo "ProPhoto Debug Uninstaller"
    echo ""
    echo "Usage: $0 <app-directory> [options]"
    echo ""
    echo "Arguments:"
    echo "  app-directory    Path to the Laravel application (e.g., /Herd-Profoto/sandbox)"
    echo ""
    echo "Options:"
    echo "  --drop-tables    Also drop the database tables"
    echo "  --force          Skip confirmation prompts"
    echo "  --help           Show this help message"
    echo ""
    echo "Example:"
    echo "  $0 /Users/davepeloso/Herd-Profoto/sandbox --drop-tables --force"
    echo ""
}

# Parse arguments
APP_DIR=""
DROP_TABLES=false
FORCE=false

for arg in "$@"; do
    case $arg in
        --drop-tables)
            DROP_TABLES=true
            ;;
        --force)
            FORCE=true
            ;;
        --help|-h)
            show_help
            exit 0
            ;;
        -*)
            echo -e "${RED}Unknown option: $arg${NC}"
            show_help
            exit 1
            ;;
        *)
            if [ -z "$APP_DIR" ]; then
                APP_DIR="$arg"
            fi
            ;;
    esac
done

# Validate app directory
if [ -z "$APP_DIR" ]; then
    echo -e "${RED}Error: Application directory is required${NC}"
    echo ""
    show_help
    exit 1
fi

if [ ! -d "$APP_DIR" ]; then
    echo -e "${RED}Error: Directory does not exist: $APP_DIR${NC}"
    exit 1
fi

if [ ! -f "$APP_DIR/artisan" ]; then
    echo -e "${RED}Error: Not a Laravel application: $APP_DIR${NC}"
    echo "Missing artisan file"
    exit 1
fi

# Resolve to absolute path
APP_DIR="$(cd "$APP_DIR" && pwd)"

echo -e "${GREEN}ProPhoto Debug Uninstaller${NC}"
echo "========================================"
echo ""
echo "Application: $APP_DIR"
echo "Package:     $PACKAGE_DIR"
echo ""

# Confirmation
if [ "$FORCE" = false ]; then
    echo "This will:"
    echo "  - Remove prophoto/debug from composer.json"
    echo "  - Remove published config and views"
    if [ "$DROP_TABLES" = true ]; then
        echo "  - Drop database tables (debug_ingest_traces, debug_config_snapshots)"
    fi
    echo ""
    read -p "Continue? [y/N] " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Cancelled."
        exit 0
    fi
fi

cd "$APP_DIR"

# Step 1: Run artisan uninstall command if tables should be dropped
if [ "$DROP_TABLES" = true ]; then
    echo ""
    echo -e "${YELLOW}Step 1: Dropping database tables...${NC}"
    php artisan debug:uninstall --drop-tables --force 2>/dev/null || echo "  (skipped - command not available)"
fi

# Step 2: Remove published files
echo ""
echo -e "${YELLOW}Step 2: Removing published files...${NC}"

if [ -f "config/debug.php" ]; then
    rm -f "config/debug.php"
    echo "  Removed: config/debug.php"
else
    echo "  Skipped: config/debug.php (not found)"
fi

if [ -d "resources/views/vendor/debug" ]; then
    rm -rf "resources/views/vendor/debug"
    echo "  Removed: resources/views/vendor/debug/"
else
    echo "  Skipped: resources/views/vendor/debug/ (not found)"
fi

# Step 3: Remove from composer.json
echo ""
echo -e "${YELLOW}Step 3: Removing from composer...${NC}"

# Check if package is installed
if grep -q '"prophoto/debug"' composer.json 2>/dev/null; then
    composer remove prophoto/debug --no-interaction 2>&1 | grep -v "^$" || true
    echo "  Removed: prophoto/debug"
else
    echo "  Skipped: prophoto/debug not in composer.json"
fi

# Step 4: Clear caches
echo ""
echo -e "${YELLOW}Step 4: Clearing caches...${NC}"
php artisan optimize:clear 2>/dev/null || php artisan cache:clear 2>/dev/null || echo "  (cache clear skipped)"

echo ""
echo -e "${GREEN}========================================"
echo "Uninstall complete!"
echo "========================================${NC}"
echo ""
echo "To reinstall, run from $APP_DIR:"
echo "  composer require prophoto/debug:@dev"
echo "  php artisan migrate"
echo "  php artisan vendor:publish --tag=debug-config"
echo ""