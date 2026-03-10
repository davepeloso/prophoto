# ProPhoto Development Guide

## Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer 2.x
- Node.js 18 or higher
- ExifTool (for metadata extraction)
- Git

### Initial Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd prophoto
   ```

2. **Run diagnostics**
   ```bash
   ./scripts/prophoto doctor
   ```
   This checks your environment and ensures everything is configured correctly.

3. **Create the sandbox** (if needed)
   ```bash
   ./scripts/prophoto sandbox:fresh
   ```
   This creates a fresh Laravel application configured to use local packages.

4. **Start developing**
   ```bash
   cd sandbox
   composer dev
   ```

## The Master CLI: `./scripts/prophoto`

**There is ONE command to rule them all.** All operational tasks go through the master CLI.

### Interactive Mode
```bash
./scripts/prophoto
```
Presents a menu with all available options.

### Direct Commands

| Command | Purpose | When to Use |
|---------|---------|-------------|
| `prophoto doctor` | System diagnostics | When setting up or troubleshooting |
| `prophoto refresh` | Daily refresh (fast) | After editing PHP or config |
| `prophoto rebuild` | Full rebuild (slow) | After pulling changes or major refactoring |
| `prophoto test` | Run all tests | Before committing |
| `prophoto sandbox:reset` | Reset sandbox | When sandbox is broken but DB is OK |
| `prophoto sandbox:fresh` | Recreate sandbox | When starting fresh or major corruption |

### Dry Run Mode
```bash
./scripts/prophoto --dry-run doctor
```
Shows what would be done without making changes.

## Daily Development Workflow

### Working on a Package

1. **Make changes** in package source
   ```bash
   cd prophoto-ingest/src
   # Edit PHP files
   ```

2. **Build assets** (if you changed frontend)
   ```bash
   cd prophoto-ingest
   npm run build
   ```

3. **Refresh sandbox**
   ```bash
   ./scripts/prophoto refresh
   ```

4. **Test your changes**
   ```bash
   cd sandbox
   php artisan serve
   # or
   composer dev  # starts all services
   ```

### After Pulling Changes
```bash
./scripts/prophoto rebuild
```
This rebuilds all packages and updates the sandbox.

### Before Committing
```bash
./scripts/prophoto test
```
Runs all package tests and sandbox integration tests.

## Package Development

### Creating a New Package

1. **Create package directory**
   ```bash
   mkdir prophoto-{name}
   cd prophoto-{name}
   ```

2. **Initialize composer**
   ```bash
   composer init --name="prophoto/{name}" --type="library"
   ```

3. **Add to workspace**
   - Add path repository to `sandbox/composer.json` (or use wildcard `../prophoto-*`)
   - Require in sandbox: `cd sandbox && composer require prophoto/{name}:@dev`

4. **Create package structure**
   ```bash
   mkdir -p src database/migrations config routes
   touch .gitignore README.md
   ```

5. **Add service provider**
   Create `src/{Name}ServiceProvider.php`

6. **Update .gitignore**
   ```
   vendor/
   node_modules/
   .phpunit.result.cache
   .DS_Store
   .idea/
   *.swp
   *.log
   dist/
   ```

### Package Testing

```bash
cd prophoto-{package}
composer test
```

Or via the master CLI:
```bash
./scripts/prophoto test
```

### Publishing Assets

In your package's service provider:
```php
$this->publishes([
    __DIR__.'/../dist' => public_path('vendor/prophoto-{package}'),
], '{package}-assets');
```

To publish manually:
```bash
cd sandbox
php artisan vendor:publish --tag={package}-assets --force
```

Or use refresh/rebuild:
```bash
./scripts/prophoto refresh
```

## Understanding the Workspace

### Directory Structure
```
prophoto/
├── prophoto-contracts/     # Shared interfaces, DTOs, enums
├── prophoto-ingest/        # Photo ingestion package
├── prophoto-galleries/     # Gallery management package
├── prophoto-access/        # Access control package
├── prophoto-debug/         # Debug utilities package
├── sandbox/                # Disposable Laravel app (symlinks to packages)
├── scripts/                # Master CLI and utilities
│   ├── prophoto           # Bash launcher
│   └── prophoto.php       # Master CLI implementation
├── test-images/            # Sample images for testing
├── SYSTEM.md               # Architecture documentation
├── DEV.md                  # This file
├── DEPENDENCIES.md         # External dependencies
└── CLAUDE.md               # AI agent instructions
```

### The Sandbox

The sandbox is a **real Laravel application** that consumes your packages via Composer path repositories with symlinks.

**Key points**:
- Changes in packages instantly reflect in sandbox (symlinks)
- Can be destroyed and recreated (`prophoto sandbox:fresh`)
- Not committed to git (except `.env.example`)
- Used for development and integration testing

**What's in the sandbox**:
- Laravel 12.x
- Inertia.js for SPAs
- Filament for admin UI
- All prophoto-* packages (symlinked)

### Path Repositories (The Magic)

In `sandbox/composer.json`:
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../prophoto-*",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "prophoto/ingest": "@dev",
    "prophoto/galleries": "@dev",
    "prophoto/access": "@dev"
  }
}
```

This tells Composer:
1. Look in `../prophoto-*` directories for packages
2. Create symlinks (not copies) in `vendor/prophoto/*`
3. Use any branch (`@dev`) not just tagged versions

**Result**: Edit `prophoto-ingest/src/Foo.php` and it's instantly available in sandbox.

## Testing

### Package-Level Tests (Testbench)

Each package has its own tests:
```bash
cd prophoto-ingest
composer test
```

Uses Orchestra Testbench to simulate Laravel without a full app.

### Sandbox Integration Tests

Tests that cross package boundaries:
```bash
cd sandbox
php artisan test
```

### Run All Tests

```bash
./scripts/prophoto test
```

Runs package tests + sandbox tests in sequence.

## Common Tasks

### Clear All Caches
```bash
./scripts/prophoto refresh
```

### Rebuild Everything
```bash
./scripts/prophoto rebuild
```

### Reset Sandbox (Keep DB)
```bash
./scripts/prophoto sandbox:reset
```

### Fresh Sandbox (Nuke Everything)
```bash
./scripts/prophoto sandbox:fresh
```

### Add a Package to Sandbox
```bash
cd sandbox
composer require prophoto/{package}:@dev
```

### Run Migrations
```bash
cd sandbox
php artisan migrate
```

### Rollback Migrations
```bash
cd sandbox
php artisan migrate:rollback
```

### Seed Database
```bash
cd sandbox
php artisan db:seed
```

### View Logs
```bash
cd sandbox
php artisan pail
# or
tail -f sandbox/storage/logs/laravel.log
```

## Troubleshooting

### "Composer can't find my package"

1. Check path repository is in `sandbox/composer.json`
2. Verify package has valid `composer.json`
3. Run `composer dump-autoload` in sandbox

### "Changes not reflecting in sandbox"

1. Check symlink exists: `ls -la sandbox/vendor/prophoto/`
2. Clear caches: `./scripts/prophoto refresh`
3. Rebuild: `./scripts/prophoto rebuild`

### "Assets not loading"

1. Build package assets: `cd prophoto-{package} && npm run build`
2. Publish to sandbox: `./scripts/prophoto refresh`
3. Hard refresh browser (Cmd+Shift+R / Ctrl+F5)

### "Tests failing"

1. Ensure dependencies installed: `composer install` in package
2. Check test database is clean: `php artisan migrate:fresh`
3. Run specific test: `php artisan test --filter=TestName`

### "Sandbox is broken"

```bash
./scripts/prophoto sandbox:reset
```

Or nuke it:
```bash
./scripts/prophoto sandbox:fresh
```

### "Nothing works"

```bash
./scripts/prophoto doctor
```

This runs diagnostics and tells you what's wrong.

## Git Workflow

### Branches

- `main`: Stable, production-ready
- `develop`: Integration branch
- `feature/*`: Feature development
- `fix/*`: Bug fixes

### Commit Messages

Follow conventional commits:
```
feat(ingest): add HEIC support
fix(galleries): resolve permission check bug
docs(system): update architecture diagrams
chore(deps): upgrade Laravel to 12.x
```

### Before Committing

1. Run tests: `./scripts/prophoto test`
2. Run linter: `composer lint` (if configured)
3. Check diagnostics: `./scripts/prophoto doctor`

### Committing Package Changes

Only commit changes in packages, not sandbox:
```bash
git add prophoto-ingest/
git commit -m "feat(ingest): add metadata extraction"
git push
```

## Environment Setup

### Required Tools

Install these before starting:

**macOS**:
```bash
brew install php@8.2 composer node exiftool
```

**Ubuntu/Debian**:
```bash
sudo apt install php8.2 php8.2-cli php8.2-mbstring php8.2-xml \
                 composer nodejs npm libimage-exiftool-perl
```

### IDE Setup

**PHPStorm**:
1. Open workspace root
2. Mark each `prophoto-*/src` as source root
3. Mark `sandbox` as Laravel project root
4. Enable Composer sync
5. Enable Laravel plugin

**VS Code**:
1. Install PHP Intelephense extension
2. Install Laravel Extension Pack
3. Open workspace root
4. Configure Intelephense to index all `prophoto-*` folders

## Performance Tips

### Speed Up Composer

```bash
composer global require hirak/prestissimo
```

### Speed Up Asset Builds

Use `npm ci` instead of `npm install`:
```bash
cd prophoto-ingest
npm ci
```

### Parallel Testing

```bash
php artisan test --parallel
```

## Additional Resources

- **Architecture**: See `SYSTEM.md`
- **Dependencies**: See `DEPENDENCIES.md`
- **API Contracts**: See `prophoto-contracts/README.md`
- **Laravel Docs**: https://laravel.com/docs
- **Inertia Docs**: https://inertiajs.com

## Getting Help

1. Run `./scripts/prophoto doctor` to diagnose issues
2. Check logs: `sandbox/storage/logs/laravel.log`
3. Review package READMEs
4. Check GitHub issues

## Key Principles

1. **One Command**: Use `./scripts/prophoto` for all operations
2. **Clean Packages**: Never commit `vendor/` or `node_modules/` in packages
3. **Disposable Sandbox**: Treat sandbox as temporary—can be destroyed anytime
4. **Contracts First**: Always use interfaces from `prophoto-contracts`
5. **Test Before Commit**: Run `./scripts/prophoto test` before pushing
