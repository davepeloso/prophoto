# ProPhoto Permissions

Contextual permissions and role management system for ProPhoto. Extends Spatie Laravel Permission with studio and organization context.

## Purpose

Provides fine-grained, contextual permission management where:
- Users can have different roles in different studios/organizations
- Permissions are contextual: "Can this user manage THIS gallery?" (not just "Can user manage galleries?")
- Invitation system for adding team members to studios/organizations
- Policy matrix maps routes/resources to permission checks

## Key Responsibilities

### Contextual Permission Checking
- Beyond simple role checks: permissions tied to specific resources
- "User can edit Gallery #123" vs "User can edit any gallery"
- Organization-scoped: user manages Client A but not Client B
- Gallery-scoped: user can view/edit specific galleries only

### Role Management
- **Studio roles**: Admin, Photographer, Assistant, Viewer
- **Organization roles**: Admin, Member, Viewer
- **Subject roles**: Self (can only see own images)
- Role inheritance: Studio Admin inherits all Organization permissions

### Invitation System
- Email-based invitations with secure tokens
- "Invite user to studio as Photographer"
- "Invite client to organization as Admin"
- Invitation workflow: sent → pending → accepted/expired/revoked

### Policy Matrix
- Central definition of "what permission is required for what action"
- Maps routes → abilities → policies
- Example: `POST /galleries/{gallery}/assets` requires `upload_to_gallery` ability

## Contracts Implemented

- `PermissionCheckerContract` - Check if user has permission for action
- `PolicyMatrixContract` - Get required permissions for routes/resources
- `InvitationContract` - Send/manage invitations

## Database Tables

- `roles` - Role definitions (extends Spatie)
- `permissions` - Permission definitions (extends Spatie)
- `model_has_roles` - User-role assignments with context (studio_id, org_id)
- `model_has_permissions` - Direct permission grants
- `invitations` - Pending team invitations
- `permission_contexts` - Contextual permission overrides (e.g., "user can edit gallery #5")

## Configuration

**config/permissions.php**
```php
return [
    'roles' => [
        'studio' => ['admin', 'photographer', 'assistant', 'viewer'],
        'organization' => ['admin', 'member', 'viewer'],
    ],

    'abilities' => [
        'view_gallery',
        'create_gallery',
        'edit_gallery',
        'delete_gallery',
        'upload_to_gallery',
        'download_from_gallery',
        'approve_images',
        'create_booking',
        'manage_invoices',
    ],

    'invitations' => [
        'expiry_days' => 7,
        'allow_resend' => true,
        'max_pending_per_email' => 3,
    ],
];
```

## Usage Examples

### Check Contextual Permission
```php
use ProPhoto\Contracts\Access\PermissionCheckerContract;

$checker = app(PermissionCheckerContract::class);

// Simple: Does user have ability?
if ($checker->can($user, 'view_galleries')) {
    // ...
}

// Contextual: Can user edit THIS specific gallery?
if ($checker->can($user, 'edit_gallery', $gallery)) {
    // ...
}

// Contextual with organization
if ($checker->can($user, 'manage_bookings', context: ['organization_id' => 5])) {
    // ...
}
```

### Grant Contextual Permission
```php
use ProPhoto\Permissions\Services\PermissionGranter;

$granter = app(PermissionGranter::class);

// Grant user permission to specific gallery
$granter->grant($user, 'edit_gallery', $gallery);

// Grant role within organization
$granter->assignRole($user, 'admin', organizationId: 5);
```

### Send Invitation
```php
use ProPhoto\Contracts\Access\InvitationContract;

$invitations = app(InvitationContract::class);

$invitation = $invitations->send([
    'email' => 'photographer@example.com',
    'role' => 'photographer',
    'studio_id' => $currentStudio->id,
    'invited_by' => auth()->id(),
    'message' => 'Join our studio team!',
]);

// Returns invitation with secure token
// Email sent automatically via event listener
```

### Policy in Controller
```php
use ProPhoto\Http\Controllers\GalleryController;

class GalleryController extends Controller
{
    public function update(Gallery $gallery)
    {
        $this->authorize('edit_gallery', $gallery);

        // User has permission to edit this specific gallery
        $gallery->update(...);
    }
}
```

## Policy Matrix

Maps routes/actions to required abilities:

| Route | Method | Ability | Context |
|-------|--------|---------|---------|
| `/galleries` | GET | `view_galleries` | Studio |
| `/galleries/{gallery}` | GET | `view_gallery` | Gallery |
| `/galleries/{gallery}` | PUT | `edit_gallery` | Gallery |
| `/galleries/{gallery}/assets` | POST | `upload_to_gallery` | Gallery |
| `/bookings` | POST | `create_booking` | Organization |
| `/invoices` | GET | `view_invoices` | Organization |

## Middleware

- `CheckPermission:ability` - Require ability for route
- `CheckPermission:ability,context` - Require contextual permission
- `RequireStudioRole:admin` - Require studio role
- `RequireOrgRole:admin` - Require organization role

## Events

- `InvitationSent` - Invitation email sent
- `InvitationAccepted` - User accepted invitation
- `InvitationExpired` - Invitation expired
- `PermissionGranted` - Permission granted to user
- `PermissionRevoked` - Permission removed from user
- `RoleAssigned` - Role assigned to user
- `RoleRemoved` - Role removed from user

## Gates & Policies

Automatically registers Laravel gates for all abilities:

```php
// In any controller/service
Gate::allows('edit_gallery', $gallery);

// Or using authorize
$this->authorize('edit_gallery', $gallery);
```

## Future Enhancements

- [ ] Permission templates (e.g., "Wedding Package" = specific set of abilities)
- [ ] Time-based permissions (temporary access)
- [ ] IP-based restrictions
- [ ] 2FA requirement for sensitive permissions
- [ ] Permission audit log (who granted what to whom)

## Dependencies

- `prophoto/contracts` - Permission contracts and DTOs
- `prophoto/tenancy` - Studio/org context for permissions
- `prophoto/notifications` - Send invitation emails
- `prophoto/audit` - Log permission changes
- `spatie/laravel-permission` - Base permission system

## Testing

```bash
cd prophoto-permissions
vendor/bin/pest
```

## Notes

- Permissions are ALWAYS checked within studio/org context
- Subject users have highly restricted permissions (view own images only)
- Invitation tokens are single-use and expire after configured days
- Permission checks are cached per request for performance
