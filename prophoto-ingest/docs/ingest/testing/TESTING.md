# Testing & CI/CD Guide

This document covers the testing infrastructure and CI/CD pipeline for the prophoto Ingest package.

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [CI/CD Pipeline](#cicd-pipeline)
3. [Local Development](#local-development)
4. [Test Switchboard System](#test-switchboard-system)
5. [Test Suites](#test-suites)
6. [Configuration Files](#configuration-files)
7. [Writing Tests](#writing-tests)

---

## Quick Start

```bash
# Install dependencies
composer install
npm install

# Run all tests
composer test

# Run code quality checks
composer lint
npm run lint
npm run type-check
```

---

## CI/CD Pipeline

The GitHub Actions workflow (`.github/workflows/tests.yml`) runs automatically on:

- **Push** to `main` or `develop` branches
- **Pull requests** targeting `main`

### Pipeline Jobs

| Job | Depends On | Description |
|-----|------------|-------------|
| **code-quality** | - | Pint, PHPStan, ESLint, TypeScript checks |
| **unit-tests** | code-quality | PHP 8.2 + 8.3 matrix, coverage upload |
| **feature-tests** | code-quality | HTTP endpoint and controller tests |
| **integration-tests** | unit-tests, feature-tests | Full pipeline workflow tests |
| **security-tests** | code-quality | Composer and NPM vulnerability audits |
| **performance-tests** | unit-tests, feature-tests | Benchmark critical operations |

### Pipeline Flow

```
                    ┌─────────────────┐
                    │  code-quality   │
                    └────────┬────────┘
                             │
           ┌─────────────────┼─────────────────┐
           │                 │                 │
           ▼                 ▼                 ▼
    ┌─────────────┐   ┌─────────────┐   ┌─────────────┐
    │ unit-tests  │   │feature-tests│   │security-tests│
    └──────┬──────┘   └──────┬──────┘   └─────────────┘
           │                 │
           └────────┬────────┘
                    │
           ┌────────┴────────┐
           │                 │
           ▼                 ▼
    ┌─────────────┐   ┌─────────────┐
    │ integration │   │ performance │
    └─────────────┘   └─────────────┘
```

---

## Local Development

### PHP Commands

```bash
# Run all tests
composer test

# Run specific test suites
composer test:unit           # Unit tests only
composer test:feature        # Feature tests only
composer test:integration    # Integration tests only

# Fast development mode (unit + feature only)
composer test:fast

# Run with coverage report
composer test:coverage

# Code quality
composer lint                # Check Pint + PHPStan
composer lint:fix            # Auto-fix code style with Pint
```

### Frontend Commands

```bash
# ESLint check
npm run lint

# ESLint auto-fix
npm run lint:fix

# TypeScript type checking
npm run type-check

# Development server
npm run dev

# Production build
npm run build
```

### Running Individual Tests

```bash
# Run a specific test file
./vendor/bin/pest tests/Unit/MetadataExtractorTest.php

# Run tests matching a pattern
./vendor/bin/pest --filter="extracts EXIF"

# Run with verbose output
./vendor/bin/pest -v
```

---

## Test Switchboard System

The switchboard system (`config/ingest-tests.php`) allows you to control which test suites run in different environments.

### Environment Variables

Control individual suites via environment variables:

```bash
# Enable/disable specific suites
TEST_UNIT=true
TEST_FEATURE=true
TEST_INTEGRATION=false
TEST_JOBS=true
TEST_E2E=false
TEST_PERFORMANCE=false
TEST_SECURITY=true
TEST_MIGRATIONS=true
TEST_CODE_QUALITY=true
```

### Test Profiles

Pre-configured combinations for common scenarios:

| Profile | Description | Suites Enabled |
|---------|-------------|----------------|
| `fast-dev` | Local development | unit, feature |
| `pre-commit` | Quick smoke test | code_quality, unit |
| `full-ci` | Complete CI run | All suites |
| `pre-release` | Release candidate | All except performance |

### Using Profiles

```bash
# Set profile via environment
TEST_PROFILE=fast-dev composer test

# Or configure in .env.testing
TEST_PROFILE=pre-commit
```

### Skipping Disabled Suites

In your tests, use the `skipIfDisabled()` helper:

```php
beforeEach(function () {
    skipIfDisabled('integration');
});

test('complete pipeline works', function () {
    // This test only runs if integration suite is enabled
});
```

---

## Test Suites

### Unit Tests (`tests/Unit/`)

Isolated component tests with mocked dependencies.

```bash
composer test:unit
```

**Coverage targets:**
- MetadataExtractor
- IngestProcessor
- Path/filename generation
- GPS coordinate parsing

### Feature Tests (`tests/Feature/`)

HTTP endpoint and controller tests.

```bash
composer test:feature
```

**Coverage targets:**
- Upload endpoints
- Batch operations
- Validation rules
- Response formats

### Integration Tests (`tests/Integration/`)

Full workflow tests with real queue/storage/database.

```bash
composer test:integration
```

**Coverage targets:**
- Complete upload-to-ingest pipeline
- Multi-image batch processing
- Tag synchronization

### Job Tests (`tests/Jobs/`)

Queue job isolation tests.

```bash
./vendor/bin/pest --testsuite=Jobs
```

**Coverage targets:**
- ProcessImageIngestJob
- Retry configuration
- Error handling

### Security Tests (`tests/Security/`)

Security validation tests.

```bash
./vendor/bin/pest --testsuite=Security
```

**Coverage targets:**
- File type validation
- Path traversal prevention
- Authorization checks
- SQL injection prevention

### Performance Tests (`tests/Performance/`)

Benchmark tests with threshold enforcement.

```bash
./vendor/bin/pest --testsuite=Performance
```

**Thresholds** (configurable in `config/ingest-tests.php`):

| Operation | Max Time |
|-----------|----------|
| Upload | 500ms |
| Thumbnail generation | 200ms |
| Metadata extraction | 100ms |
| Ingest job (per image) | 2000ms |

### Migration Tests (`tests/Database/`)

Database schema validation.

```bash
./vendor/bin/pest --testsuite=Database
```

**Coverage targets:**
- Table schema verification
- Rollback testing

---

## Configuration Files

### `.github/workflows/tests.yml`

GitHub Actions workflow definition. Configures:
- PHP version matrix (8.2, 8.3)
- Composer/NPM caching
- Job dependencies
- Coverage uploads

### `config/ingest-tests.php`

Test switchboard configuration:
- Suite toggles
- Test profiles
- Performance thresholds
- Test data paths

### `.env.testing`

CI environment variables:
- Database: SQLite in-memory
- Queue: Sync driver
- Cache/Session: Array driver

### `pint.json`

Laravel Pint code style rules:
- Laravel preset
- Ordered imports
- No unused imports

### `phpstan.neon`

PHPStan static analysis:
- Level 8 (strictest)
- Source path: `src/`

### `.eslintrc.json`

ESLint configuration:
- TypeScript support
- React/React Hooks plugins
- Recommended rulesets

---

## Writing Tests

### Test Structure

Tests use [Pest PHP](https://pestphp.com/) with Orchestra Testbench:

```php
<?php

use Illuminate\Support\Facades\Storage;
use prophoto\Ingest\Services\MetadataExtractor;

beforeEach(function () {
    skipIfDisabled('unit');  // Respect switchboard
    Storage::fake('test-temp');
});

test('extracts EXIF from JPEG', function () {
    $extractor = app(MetadataExtractor::class);
    $metadata = $extractor->extract($fixturePath);

    expect($metadata)
        ->toBeArray()
        ->toHaveKeys(['Make', 'Model', 'DateTimeOriginal']);
});
```

### Test Fixtures

Place test images in `tests/fixtures/images/`:

```
tests/
└── fixtures/
    └── images/
        ├── sample.jpg      # Standard JPEG with EXIF
        ├── large.jpg       # High-resolution image
        ├── no-exif.jpg     # Image without metadata
        └── with-gps.jpg    # Image with GPS coordinates
```

### Custom Expectations

Available in `tests/Pest.php`:

```php
expect($uuid)->toBeValidUuid();
expect($date)->toBeValidExifDate();
```

### Base TestCase

All tests extend `prophoto\Ingest\Tests\TestCase` which provides:

- Orchestra Testbench integration
- Package service provider loading
- SQLite in-memory database
- Test disk configuration
- Automatic cleanup after each test

---

## Troubleshooting

### Tests not running

Check if the suite is enabled:
```bash
php -r "var_dump(env('TEST_UNIT'));"
```

### Coverage not generating

Ensure Xdebug is installed:
```bash
php -m | grep xdebug
```

### CI failing on code quality

Run locally first:
```bash
composer lint
npm run lint
npm run type-check
```

### Performance tests failing

Thresholds may need adjustment for CI environments. Check `config/ingest-tests.php`:
```php
'performance' => [
    'upload_max_time' => 500,  // Increase if needed
],
```
