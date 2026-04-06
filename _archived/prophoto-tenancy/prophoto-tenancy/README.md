# ProPhoto Tenancy

Multi-tenancy core for ProPhoto application. Handles studio context resolution, organization context, and admin impersonation.

## Purpose

Provides the foundation for multi-tenant architecture where:
- Multiple photography studios can operate independently
- Each studio can have multiple client organizations
- Admins can impersonate clients/subjects for testing and support
- Context is automatically resolved from subdomain or session

## Key Responsibilities

### Studio Context Resolution
- Detect current studio from subdomain (e.g., `prophoto.davepeloso.com`)
- Middleware: `SetCurrentStudio` - automatically sets studio context for every request
- Scoped queries: All database queries automatically filtered by current studio
- Studio switching: Support for admins managing multiple studios

### Organization Context
- Track which client organization a user is acting on behalf of
- Handle cases where users belong to multiple organizations
- Organization-scoped permissions and data access

### Impersonation System
- Secure admin → client impersonation for support/testing
- Audit trail of all impersonation sessions (start/end/actions)
- Permission preservation: impersonated user's actual permissions apply
- Easy "exit impersonation" mechanism

## Contracts Implemented

- `StudioContextContract` - Get/set current studio
- `OrgContextContract` - Get/set current organization
- `ImpersonationContract` - Start/stop impersonation sessions

## Database Tables

- `studios` - Photography studio accounts
- `organizations` - Client organizations
- `studio_users` - Users belonging to studios
- `organization_users` - Users belonging to organizations
- `impersonation_sessions` - Audit trail of impersonation

## Configuration

**config/tenancy.php**
```php
return [
    'studio_resolution' => 'subdomain', // subdomain | session | header
    'default_studio' => env('DEFAULT_STUDIO_ID'),
    'impersonation' => [
        'enabled' => true,
        'session_timeout' => 3600, // 1 hour
        'require_reason' => true,
    ],
];
```

## Usage Examples

### Get Current Studio
```php
use ProPhoto\Contracts\Tenancy\StudioContextContract;

$studio = app(StudioContextContract::class)->current();
```

### Temporarily Act as Different Studio
```php
$studioContext->forStudio($otherStudio, function () {
    // All queries in this closure are scoped to $otherStudio
    Gallery::all(); // Only galleries for $otherStudio
});
```

### Impersonate User
```php
use ProPhoto\Contracts\Tenancy\ImpersonationContract;

$impersonation = app(ImpersonationContract::class);
$impersonation->start($targetUser, reason: 'Support ticket #1234');

// ... perform actions as $targetUser

$impersonation->stop();
```

## Middleware

- `SetCurrentStudio` - Resolves and sets studio context (runs on every request)
- `RequireStudio` - Ensures a studio is set (403 if not)
- `RequireOrganization` - Ensures organization context is set
- `PreventImpersonation` - Blocks impersonated users from sensitive actions

## Events

- `StudioContextChanged` - Fired when current studio changes
- `ImpersonationStarted` - Fired when admin starts impersonating
- `ImpersonationEnded` - Fired when impersonation session ends

## Future Enhancements

- [ ] Full multi-tenant database separation (separate schemas per studio)
- [ ] Studio-specific subdomain SSL certificates
- [ ] Cross-studio data sharing/permissions
- [ ] Studio marketplace/theme system
- [ ] Billing/subscription per studio

## Dependencies

- `prophoto/contracts` - Tenancy contracts and DTOs
- `prophoto/audit` - Logs all impersonation events

## Testing

```bash
cd prophoto-tenancy
vendor/bin/pest
```

## Notes

- Studio context is set ONCE per request (never changes mid-request)
- Organization context can change during request (e.g., switching between client orgs)
- Impersonation sessions expire after configured timeout
- All impersonation actions are logged to audit trail
