# Permission Management in Admin UI

**Overview:** This document outlines a comprehensive role-based permission system for photo gallery management with three-tier inheritance: Organization → User → Gallery-Specific permissions.

Let me show you what this would look like:

# 1. Organization Edit Page

### When editing an organization like "UCLA Health", you'd see

## Organization Form - Permissions Tab

UCLA Health - Edit Organization

Tabs: [Basic Info] [Billing] [Settings] [Permissions] [Users]

┌─ Permissions Tab ─────────────────────────────┐
│                                                │
│ Gallery Permissions                            │
│ ☑ Can view galleries                          │
│ ☑ Can approve images for marketing            │
│ ☑ Can download images                         │
│ ☑ Can archive galleries                       │
│ ☐ Can upload images (photographer only)       │
│ ☐ Can delete images (photographer only)       │
│                                                │
│ AI Portrait Permissions                        │
│ ☑ AI portraits enabled for this organization  │
│ ☑ Users can generate AI portraits             │
│ ☑ Users can disable AI for specific subjects  │
│ ☐ Users can view AI costs (photographer only) │
│                                                │
│ Booking Permissions                            │
│ ☑ Can request bookings                        │
│ ☑ Can view availability calendar              │
│ ☐ Can confirm bookings (photographer only)    │
│                                                │
│ Invoice Permissions                            │
│ ☑ Can view invoices                           │
│ ☑ Can download invoice PDFs                   │
│ ☐ Can create invoices (photographer only)     │
│ ☐ Can record payments (photographer only)     │
│                                                │
│ User Management                                │
│ ☑ Can invite team members                     │
│ ☑ Can manage organization settings            │
│                                                │
└────────────────────────────────────────────────┘

[Save Changes] [Cancel]

```

**What this does:**
- Controls what ALL users from this organization can do
- Acts as organization-wide permission defaults
- Individual users can have permissions further restricted

---

## **2. User Edit Page - Client User**

When editing a specific client user like "Gabby Rodriguez":

### **User Form - Permissions Tab**
```

Gabby Rodriguez - Edit User

Tabs: [Personal Info] [Role & Permissions] [Organization]

┌─ Role & Permissions Tab ───────────────────────┐
│                                                 │
│ Base Role: [Client User ▼]                     │
│                                                 │
│ Organization: UCLA Health                       │
│                                                 │
│ ┌─ Organization-Level Permissions ──────────┐  │
│ │ These apply to all UCLA Health resources  │  │
│ │                                            │  │
│ │ ☑ Can view all organization galleries     │  │
│ │ ☑ Can approve images                       │  │
│ │ ☑ Can download images                      │  │
│ │ ☑ Can request bookings                     │  │
│ │ ☑ Can view invoices                        │  │
│ │ ☑ Can manage team members                  │  │
│ └────────────────────────────────────────────┘  │
│                                                 │
│ ┌─ Gallery-Specific Permissions ────────────┐  │
│ │ Restrict access to specific galleries     │  │
│ │                                            │  │
│ │ ☐ Limit to specific galleries only        │  │
│ │   └─ Select Galleries: [Choose...▼]       │  │
│ └────────────────────────────────────────────┘  │
│                                                 │
│ Special Roles (optional)                        │
│ ☑ Billing Contact                              │
│   └─ Can view payment details & amounts        │
│ ☐ Marketing Admin                               │
│   └─ Can approve for all campaigns             │
│                                                 │
└─────────────────────────────────────────────────┘

[Save Changes] [Cancel]

```

---

## **3. User Edit Page - Subject (Guest User)**

When editing/viewing a subject like "Dr. Jessica Haslam":

### **Subject Access Management**
```

Dr. Jessica Haslam - Gallery Access

Gallery: #445 "Dr. Jessica Haslam Headshots"
Access Code: HASLAM-2025-445
Magic Link: <haslam.gallery.2025@access.solo.com>

┌─ Subject Permissions ──────────────────────────┐
│                                                 │
│ Gallery Access                                  │
│ ☑ Can view gallery                             │
│ ☑ Can rate images (1-5 stars)                  │
│ ☑ Can add notes to images                      │
│ ☑ Can approve images for marketing             │
│ ☑ Can download images                          │
│ ☑ Can share gallery link                       │
│                                                 │
│ AI Portrait Generation                          │
│ ☑ AI portraits enabled for this subject       │
│ ☑ Can generate AI portraits (3 of 5 used)     │
│ ☐ Disable AI generation                        │
│                                                 │
│ Access Control                                  │
│ Magic Link Expires: October 24, 2025           │
│ [Extend Access] [Reset Magic Link]             │
│ [Revoke Access]                                 │
│                                                 │
└─────────────────────────────────────────────────┘

[Save Changes] [Send Access Email]

# 4. Improved Implementation

## Permission Constants & Service

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case STUDIO_USER = 'studio_user';  // Photographer
    case CLIENT_USER = 'client_user';  // Client user
    case GUEST_USER = 'guest_user';    // Subject/Guest

    public function label(): string
    {
        return match($this) {
            self::STUDIO_USER => 'Studio User (Photographer)',
            self::CLIENT_USER => 'Client User',
            self::GUEST_USER => 'Guest User (Subject)',
        };
    }
}

class Permissions
{
    // Gallery Permissions
    public const VIEW_GALLERIES = 'can_view_gallery';
    public const APPROVE_IMAGES = 'can_approve_images';
    public const DOWNLOAD_IMAGES = 'can_download_images';
    public const UPLOAD_IMAGES = 'can_upload_images';
    public const DELETE_IMAGES = 'can_delete_images';
    public const ARCHIVE_GALLERY = 'can_archive_gallery';

    // AI Portrait Permissions
    public const GENERATE_AI_PORTRAITS = 'can_generate_ai_portraits';
    public const DISABLE_AI_PORTRAITS = 'can_disable_ai_portraits';
    public const VIEW_AI_COSTS = 'can_view_ai_costs';

    // Booking Permissions
    public const REQUEST_BOOKING = 'can_request_booking';
    public const VIEW_CALENDAR = 'can_view_calendar';
    public const CONFIRM_BOOKINGS = 'can_confirm_bookings';

    // Invoice Permissions
    public const VIEW_INVOICES = 'can_view_invoice';
    public const DOWNLOAD_INVOICE_PDF = 'can_export_invoice_pdf';
    public const CREATE_INVOICES = 'can_create_invoice';
    public const RECORD_PAYMENTS = 'can_record_payments';

    // User Management
    public const INVITE_TEAM_MEMBERS = 'can_invite_team_members';
    public const MANAGE_ORG_SETTINGS = 'can_manage_org_settings';

    // Special Roles
    public const IS_BILLING_CONTACT = 'is_billing_contact';
    public const IS_MARKETING_ADMIN = 'is_marketing_admin';

    // Permission Labels
    public static function labels(): array
    {
        return [
            self::VIEW_GALLERIES => 'Can view galleries',
            self::APPROVE_IMAGES => 'Can approve images for marketing',
            self::DOWNLOAD_IMAGES => 'Can download images',
            self::UPLOAD_IMAGES => 'Can upload images',
            self::DELETE_IMAGES => 'Can delete images',
            self::ARCHIVE_GALLERY => 'Can archive galleries',
            self::GENERATE_AI_PORTRAITS => 'Can generate AI portraits',
            self::DISABLE_AI_PORTRAITS => 'Can disable AI for specific subjects',
            self::VIEW_AI_COSTS => 'Can view AI costs',
            self::REQUEST_BOOKING => 'Can request bookings',
            self::VIEW_CALENDAR => 'Can view availability calendar',
            self::CONFIRM_BOOKINGS => 'Can confirm bookings',
            self::VIEW_INVOICES => 'Can view invoices',
            self::DOWNLOAD_INVOICE_PDF => 'Can download invoice PDFs',
            self::CREATE_INVOICES => 'Can create invoices',
            self::RECORD_PAYMENTS => 'Can record payments',
            self::INVITE_TEAM_MEMBERS => 'Can invite team members',
            self::MANAGE_ORG_SETTINGS => 'Can manage organization settings',
        ];
    }

    // Default permissions by role
    public static function getRoleDefaults(string $role): array
    {
        return match($role) {
            UserRole::STUDIO_USER->value => [
                self::VIEW_GALLERIES => true,
                self::APPROVE_IMAGES => true,
                self::DOWNLOAD_IMAGES => true,
                self::UPLOAD_IMAGES => true,
                self::DELETE_IMAGES => true,
                self::ARCHIVE_GALLERY => true,
                self::GENERATE_AI_PORTRAITS => true,
                self::VIEW_AI_COSTS => true,
                self::REQUEST_BOOKING => true,
                self::VIEW_CALENDAR => true,
                self::CONFIRM_BOOKINGS => true,
                self::VIEW_INVOICES => true,
                self::DOWNLOAD_INVOICE_PDF => true,
                self::CREATE_INVOICES => true,
                self::RECORD_PAYMENTS => true,
                self::INVITE_TEAM_MEMBERS => true,
                self::MANAGE_ORG_SETTINGS => true,
            ],
            UserRole::CLIENT_USER->value => [
                self::VIEW_GALLERIES => true,
                self::APPROVE_IMAGES => true,
                self::DOWNLOAD_IMAGES => true,
                self::REQUEST_BOOKING => true,
                self::VIEW_CALENDAR => true,
                self::VIEW_INVOICES => true,
                self::DOWNLOAD_INVOICE_PDF => true,
                self::GENERATE_AI_PORTRAITS => true,
            ],
            UserRole::GUEST_USER->value => [
                self::VIEW_GALLERIES => true, // Gallery-specific override only
                self::APPROVE_IMAGES => true,
                self::DOWNLOAD_IMAGES => true,
                self::GENERATE_AI_PORTRAITS => true,
            ],
            default => []
        };
    }
}
```

## Permission Service

```php
<?php

namespace App\Services;

use App\Models\User;
use App\Enums\UserRole;

class PermissionService
{
    /**
     * Get effective permissions for a user, considering inheritance hierarchy:
     * Organization Defaults → User Overrides → Gallery-Specific Restrictions
     */
    public function getEffectivePermissions(User $user, ?int $galleryId = null): array
    {
        // Start with organization defaults
        $permissions = $this->getOrganizationDefaults($user);

        // Apply user-level overrides (can only restrict, not expand)
        if ($user->role === UserRole::CLIENT_USER->value) {
            $userOverrides = $user->permissions ?? [];
            $permissions = $this->applyUserOverrides($permissions, $userOverrides);
        }

        // Apply gallery-specific restrictions for client users
        if ($galleryId && $user->role === UserRole::CLIENT_USER->value && $user->limit_to_galleries) {
            $permissions = $this->applyGalleryRestrictions($permissions, $user, $galleryId);
        }

        return $permissions;
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(User $user, string $permission, ?int $galleryId = null): bool
    {
        $effectivePermissions = $this->getEffectivePermissions($user, $galleryId);
        return $effectivePermissions[$permission] ?? false;
    }

    /**
     * Get organization permission defaults
     */
    private function getOrganizationDefaults(User $user): array
    {
        if (!$user->organization) {
            return [];
        }

        $orgSettings = $user->organization->settings ?? [];
        $aiEnabled = $orgSettings['ai_enabled'] ?? false;

        return [
            Permissions::VIEW_GALLERIES => $user->organization->permissions['can_view_gallery'] ?? true,
            Permissions::APPROVE_IMAGES => $user->organization->permissions['can_approve_images'] ?? true,
            Permissions::DOWNLOAD_IMAGES => $user->organization->permissions['can_download_images'] ?? true,
            Permissions::ARCHIVE_GALLERY => $user->organization->permissions['can_archive_gallery'] ?? true,
            Permissions::GENERATE_AI_PORTRAITS => $aiEnabled ? ($user->organization->permissions['can_generate_ai_portraits'] ?? true) : false,
            Permissions::DISABLE_AI_PORTRAITS => $user->organization->permissions['can_disable_ai_portraits'] ?? true,
            Permissions::REQUEST_BOOKING => $user->organization->permissions['can_request_booking'] ?? true,
            Permissions::VIEW_CALENDAR => $user->organization->permissions['can_view_calendar'] ?? true,
            Permissions::VIEW_INVOICES => $user->organization->permissions['can_view_invoice'] ?? true,
            Permissions::DOWNLOAD_INVOICE_PDF => $user->organization->permissions['can_export_invoice_pdf'] ?? true,
            Permissions::INVITE_TEAM_MEMBERS => $user->organization->permissions['can_invite_team_members'] ?? true,
            Permissions::MANAGE_ORG_SETTINGS => $user->organization->permissions['can_manage_org_settings'] ?? false,
        ];
    }

    /**
     * Apply user-level overrides (restrictive only)
     */
    private function applyUserOverrides(array $orgDefaults, array $userOverrides): array
    {
        $result = $orgDefaults;

        foreach ($userOverrides as $permission => $allowed) {
            // User can only restrict permissions, not grant new ones
            if (isset($orgDefaults[$permission]) && $allowed === false) {
                $result[$permission] = false;
            }
        }

        return $result;
    }

    /**
     * Apply gallery-specific restrictions
     */
    private function applyGalleryRestrictions(array $permissions, User $user, int $galleryId): array
    {
        if (!$user->allowedGalleries->contains('id', $galleryId)) {
            // User doesn't have access to this specific gallery
            return array_fill_keys(array_keys($permissions), false);
        }

        // Could implement gallery-specific overrides here if needed
        return $permissions;
    }
}
```

## Filament Implementation

### Organization Resource - Permissions Tab

```php
<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Permissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganizationResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Organization Details')
                    ->tabs([
                        // ... Basic Info, Billing, Settings tabs

                        Tab::make('Permissions')
                            ->schema([
                                Section::make('Gallery Permissions')
                                    ->schema([
                                        Checkbox::make('permissions.can_view_gallery')
                                            ->label('Can view galleries')
                                            ->default(true),
                                        Checkbox::make('permissions.can_approve_images')
                                            ->label('Can approve images for marketing')
                                            ->default(true),
                                        Checkbox::make('permissions.can_download_images')
                                            ->label('Can download images')
                                            ->default(true),
                                        Checkbox::make('permissions.can_archive_gallery')
                                            ->label('Can archive galleries')
                                            ->default(true),
                                    ]),
                                    
                                Section::make('AI Portrait Permissions')
                                    ->schema([
                                        Toggle::make('settings.ai_enabled')
                                            ->label('AI portraits enabled for this organization')
                                            ->default(false),
                                        Checkbox::make('permissions.can_generate_ai_portraits')
                                            ->label('Users can generate AI portraits')
                                            ->default(true)
                                            ->visible(fn (Get $get) => $get('settings.ai_enabled')),
                                        Checkbox::make('permissions.can_disable_ai_portraits')
                                            ->label('Users can disable AI for specific subjects')
                                            ->default(true),
                                    ]),
                                    
                                Section::make('Booking Permissions')
                                    ->schema([
                                        Checkbox::make('permissions.can_request_booking')
                                            ->label('Can request bookings')
                                            ->default(true),
                                        Checkbox::make('permissions.can_view_calendar')
                                            ->label('Can view availability calendar')
                                            ->default(true),
                                    ]),
                                    
                                Section::make('Invoice Permissions')
                                    ->schema([
                                        Checkbox::make('permissions.can_view_invoice')
                                            ->label('Can view invoices')
                                            ->default(true),
                                        Checkbox::make('permissions.can_export_invoice_pdf')
                                            ->label('Can download invoice PDFs')
                                            ->default(true),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}

## User Resource - Permissions Tab

php
class UserResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('User Details')
                    ->tabs([
                        Tab::make('Personal Info')
                            ->schema([
                                TextInput::make('name')->required(),
                                TextInput::make('email')->email()->required(),
                                // ... other personal fields
                            ]),

                        Tab::make('Role & Permissions')
                            ->schema([
                                Select::make('role')
                                    ->label('Base Role')
                                    ->options([
                                        'studio_user' => 'Studio User (Photographer)',
                                        'client_user' => 'Client User',
                                        'guest_user' => 'Guest User (Subject)',
                                    ])
                                    ->required()
                                    ->reactive(),
                                
                                Select::make('organization_id')
                                    ->relationship('organization', 'name')
                                    ->visible(fn (Get $get) => $get('role') === 'client_user')
                                    ->required(fn (Get $get) => $get('role') === 'client_user'),
                                
                                // Organization-level permissions for client users
                                Section::make('Organization-Level Permissions')
                                    ->visible(fn (Get $get) => $get('role') === 'client_user')
                                    ->description('These apply to all resources in their organization')
                                    ->schema([
                                        CheckboxList::make('org_permissions')
                                            ->label('Permissions')
                                            ->options([
                                                'can_view_gallery' => 'Can view all organization galleries',
                                                'can_approve_images' => 'Can approve images',
                                                'can_download_images' => 'Can download images',
                                                'can_request_booking' => 'Can request bookings',
                                                'can_view_invoice' => 'Can view invoices',
                                                'can_manage_org_users' => 'Can manage team members',
                                            ])
                                            ->default([
                                                'can_view_gallery',
                                                'can_approve_images',
                                                'can_download_images',
                                            ])
                                            ->columns(2),
                                    ]),
                                
                                *// Gallery-specific permissions*
                                Section::make('Gallery-Specific Permissions')
                                    ->visible(fn (Get $get) => $get('role') === 'client_user')
                                    ->description('Optionally restrict to specific galleries only')
                                    ->schema([
                                        Toggle::make('limit_to_galleries')
                                            ->label('Limit to specific galleries only')
                                            ->reactive(),
                                        
                                        Select::make('allowed_gallery_ids')
                                            ->label('Select Galleries')
                                            ->multiple()
                                            ->relationship('allowedGalleries', 'subject_name')
                                            ->visible(fn (Get $get) => $get('limit_to_galleries'))
                                            ->searchable(),
                                    ]),
                                
                                *// Special roles*
                                Section::make('Special Roles')
                                    ->visible(fn (Get $get) => $get('role') === 'client_user')
                                    ->schema([
                                        Toggle::make('is_billing_contact')
                                            ->label('Billing Contact')
                                            ->helperText('Can view payment details & amounts'),
                                        
                                        Toggle::make('is_marketing_admin')
                                            ->label('Marketing Admin')
                                            ->helperText('Can approve for all campaigns'),
                                    ]),
                                
                                *// Subject (guest) permissions*
                                Section::make('Subject Permissions')
                                    ->visible(fn (Get $get) => $get('role') === 'guest_user')
                                    ->schema([
                                        Select::make('gallery_id')
                                            ->label('Gallery')
                                            ->relationship('gallery', 'subject_name')
                                            ->required(),
                                        
                                        DatePicker::make('access_expires_at')
                                            ->label('Access Expires')
                                            ->default(now()->addDays(30)),
                                        
                                        Toggle::make('ai_enabled')
                                            ->label('AI portraits enabled for this subject')
                                            ->default(false),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}

## Gallery Resource - Subject Access Manager

``` php
class GalleryResource extends Resource
{
    public static function getRelations(): array
    {
        return [
            *// ... other relations*

            RelationManagers\SubjectAccessManager::class,
        ];
    }
}

class SubjectAccessManager extends RelationManager
{
    protected static string $relationship = 'subjectAccess';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('subject_name')
                    ->label('Subject Name')
                    ->disabled(),
                
                TextInput::make('access_code')
                    ->label('Access Code')
                    ->disabled(),
                
                TextInput::make('magic_link')
                    ->label('Magic Link')
                    ->disabled(),
                
                Section::make('Permissions')
                    ->schema([
                        Toggle::make('can_view')
                            ->label('Can view gallery')
                            ->default(true)
                            ->disabled(), *// Always true*
                        
                        Toggle::make('can_rate')
                            ->label('Can rate images')
                            ->default(true),
                        
                        Toggle::make('can_comment')
                            ->label('Can add notes to images')
                            ->default(true),
                        
                        Toggle::make('can_approve')
                            ->label('Can approve for marketing')
                            ->default(true),
                        
                        Toggle::make('can_download')
                            ->label('Can download images')
                            ->default(true),
                        
                        Toggle::make('ai_enabled')
                            ->label('AI portraits enabled')
                            ->helperText('Subject can generate AI portraits')
                            ->default(false),
                    ]),
                
                Section::make('Access Control')
                    ->schema([
                        DateTimePicker::make('access_expires_at')
                            ->label('Magic Link Expires')
                            ->default(now()->addDays(30)),
                        
                        Placeholder::make('generations_used')
                            ->label('AI Generations Used')
                            ->content(fn ($record) => 
                                $record->ai_generations_count . ' of 5 used'
                            ),
                    ]),
            ]);
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject_name'),
                TextColumn::make('access_code'),
                IconColumn::make('ai_enabled')
                    ->boolean(),
                TextColumn::make('access_expires_at')
                    ->dateTime(),
                TextColumn::make('last_accessed_at')
                    ->dateTime()
                    ->label('Last Activity'),
            ])
            ->actions([
                Action::make('sendAccess')
                    ->label('Send Access Email')
                    ->icon('heroicon-o-envelope')
                    ->action(fn ($record) => */* send email */*),
                
                Action::make('extendAccess')
                    ->label('Extend Access')
                    ->icon('heroicon-o-clock')
                    ->form([
                        DatePicker::make('extend_to')
                            ->label('Extend Until')
                            ->default(now()->addDays(30)),
                    ])
                    ->action(fn ($record, $data) => 
                        $record->update(['access_expires_at' => $data['extend_to']])
                    ),
                
                Action::make('revokeAccess')
                    ->label('Revoke Access')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($record) => 
                        $record->update(['access_expires_at' => now()])
                    ),
                
                Actions\EditAction::make(),
            ]);
    }
}

```

---

## **5. Visual Summary**

**Permissions displayed in 3 places:**

1. **Organization Settings** → Default permissions for all org users
2. **Individual User Edit** → Override/restrict org permissions
3. **Gallery Subject Access** → Per-gallery subject permissions

**Hierarchy:**
```
Organization Defaults
  └── User Permissions (can be more restrictive)
       └── Gallery-Specific (subject access)
```

**Example:**
```

UCLA Health Org:
  ✅ can_approve_images (org default)
  
  User: Gabby Rodriguez
    ✅ can_approve_images (inherits from org)
    ✅ can_view_invoice (additional permission)

  User: Dr. Haslam (Subject)
    ✅ can_approve_images (for Gallery *#445 only)*
    ❌ can_view_invoice (not granted)
