
OVERVIEW prophoto-access is a **Hybrid Context-Aware RBAC** (Role-Based Access Control) Filament Admin Panel that controls a larger photography business application. The package backend is a filament v4.5.2 https://filamentphp.com/docs and has 3 options:  "Roles" "Permissions" and "Permission Matrix", "Roles" and "Permissions" are fine but " "Permission Matrix" is different, I believe its not a filament page, because it has a corresponding blade file here resources/views/filament/pages/permission-matrix.blade.php and its broke, I believe this mix of Filament and blade is causing problems IS  has  . Keep in mind that the prophoto-access project directory is a composer package that is symlinked and installed into directory `sandbox-003` a Laravel package used for dev testing.

## What’s actually happening
### Roles & Permissions (working)

These are **pure Filament constructs**:

* Filament **Resources**
* Filament **Tables**
* Filament **Actions**
* Filament controls state, routing, authorization, lifecycle

Everything:

* runs inside Filament’s Livewire context
* uses Filament components
* benefits from Filament v4 upgrades

That’s why they’re stable.

---

### Permission Matrix (broken)

This is **not** a Filament-native page.

Key red flags from what you described and showed:

* Has its **own Blade view**
  `resources/views/filament/pages/permission-matrix.blade.php`
* Appears to:

    * render raw HTML
    * manually loop permissions & roles
    * manually wire click handlers
* Likely **bypasses Filament Tables, Forms, Actions**
* Probably:

    * uses Alpine or vanilla JS
    * talks directly to routes/controllers
    * assumes old Filament internals

So you effectively have:

> **A Blade-powered RBAC UI living inside a Filament shell**

That *can* work — but it’s fragile, especially in Filament v4.

---

## Why this breaks in Filament v4+

Filament v4 tightened a lot of assumptions:

### 1. Livewire ownership

Filament pages expect:

* Livewire to control DOM updates
* consistent component lifecycle

A Blade-heavy page:

* mutates DOM outside Livewire
* loses reactivity
* breaks when Filament re-renders

---

### 2. Action system mismatch

Your matrix:

* likely toggles permissions via AJAX or routes
* bypasses `Filament\Actions\Action`
* bypasses `Tables\Actions`

That’s why you’re seeing issues like:

* broken toggles
* stale UI state
* permission counts desyncing
* 
### 3. Authorization context drift

Filament evaluates:

* policies
* guards
* tenancy
* auth state

A Blade page:

* doesn’t automatically inherit those
* can silently fail authorization
* can behave differently per role

---

### 4. Filament upgrades won’t protect you

Roles & Permissions benefit from:

* Filament upgrades
* Livewire fixes
* accessibility improvements

The matrix:

* is frozen in time
* breaks every major Filament upgrade
* requires manual fixes


### ✅ **Option 1 (Best): Rebuild Permission Matrix as a Filament Page**

This is the **correct long-term solution**.

_**Create a real Filament page:**_

```php
class PermissionMatrix extends Page
{
    protected static string $view = 'filament.pages.permission-matrix';
}
```

But instead of raw Blade logic:

* Use:

    * Livewire state
    * Filament buttons
    * Filament actions
* Each cell becomes:

    * a Livewire-driven toggle
    * backed by a single action

Benefits:

* Native Filament lifecycle
* Stable in v4+
* Can still look identical visually
* Plays perfectly with Roles & Permissions

NEXT STEPS

1. **Map your current Blade matrix → Filament concepts**

    * rows
    * columns
    * bulk grant/revoke
2. Design the **Livewire state model**
3. Sketch the **Filament Page class**
4. Replace each cell with a clean, debounced toggle action

* high-level architecture
* Livewire state model
* or straight into Filament v4 code scaffolding



#### **--------  INITIAL BACKEND DOCUMENTATION ---------**

Filament Admin Panel Features
Roles Resource (/admin/roles) List all roles with permission counts & user counts Color-coded badges (studio_user=green, client_user=blue, guest_user=amber) Create/edit roles with tabbed permission selection by category: Galleries, AI Portraits, Sessions & Bookings, Organizations, Invoices, Users & System

Permissions Resource (/admin/permissions) List all 50+ permissions with: Auto-categorization (Gallery, AI, Session, etc.) Human-readable descriptions Which roles have each permission Filter by category Copyable permission names

Permission Matrix Page (/admin/permission-matrix) Visual grid showing all roles vs all permissions Click any cell to toggle permissions on/off Grouped by category with collapsible headers Role statistics cards with progress bars showing % coverage To Use It Add to your Filament panel provider:


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

3. Role-Permission Matrix
   | **Permission** | **studio_user** | **client_user** | **guest_user** | **Notes** |
   |---|---|---|---|---|
   | Galleries |  |  |  |  |
   | can_create_gallery | ✅ | ❌ | ❌ | Photographer only |
   | can_view_gallery | ✅ | ✅ (org) | ✅ (own) | Context-scoped |
   | can_edit_gallery | ✅ | ❌ | ❌ |  |
   | can_delete_gallery | ✅ | ❌ | ❌ |  |
   | can_archive_gallery | ✅ | ✅ (org) | ❌ | Client can archive their galleries |
   | can_upload_images | ✅ | ❌ | ❌ |  |
   | can_delete_images | ✅ | ❌ | ❌ |  |
   | can_download_images | ✅ | ✅ | ✅ | All can download |
   | can_share_gallery | ✅ | ✅ | ✅ | Subject shares own link |
   | can_approve_images | ✅ | ✅ | ✅ | All can approve for marketing |
   | can_rate_images | ✅ | ✅ | ✅ |  |
   | can_comment_on_images | ✅ | ✅ | ✅ | Image notes |
   | can_request_edits | ❌ | ❌ | ✅ | Subject requests only |
   | can_mark_gallery_complete | ✅ | ❌ | ❌ |  |
   | AI Portraits |  |  |  |  |
   | can_enable_ai_portraits | ✅ | ❌ | ❌ | Photographer controls cost |
   | can_train_ai_model | ✅ | ❌ | ❌ | Photographer initiates |
   | can_generate_ai_portraits | ✅ | ✅ | ✅ | If enabled for gallery |
   | can_disable_ai_portraits | ✅ | ✅ (org) | ❌ | Client can disable for subjects |
   | can_view_ai_costs | ✅ | ❌ | ❌ | Photographer only |
   | Sessions & Booking |  |  |  |  |
   | can_create_session | ✅ | ❌ | ❌ |  |
   | can_edit_session | ✅ | ❌ | ❌ |  |
   | can_request_booking | ❌ | ✅ | ❌ | Client books |
   | can_confirm_booking | ✅ | ❌ | ❌ | Photographer confirms |
   | can_view_calendar | ✅ | ✅ | ❌ | Client sees availability |
   | Organizations |  |  |  |  |
   | can_create_organization | ✅ | ❌ | ❌ |  |
   | can_view_organization | ✅ | ✅ (own) | ❌ | Context-scoped |
   | can_edit_organization | ✅ | ❌ | ❌ |  |
   | can_manage_org_users | ✅ | ✅ (own) | ❌ | Client manages team |
   | can_manage_org_settings | ✅ | ✅ (own) | ❌ |  |
   | Invoicing |  |  |  |  |
   | can_create_invoice | ✅ | ❌ | ❌ |  |
   | can_view_invoice | ✅ | ✅ (org) | ❌ | Context-scoped |
   | can_record_payment | ✅ | ❌ | ❌ |  |
   | can_export_invoice_pdf | ✅ | ✅ (org) | ❌ |  |
   | Users |  |  |  |  |
   | can_create_user | ✅ | ❌ | ❌ |  |
   | can_invite_users | ✅ | ✅ (org) | ❌ | Client invites team |
   | can_assign_roles | ✅ | ❌ | ❌ |  |
   | System |  |  |  |  |
   | can_manage_studio_settings | ✅ | ❌ | ❌ |  |
   | can_access_staging | ✅ | ❌ | ❌ | Ingest interface |
   | can_view_all_data | ✅ | ❌ | ❌ | Super admin |


