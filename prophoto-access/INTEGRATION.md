# ProPhoto Access Integration Guide

This guide explains how to integrate the `prophoto/access` package with other ProPhoto packages (e.g., `prophoto/gallery`, `prophoto/invoicing`, etc.).

## Table of Contents

1. [Installation](#installation)
2. [User Model Setup](#user-model-setup)
3. [Using Permissions in Controllers](#using-permissions-in-controllers)
4. [Using Policies](#using-policies)
5. [Using Middleware](#using-middleware)
6. [Using the Facade & Helpers](#using-the-facade--helpers)
7. [Granting Contextual Permissions](#granting-contextual-permissions)
8. [Filament Integration](#filament-integration)
9. [Example: Gallery Package Integration](#example-gallery-package-integration)
10. [Example: Invoice Package Integration](#example-invoice-package-integration)

---

## Installation

### Prerequisites

This package requires [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission). The Spatie migrations must be published and run **before** running this package's migrations.

### 1. Require the package in the project

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../prophoto-access",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

```bash
composer require prophoto/access
```

### 2. Publish and run Spatie Permission migrations

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-migrations" --force
php artisan migrate
```

### 3. Publish ProPhoto Access configuration (optional)

```bash
php artisan vendor:publish --tag=prophoto-access-config
```

### 4. Run ProPhoto Access migrations

```bash
php artisan migrate
```

> **Note:** This package modifies your existing `users` table (adding columns like `studio_id`, `phone`, `avatar_url`, `timezone`, `role`, and soft deletes). It does NOT create a new users table.
>
> **Note:** Photo sessions are stored in the `photo_sessions` table (not `sessions`) to avoid conflicts with Laravel's default session storage table.

### 5. Seed roles and permissions

```bash
php artisan db:seed --class="ProPhoto\\Access\\Database\\Seeders\\RolesAndPermissionsSeeder"
```

Or add to your `DatabaseSeeder.php`:

```php
use ProPhoto\Access\Database\Seeders\RolesAndPermissionsSeeder;

public function run(): void
{
    $this->call([
        RolesAndPermissionsSeeder::class,
        // ... other seeders
    ]);
}
```

---

## User Model Setup

Your `User` model must use both the Spatie and ProPhoto traits:

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

    protected $fillable = [
        'name',
        'email',
        'password',
        'studio_id',
        'organization_id', // For client users
        'phone',
        'avatar_url',
        'timezone',
    ];

    // The trait provides these relationships:
    // - permissionContexts() - HasMany to PermissionContext
    // - organization() - BelongsTo Organization

    // Add studio relationship
    public function studio()
    {
        return $this->belongsTo(\ProPhoto\Access\Models\Studio::class);
    }
}
```

---

## Using Permissions in Controllers

### Basic Permission Check

```php
use ProPhoto\Access\Permissions;

class GalleryController extends Controller
{
    public function index()
    {
        // Check if user has permission
        if (!auth()->user()->hasPermissionTo(Permissions::VIEW_GALLERIES)) {
            abort(403);
        }

        // ... return galleries
    }

    public function store(Request $request)
    {
        // Check permission
        if (!auth()->user()->hasPermissionTo(Permissions::CREATE_GALLERY)) {
            abort(403);
        }

        // ... create gallery
    }
}
```

### Contextual Permission Check

```php
use ProPhoto\Access\Permissions;
use ProPhoto\Access\Models\Gallery;

class GalleryController extends Controller
{
    public function show(Gallery $gallery)
    {
        $user = auth()->user();

        // Check contextual permission
        if (!$user->hasContextualPermission(Permissions::VIEW_GALLERIES, $gallery)) {
            abort(403);
        }

        return view('galleries.show', compact('gallery'));
    }

    public function approveImage(Gallery $gallery, Image $image)
    {
        $user = auth()->user();

        if (!$user->hasContextualPermission(Permissions::APPROVE_IMAGES, $gallery)) {
            abort(403);
        }

        // ... approve image
    }
}
```

### Using Role Checks

```php
use ProPhoto\Access\Enums\UserRole;

class AdminController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();

        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return view('admin.dashboard');
        }

        if ($user->hasRole(UserRole::CLIENT_USER->value)) {
            return view('client.dashboard');
        }

        if ($user->hasRole(UserRole::GUEST_USER->value)) {
            return view('subject.dashboard');
        }

        abort(403);
    }
}
```

---

## Using Policies

Policies are automatically registered by the AccessServiceProvider. Use them with Laravel's standard authorization:

### In Controllers

```php
use ProPhoto\Access\Models\Gallery;

class GalleryController extends Controller
{
    public function __construct()
    {
        // Authorize all actions via policy
        $this->authorizeResource(Gallery::class, 'gallery');
    }

    public function show(Gallery $gallery)
    {
        // Already authorized by authorizeResource
        return view('galleries.show', compact('gallery'));
    }

    public function uploadImages(Gallery $gallery)
    {
        // Manual authorization for custom actions
        $this->authorize('uploadImages', $gallery);

        // ... handle upload
    }

    public function generateAiPortraits(Gallery $gallery)
    {
        $this->authorize('generateAiPortraits', $gallery);

        // ... generate portraits
    }
}
```

### In Blade Templates

```blade
@can('view', $gallery)
    <a href="{{ route('galleries.show', $gallery) }}">View Gallery</a>
@endcan

@can('uploadImages', $gallery)
    <button>Upload Images</button>
@endcan

@can('approveImages', $gallery)
    <button>Approve for Marketing</button>
@endcan

@can('generateAiPortraits', $gallery)
    <button>Generate AI Portraits</button>
@endcan

{{-- Check by permission name --}}
@can('can_create_gallery')
    <a href="{{ route('galleries.create') }}">Create New Gallery</a>
@endcan
```

### Available Policy Methods

**GalleryPolicy:**

- `viewAny`, `view`, `create`, `update`, `delete`
- `archive`, `uploadImages`, `deleteImages`, `downloadImages`
- `approveImages`, `rateImages`, `commentOnImages`, `requestEdits`
- `generateAiPortraits`, `enableAi`, `markComplete`, `share`

**SessionPolicy:**

- `viewAny`, `view`, `create`, `update`, `delete`, `cancel`

**OrganizationPolicy:**

- `viewAny`, `view`, `create`, `update`, `delete`
- `manageUsers`, `manageSettings`

**InvoicePolicy:**

- `viewAny`, `view`, `create`, `update`, `delete`
- `send`, `recordPayment`, `downloadPdf`

---

## Using Middleware

### Route-Level Permission Checks

```php
use Illuminate\Support\Facades\Route;

// Check contextual permission on a route
Route::get('/galleries/{gallery}', [GalleryController::class, 'show'])
    ->middleware('contextual_permission:can_view_gallery,gallery');

Route::post('/galleries/{gallery}/approve', [GalleryController::class, 'approve'])
    ->middleware('contextual_permission:can_approve_images,gallery');

Route::post('/galleries/{gallery}/ai/generate', [AiController::class, 'generate'])
    ->middleware('contextual_permission:can_generate_ai_portraits,gallery');
```

### In Route Groups

```php
Route::middleware(['auth', 'contextual_permission:can_view_gallery,gallery'])
    ->prefix('galleries/{gallery}')
    ->group(function () {
        Route::get('/', [GalleryController::class, 'show']);
        Route::get('/images', [GalleryController::class, 'images']);
        Route::get('/download', [GalleryController::class, 'download']);
    });
```

### Combining with Spatie Middleware

```php
// Using Spatie's permission middleware for global permissions
Route::middleware(['auth', 'permission:can_create_gallery'])
    ->post('/galleries', [GalleryController::class, 'store']);

// Using contextual middleware for resource-specific permissions
Route::middleware(['auth', 'contextual_permission:can_edit_gallery,gallery'])
    ->put('/galleries/{gallery}', [GalleryController::class, 'update']);
```

---

## Using the Facade & Helpers

### Access Facade

```php
use ProPhoto\Access\Facades\Access;

// Get effective permissions for a user
$permissions = Access::getEffectivePermissions($user);

// Check permission
$canView = Access::hasPermission($user, 'can_view_gallery');

// With context
$canApprove = Access::hasPermission(
    $user,
    'can_approve_images',
    $gallery->id,
    Gallery::class
);
```

### Helper Functions

```php
// Check if current user has permission (with optional context)
if (can_access('can_view_gallery', $gallery)) {
    // User can view this gallery
}

// Get all effective permissions
$permissions = user_permissions($gallery);

// Check user role
if (is_studio_user()) {
    // Current user is photographer/admin
}

if (is_client_user()) {
    // Current user is from an organization
}

if (is_guest_user()) {
    // Current user is a subject (magic link)
}
```

### In Blade Templates

```blade
@if(can_access('can_approve_images', $gallery))
    <button>Approve Image</button>
@endif

@if(is_studio_user())
    <a href="/admin">Admin Panel</a>
@elseif(is_client_user())
    <a href="/client">Client Portal</a>
@endif
```

---

## Granting Contextual Permissions

### When Creating a Subject (Guest User)

```php
use ProPhoto\Access\Permissions;
use ProPhoto\Access\Models\Gallery;

class SubjectService
{
    public function createSubjectAccess(User $user, Gallery $gallery): void
    {
        // Grant gallery-specific permissions
        $user->grantContextualPermissions([
            Permissions::VIEW_GALLERIES,
            Permissions::RATE_IMAGES,
            Permissions::COMMENT_ON_IMAGES,
            Permissions::APPROVE_IMAGES,
            Permissions::DOWNLOAD_IMAGES,
        ], $gallery, now()->addDays(30)); // Expires in 30 days

        // Optionally enable AI generation
        if ($gallery->ai_enabled) {
            $user->grantContextualPermission(
                Permissions::GENERATE_AI_PORTRAITS,
                $gallery,
                now()->addDays(30)
            );
        }
    }
}
```

### When Assigning Client User to Organization

```php
use ProPhoto\Access\Permissions;

class OrganizationService
{
    public function addUserToOrganization(User $user, Organization $organization): void
    {
        // Attach user to organization
        $organization->users()->attach($user->id, [
            'role' => 'marketing_contact',
            'is_primary' => false,
        ]);

        // Grant organization-level contextual permissions
        $user->grantContextualPermissions([
            Permissions::VIEW_GALLERIES,
            Permissions::APPROVE_IMAGES,
            Permissions::DOWNLOAD_IMAGES,
            Permissions::VIEW_INVOICES,
        ], $organization);
    }

    public function makeBillingContact(User $user, Organization $organization): void
    {
        // Additional permissions for billing contacts
        $user->grantContextualPermissions([
            Permissions::VIEW_INVOICES,
            Permissions::DOWNLOAD_INVOICE_PDF,
            Permissions::VIEW_PAYMENT_HISTORY,
        ], $organization);
    }
}
```

### Revoking Permissions

```php
// Revoke specific permission
$user->revokeContextualPermission(Permissions::GENERATE_AI_PORTRAITS, $gallery);

// Revoke all permissions for a context
$user->revokeAllContextualPermissions($gallery);

// Sync permissions (removes existing, adds new)
$user->syncContextualPermissions([
    Permissions::VIEW_GALLERIES,
    Permissions::DOWNLOAD_IMAGES,
], $gallery);
```

### Checking Multiple Permissions

```php
// Check if user has ANY of these permissions
if ($user->hasAnyContextualPermission([
    Permissions::APPROVE_IMAGES,
    Permissions::RATE_IMAGES,
], $gallery)) {
    // Can interact with gallery
}

// Check if user has ALL of these permissions
if ($user->hasAllContextualPermissions([
    Permissions::VIEW_GALLERIES,
    Permissions::DOWNLOAD_IMAGES,
    Permissions::APPROVE_IMAGES,
], $gallery)) {
    // Has full access
}
```

---

## Filament Integration

### Admin Panel Setup

This package includes a Filament plugin with built-in resources for managing roles and permissions. Add it to your Filament Panel:

```php
// app/Providers/Filament/AdminPanelProvider.php

use ProPhoto\Access\Filament\AccessPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            AccessPlugin::make(),
        ]);
}
```

This adds three pages to your admin panel under "Access Control":

1. **Roles** - Create and manage roles with their permissions
2. **Permissions** - View all permissions with categories and descriptions
3. **Permission Matrix** - Visual grid showing which roles have which permissions (click to toggle!)

You can customize which features are enabled:

```php
AccessPlugin::make()
    ->roleResource(true)        // Enable/disable Roles resource
    ->permissionResource(true)  // Enable/disable Permissions resource
    ->permissionMatrix(true)    // Enable/disable Permission Matrix page
```

### Scoping Queries in Resources

```php
<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use ProPhoto\Access\Enums\UserRole;
use ProPhoto\Access\Models\Gallery;
use ProPhoto\Access\Models\PermissionContext;

class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Studio users see everything
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return $query;
        }

        // Client users see only their organization's galleries
        if ($user->hasRole(UserRole::CLIENT_USER->value)) {
            return $query->where('organization_id', $user->organization_id);
        }

        // Guest users see only galleries with contextual permission
        if ($user->hasRole(UserRole::GUEST_USER->value)) {
            $galleryIds = PermissionContext::where('user_id', $user->id)
                ->where('contextable_type', Gallery::class)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->pluck('contextable_id');

            return $query->whereIn('id', $galleryIds);
        }

        // Default: no access
        return $query->whereRaw('1 = 0');
    }
}
```

### Conditional Actions in Tables

```php
use Filament\Tables;
use ProPhoto\Access\Permissions;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // ... columns
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),

            Tables\Actions\Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->visible(fn ($record) =>
                    auth()->user()->hasContextualPermission(
                        Permissions::APPROVE_IMAGES,
                        $record
                    )
                )
                ->action(function ($record) {
                    // Approve logic
                }),

            Tables\Actions\Action::make('generateAi')
                ->icon('heroicon-o-sparkles')
                ->visible(fn ($record) =>
                    $record->ai_enabled &&
                    $record->canGenerateAiPortraits() &&
                    auth()->user()->hasContextualPermission(
                        Permissions::GENERATE_AI_PORTRAITS,
                        $record
                    )
                )
                ->action(function ($record) {
                    // AI generation logic
                }),

            Tables\Actions\Action::make('archive')
                ->icon('heroicon-o-archive-box')
                ->visible(fn ($record) =>
                    auth()->user()->can('archive', $record)
                )
                ->requiresConfirmation()
                ->action(fn ($record) => $record->update(['status' => 'archived'])),
        ]);
}
```

### Form Field Visibility

```php
use Filament\Forms;
use ProPhoto\Access\Enums\UserRole;

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('subject_name')
                ->required(),

            // Only studio users can enable AI
            Forms\Components\Toggle::make('ai_enabled')
                ->label('Enable AI Portraits')
                ->visible(fn () =>
                    auth()->user()->hasRole(UserRole::STUDIO_USER->value)
                ),

            // Only studio users can set organization
            Forms\Components\Select::make('organization_id')
                ->relationship('organization', 'name')
                ->visible(fn () =>
                    auth()->user()->hasRole(UserRole::STUDIO_USER->value)
                )
                ->disabled(fn () =>
                    !auth()->user()->hasRole(UserRole::STUDIO_USER->value)
                ),

            // Show to all who can view
            Forms\Components\Placeholder::make('image_count')
                ->content(fn ($record) => $record?->image_count ?? 0),
        ]);
}
```

### Multiple Filament Panels

```php
// app/Providers/Filament/AdminPanelProvider.php
use ProPhoto\Access\Enums\UserRole;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('web')
            // Only studio users can access admin panel
            ->tenant(null)
            ->plugins([])
            ->middleware([
                // Custom middleware to check role
                \App\Http\Middleware\EnsureUserIsStudioUser::class,
            ]);
    }
}

// app/Providers/Filament/ClientPanelProvider.php
class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('client')
            ->path('client')
            ->login()
            ->middleware([
                \App\Http\Middleware\EnsureUserIsClientUser::class,
            ]);
    }
}
```

---

## Example: Gallery Package Integration

Here's how a `prophoto/gallery` package would integrate with `prophoto/access`:

### Package Service Provider

```php
<?php

namespace ProPhoto\Gallery;

use Illuminate\Support\ServiceProvider;
use ProPhoto\Access\Models\Gallery;

class GalleryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The access package already registers the Gallery model and policy
        // Just load your routes, views, etc.

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'gallery');
    }
}
```

### Routes

```php
<?php
// routes/web.php

use Illuminate\Support\Facades\Route;
use ProPhoto\Gallery\Http\Controllers\GalleryController;
use ProPhoto\Gallery\Http\Controllers\ImageController;

Route::middleware(['web', 'auth'])->group(function () {

    // Gallery CRUD (uses policy)
    Route::resource('galleries', GalleryController::class);

    // Gallery-specific actions with contextual permission middleware
    Route::prefix('galleries/{gallery}')->group(function () {

        Route::middleware('contextual_permission:can_upload_images,gallery')
            ->post('/images', [ImageController::class, 'store'])
            ->name('galleries.images.store');

        Route::middleware('contextual_permission:can_approve_images,gallery')
            ->post('/images/{image}/approve', [ImageController::class, 'approve'])
            ->name('galleries.images.approve');

        Route::middleware('contextual_permission:can_rate_images,gallery')
            ->post('/images/{image}/rate', [ImageController::class, 'rate'])
            ->name('galleries.images.rate');

        Route::middleware('contextual_permission:can_download_images,gallery')
            ->get('/download', [GalleryController::class, 'download'])
            ->name('galleries.download');

        Route::middleware('contextual_permission:can_generate_ai_portraits,gallery')
            ->post('/ai/generate', [GalleryController::class, 'generateAi'])
            ->name('galleries.ai.generate');
    });
});

// Magic link access (no auth required initially)
Route::get('/g/{accessCode}', [GalleryController::class, 'accessByCode'])
    ->name('galleries.access');

Route::post('/g/{accessCode}/verify', [GalleryController::class, 'verifyEmail'])
    ->name('galleries.verify');
```

### Controller Example

```php
<?php

namespace ProPhoto\Gallery\Http\Controllers;

use Illuminate\Http\Request;
use ProPhoto\Access\Models\Gallery;
use ProPhoto\Access\Permissions;

class GalleryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Gallery::class, 'gallery');
    }

    public function index()
    {
        $user = auth()->user();

        $galleries = Gallery::query()
            ->when($user->hasRole('client_user'), function ($q) use ($user) {
                $q->where('organization_id', $user->organization_id);
            })
            ->when($user->hasRole('guest_user'), function ($q) use ($user) {
                $galleryIds = $user->permissionContexts()
                    ->where('contextable_type', Gallery::class)
                    ->pluck('contextable_id');
                $q->whereIn('id', $galleryIds);
            })
            ->latest()
            ->paginate();

        return view('gallery::index', compact('galleries'));
    }

    public function show(Gallery $gallery)
    {
        // Policy already checked by authorizeResource

        $gallery->load(['images' => function ($q) {
            $q->orderBy('sort_order');
        }]);

        // Record activity
        $gallery->recordActivity();

        return view('gallery::show', compact('gallery'));
    }

    public function download(Gallery $gallery)
    {
        // Middleware already checked contextual permission

        $gallery->increment('download_count');

        // ... zip and return download
    }

    public function generateAi(Request $request, Gallery $gallery)
    {
        // Middleware already checked contextual permission

        if (!$gallery->canGenerateAiPortraits()) {
            return back()->with('error', 'AI model not ready');
        }

        // Check generation limit
        $aiGeneration = $gallery->aiGeneration;
        if ($aiGeneration->remaining_generations <= 0) {
            return back()->with('error', 'Generation limit reached');
        }

        // ... trigger AI generation

        return back()->with('success', 'AI portraits are being generated');
    }

    /**
     * Magic link access - no auth required
     */
    public function accessByCode(string $accessCode)
    {
        $gallery = Gallery::where('access_code', $accessCode)->firstOrFail();

        // If already authenticated and has access, redirect to gallery
        if (auth()->check() && auth()->user()->hasContextualPermission(
            Permissions::VIEW_GALLERIES,
            $gallery
        )) {
            return redirect()->route('galleries.show', $gallery);
        }

        // Show email collection form
        return view('gallery::access', compact('gallery'));
    }

    /**
     * Verify email and create/find user with magic link
     */
    public function verifyEmail(Request $request, string $accessCode)
    {
        $request->validate(['email' => 'required|email']);

        $gallery = Gallery::where('access_code', $accessCode)->firstOrFail();

        // Find or create guest user
        $user = User::firstOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->email,
                'studio_id' => $gallery->studio_id,
            ]
        );

        // Assign guest role if not already assigned
        if (!$user->hasRole('guest_user')) {
            $user->assignRole('guest_user');
        }

        // Grant contextual permissions for this gallery
        $user->grantContextualPermissions([
            Permissions::VIEW_GALLERIES,
            Permissions::RATE_IMAGES,
            Permissions::COMMENT_ON_IMAGES,
            Permissions::APPROVE_IMAGES,
            Permissions::DOWNLOAD_IMAGES,
            Permissions::REQUEST_EDITS,
        ], $gallery, $gallery->magic_link_expires_at);

        // If AI is enabled, grant that permission too
        if ($gallery->ai_enabled) {
            $user->grantContextualPermission(
                Permissions::GENERATE_AI_PORTRAITS,
                $gallery,
                $gallery->magic_link_expires_at
            );
        }

        // Send magic link email
        // ...

        return back()->with('success', 'Check your email for the access link');
    }
}
```

---

## Example: Invoice Package Integration

```php
<?php

namespace ProPhoto\Invoicing\Http\Controllers;

use Illuminate\Http\Request;
use ProPhoto\Access\Models\Invoice;
use ProPhoto\Access\Models\Organization;
use ProPhoto\Access\Permissions;
use ProPhoto\Access\Enums\UserRole;

class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Invoice::class, 'invoice');
    }

    public function index()
    {
        $user = auth()->user();

        $invoices = Invoice::query()
            ->when($user->hasRole(UserRole::CLIENT_USER->value), function ($q) use ($user) {
                $q->where('organization_id', $user->organization_id);
            })
            ->with('organization')
            ->latest()
            ->paginate();

        return view('invoicing::index', compact('invoices'));
    }

    public function store(Request $request)
    {
        // Policy already checked 'create' permission

        $invoice = Invoice::create([
            'studio_id' => auth()->user()->studio_id,
            'organization_id' => $request->organization_id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'status' => Invoice::STATUS_DRAFT,
            'created_by_user_id' => auth()->id(),
            // ... other fields
        ]);

        return redirect()->route('invoices.edit', $invoice);
    }

    public function downloadPdf(Invoice $invoice)
    {
        $this->authorize('downloadPdf', $invoice);

        // ... generate and return PDF
    }

    public function recordPayment(Request $request, Invoice $invoice)
    {
        $this->authorize('recordPayment', $invoice);

        $invoice->markAsPaid(
            $request->payment_method,
            $request->reference,
            $request->notes
        );

        return back()->with('success', 'Payment recorded');
    }

    public function send(Invoice $invoice)
    {
        $this->authorize('send', $invoice);

        $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'issued_at' => now(),
            'due_at' => now()->addDays(
                $invoice->organization->payment_terms ?? 30
            ),
        ]);

        // Send email to billing contacts
        $billingContacts = $invoice->organization->users()
            ->wherePivot('role', 'billing_contact')
            ->get();

        // ... send emails

        return back()->with('success', 'Invoice sent');
    }
}
```

---

## Quick Reference

### Permission Constants

```php
use ProPhoto\Access\Permissions;

// Gallery
Permissions::CREATE_GALLERY       // 'can_create_gallery'
Permissions::VIEW_GALLERIES       // 'can_view_gallery'
Permissions::EDIT_GALLERY         // 'can_edit_gallery'
Permissions::DELETE_GALLERY       // 'can_delete_gallery'
Permissions::ARCHIVE_GALLERY      // 'can_archive_gallery'
Permissions::UPLOAD_IMAGES        // 'can_upload_images'
Permissions::DELETE_IMAGES        // 'can_delete_images'
Permissions::DOWNLOAD_IMAGES      // 'can_download_images'
Permissions::SHARE_GALLERY        // 'can_share_gallery'
Permissions::APPROVE_IMAGES       // 'can_approve_images'
Permissions::RATE_IMAGES          // 'can_rate_images'
Permissions::COMMENT_ON_IMAGES    // 'can_comment_on_images'
Permissions::REQUEST_EDITS        // 'can_request_edits'
Permissions::MARK_GALLERY_COMPLETE // 'can_mark_gallery_complete'

// AI
Permissions::ENABLE_AI_PORTRAITS     // 'can_enable_ai_portraits'
Permissions::TRAIN_AI_MODEL          // 'can_train_ai_model'
Permissions::GENERATE_AI_PORTRAITS   // 'can_generate_ai_portraits'
Permissions::VIEW_AI_PORTRAITS       // 'can_view_ai_portraits'
Permissions::DOWNLOAD_AI_PORTRAITS   // 'can_download_ai_portraits'
Permissions::DISABLE_AI_PORTRAITS    // 'can_disable_ai_portraits'
Permissions::VIEW_AI_COSTS           // 'can_view_ai_costs'

// Sessions
Permissions::CREATE_SESSION    // 'can_create_session'
Permissions::VIEW_SESSION      // 'can_view_session'
Permissions::EDIT_SESSION      // 'can_edit_session'
Permissions::DELETE_SESSION    // 'can_delete_session'
Permissions::REQUEST_BOOKING   // 'can_request_booking'
Permissions::CONFIRM_BOOKING   // 'can_confirm_booking'
Permissions::DENY_BOOKING      // 'can_deny_booking'
Permissions::CANCEL_SESSION    // 'can_cancel_session'
Permissions::VIEW_CALENDAR     // 'can_view_calendar'

// Organizations
Permissions::CREATE_ORGANIZATION  // 'can_create_organization'
Permissions::VIEW_ORGANIZATION    // 'can_view_organization'
Permissions::EDIT_ORGANIZATION    // 'can_edit_organization'
Permissions::DELETE_ORGANIZATION  // 'can_delete_organization'
Permissions::MANAGE_ORG_USERS     // 'can_manage_org_users'
Permissions::MANAGE_ORG_SETTINGS  // 'can_manage_org_settings'

// Invoices
Permissions::CREATE_INVOICE       // 'can_create_invoice'
Permissions::VIEW_INVOICES        // 'can_view_invoice'
Permissions::EDIT_INVOICE         // 'can_edit_invoice'
Permissions::DELETE_INVOICE       // 'can_delete_invoice'
Permissions::SEND_INVOICE         // 'can_send_invoice'
Permissions::RECORD_PAYMENTS      // 'can_record_payment'
Permissions::VIEW_PAYMENT_HISTORY // 'can_view_payment_history'
Permissions::DOWNLOAD_INVOICE_PDF // 'can_export_invoice_pdf'
Permissions::MANAGE_STRIPE        // 'can_manage_stripe'

// Users
Permissions::CREATE_USER         // 'can_create_user'
Permissions::VIEW_USER           // 'can_view_user'
Permissions::EDIT_USER           // 'can_edit_user'
Permissions::DELETE_USER         // 'can_delete_user'
Permissions::ASSIGN_ROLES        // 'can_assign_roles'
Permissions::MANAGE_PERMISSIONS  // 'can_manage_permissions'
Permissions::INVITE_USERS        // 'can_invite_users'

// System
Permissions::MANAGE_STUDIO_SETTINGS // 'can_manage_studio_settings'
Permissions::VIEW_ANALYTICS         // 'can_view_analytics'
Permissions::MANAGE_INTEGRATIONS    // 'can_manage_integrations'
Permissions::ACCESS_STAGING         // 'can_access_staging'
Permissions::VIEW_ALL_DATA          // 'can_view_all_data'
```

### User Roles

```php
use ProPhoto\Access\Enums\UserRole;

UserRole::STUDIO_USER->value  // 'studio_user' - Photographer/Admin
UserRole::CLIENT_USER->value  // 'client_user' - Organization contacts
UserRole::GUEST_USER->value   // 'guest_user' - Subjects (magic link)
UserRole::VENDOR_USER->value  // 'vendor_user' - External collaborators
```

---

## Summary

1. **Install** the `prophoto/access` package
2. **Add traits** to your User model (`HasRoles`, `HasContextualPermissions`)
3. **Use policies** for model-level authorization
4. **Use middleware** for route-level contextual permissions
5. **Grant contextual permissions** when creating subjects or assigning users
6. **Scope queries** in Filament resources based on user role
7. **Use helpers** for quick permission checks in views

## Filament Admin Panel Features

1. Roles Resource (/admin/roles)
List all roles with permission counts & user counts
Color-coded badges (studio_user=green, client_user=blue, guest_user=amber)
Create/edit roles with tabbed permission selection by category:
Galleries, AI Portraits, Sessions & Bookings, Organizations, Invoices, Users & System

2. Permissions Resource (/admin/permissions)
List all 50+ permissions with:
Auto-categorization (Gallery, AI, Session, etc.)
Human-readable descriptions
Which roles have each permission
Filter by category
Copyable permission names

3. Permission Matrix Page (/admin/permission-matrix)
Visual grid showing all roles vs all permissions
Click any cell to toggle permissions on/off
Grouped by category with collapsible headers
Role statistics cards with progress bars showing % coverage
To Use It
Add to your Filament panel provider:

```php
// app/Providers/Filament/AdminPanelProvider.php

use ProPhoto\Access\Filament\AccessPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            AccessPlugin::make(),
        ]);
}
```

*Then visit /admin and look for "Access Control" in the sidebar!*

