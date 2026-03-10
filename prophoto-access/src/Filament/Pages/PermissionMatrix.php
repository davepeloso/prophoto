<?php

namespace ProPhoto\Access\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionMatrix extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-table-cells';

    protected static string|null|\UnitEnum $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Permission Matrix';

    protected static ?string $navigationLabel = 'Permission Matrix';

    protected string $view = 'prophoto-access::filament.pages.permission-matrix';

    public array $roles = [];

    public array $permissions = [];

    public array $matrix = [];

    public array $categories = [];

    public function mount(): void
    {
        $this->loadMatrix();
    }

    protected function loadMatrix(): void
    {
        $this->roles = Role::orderBy('name')->pluck('name', 'id')->toArray();

        $permissions = Permission::orderBy('name')->get();

        // Group permissions by category
        $this->categories = [];
        foreach ($permissions as $permission) {
            $category = $this->getCategory($permission->name);
            if (! isset($this->categories[$category])) {
                $this->categories[$category] = [];
            }
            $this->categories[$category][] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $this->getDescription($permission->name),
            ];
        }

        // Build the matrix
        $this->matrix = [];
        foreach (Role::with('permissions')->get() as $role) {
            $this->matrix[$role->id] = $role->permissions->pluck('id')->toArray();
        }
    }

    public function togglePermission(int $roleId, int $permissionId): void
    {
        $role = Role::findById($roleId);
        $permission = Permission::findById($permissionId);

        if ($role->hasPermissionTo($permission)) {
            $role->revokePermissionTo($permission);
        } else {
            $role->givePermissionTo($permission);
        }

        $this->loadMatrix();
        Notification::make()
            ->title("Updated '{$permission->name}' for '{$role->name}'")
            ->success()
            ->send();
    }

    public function grantAllInCategory(int $roleId, string $category): void
    {
        $role = Role::findById($roleId);

        foreach ($this->categories[$category] as $permission) {
            $role->givePermissionTo($permission['name']);
        }

        $this->loadMatrix();
        Notification::make()
            ->title("Granted all '{$category}' permissions to '{$role->name}'")
            ->success()
            ->send();
    }

    public function revokeAllInCategory(int $roleId, string $category): void
    {
        $role = Role::findById($roleId);

        foreach ($this->categories[$category] as $permission) {
            $role->revokePermissionTo($permission['name']);
        }

        $this->loadMatrix();
        Notification::make()
            ->title("Revoked all '{$category}' permissions from '{$role->name}'")
            ->success()
            ->send();
    }

    protected function getCategory(string $name): string
    {
        return match (true) {
            str_contains($name, 'gallery') || str_contains($name, 'image') || str_contains($name, 'collection') || str_contains($name, 'share') || str_contains($name, 'template') || str_contains($name, 'tag') || str_contains($name, 'comment') || str_contains($name, 'access_log') => 'Galleries & Images',
            str_contains($name, 'ai') => 'AI Portraits',
            str_contains($name, 'session') || str_contains($name, 'booking') || str_contains($name, 'calendar') => 'Sessions & Bookings',
            str_contains($name, 'organization') || str_contains($name, 'org_') => 'Organizations',
            str_contains($name, 'invoice') || str_contains($name, 'payment') || str_contains($name, 'stripe') => 'Invoices & Payments',
            str_contains($name, 'user') || str_contains($name, 'role') || str_contains($name, 'permission') || str_contains($name, 'invite') => 'Users & Roles',
            str_contains($name, 'message') || str_contains($name, 'notification') => 'Messages',
            str_contains($name, 'studio') || str_contains($name, 'analytics') || str_contains($name, 'integration') || str_contains($name, 'staging') => 'System',
            default => 'Other',
        };
    }

    protected function getDescription(string $name): string
    {
        $descriptions = [
            // Core Gallery Permissions
            'can_create_gallery' => 'Create new galleries',
            'can_view_gallery' => 'View galleries',
            'can_edit_gallery' => 'Edit galleries',
            'can_delete_gallery' => 'Delete galleries',
            'can_archive_gallery' => 'Archive galleries',
            'can_upload_images' => 'Upload images',
            'can_delete_images' => 'Delete images',
            'can_download_images' => 'Download images',
            'can_share_gallery' => 'Share galleries',
            'can_approve_images' => 'Approve for marketing',
            'can_rate_images' => 'Rate images',
            'can_comment_on_images' => 'Comment on images',
            'can_request_edits' => 'Request edits',
            'can_mark_gallery_complete' => 'Mark complete',

            // Gallery Collections
            'can_create_collection' => 'Create collections',
            'can_view_collection' => 'View collections',
            'can_edit_collection' => 'Edit collections',
            'can_delete_collection' => 'Delete collections',

            // Gallery Sharing
            'can_create_share_link' => 'Create share links',
            'can_revoke_share_link' => 'Revoke share links',
            'can_view_share_analytics' => 'View share analytics',

            // Advanced Gallery Features
            'can_export_gallery' => 'Export gallery',
            'can_duplicate_gallery' => 'Duplicate gallery',
            'can_tag_images' => 'Tag images',
            'can_view_access_logs' => 'View access logs',
            'can_create_gallery_template' => 'Create templates',
            'can_manage_gallery_templates' => 'Manage templates',

            // Internal Collaboration
            'can_view_internal_comments' => 'View internal comments',
            'can_create_internal_comments' => 'Create internal comments',
            'can_enable_ai_portraits' => 'Enable AI',
            'can_train_ai_model' => 'Train AI model',
            'can_generate_ai_portraits' => 'Generate portraits',
            'can_view_ai_portraits' => 'View portraits',
            'can_download_ai_portraits' => 'Download portraits',
            'can_disable_ai_portraits' => 'Disable AI',
            'can_view_ai_costs' => 'View AI costs',
            'can_create_session' => 'Create sessions',
            'can_view_session' => 'View sessions',
            'can_edit_session' => 'Edit sessions',
            'can_delete_session' => 'Delete sessions',
            'can_request_booking' => 'Request bookings',
            'can_confirm_booking' => 'Confirm bookings',
            'can_deny_booking' => 'Deny bookings',
            'can_cancel_session' => 'Cancel sessions',
            'can_view_calendar' => 'View calendar',
            'can_create_organization' => 'Create organizations',
            'can_view_organization' => 'View organizations',
            'can_edit_organization' => 'Edit organizations',
            'can_delete_organization' => 'Delete organizations',
            'can_manage_org_users' => 'Manage org users',
            'can_manage_org_settings' => 'Manage org settings',
            'can_create_invoice' => 'Create invoices',
            'can_view_invoice' => 'View invoices',
            'can_edit_invoice' => 'Edit invoices',
            'can_delete_invoice' => 'Delete invoices',
            'can_send_invoice' => 'Send invoices',
            'can_record_payment' => 'Record payments',
            'can_view_payment_history' => 'View payments',
            'can_export_invoice_pdf' => 'Export PDF',
            'can_manage_stripe' => 'Manage Stripe',
            'can_create_user' => 'Create users',
            'can_view_user' => 'View users',
            'can_edit_user' => 'Edit users',
            'can_delete_user' => 'Delete users',
            'can_assign_roles' => 'Assign roles',
            'can_manage_permissions' => 'Manage permissions',
            'can_invite_users' => 'Invite users',
            'can_send_message' => 'Send messages',
            'can_view_messages' => 'View messages',
            'can_delete_message' => 'Delete messages',
            'can_manage_notifications' => 'Manage notifications',
            'can_manage_studio_settings' => 'Studio settings',
            'can_view_analytics' => 'View analytics',
            'can_manage_integrations' => 'Manage integrations',
            'can_access_staging' => 'Access staging',
            'can_view_all_data' => 'View all data',
        ];

        return $descriptions[$name] ?? $name;
    }

    protected function getCategoryColor(string $category): string
    {
        return match ($category) {
            'Galleries & Images' => 'emerald',
            'AI Portraits' => 'purple',
            'Sessions & Bookings' => 'sky',
            'Organizations' => 'amber',
            'Invoices & Payments' => 'rose',
            'Users & Roles' => 'slate',
            'Messages' => 'indigo',
            'System' => 'zinc',
            default => 'gray',
        };
    }

    protected function getRoleColor(string $role): string
    {
        return match ($role) {
            'studio_user' => 'success',
            'client_user' => 'info',
            'guest_user' => 'warning',
            'vendor_user' => 'gray',
            default => 'primary',
        };
    }
}
