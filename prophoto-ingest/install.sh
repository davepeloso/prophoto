#!/bin/bash

# prophoto Ingest Package - Installation Script
# This script installs the package into a Laravel project
# 
# Usage: ./install.sh [--move-testing]
#   --move-testing: Copy testing assets to Laravel project root

set -e

# Default values
MOVE_TESTING=false

# Parse command line arguments
for arg in "$@"; do
    case $arg in
        --move-testing)
            MOVE_TESTING=true
            ;;
        --help)
            echo "prophoto Ingest Package - Installation Script"
            echo ""
            echo "Usage: ./install.sh [--move-testing]"
            echo ""
            echo "Options:"
            echo "  --move-testing   Copy testing assets to Laravel project root"
            echo "  --help          Show this help message"
            echo ""
            exit 0
            ;;
        *)
            # Unknown argument - ignore for backwards compatibility
            ;;
    esac
done

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   prophoto Ingest Package - Installer"
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

# Check for required PHP extensions
echo "Checking PHP extensions..."
php -m | grep -q exif && echo "✓ exif extension available" || echo "⚠️  Warning: exif extension not found (EXIF data extraction will fail)"
php -m | grep -q gd && echo "✓ GD extension available" || echo "⚠️  Warning: GD extension not found"
php -m | grep -q imagick && echo "✓ Imagick extension available" || echo "ℹ️  Info: Imagick not found (will use GD)"
echo ""

# Require prophoto/ingest package
echo "Require prophoto/ingest package..."
composer require prophoto/ingest:@dev
echo "✓ prophoto/ingest package installed"
echo ""

# Publish configuration + assets + source (root tag)
echo "Publishing Ingest root resources..."
php artisan vendor:publish --tag=ingest --force
echo "✓ Configuration, assets, and source files published"
echo ""

# Optionally publish migrations (explicit opt-in)
echo "Publishing migrations (opt-in)..."
php artisan vendor:publish --tag=ingest-migrations --force
echo "✓ Migrations published"
echo ""

# Run migrations
echo "Running migrations..."
php artisan migrate
echo "✓ Database tables created"
echo ""

# Create storage directories
echo "Creating storage directories..."
mkdir -p storage/app/public/ingest-temp/thumbs
mkdir -p storage/app/public/ingest-temp/previews
mkdir -p storage/app/images
echo "✓ Storage directories created"
echo ""

# Create symbolic link
if [ ! -L "public/storage" ]; then
    echo "Creating storage symlink..."
    php artisan storage:link
    echo "✓ Storage symlink created"
else
    echo "✓ Storage symlink already exists"
fi
echo ""

# Move testing assets if requested
if [ "$MOVE_TESTING" = true ]; then
    echo "Moving testing assets to project root..."
    TESTING_SOURCE="vendor/prophoto/ingest/docs/ingest/testing"
    
    if [ -d "$TESTING_SOURCE" ]; then
        # Copy all testing assets to root
        cp -r "$TESTING_SOURCE"/* ./
        echo "✓ Testing assets copied to project root"
        echo ""
        echo "  Copied files:"
        ls -la "$TESTING_SOURCE" | grep -v "^total" | grep -v "^d" | awk '{print "    • " $9}'
        echo ""
    else
        echo "⚠️  Warning: Testing directory not found at $TESTING_SOURCE"
        echo ""
    fi
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   Installation Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📋 Next Steps:"
echo ""
echo "1. Configure your queue driver in .env:"
echo "   QUEUE_CONNECTION=database  (or redis/sync)"
echo ""
echo "2. If using database queue, run:"
echo "   php artisan queue:table"
echo "   php artisan migrate"
echo ""
echo "3. Start the queue worker:"
echo "   php artisan queue:work"
echo ""
echo "4. (Optional) Review config/ingest.php for customization"
echo ""
echo "5. Access the ingest panel at: /ingest"
echo "   (Requires authentication by default)"
echo ""

if [ "$MOVE_TESTING" = true ]; then
    echo "🔧 Testing assets available:"
    echo ""
    echo "• debug-tools.php - Debug utilities for Tinker"
    echo "• test-extract-fast.php - Fast extraction testing"
    echo "• *.md files - Documentation for testing and debugging"
    echo ""
    echo "Usage in Tinker:"
    echo "  include_once 'debug-tools.php';"
    echo "  \$debug = new IngestDebugger();"
    echo "  \$debug->debugExifTool();"
    echo ""
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""