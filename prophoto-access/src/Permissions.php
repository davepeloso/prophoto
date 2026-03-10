<?php

namespace ProPhoto\Access;

use ProPhoto\Access\Enums\UserRole;

class Permissions
{
    // Gallery Permissions - Core
    public const CREATE_GALLERY = 'can_create_gallery';
    public const VIEW_GALLERIES = 'can_view_gallery';
    public const EDIT_GALLERY = 'can_edit_gallery';
    public const DELETE_GALLERY = 'can_delete_gallery';
    public const ARCHIVE_GALLERY = 'can_archive_gallery';
    public const UPLOAD_IMAGES = 'can_upload_images';
    public const DELETE_IMAGES = 'can_delete_images';
    public const DOWNLOAD_IMAGES = 'can_download_images';
    public const SHARE_GALLERY = 'can_share_gallery';
    public const APPROVE_IMAGES = 'can_approve_images';
    public const RATE_IMAGES = 'can_rate_images';
    public const COMMENT_ON_IMAGES = 'can_comment_on_images';
    public const REQUEST_EDITS = 'can_request_edits';
    public const MARK_GALLERY_COMPLETE = 'can_mark_gallery_complete';

    // Gallery Permissions - Collections
    public const CREATE_COLLECTION = 'can_create_collection';
    public const VIEW_COLLECTION = 'can_view_collection';
    public const EDIT_COLLECTION = 'can_edit_collection';
    public const DELETE_COLLECTION = 'can_delete_collection';

    // Gallery Permissions - Sharing
    public const CREATE_SHARE_LINK = 'can_create_share_link';
    public const REVOKE_SHARE_LINK = 'can_revoke_share_link';
    public const VIEW_SHARE_ANALYTICS = 'can_view_share_analytics';

    // Gallery Permissions - Advanced Features
    public const EXPORT_GALLERY = 'can_export_gallery';
    public const DUPLICATE_GALLERY = 'can_duplicate_gallery';
    public const TAG_IMAGES = 'can_tag_images';
    public const VIEW_ACCESS_LOGS = 'can_view_access_logs';
    public const CREATE_GALLERY_TEMPLATE = 'can_create_gallery_template';
    public const MANAGE_GALLERY_TEMPLATES = 'can_manage_gallery_templates';

    // Gallery Permissions - Internal Collaboration
    public const VIEW_INTERNAL_COMMENTS = 'can_view_internal_comments';
    public const CREATE_INTERNAL_COMMENTS = 'can_create_internal_comments';

    // AI Portrait Permissions
    public const ENABLE_AI_PORTRAITS = 'can_enable_ai_portraits';
    public const TRAIN_AI_MODEL = 'can_train_ai_model';
    public const GENERATE_AI_PORTRAITS = 'can_generate_ai_portraits';
    public const VIEW_AI_PORTRAITS = 'can_view_ai_portraits';
    public const DOWNLOAD_AI_PORTRAITS = 'can_download_ai_portraits';
    public const DISABLE_AI_PORTRAITS = 'can_disable_ai_portraits';
    public const VIEW_AI_COSTS = 'can_view_ai_costs';

    // Sessions & Booking Permissions
    public const CREATE_SESSION = 'can_create_session';
    public const VIEW_SESSION = 'can_view_session';
    public const EDIT_SESSION = 'can_edit_session';
    public const DELETE_SESSION = 'can_delete_session';
    public const REQUEST_BOOKING = 'can_request_booking';
    public const CONFIRM_BOOKING = 'can_confirm_booking';
    public const DENY_BOOKING = 'can_deny_booking';
    public const CANCEL_SESSION = 'can_cancel_session';
    public const VIEW_CALENDAR = 'can_view_calendar';

    // Organizations Permissions
    public const CREATE_ORGANIZATION = 'can_create_organization';
    public const VIEW_ORGANIZATION = 'can_view_organization';
    public const EDIT_ORGANIZATION = 'can_edit_organization';
    public const DELETE_ORGANIZATION = 'can_delete_organization';
    public const MANAGE_ORG_USERS = 'can_manage_org_users';
    public const MANAGE_ORG_SETTINGS = 'can_manage_org_settings';

    // Invoicing & Payments Permissions
    public const CREATE_INVOICE = 'can_create_invoice';
    public const VIEW_INVOICES = 'can_view_invoice';
    public const EDIT_INVOICE = 'can_edit_invoice';
    public const DELETE_INVOICE = 'can_delete_invoice';
    public const SEND_INVOICE = 'can_send_invoice';
    public const RECORD_PAYMENTS = 'can_record_payment';
    public const VIEW_PAYMENT_HISTORY = 'can_view_payment_history';
    public const DOWNLOAD_INVOICE_PDF = 'can_export_invoice_pdf';
    public const MANAGE_STRIPE = 'can_manage_stripe';

    // Users & Permissions Management
    public const CREATE_USER = 'can_create_user';
    public const VIEW_USER = 'can_view_user';
    public const EDIT_USER = 'can_edit_user';
    public const DELETE_USER = 'can_delete_user';
    public const ASSIGN_ROLES = 'can_assign_roles';
    public const MANAGE_PERMISSIONS = 'can_manage_permissions';
    public const INVITE_USERS = 'can_invite_users';

    // Messages & Notifications Permissions
    public const SEND_MESSAGE = 'can_send_message';
    public const VIEW_MESSAGES = 'can_view_messages';
    public const DELETE_MESSAGE = 'can_delete_message';
    public const MANAGE_NOTIFICATIONS = 'can_manage_notifications';

    // System & Studio Permissions
    public const MANAGE_STUDIO_SETTINGS = 'can_manage_studio_settings';
    public const VIEW_ANALYTICS = 'can_view_analytics';
    public const MANAGE_INTEGRATIONS = 'can_manage_integrations';
    public const ACCESS_STAGING = 'can_access_staging';
    public const VIEW_ALL_DATA = 'can_view_all_data';

    // Special Roles
    public const IS_BILLING_CONTACT = 'is_billing_contact';
    public const IS_MARKETING_ADMIN = 'is_marketing_admin';

    public static function labels(): array
    {
        return [
            self::CREATE_GALLERY => 'Can create new galleries',
            self::VIEW_GALLERIES => 'Can view gallery contents',
            self::EDIT_GALLERY => 'Can edit gallery details',
            self::DELETE_GALLERY => 'Can delete galleries',
            self::ARCHIVE_GALLERY => 'Can archive galleries',
            self::UPLOAD_IMAGES => 'Can upload images to gallery',
            self::DELETE_IMAGES => 'Can delete images from gallery',
            self::DOWNLOAD_IMAGES => 'Can download images',
            self::SHARE_GALLERY => 'Can generate/manage share links',
            self::APPROVE_IMAGES => 'Can approve images for marketing',
            self::RATE_IMAGES => 'Can rate images (1-5 stars)',
            self::COMMENT_ON_IMAGES => 'Can add notes to images',
            self::REQUEST_EDITS => 'Can request image edits',
            self::MARK_GALLERY_COMPLETE => 'Can mark gallery as complete',

            self::CREATE_COLLECTION => 'Can create gallery collections (albums/portfolios)',
            self::VIEW_COLLECTION => 'Can view gallery collections',
            self::EDIT_COLLECTION => 'Can edit gallery collections',
            self::DELETE_COLLECTION => 'Can delete gallery collections',

            self::CREATE_SHARE_LINK => 'Can create share links for galleries',
            self::REVOKE_SHARE_LINK => 'Can revoke/delete share links',
            self::VIEW_SHARE_ANALYTICS => 'Can view share analytics (who accessed, when)',

            self::EXPORT_GALLERY => 'Can export galleries (zip download)',
            self::DUPLICATE_GALLERY => 'Can duplicate/clone galleries',
            self::TAG_IMAGES => 'Can tag/categorize images',
            self::VIEW_ACCESS_LOGS => 'Can view gallery access logs and analytics',
            self::CREATE_GALLERY_TEMPLATE => 'Can create reusable gallery templates',
            self::MANAGE_GALLERY_TEMPLATES => 'Can manage gallery templates',

            self::VIEW_INTERNAL_COMMENTS => 'Can view internal gallery comments/notes',
            self::CREATE_INTERNAL_COMMENTS => 'Can create internal gallery comments/notes',

            self::ENABLE_AI_PORTRAITS => 'Can enable AI for galleries',
            self::TRAIN_AI_MODEL => 'Can initiate model training',
            self::GENERATE_AI_PORTRAITS => 'Can generate portraits from trained model',
            self::VIEW_AI_PORTRAITS => 'Can view generated portraits',
            self::DOWNLOAD_AI_PORTRAITS => 'Can download AI portraits',
            self::DISABLE_AI_PORTRAITS => 'Can disable AI for subjects (client override)',
            self::VIEW_AI_COSTS => 'Can view AI generation costs',

            self::CREATE_SESSION => 'Can create sessions',
            self::VIEW_SESSION => 'Can view session details',
            self::EDIT_SESSION => 'Can edit session details',
            self::DELETE_SESSION => 'Can delete sessions',
            self::REQUEST_BOOKING => 'Can request booking (client)',
            self::CONFIRM_BOOKING => 'Can confirm booking requests',
            self::DENY_BOOKING => 'Can deny booking requests',
            self::CANCEL_SESSION => 'Can cancel scheduled sessions',
            self::VIEW_CALENDAR => 'Can view calendar/availability',

            self::CREATE_ORGANIZATION => 'Can create client organizations',
            self::VIEW_ORGANIZATION => 'Can view organization details',
            self::EDIT_ORGANIZATION => 'Can edit organization details',
            self::DELETE_ORGANIZATION => 'Can delete organizations',
            self::MANAGE_ORG_USERS => 'Can add/remove users from org',
            self::MANAGE_ORG_SETTINGS => 'Can edit org settings/preferences',

            self::CREATE_INVOICE => 'Can create invoices',
            self::VIEW_INVOICES => 'Can view invoice details',
            self::EDIT_INVOICE => 'Can edit invoice details',
            self::DELETE_INVOICE => 'Can delete invoices',
            self::SEND_INVOICE => 'Can send invoice to client',
            self::RECORD_PAYMENTS => 'Can record manual payments',
            self::VIEW_PAYMENT_HISTORY => 'Can view payment history',
            self::DOWNLOAD_INVOICE_PDF => 'Can download invoice PDFs',
            self::MANAGE_STRIPE => 'Can manage Stripe integration',

            self::CREATE_USER => 'Can create new users',
            self::VIEW_USER => 'Can view user details',
            self::EDIT_USER => 'Can edit user details',
            self::DELETE_USER => 'Can delete users',
            self::ASSIGN_ROLES => 'Can assign roles to users',
            self::MANAGE_PERMISSIONS => 'Can manage contextual permissions',
            self::INVITE_USERS => 'Can send user invitations',

            self::SEND_MESSAGE => 'Can send messages',
            self::VIEW_MESSAGES => 'Can view messages',
            self::DELETE_MESSAGE => 'Can delete messages',
            self::MANAGE_NOTIFICATIONS => 'Can configure notification preferences',

            self::MANAGE_STUDIO_SETTINGS => 'Can edit studio settings',
            self::VIEW_ANALYTICS => 'Can view reports/analytics',
            self::MANAGE_INTEGRATIONS => 'Can manage API integrations',
            self::ACCESS_STAGING => 'Can access ingest/staging interface',
            self::VIEW_ALL_DATA => 'Can view all data (super admin)',

            self::IS_BILLING_CONTACT => 'Is a billing contact',
            self::IS_MARKETING_ADMIN => 'Is a marketing admin',
        ];
    }

    public static function getRoleDefaults(string $role): array
    {
        return match($role) {
            UserRole::STUDIO_USER->value => array_fill_keys(array_keys(static::labels()), true), // Studio users have all permissions
            UserRole::CLIENT_USER->value => [
                self::VIEW_GALLERIES => true,
                self::DOWNLOAD_IMAGES => true,
                self::APPROVE_IMAGES => true,
                self::RATE_IMAGES => true,
                self::COMMENT_ON_IMAGES => true,
                self::GENERATE_AI_PORTRAITS => true,
                self::VIEW_AI_PORTRAITS => true,
                self::DISABLE_AI_PORTRAITS => true,
                self::REQUEST_BOOKING => true,
                self::VIEW_CALENDAR => true,
                self::VIEW_ORGANIZATION => true,
                self::MANAGE_ORG_USERS => true,
                self::MANAGE_ORG_SETTINGS => true,
                self::VIEW_INVOICES => true,
                self::DOWNLOAD_INVOICE_PDF => true,
                self::INVITE_USERS => true,
                self::SEND_MESSAGE => true,
                self::VIEW_MESSAGES => true,
                self::MANAGE_NOTIFICATIONS => true,
                // New gallery permissions for client users
                self::VIEW_COLLECTION => true,
                self::CREATE_SHARE_LINK => true,
                self::EXPORT_GALLERY => true,
            ],
            UserRole::GUEST_USER->value => [
                self::VIEW_GALLERIES => true, // Gallery-specific override only
                self::DOWNLOAD_IMAGES => true,
                self::APPROVE_IMAGES => true,
                self::RATE_IMAGES => true,
                self::COMMENT_ON_IMAGES => true,
                self::REQUEST_EDITS => true,
                self::GENERATE_AI_PORTRAITS => true,
                self::VIEW_AI_PORTRAITS => true,
                self::SHARE_GALLERY => true,
            ],
            UserRole::VENDOR_USER->value => [
                // Minimal permissions, mostly contextual
                // Add specific permissions here as needed, e.g., view sessions related to their work
            ],
            default => []
        };
    }
}
