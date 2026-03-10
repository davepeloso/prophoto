# ProPhoto Access - RBAC & Tenancy

**Role-Based Access Control (RBAC) and multi-tenancy foundation for the ProPhoto modular system.**

---

## 📦 Package Scope

This package provides the **security and tenancy layer** for all ProPhoto packages. It contains:

### Core Identity & Tenancy
- **Studios** - Photography studio accounts (top-level tenant)
- **Organizations** - Client organizations (sub-tenant)
- **Permission Contexts** - Contextual, resource-specific permissions
- **User Extensions** - RBAC columns and traits for User model

### RBAC System
- **Roles**: `studio_user`, `client_user`, `guest_user`, `vendor_user`
- **50+ Permissions** (see `Permissions.php` for full list)
- **Contextual Permissions**: "User can edit THIS gallery" (not just "can edit galleries")
- **Filament Plugin**: Interactive permission matrix, role management UI
- **Middleware**: `CheckContextualPermission` for route protection

### Key Architecture Principles

1. **prophoto-access owns ONLY RBAC/tenancy** - Domain logic lives in vertical packages
2. **Other packages reference this package** for permissions, roles, Studio, Organization models
3. **Policies can live anywhere** - Policies reference models from their respective packages
4. **Event-driven integration** - Packages stay loosely coupled

---

## 🏗️ What This Package Does NOT Contain

The following domains are in **separate vertical packages**:

- **prophoto-gallery** - Gallery, Image, ImageVersion models
- **prophoto-booking** - Session, BookingRequest models
- **prophoto-invoicing** - Invoice, InvoiceItem, CustomFee models
- **prophoto-ai** - AiGeneration, AiGenerationRequest models
- **prophoto-interactions** - ImageInteraction model
- **prophoto-ingest** - StagingImage model
- **prophoto-notifications** - Message model

This package provides the **permissions constants** and **enforcement mechanisms** that those packages use.

---

## 📖 Integration Guide

### Installation

#### Prerequisites

This package requires [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission). Install it first:

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

#### Add to your Laravel app

In your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../prophoto-access",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "prophoto/access": "dev-main"
  }
}
```

```bash
composer require prophoto/access:dev-main
```

#### Run migrations

```bash
php artisan migrate
```

This will create:
- `studios`
- `organizations`, `organization_documents`, `organization_user`
- `permission_contexts`
- Add RBAC columns to `users` table

#### Seed roles and permissions

```bash
php artisan db:seed --class="\ProPhoto\Access\Database\Seeders\RolesAndPermissionsSeeder"
```

---

### User Model Setup

Your User model must use two traits:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use ProPhoto\Access\Traits\HasContextualPermissions;

class User extends Authenticatable
{
    use HasRoles;
    use HasContextualPermissions;

    // ... rest of your User model
}
```

**What these traits provide:**

- **HasRoles** (Spatie): `hasRole()`, `hasPermissionTo()`, `assignRole()`, etc.
- **HasContextualPermissions**: `grantContextualPermissions()`, `hasContextualPermission()`, etc.

---

### Using Permissions in Your Packages

All permission constants live in `ProPhoto\Access\Permissions`:

```php
<?php

namespace ProPhoto\Gallery\Http\Controllers;

use ProPhoto\Access\Permissions;
use ProPhoto\Access\Enums\UserRole;

class GalleryController extends Controller
{
    public function index()
    {
        // Check global permission
        if (!auth()->user()->hasPermissionTo(Permissions::VIEW_GALLERIES)) {
            abort(403);
        }

        // ... your logic
    }

    public function show(Gallery $gallery)
    {
        // Check contextual permission (user can view THIS specific gallery)
        if (!auth()->user()->hasContextualPermission(Permissions::VIEW_GALLERIES, $gallery)) {
            abort(403);
        }

        // ... your logic
    }
}
```

---

### Using Policies

Policies reference models from their own packages:

```php
<?php

namespace ProPhoto\Gallery\Policies;

use ProPhoto\Access\Enums\UserRole;
use ProPhoto\Access\Permissions;
use ProPhoto\Gallery\Models\Gallery; // Model from gallery package

class GalleryPolicy
{
    public function view($user, Gallery $gallery): bool
    {
        // Studio users see all
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return true;
        }

        // Client users see their organization's galleries
        if ($user->hasRole(UserRole::CLIENT_USER->value)) {
            return $user->organization_id === $gallery->organization_id;
        }

        // Guest users see only via contextual permission
        if ($user->hasRole(UserRole::GUEST_USER->value)) {
            return $user->hasContextualPermission(Permissions::VIEW_GALLERIES, $gallery);
        }

        return false;
    }
}
```

**Register the policy** in your package's service provider:

```php
<?php

namespace ProPhoto\Gallery;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Policies\GalleryPolicy;

class GalleryServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Gate::policy(Gallery::class, GalleryPolicy::class);
    }
}
```

---

### Using Middleware

Protect routes with contextual permission checks:

```php
<?php

use ProPhoto\Access\Permissions;

Route::middleware(['auth', 'contextual_permission:' . Permissions::UPLOAD_IMAGES . ',gallery'])
    ->post('/galleries/{gallery}/upload', [GalleryController::class, 'upload']);
```

The middleware resolves the `{gallery}` route parameter and checks the user's contextual permission.

---

### Granting Contextual Permissions (Magic Links)

When a subject (guest user) receives a magic link to access a gallery:

```php
<?php

use ProPhoto\Access\Permissions;
use ProPhoto\Gallery\Models\Gallery;

$gallery = Gallery::find($id);
$guestUser = User::find($guestUserId);

// Grant contextual permissions that expire in 30 days
$guestUser->grantContextualPermissions(
    [
        Permissions::VIEW_GALLERIES,
        Permissions::RATE_IMAGES,
        Permissions::COMMENT_ON_IMAGES,
        Permissions::DOWNLOAD_IMAGES,
    ],
    $gallery,
    now()->addDays(30) // expiration
);
```

**Check expiration:**

```php
if (!$guestUser->hasContextualPermission(Permissions::VIEW_GALLERIES, $gallery)) {
    return redirect()->route('link-expired');
}
```

---

### Filament Integration

This package includes a Filament plugin for managing roles and permissions:

In your Filament panel provider:

```php
<?php

use ProPhoto\Access\Filament\AccessPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            AccessPlugin::make(),
        ]);
}
```

This provides:
- **Permission Matrix** page - Interactive grid for assigning permissions to roles
- **Role Resource** - CRUD for roles
- **Permission Resource** - CRUD for permissions

---

## 🔑 Available Permissions

See `src/Permissions.php` for the complete list. Key categories:

### Gallery Permissions
- `VIEW_GALLERIES`, `CREATE_GALLERIES`, `EDIT_GALLERIES`, `DELETE_GALLERIES`, `ARCHIVE_GALLERY`
- `UPLOAD_IMAGES`, `DELETE_IMAGES`, `DOWNLOAD_IMAGES`
- `APPROVE_IMAGES`, `RATE_IMAGES`, `COMMENT_ON_IMAGES`, `REQUEST_EDITS`

### Booking Permissions
- `VIEW_SESSIONS`, `CREATE_SESSIONS`, `EDIT_SESSIONS`, `DELETE_SESSIONS`, `CANCEL_SESSION`
- `VIEW_BOOKING_REQUESTS`, `CONFIRM_BOOKING`, `DENY_BOOKING`

### AI Permissions
- `TRAIN_AI_MODEL`, `GENERATE_AI_PORTRAITS`, `VIEW_AI_GENERATIONS`

### Invoicing Permissions
- `VIEW_INVOICES`, `CREATE_INVOICES`, `EDIT_INVOICES`, `DELETE_INVOICES`
- `SEND_INVOICES`, `RECORD_PAYMENT`, `DOWNLOAD_INVOICE_PDF`

### Organization Permissions
- `VIEW_ORGANIZATIONS`, `CREATE_ORGANIZATIONS`, `EDIT_ORGANIZATIONS`, `DELETE_ORGANIZATIONS`
- `VIEW_ORGANIZATION_DOCUMENTS`, `UPLOAD_ORGANIZATION_DOCUMENTS`

---

## 📚 Models Provided

### Studio
Top-level tenant. All data belongs to a studio.

```php
$studio = Studio::create(['name' => 'Acme Photography']);
```

### Organization
Sub-tenant. Client organizations within a studio.

```php
$org = Organization::create([
    'studio_id' => $studio->id,
    'name' => 'Tech Corp',
    'type' => 'client',
]);
```

### OrganizationDocument
Documents attached to organizations (contracts, agreements).

```php
$doc = OrganizationDocument::create([
    'organization_id' => $org->id,
    'name' => 'Service Agreement',
    'file_path' => 'path/to/file.pdf',
]);
```

### PermissionContext
Links user permissions to specific resources.

```php
$context = PermissionContext::create([
    'user_id' => $user->id,
    'permission_name' => Permissions::VIEW_GALLERIES,
    'contextable_type' => Gallery::class,
    'contextable_id' => $gallery->id,
    'expires_at' => now()->addDays(30),
]);
```

---

## 🧪 Testing

Run tests with:

```bash
composer test
```

---

## 📄 License

Proprietary - ProPhoto System

---

## 🔗 Related Packages

- **prophoto-contracts** - Shared interfaces/DTOs
- **prophoto-gallery** - Gallery domain
- **prophoto-booking** - Booking domain
- **prophoto-invoicing** - Invoicing domain
- **prophoto-ai** - AI portrait generation domain
- **prophoto-interactions** - Image interactions domain
- **prophoto-ingest** - Image ingestion domain
- **prophoto-notifications** - Messaging domain

---

## 📞 Questions?

See `docs/` for additional documentation:
- `Permissions-Schema - Comprehensive RBAC.md` - Full RBAC schema
- `Subject-Access-Flow.md` - Magic link flow documentation
- `Tables-Overview.md` - Database schema reference
