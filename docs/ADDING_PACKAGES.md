# Adding New Packages to ProPhoto

This guide explains how to add new packages to the ProPhoto workspace as the system grows.

## The Process (5 Steps)

Before Step 1, confirm package scope complies with [`RULES.md`](../RULES.md).

### 1. Define Contracts First

Before writing implementation code, add interfaces/DTOs/enums to `prophoto-contracts`:

```bash
# Example: Adding booking functionality
prophoto-contracts/src/
  Contracts/Booking/
    BookingEngineContract.php
    CalendarSyncContract.php
  DTOs/
    BookingRequest.php
    BookingDetails.php
  Enums/
    BookingStatus.php  # pending, confirmed, cancelled, etc.
  Events/
    BookingConfirmed.php
    BookingCancelled.php
```

**Why contracts first?** This forces you to think about boundaries before implementation.

### 2. Create the Package Directory

```bash
mkdir -p prophoto-booking/src
cd prophoto-booking
```

Create `composer.json`:

```json
{
    "name": "prophoto/booking",
    "description": "Booking workflow and calendar integration for ProPhoto",
    "type": "library",
    "require": {
        "php": "^8.2",
        "illuminate/support": "^11.0|^12.0",
        "prophoto/contracts": "@dev"
    },
    "autoload": {
        "psr-4": {
            "ProPhoto\\Booking\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "ProPhoto\\Booking\\BookingServiceProvider"
            ]
        }
    }
}
```

Add `.gitignore`:

```
vendor/
node_modules/
.phpunit.result.cache
.DS_Store
```

### 3. Implement Contracts

Create your service provider and bind implementations:

```php
// prophoto-booking/src/BookingServiceProvider.php
namespace ProPhoto\Booking;

use Illuminate\Support\ServiceProvider;
use ProPhoto\Contracts\Booking\BookingEngineContract;
use ProPhoto\Booking\Services\BookingEngine;

class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind contract to implementation
        $this->app->bind(
            BookingEngineContract::class,
            BookingEngine::class
        );
    }

    public function boot(): void
    {
        // Load routes, migrations, etc.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/booking.php');
    }
}
```

### 4. Add to Sandbox

```bash
cd sandbox
composer require prophoto/booking:@dev
```

The sandbox will automatically symlink it because of the path repository pattern.

### 5. Update Documentation

Add the package to `SYSTEM.md`:

```markdown
### prophoto-booking
**Purpose**: Session booking workflow, calendar integration, conflict detection
**Provides**: BookingEngine, Google Calendar sync, booking state machine
**Depends on**: prophoto-contracts, prophoto-notifications
**Events emitted**: BookingConfirmed, BookingCancelled, BookingRescheduled
**Database tables**: bookings, booking_messages, calendar_sync_tokens
```

## Package Organization Strategy

Based on your roadmap, here's the recommended package structure:

### Core Infrastructure
- `prophoto-contracts` ⭐ (already created)
- `prophoto-tenancy` - Studio context, org context, impersonation
- `prophoto-permissions` - Contextual permissions, policy matrix, invitations
- `prophoto-settings` - Feature flags, studio/org settings, typed accessors
- `prophoto-audit` - Activity logging, event trails (used by all packages)

### Image Pipeline
- `prophoto-ingest` ✅ (already exists)
- `prophoto-storage` - ImageKit abstraction, signed URLs, path conventions
- `prophoto-galleries` ✅ (already exists)
- `prophoto-downloads` - Bulk download system, ZIP generation, progress tracking

### Interactions
- `prophoto-interactions` - Ratings, approvals, comments, edit requests
- `prophoto-notifications` - Email templates, preferences, delivery logs

### AI Features
- `prophoto-ai` - Model training, generation requests, rate limiting, cost tracking

### Business Operations
- `prophoto-booking` - Booking workflow engine, calendar integration
- `prophoto-invoicing` - Invoice generation, line items, tax, PDF rendering
- `prophoto-payments` - Stripe integration, webhooks, reconciliation

### Security
- `prophoto-security` - Magic links, rate limiting, abuse controls

### Existing
- `prophoto-access` ✅ (already exists)
- `prophoto-debug` ✅ (already exists)

## What Gets Shared vs Package-Specific

### Shared in prophoto-contracts
- ✅ Interfaces (service contracts)
- ✅ DTOs (data transfer objects)
- ✅ Enums (statuses, types, abilities)
- ✅ Events (integration events)
- ✅ Exceptions (domain exceptions)

### Package-Specific (NOT in contracts)
- ❌ Eloquent models
- ❌ Migrations
- ❌ Controllers
- ❌ Routes
- ❌ Views
- ❌ Service providers (except the provider itself)
- ❌ Implementation logic
- ❌ Database queries

## Testing Strategy

Each package should have:

```
prophoto-booking/
  tests/
    Unit/
      BookingEngineTest.php
    Feature/
      BookingWorkflowTest.php
  phpunit.xml
```

Run all tests via:

```bash
./scripts/prophoto test
```

The master CLI automatically discovers and runs tests from all packages.

## Integration via Events (Loose Coupling)

Packages should integrate via events, NOT direct calls:

**Good** (loose coupling):
```php
// In prophoto-booking
event(new BookingConfirmed($booking));

// In prophoto-notifications (listener)
class SendBookingConfirmationEmail
{
    public function handle(BookingConfirmed $event) { ... }
}
```

**Bad** (tight coupling):
```php
// In prophoto-booking
app(NotificationService::class)->sendEmail(...);  // ❌ Direct dependency
```

## Dependency Rules

```
All packages → prophoto-contracts (stable core)
prophoto-contracts → NOTHING (zero dependencies)
```

**Valid dependencies**:
- prophoto-booking depends on prophoto-contracts ✅
- prophoto-booking depends on prophoto-notifications ✅
- prophoto-ai depends on prophoto-audit ✅

**Invalid dependencies**:
- prophoto-contracts depends on prophoto-booking ❌
- Circular: prophoto-booking ↔ prophoto-invoicing ❌

## CLI Extensions (Optional)

You CAN add package-specific commands to the master CLI:

```php
// In prophoto.php, add new menu items:
$actions = [
    'Daily Refresh',
    'Full Rebuild',
    'Run Tests',
    'Doctor',
    'Sandbox → Fresh',
    'Sandbox → Reset',
    'AI → Train Model',        // New
    'Booking → Sync Calendar',  // New
    'Invoice → Generate PDFs',  // New
    'Exit',
];
```

Or use traditional Laravel artisan commands:

```php
// prophoto-booking/src/Commands/SyncCalendarCommand.php
php artisan booking:sync-calendar
```

Both approaches work. Master CLI is for workflow automation, artisan is for specific tasks.

## When to Create a New Package vs Extend Existing

**Create NEW package when**:
- It's a distinct domain (booking, invoicing, AI)
- It has its own database tables
- It can be developed/tested independently
- It might be optional (feature-flagged)

**Extend EXISTING package when**:
- It's a minor enhancement to existing functionality
- It shares the same database tables
- It's tightly coupled to existing logic

## Example: Adding prophoto-tenancy

Let's walk through adding the tenancy package:

### Step 1: Add contracts

```bash
# prophoto-contracts/src/Contracts/Tenancy/StudioContextContract.php
<?php

namespace ProPhoto\Contracts\Tenancy;

interface StudioContextContract
{
    public function current(): ?Studio;
    public function setStudio(Studio $studio): void;
    public function forStudio(Studio $studio, callable $callback): mixed;
}

# prophoto-contracts/src/DTOs/Studio.php
<?php

namespace ProPhoto\Contracts\DTOs;

readonly class Studio
{
    public function __construct(
        public int $id,
        public string $name,
        public string $subdomain,
    ) {}
}
```

### Step 2: Create package

```bash
mkdir prophoto-tenancy
cd prophoto-tenancy
# Create composer.json, src/, tests/, etc.
```

### Step 3: Implement

```php
// prophoto-tenancy/src/Services/StudioContext.php
namespace ProPhoto\Tenancy\Services;

use ProPhoto\Contracts\Tenancy\StudioContextContract;
use ProPhoto\Contracts\DTOs\Studio;

class StudioContext implements StudioContextContract
{
    private ?Studio $currentStudio = null;

    public function current(): ?Studio
    {
        return $this->currentStudio;
    }

    public function setStudio(Studio $studio): void
    {
        $this->currentStudio = $studio;
    }

    public function forStudio(Studio $studio, callable $callback): mixed
    {
        $previous = $this->currentStudio;
        $this->currentStudio = $studio;

        try {
            return $callback();
        } finally {
            $this->currentStudio = $previous;
        }
    }
}

// prophoto-tenancy/src/TenancyServiceProvider.php
namespace ProPhoto\Tenancy;

use Illuminate\Support\ServiceProvider;
use ProPhoto\Contracts\Tenancy\StudioContextContract;
use ProPhoto\Tenancy\Services\StudioContext;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StudioContextContract::class, StudioContext::class);
    }
}
```

### Step 4: Add to sandbox

```bash
cd sandbox
composer require prophoto/tenancy:@dev
# Automatically symlinked!
```

### Step 5: Use in other packages

```php
// In prophoto-galleries or any other package
namespace ProPhoto\Galleries\Http\Controllers;

use ProPhoto\Contracts\Tenancy\StudioContextContract;

class GalleryController
{
    public function __construct(
        private StudioContextContract $studioContext
    ) {}

    public function index()
    {
        $studio = $this->studioContext->current();
        // ...
    }
}
```

## Summary

**What you DO need to do**:
1. ✅ Add contracts/DTOs/enums to prophoto-contracts
2. ✅ Create package directory with composer.json
3. ✅ Implement contracts in the package
4. ✅ Add package to sandbox composer.json
5. ✅ Update SYSTEM.md documentation

**What you DON'T need to do**:
1. ❌ Manually register packages anywhere
2. ❌ Update a central service provider
3. ❌ Modify the master CLI (it auto-discovers tests)
4. ❌ Configure symlinks (automatic via path repositories)

The architecture is **decentralized and self-registering**. Each package announces itself via Laravel's auto-discovery mechanism.

As you add the 20+ components in your roadmap, the process stays the same. The system scales horizontally without bottlenecks.
