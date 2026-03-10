# #SOLO/Permissions-Schema - Comprehensive RBAC

# 1. The Three Layers 
## Layer 1: Roles (Who you are)
* •	studio_user - Photographer/admin
* •	client_user - Organization contacts
* •	guest_user - Subjects (magic link auth)
* •	vendor_user - External collaborators (future)

⠀Layer 2: Capabilities (What you can do)
* •	Permissions like can_upload, can_invoice, can_approve_gallery

⠀Layer 3: Context (Where you can do it)
* •	Scoped to specific Studio, Organization, Gallery, or Session
* •	Example: can_approve_gallery for Gallery #122 only

# 2. All Permissions Defined
## Gallery Management


``` php
'can_create_gallery'         *// Create new galleries*
'can_view_gallery'           *// View gallery contents*
'can_edit_gallery'           *// Edit gallery details*
'can_delete_gallery'         *// Delete galleries*
'can_archive_gallery'        *// Archive galleries*
'can_upload_images'          *// Upload images to gallery*
'can_delete_images'          *// Delete images from gallery*
'can_download_images'        *// Download images*
'can_share_gallery'          *// Generate/manage share links*
'can_approve_images'         *// Approve images for marketing*
'can_rate_images'            *// Rate images (1-5 stars)*
'can_comment_on_images'      *// Add notes to images*
'can_request_edits'          *// Request image edits*
'can_mark_gallery_complete'  *// Mark gallery as complete*
```
## AI Portrait Generation

``` php
'can_enable_ai_portraits'    *// Enable AI for galleries*
'can_train_ai_model'         *// Initiate model training*
'can_generate_ai_portraits'  *// Generate portraits from trained model*
'can_view_ai_portraits'      *// View generated portraits*
'can_download_ai_portraits'  *// Download AI portraits*
'can_disable_ai_portraits'   *// Disable AI for subjects (client override)*
'can_view_ai_costs'          *// View AI generation costs*
```
## Sessions & Booking

``` php
'can_create_session'         *// Create sessions*
'can_view_session'           *// View session details*
'can_edit_session'           *// Edit session details*
'can_delete_session'         *// Delete sessions*
'can_request_booking'        *// Request booking (client)*
'can_confirm_booking'        *// Confirm booking requests*
'can_deny_booking'           *// Deny booking requests*
'can_cancel_session'         *// Cancel scheduled sessions*
'can_view_calendar'          *// View calendar/availability*
```
## Organizations (Clients)

``` php
'can_create_organization'    *// Create client organizations*
'can_view_organization'      *// View organization details*
'can_edit_organization'      *// Edit organization details*
'can_delete_organization'    *// Delete organizations*
'can_manage_org_users'       *// Add/remove users from org*
'can_manage_org_settings'    *// Edit org settings/preferences*
```
## Invoicing & Payments

``` php
'can_create_invoice'         *// Create invoices*
'can_view_invoice'           *// View invoice details*
'can_edit_invoice'           *// Edit invoice details*
'can_delete_invoice'         *// Delete invoices*
'can_send_invoice'           *// Send invoice to client*
'can_record_payment'         *// Record manual payments*
'can_view_payment_history'   *// View payment history*
'can_export_invoice_pdf'     *// Download invoice PDFs*
'can_manage_stripe'          *// Manage Stripe integration*
```
## Users & Permissions


```php
'can_create_user'            *// Create new users*
'can_view_user'              *// View user details*
'can_edit_user'              *// Edit user details*
'can_delete_user'            *// Delete users*
'can_assign_roles'           *// Assign roles to users*
'can_manage_permissions'     *// Manage contextual permissions*
'can_invite_users'           *// Send user invitations*
```
## Messages & Notifications

``` php
'can_send_message'           *// Send messages*
'can_view_messages'          *// View messages*
'can_delete_message'         *// Delete messages*
'can_manage_notifications'   *// Configure notification preferences*
```
## System & Studio

``` php
'can_manage_studio_settings' *// Edit studio settings*
'can_view_analytics'         *// View reports/analytics*
'can_manage_integrations'    *// Manage API integrations*
'can_access_staging'         *// Access ingest/staging interface*
'can_view_all_data'          *// View all data (super admin)*
```


# 3. Role-Permission Matrix
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


# 4. Context-Scoped Permissions
## Database Schema (Reminder)
### permission_contexts table:

``` php
id
user_id              *// Who has the permission*
permission_id        *// Which permission (from Spatie)*
contextable_type     *// Gallery, Organization, Session, etc.*
contextable_id       *// Specific record ID*
granted_at
expires_at           *// Optional expiration*
created_at
updated_at
```
## Example Contexts:
### Client User: Gabby Rodriguez (UCLA Health)

php
User: Gabby
Role: client_user
Contextual Permissions:
- can_view_gallery → Organization: UCLA Health (sees all UCLA galleries)
- can_approve_images → Organization: UCLA Health
- can_manage_org_users → Organization: UCLA Health
- can_view_invoice → Organization: UCLA Health
### Subject: Dr. Jessica Haslam

php
User: Dr. Haslam (guest_user, magic link)
Role: guest_user
Contextual Permissions:
- can_view_gallery → Gallery: *#445 (her gallery only)*
- can_rate_images → Gallery: *#445*
- can_approve_images → Gallery: *#445*
- can_download_images → Gallery: *#445*
- can_generate_ai_portraits → Gallery: *#445 (if enabled)*
### Billing Contact: John Smith (UCLA Health)

php
User: John Smith
Role: client_user
Contextual Permissions:
- can_view_invoice → Organization: UCLA Health
- can_view_payment_history → Organization: UCLA Health
- can_view_organization → Organization: UCLA Health
  (No gallery access - billing only)


# 5. Permission Checking in Code
## Using Spatie + Custom Context Trait
### Model Trait:HasContextualPermissions

``` php
trait HasContextualPermissions
{
    public function hasContextualPermission($permission, $context)
    {
        *// Check if user has global permission (studio_user)*
        if ($this->hasPermissionTo($permission)) {
            return true;
        }
        
        *// Check contextual permission*
        return PermissionContext::where('user_id', $this->id)
            ->whereHas('permission', fn($q) => $q->where('name', $permission))
            ->where('contextable_type', get_class($context))
            ->where('contextable_id', $context->id)
            ->exists();
    }
    
    public function grantContextualPermission($permission, $context, $expiresAt = null)
    {
        $permissionModel = Permission::findByName($permission);
        
        return PermissionContext::create([
            'user_id' => $this->id,
            'permission_id' => $permissionModel->id,
            'contextable_type' => get_class($context),
            'contextable_id' => $context->id,
            'expires_at' => $expiresAt,
        ]);
    }
}
```
## Policy Example: GalleryPolicy

``` php
class GalleryPolicy
{
    public function view(User $user, Gallery $gallery)
    {
        *// Studio users see all*
        if ($user->hasRole('studio_user')) {
            return true;
        }
        
        *// Client users see their organization's galleries*
        if ($user->hasRole('client_user')) {
            return $user->organization_id === $gallery->organization_id;
        }
        
        *// Guest users see only their specific gallery*
        if ($user->hasRole('guest_user')) {
            return $user->hasContextualPermission('can_view_gallery', $gallery);
        }
        
        return false;
    }
    
    public function approve(User $user, Gallery $gallery)
    {
        return $user->hasContextualPermission('can_approve_images', $gallery);
    }
    
    public function generateAiPortraits(User $user, Gallery $gallery)
    {
        *// Must be enabled for the gallery*
        if (!$gallery->ai_enabled) {
            return false;
        }
        
        return $user->hasContextualPermission('can_generate_ai_portraits', $gallery);
    }
}
```
## Middleware: CheckContextualPermission

``` php
class CheckContextualPermission
{
    public function handle($request, Closure $next, $permission, $contextParam)
    {
        $context = $request->route($contextParam);
        
        if (!$request->user()->hasContextualPermission($permission, $context)) {
            abort(403, 'Unauthorized action.');
        }
        
        return $next($request);
    }
}

*// Usage in routes:*
Route::post('/galleries/{gallery}/approve', [GalleryController::class, 'approve'])
    ->middleware('contextual_permission:can_approve_images,gallery');
```
# 6. Filament Integration
## Scoping Queries Automatically
### In Filament Resources:

```php
class GalleryResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        
        *// Studio users see everything*
        if ($user->hasRole('studio_user')) {
            return $query;
        }
        
        *// Client users see only their organization's galleries*
        if ($user->hasRole('client_user')) {
            return $query->where('organization_id', $user->organization_id);
        }
        
        *// Guest users see only galleries with contextual permission*
        if ($user->hasRole('guest_user')) {
            $galleryIds = PermissionContext::where('user_id', $user->id)
                ->where('contextable_type', Gallery::class)
                ->pluck('contextable_id');
                
            return $query->whereIn('id', $galleryIds);
        }
        
        *// Default: no access*
        return $query->whereRaw('1 = 0');
    }
}
```
## Action Authorization:

``` php
Actions\Action::make('approve')
    ->icon('heroicon-o-check-circle')
    ->visible(fn ($record) => auth()->user()->hasContextualPermission('can_approve_images', $record))
    ->action(function ($record) {
        *// Approve logic*
    }),
```

# 7. Permission Seeder
### database/seeders/RolesAndPermissionsSeeder.php

``` php
class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        *// Reset cached roles and permissions*
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        *// Create all permissions*
        $permissions = [
            *// Galleries*
            'can_create_gallery',
            'can_view_gallery',
            'can_edit_gallery',
            'can_delete_gallery',
            'can_archive_gallery',
            'can_upload_images',
            'can_delete_images',
            'can_download_images',
            'can_share_gallery',
            'can_approve_images',
            'can_rate_images',
            'can_comment_on_images',
            'can_request_edits',
            'can_mark_gallery_complete',
            
            *// AI*
            'can_enable_ai_portraits',
            'can_train_ai_model',
            'can_generate_ai_portraits',
            'can_view_ai_portraits',
            'can_download_ai_portraits',
            'can_disable_ai_portraits',
            'can_view_ai_costs',
            
            *// Sessions*
            'can_create_session',
            'can_view_session',
            'can_edit_session',
            'can_delete_session',
            'can_request_booking',
            'can_confirm_booking',
            'can_deny_booking',
            'can_cancel_session',
            'can_view_calendar',
            
            *// Organizations*
            'can_create_organization',
            'can_view_organization',
            'can_edit_organization',
            'can_delete_organization',
            'can_manage_org_users',
            'can_manage_org_settings',
            
            *// Invoices*
            'can_create_invoice',
            'can_view_invoice',
            'can_edit_invoice',
            'can_delete_invoice',
            'can_send_invoice',
            'can_record_payment',
            'can_view_payment_history',
            'can_export_invoice_pdf',
            'can_manage_stripe',
            
            *// Users*
            'can_create_user',
            'can_view_user',
            'can_edit_user',
            'can_delete_user',
            'can_assign_roles',
            'can_manage_permissions',
            'can_invite_users',
            
            *// Messages*
            'can_send_message',
            'can_view_messages',
            'can_delete_message',
            'can_manage_notifications',
            
            *// System*
            'can_manage_studio_settings',
            'can_view_analytics',
            'can_manage_integrations',
            'can_access_staging',
            'can_view_all_data',
        ];
        
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }
        
        *// Create roles and assign permissions*
        $studioUser = Role::create(['name' => 'studio_user']);
        $studioUser->givePermissionTo(Permission::all()); *// All permissions*
        
        $clientUser = Role::create(['name' => 'client_user']);
        $clientUser->givePermissionTo([
            'can_view_gallery',
            'can_download_images',
            'can_approve_images',
            'can_rate_images',
            'can_comment_on_images',
            'can_generate_ai_portraits',
            'can_view_ai_portraits',
            'can_disable_ai_portraits',
            'can_request_booking',
            'can_view_calendar',
            'can_view_organization',
            'can_manage_org_users',
            'can_manage_org_settings',
            'can_view_invoice',
            'can_export_invoice_pdf',
            'can_invite_users',
            'can_send_message',
            'can_view_messages',
            'can_manage_notifications',
        ]);
        
        $guestUser = Role::create(['name' => 'guest_user']);
        $guestUser->givePermissionTo([
            'can_view_gallery',
            'can_download_images',
            'can_approve_images',
            'can_rate_images',
            'can_comment_on_images',
            'can_request_edits',
            'can_generate_ai_portraits',
            'can_view_ai_portraits',
            'can_share_gallery',
        ]);
        
        $vendorUser = Role::create(['name' => 'vendor_user']);
        *// Vendors get minimal permissions, mostly contextual*
    }
}
```

# 8. Config File
### config/permissions.php
``` php
<?php

return [
    'roles' => [
        'studio_user' => [
            'name' => 'Studio User',
            'description' => 'Photographer/administrator with full access',
        ],
        'client_user' => [
            'name' => 'Client User',
            'description' => 'Organization contacts (marketing, billing, etc.)',
        ],
        'guest_user' => [
            'name' => 'Guest User',
            'description' => 'Subjects with magic link access to their galleries',
        ],
        'vendor_user' => [
            'name' => 'Vendor User',
            'description' => 'External collaborators (future use)',
        ],
    ],
    
    'permission_categories' => [
        'galleries' => 'Gallery Management',
        'ai' => 'AI Portrait Generation',
        'sessions' => 'Sessions & Booking',
        'organizations' => 'Client Organizations',
        'invoices' => 'Invoicing & Payments',
        'users' => 'User Management',
        'messages' => 'Messages & Notifications',
        'system' => 'System Administration',
    ],
];
