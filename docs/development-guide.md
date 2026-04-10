# ProPhoto Development Guide

Setup, testing, and deployment guide for the ProPhoto monorepo.

## Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer 2.0+
- Laravel 11 (included via packages)
- MySQL 8.0+ or PostgreSQL 14+
- Redis (for queue/cache)
- Git

### Installation

1. **Clone and setup dependencies**
```bash
cd /path/to/prophoto
composer install
```

2. **Install all packages**
Each package is a Laravel package with its own composer.json:
```bash
# Packages are already in source tree, no separate installation needed
# All dependencies resolved via root composer.json
```

3. **Setup database**
```bash
# Create migrations from all packages
php artisan migrate

# Seeds (if available)
php artisan db:seed
```

4. **Cache/Config**
```bash
php artisan config:cache
php artisan route:cache
```

---

## Project Structure

### Directory Organization
```
prophoto/
├── prophoto-{name}/                  # Each package
│   ├── src/
│   │   ├── ServiceProvider.php
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Repositories/
│   │   ├── Listeners/
│   │   └── ...
│   ├── database/migrations/
│   ├── tests/
│   └── composer.json
│
├── docs/                             # Documentation (this folder)
├── .claude/                          # Claude skills & config
└── storage/                          # Laravel storage (app, logs)
```

### Configuration Files Per Package

Each package has a config file published to `/config/{package}.php`:

```bash
# Publish specific package configs
php artisan vendor:publish --provider="ProPhoto\Assets\AssetServiceProvider"

# All configs
php artisan vendor:publish --tag="prophoto-{package}-config"
```

---

## Testing

### Test Framework Setup

**PHPUnit** (prophoto-assets, contracts, ingest, intelligence)
```bash
vendor/bin/phpunit prophoto-assets/tests
vendor/bin/phpunit prophoto-contracts/tests
```

**Pest** (prophoto-booking, invoicing, notifications)
```bash
vendor/bin/pest prophoto-booking/tests
```

### Running Tests

**All Tests**
```bash
./vendor/bin/phpunit
```

**Specific Package**
```bash
./vendor/bin/phpunit prophoto-assets/tests
./vendor/bin/phpunit prophoto-intelligence/tests
```

**Specific Test File**
```bash
./vendor/bin/phpunit prophoto-assets/tests/Unit/AssetRepositoryTest.php
```

**Watch Mode** (if using Pest)
```bash
./vendor/bin/pest --watch
```

### Test Coverage

Currently tested packages:
- ✅ prophoto-assets (9 tests)
- ✅ prophoto-contracts (8 tests)
- ✅ prophoto-ingest (9 tests)
- ✅ prophoto-intelligence (13 tests)
- ❌ prophoto-access, ai, gallery, interactions, invoicing, notifications, booking (no tests)

**Generate Coverage Report**
```bash
./vendor/bin/phpunit --coverage-html=coverage

# Open coverage/index.html
```

---

## Database Migrations

### Running Migrations

All migrations are loaded from each package:
```bash
php artisan migrate
```

**Specific Package Migrations**
Each package's migrations are published to the root database/migrations folder.

### Creating Migrations

For a new table in prophoto-assets:
```bash
php artisan make:migration create_asset_backups_table \
  --path=prophoto-assets/database/migrations
```

### Rollback

```bash
# Rollback last batch
php artisan migrate:rollback

# Rollback all
php artisan migrate:reset

# Refresh (drop and recreate)
php artisan migrate:refresh
```

---

## Service Providers & Bootstrapping

### Package Service Providers

Each package has a ServiceProvider that:
1. Registers service bindings
2. Loads migrations
3. Publishes configs
4. Registers event listeners
5. Registers policies/gates

**Location:** `prophoto-{name}/src/{Package}ServiceProvider.php`

**Example (assets):**
```php
class AssetServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config/assets.php', 'prophoto-assets');
        
        // Bind contracts to implementations
        $this->app->singleton(AssetRepositoryContract::class, EloquentAssetRepository::class);
        // ... more bindings
    }
    
    public function boot(): void {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        // Register event listeners
        Event::listen(SessionAssociationResolved::class, HandleSessionAssociationResolved::class);
        
        // Publish configs
        $this->publishes([
            __DIR__ . '/../config/assets.php' => config_path('prophoto-assets.php'),
        ]);
    }
}
```

### Enabling/Disabling Packages

Package service providers must be registered in your main Laravel app's `config/app.php`:

```php
'providers' => [
    // ...
    ProPhoto\Access\AccessServiceProvider::class,
    ProPhoto\Assets\AssetServiceProvider::class,
    ProPhoto\Gallery\GalleryServiceProvider::class,
    ProPhoto\Booking\BookingServiceProvider::class,
    // ... etc
],
```

---

## Event-Driven Development

### Listening to Events

Register listeners in ServiceProvider `boot()` method:

```php
use Illuminate\Support\Facades\Event;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use MyApp\Listeners\ProcessAssetIntelligence;

Event::listen(AssetReadyV1::class, ProcessAssetIntelligence::class);
```

### Creating Event Listeners

```php
<?php

namespace MyApp\Listeners;

use ProPhoto\Contracts\Events\Asset\AssetReadyV1;

class ProcessAssetIntelligence {
    public function handle(AssetReadyV1 $event): void {
        // $event->assetId
        // $event->metadata
        // $event->occurredAt
    }
}
```

### Broadcasting Events

For queued execution:

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessAssetIntelligence implements ShouldQueue {
    public function handle(AssetReadyV1 $event): void {
        // Processed in queue
    }
}
```

### Event List (What to Listen For)

See [API Contracts](./api-contracts.md) for complete event definitions:
- `AssetCreated`, `AssetStored`, `AssetMetadataExtracted`, `AssetMetadataNormalized`, `AssetDerivativesGenerated`, `AssetReadyV1`
- `AssetIntelligenceRunStarted`, `AssetIntelligenceGenerated`, `AssetEmbeddingUpdated`
- `SessionMatchProposalCreated`, `SessionAutoAssignmentApplied`, `SessionManualAssignmentApplied`, `SessionAssociationResolved`

---

## Working with Services

### Dependency Injection

Services are injected via constructor:

```php
<?php

namespace App\Http\Controllers;

use ProPhoto\Assets\Services\Assets\AssetCreationService;
use ProPhoto\Contracts\DTOs\IngestRequest;

class AssetController extends Controller {
    public function __construct(
        private AssetCreationService $assetService
    ) {}
    
    public function store(Request $request) {
        $ingestRequest = new IngestRequest(
            uploadBatchId: 'batch-123',
            filename: $request->file('file')->getClientOriginalName(),
            // ... more parameters
        );
        
        return $this->assetService->create($ingestRequest);
    }
}
```

### Resolving from Container

```php
$assetRepo = app(AssetRepositoryContract::class);
$assets = $assetRepo->list($query);
```

---

## Working with Repositories

### Asset Repository

```php
use ProPhoto\Contracts\Contracts\Asset\AssetRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;

$repo = app(AssetRepositoryContract::class);

// Find by ID
$asset = $repo->find(new AssetId('asset-123'));

// List with filters
$assets = $repo->list($query);

// Browse by path
$result = $repo->browse('2024/studio-123');
```

### Session Matching Repository

```php
use ProPhoto\Ingest\Repositories\SessionAssignmentRepository;

$repo = app(SessionAssignmentRepository::class);

// Query assignments
$assignments = $repo->where('session_id', $sessionId)->get();
```

---

## Database Queries

### Using Models

```php
use ProPhoto\Assets\Models\Asset;

// Find by ID
$asset = Asset::find($id);

// Query
$assets = Asset::where('studio_id', $studioId)
              ->where('status', 'ready')
              ->get();

// With relationships
$asset = Asset::with('derivatives', 'metadata')->find($id);
```

### Using Repositories

```php
use ProPhoto\Contracts\Contracts\Asset\AssetRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetQuery;

$repo = app(AssetRepositoryContract::class);

$query = new AssetQuery(
    studioId: $studioId,
    organizationId: $orgId,
    type: 'image',
    status: 'ready'
);

$assets = $repo->list($query);
```

---

## Deployment

### Pre-Deployment Checklist

- [ ] All tests passing: `./vendor/bin/phpunit`
- [ ] Database migrations ready: `php artisan migrate --pretend`
- [ ] Config cached: `php artisan config:cache`
- [ ] Routes cached: `php artisan route:cache`
- [ ] Assets compiled (if frontend): `npm run build`

### Deployment Steps

```bash
# 1. Pull latest code
git pull origin main

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php artisan migrate --force

# 4. Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# 5. Warm cache
php artisan config:cache
php artisan route:cache

# 6. Restart queue workers (if applicable)
# This depends on your deployment system
```

### Environment Variables

Key .env variables per package:

```env
# Asset storage
ASSET_DISK=s3
AWS_BUCKET=prophoto-assets
AWS_REGION=us-east-1

# Queue/Jobs
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

# Intelligence generation
INTELLIGENCE_ENABLED=true
INTELLIGENCE_QUEUE=default

# AI features
AI_ENABLED=true
AI_MODEL=openai
OPENAI_API_KEY=sk-...

# Notifications
MAIL_DRIVER=smtp
MAIL_FROM_ADDRESS=noreply@prophoto.com

# Database (per environment)
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=prophoto
DB_USERNAME=root
DB_PASSWORD=secret
```

---

## Performance Optimization

### Database
- ✅ Indexes on studio_id, organization_id, status fields
- ✅ Use pagination for large queries
- ✅ Eager load relationships: `with(['derivatives', 'metadata'])`

### Caching
```php
// Cache asset metadata
$asset = Cache::remember("asset.{$id}.metadata", 3600, function () {
    return Asset::with('normalizedMetadata')->find($id);
});
```

### Queue Processing
Long-running tasks should be queued:
- Asset metadata extraction
- Intelligence generation
- Email notifications
- Invoice PDF generation

```bash
# Start queue worker
php artisan queue:work

# Specific queue
php artisan queue:work --queue=intelligence
```

### Asset Storage
- Store large files in S3/distributed storage
- Generate derivatives on-demand or async
- Use signed URLs for temporary access

---

## Debugging

### Enable Debug Mode
```env
APP_DEBUG=true
```

### Log Levels
```env
LOG_LEVEL=debug  # or info, warning, error
```

### Query Logging
```php
use Illuminate\Support\Facades\DB;

DB::listen(function ($query) {
    Log::debug($query->sql, $query->bindings);
});
```

### Event Debugging
```php
Event::listen('*', function ($eventName, $data) {
    Log::debug("Event: $eventName", $data);
});
```

### Queue Job Debugging
```bash
# See failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

---

## Common Tasks

### Add New Model to Package

1. Create migration in `prophoto-{name}/database/migrations/`
2. Create model in `prophoto-{name}/src/Models/`
3. Add relationships to related models
4. Create test in `prophoto-{name}/tests/`

### Add New Service

1. Create service class in `prophoto-{name}/src/Services/`
2. Register binding in ServiceProvider if it's a contract
3. Inject into controller/service that needs it
4. Add tests

### Add Event Listener

1. Create listener class: `prophoto-{name}/src/Listeners/MyListener.php`
2. Register in ServiceProvider boot method
3. Test the listener

### Create API Endpoint

1. Create controller in `prophoto-{name}/src/Http/Controllers/`
2. Add route in `prophoto-{name}/routes/api.php`
3. Create request validation class (optional)
4. Add tests

---

## Related Documentation

- [Project Overview](./project-overview.md) - Architecture
- [Source Tree Analysis](./source-tree-analysis.md) - File locations
- [Component Inventory](./component-inventory.md) - Services and models
- [API Contracts](./api-contracts.md) - Event definitions
- [Data Models](./data-models.md) - Database schema
