# ProPhoto Settings

Feature flags and settings management system for ProPhoto studios and organizations.

## Purpose

Provides type-safe, cached, hierarchical settings management where:
- Feature flags control what features are enabled per studio/organization
- Settings have defaults, can be overridden at studio/org level
- Type-safe accessors prevent runtime errors
- Cached for performance
- Validated on write

## Key Responsibilities

### Feature Flag Management
- Enable/disable features per studio or organization
- Examples: AI portraits, booking system, Stripe invoicing, bulk downloads
- Runtime checks: `if (Features::enabled('ai_portraits')) { ... }`
- UI: Admin can toggle features in Filament panel

### Studio Settings
- Studio-wide configuration (defaults for all galleries/orgs)
- Examples:
  - Branding: logo, colors, domain
  - Defaults: watermark settings, download permissions
  - Integrations: Stripe keys, ImageKit credentials
  - Quotas: max galleries, max storage, max AI generations

### Organization Settings
- Organization-specific overrides
- Inherit from studio defaults, can override
- Examples:
  - Client-specific branding
  - Custom download permissions
  - Notification preferences

### Type-Safe Accessors
- Avoid raw JSON access everywhere
- Type-safe getters: `settings()->getInt('max_galleries')`
- IDE autocomplete for all settings
- Validation on write

## Contracts Implemented

- `SettingsRepositoryContract` - Get/set settings
- `FeatureFlagContract` - Check if feature enabled

## Database Tables

- `studio_settings` - Studio-level settings (key-value)
- `organization_settings` - Organization-level settings (key-value)
- `feature_flags` - Feature flag states per studio/org

## Configuration

**config/settings.php**
```php
return [
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'prefix' => 'prophoto_settings',
    ],

    'defaults' => [
        // Studio defaults
        'max_galleries' => 100,
        'max_storage_gb' => 50,
        'watermark_enabled' => false,
        'download_permission' => 'client_team',
        'ai_generation_quota' => 100,
        'session_types' => ['portrait', 'wedding', 'event', 'commercial'],
    ],

    'features' => [
        'ai_portraits' => [
            'label' => 'AI Portrait Generation',
            'description' => 'Enable AI-powered portrait generation for subjects',
            'default' => false,
            'requires' => ['stripe_billing'], // Dependent features
        ],
        'booking' => [
            'label' => 'Booking System',
            'description' => 'Allow clients to request and book sessions',
            'default' => true,
        ],
        'stripe_invoicing' => [
            'label' => 'Stripe Invoicing',
            'description' => 'Generate and send invoices via Stripe',
            'default' => false,
        ],
        'bulk_download' => [
            'label' => 'Bulk Downloads',
            'description' => 'Allow ZIP downloads of multiple images',
            'default' => true,
        ],
        'google_calendar_sync' => [
            'label' => 'Google Calendar Sync',
            'description' => 'Sync bookings to Google Calendar',
            'default' => false,
        ],
        'marketing_approvals' => [
            'label' => 'Marketing Approvals',
            'description' => 'Collect marketing use approvals from subjects',
            'default' => true,
        ],
    ],
];
```

## Usage Examples

### Check Feature Flag
```php
use ProPhoto\Contracts\Settings\FeatureFlagContract;

$features = app(FeatureFlagContract::class);

if ($features->enabled('ai_portraits')) {
    // Show AI portrait generation UI
}

// Check with context
if ($features->enabled('booking', organizationId: 5)) {
    // This specific organization has booking enabled
}
```

### Get Settings (Type-Safe)
```php
use ProPhoto\Contracts\Settings\SettingsRepositoryContract;

$settings = app(SettingsRepositoryContract::class);

// Studio settings
$maxGalleries = $settings->getInt('max_galleries'); // Returns int
$watermark = $settings->getBool('watermark_enabled'); // Returns bool
$sessionTypes = $settings->getArray('session_types'); // Returns array
$logo = $settings->getString('studio_logo_url'); // Returns string

// Organization settings (with fallback to studio defaults)
$downloadPerm = $settings->getForOrganization($orgId, 'download_permission');
```

### Set Settings
```php
// Studio level
$settings->set('max_galleries', 200);
$settings->set('watermark_enabled', true);

// Organization level (override)
$settings->setForOrganization($orgId, 'watermark_enabled', false);
```

### Bulk Get/Set
```php
// Get all settings for studio
$all = $settings->all();

// Get multiple
$config = $settings->getMany(['max_galleries', 'watermark_enabled', 'ai_generation_quota']);

// Bulk set
$settings->setMany([
    'max_galleries' => 500,
    'max_storage_gb' => 100,
    'watermark_enabled' => true,
]);
```

### Blade Directive
```blade
@feature('ai_portraits')
    <button>Generate AI Portraits</button>
@endfeature

@feature('booking', $organization->id)
    <a href="/bookings/create">Book a Session</a>
@endfeature
```

### Validation
```php
// Settings are validated on write
$settings->set('max_galleries', 'not-a-number'); // Throws ValidationException

// Custom validation rules
$settings->define('email_from', [
    'type' => 'string',
    'rules' => ['required', 'email'],
]);
```

## Settings Schema

Settings are organized into groups:

### Branding
- `studio_name`
- `studio_logo_url`
- `primary_color`
- `secondary_color`
- `custom_domain`

### Defaults
- `watermark_enabled`
- `watermark_text`
- `download_permission` (enum: subject_only, client_team, admin, public)
- `default_gallery_visibility` (enum: private, unlisted, public)

### Quotas & Limits
- `max_galleries`
- `max_storage_gb`
- `max_users_per_org`
- `ai_generation_quota`
- `max_concurrent_uploads`

### Integrations
- `imagekit_public_key`
- `imagekit_private_key`
- `stripe_publishable_key`
- `stripe_secret_key`
- `google_calendar_enabled`
- `google_calendar_id`

### Notifications
- `email_from_address`
- `email_from_name`
- `notify_on_new_booking`
- `notify_on_image_approval`
- `notify_on_invoice_paid`

## Filament Integration

Settings panel automatically generated:

```php
// Studio admin sees settings page with:
// - Feature flags (toggles)
// - Branding settings (text inputs, color pickers)
// - Quotas (number inputs)
// - Integrations (secure text inputs for API keys)
```

## Caching Strategy

- Settings cached per studio/org
- Cache invalidated on write
- Fallback to database if cache miss
- Eager load common settings on app boot

## Events

- `SettingChanged` - Setting value changed
- `FeatureEnabled` - Feature flag enabled
- `FeatureDisabled` - Feature flag disabled

## Future Enhancements

- [ ] Settings history/audit trail
- [ ] Settings import/export
- [ ] Settings templates (presets)
- [ ] Environment-specific overrides (staging vs production)
- [ ] Settings validation dashboard

## Dependencies

- `prophoto/contracts` - Settings contracts and DTOs
- `prophoto/tenancy` - Studio/org context
- `prophoto/audit` - Log setting changes

## Testing

```bash
cd prophoto-settings
vendor/bin/pest
```

## Notes

- All API keys/secrets stored encrypted
- Settings cached aggressively (clear cache on write)
- Feature flags can have dependencies (enabling X requires Y)
- Organization settings inherit from studio, can override
- Type-safe accessors prevent common bugs
