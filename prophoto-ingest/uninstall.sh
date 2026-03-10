#!/bin/bash

# prophoto Ingest Package - Uninstall Script
# This script uninstalls the package from a Laravel project

set -e

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   prophoto Ingest Package - Uninstaller"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Find Laravel project root (look for artisan file)
LARAVEL_ROOT=$(pwd)
while [ "$LARAVEL_ROOT" != "/" ] && [ ! -f "$LARAVEL_ROOT/artisan" ]; do
    LARAVEL_ROOT=$(dirname "$LARAVEL_ROOT")
done

if [ ! -f "$LARAVEL_ROOT/artisan" ]; then
    echo "❌ Error: Laravel project not found"
    echo "   Please run this script from within a Laravel project or its subdirectories"
    exit 1
fi

echo "✓ Laravel project detected at: $LARAVEL_ROOT"
echo ""

# Change to Laravel project root
cd "$LARAVEL_ROOT"

# Remove published configuration
if [ -f "config/ingest.php" ]; then
    echo "Removing config/ingest.php..."
    rm -f config/ingest.php
    echo "✓ config removed"
else
    echo "⚠️  config/ingest.php not found"
fi

if [ -f "config/exiftool.php" ]; then
    echo "Removing config/exiftool.php..."
    rm -f config/exiftool.php
    echo "✓ config removed"
else
    echo "⚠️  config/exiftool.php not found"
fi
echo ""

# Remove published assets
if [ -d "public/vendor/ingest" ]; then
    echo "Removing public/vendor/ingest..."
    rm -rf public/vendor/ingest
    echo "✓ Assets removed"
else
    echo "⚠️  public/vendor/ingest not found"
fi
echo ""

# Remove published frontend source
if [ -d "resources/js/vendor/ingest" ]; then
    echo "Removing resources/js/vendor/ingest..."
    rm -rf resources/js/vendor/ingest
    echo "✓ Source removed"
else
    echo "⚠️  resources/js/vendor/ingest not found"
fi
echo ""

# Optional: remove storage directories
for dir in storage/app/public/ingest-temp/thumbs storage/app/public/ingest-temp/previews storage/app/images; do
    if [ -d "$dir" ]; then
        echo "Removing $dir..."
        rm -rf "$dir"
        echo "✓ Directory removed"
    fi
done
echo ""

# Optional: remove migrations (warn first)
echo "⚠️  WARNING: This will remove migrations published by the package!"
echo "Migrations must be removed manually if already run in the database."
echo "Removing migration files..."
MIGRATION_DIR="database/migrations"
for file in "$MIGRATION_DIR"/*_ingest_*.php; do
    if [ -f "$file" ]; then
        echo "Removing $file..."
        rm -f "$file"
    fi
done
echo "✓ Migrations removed (files only, DB tables remain until manually rolled back)"
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   Uninstallation Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "ℹ️  Database tables remain. Use 'php artisan migrate:rollback' if needed."
echo ""
echo "📋 Next Steps (optional):"
echo "1. Clean up any leftover configuration in .env or custom files."
echo "2. Remove any queues, storage, or logs associated with ingest."
echo "3. Confirm database tables are removed if desired."
echo ""
