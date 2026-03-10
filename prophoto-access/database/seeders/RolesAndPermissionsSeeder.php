<?php

namespace ProPhoto\Access\Database\Seeders;

use Illuminate\Database\Seeder;
use ProPhoto\Access\Permissions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions from the Permissions class
        $permissions = [
            // Galleries - Core
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

            // Galleries - Collections
            'can_create_collection',
            'can_view_collection',
            'can_edit_collection',
            'can_delete_collection',

            // Galleries - Sharing
            'can_create_share_link',
            'can_revoke_share_link',
            'can_view_share_analytics',

            // Galleries - Advanced Features
            'can_export_gallery',
            'can_duplicate_gallery',
            'can_tag_images',
            'can_view_access_logs',
            'can_create_gallery_template',
            'can_manage_gallery_templates',

            // Galleries - Internal Collaboration
            'can_view_internal_comments',
            'can_create_internal_comments',
            
            // AI
            'can_enable_ai_portraits',
            'can_train_ai_model',
            'can_generate_ai_portraits',
            'can_view_ai_portraits',
            'can_download_ai_portraits',
            'can_disable_ai_portraits',
            'can_view_ai_costs',
            
            // Sessions
            'can_create_session',
            'can_view_session',
            'can_edit_session',
            'can_delete_session',
            'can_request_booking',
            'can_confirm_booking',
            'can_deny_booking',
            'can_cancel_session',
            'can_view_calendar',
            
            // Organizations
            'can_create_organization',
            'can_view_organization',
            'can_edit_organization',
            'can_delete_organization',
            'can_manage_org_users',
            'can_manage_org_settings',
            
            // Invoices
            'can_create_invoice',
            'can_view_invoice',
            'can_edit_invoice',
            'can_delete_invoice',
            'can_send_invoice',
            'can_record_payment',
            'can_view_payment_history',
            'can_export_invoice_pdf',
            'can_manage_stripe',
            
            // Users
            'can_create_user',
            'can_view_user',
            'can_edit_user',
            'can_delete_user',
            'can_assign_roles',
            'can_manage_permissions',
            'can_invite_users',
            
            // Messages
            'can_send_message',
            'can_view_messages',
            'can_delete_message',
            'can_manage_notifications',
            
            // System
            'can_manage_studio_settings',
            'can_view_analytics',
            'can_manage_integrations',
            'can_access_staging',
            'can_view_all_data',
        ];
        
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission); // Using findOrCreate to prevent duplicates
        }
        
        // Create roles and assign permissions
        $studioUser = Role::findOrCreate('studio_user');
        $studioUser->givePermissionTo(Permission::all()); // All permissions
        
        $clientUser = Role::findOrCreate('client_user');
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
            // New gallery permissions
            'can_view_collection',
            'can_create_share_link',
            'can_export_gallery',
        ]);
        
        $guestUser = Role::findOrCreate('guest_user');
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
        
        $vendorUser = Role::findOrCreate('vendor_user');
        // Vendors get minimal permissions, mostly contextual
    }
}
