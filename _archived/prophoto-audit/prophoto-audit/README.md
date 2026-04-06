# ProPhoto Audit

Comprehensive activity logging and audit trail system for ProPhoto. Listens to events from all packages and creates searchable audit logs.

## Purpose

Provides centralized audit logging where:
- Every significant action is logged (gallery actions, downloads, approvals, payments, etc.)
- "Who did what, when, where, and why" is always answerable
- Audit logs displayed in Filament "Activity" tabs
- Compliance and debugging support
- Security monitoring

## Key Responsibilities

### Event-Based Activity Logging
- Listens to events from ALL ProPhoto packages
- Automatically logs: creates, updates, deletes, downloads, approvals, payments, etc.
- Captures: actor (who), action (what), subject (what resource), context (studio/org), metadata (details)

### Searchable Audit Trail
- Query by: actor, action, resource type, resource ID, date range, studio, organization
- Filter by: severity, success/failure, IP address
- Export audit logs for compliance

### Filament Integration
- "Activity" tab on every Filament resource (galleries, bookings, invoices, etc.)
- Shows recent activity for that specific resource
- Example: Gallery detail page shows "Image uploaded", "Subject approved 5 images", "ZIP downloaded"

### Security Monitoring
- Track failed login attempts
- Track permission denials
- Track sensitive actions (impersonation, permission changes, setting changes)
- Alert on suspicious patterns

## Contracts Implemented

- `AuditLoggerContract` - Log activity events
- `ActivityQueryContract` - Query audit trail

## Database Tables

- `audit_logs` - Main audit trail (polymorphic, relates to any model)
  - `id`
  - `studio_id` (which studio)
  - `organization_id` (which org, nullable)
  - `user_id` (who performed action, nullable for system events)
  - `action` (created, updated, deleted, downloaded, approved, etc.)
  - `auditable_type` (Gallery, Booking, Invoice, etc.)
  - `auditable_id` (which specific resource)
  - `metadata` (JSON: old values, new values, extra context)
  - `ip_address`
  - `user_agent`
  - `created_at`

## Configuration

**config/audit.php**
```php
return [
    'enabled' => true,

    'log_actions' => [
        'created',
        'updated',
        'deleted',
        'downloaded',
        'approved',
        'rejected',
        'uploaded',
        'sent',
        'paid',
        'refunded',
        'cancelled',
        'confirmed',
        'viewed',
    ],

    'track_changes' => true, // Store old/new values on update

    'exclude_fields' => [
        'password',
        'remember_token',
        'api_token',
    ],

    'retention_days' => 365, // Keep audit logs for 1 year

    'async' => true, // Log via queue for performance
];
```

## Usage Examples

### Manual Logging
```php
use ProPhoto\Contracts\Audit\AuditLoggerContract;

$audit = app(AuditLoggerContract::class);

$audit->log(
    action: 'downloaded',
    auditable: $gallery,
    metadata: [
        'images' => $imageIds,
        'format' => 'zip',
        'size_mb' => 125,
    ]
);
```

### Automatic Event Listening
```php
// In AuditServiceProvider, listen to all domain events:

Event::listen(AssetIngested::class, LogAssetIngestActivity::class);
Event::listen(BookingConfirmed::class, LogBookingActivity::class);
Event::listen(InvoicePaid::class, LogInvoiceActivity::class);
Event::listen(GenerationComplete::class, LogAiActivity::class);

// Each listener extracts relevant info and logs it
```

### Query Audit Trail
```php
use ProPhoto\Contracts\Audit\ActivityQueryContract;

$activity = app(ActivityQueryContract::class);

// Get recent activity for a gallery
$logs = $activity->forResource($gallery)
    ->latest()
    ->take(20)
    ->get();

// Get all downloads in date range
$downloads = $activity->forAction('downloaded')
    ->between($startDate, $endDate)
    ->get();

// Get all actions by specific user
$userActivity = $activity->forUser($user)
    ->orderByDesc('created_at')
    ->paginate(50);

// Get failed actions (security monitoring)
$failures = $activity->failed()
    ->whereAction('login_attempt')
    ->get();
```

### Filament Resource Tab
```php
// In any Filament resource:
use ProPhoto\Audit\Filament\AuditLogRelationManager;

public static function getRelations(): array
{
    return [
        AuditLogRelationManager::class,
    ];
}

// Shows "Activity" tab with logs for this resource
```

## What Gets Logged

### Gallery Actions
- Gallery created
- Gallery updated (settings, name, visibility)
- Images uploaded to gallery
- Gallery shared (magic link generated)
- Gallery accessed by subject
- Images downloaded (individual or bulk)
- Gallery deleted

### Booking Actions
- Booking requested
- Booking confirmed/declined
- Booking rescheduled
- Booking cancelled
- Calendar synced

### Invoice Actions
- Invoice generated
- Invoice sent
- Invoice viewed
- Payment processed
- Payment failed
- Refund processed

### AI Actions
- Training started
- Training completed
- Generation requested
- Generation completed
- Quota exceeded

### Image Interactions
- Image rated by subject
- Marketing approval given/denied
- Comment added
- Edit request submitted

### Permission Actions
- Permission granted
- Permission revoked
- Role assigned
- Invitation sent
- Impersonation started/ended

### Setting Actions
- Setting changed (which key, old value, new value)
- Feature flag enabled/disabled

## Events

- `ActivityLogged` - Fired after activity is logged (for real-time dashboards)

## Filament Activity Widget

Dashboard widget showing recent activity across entire studio:

```php
// Shows latest 10 actions across all resources
// "John uploaded 25 images to Wedding Gallery"
// "Sarah approved 10 images in Portrait Gallery"
// "System: Invoice #123 paid via Stripe"
```

## Security Monitoring Queries

```php
// Failed login attempts in last hour
$failedLogins = $activity->forAction('login_attempt')
    ->failed()
    ->since(now()->subHour())
    ->get();

// Permission denials by user
$denials = $activity->forAction('permission_denied')
    ->forUser($suspiciousUser)
    ->get();

// Mass downloads (potential data exfiltration)
$massDownloads = $activity->forAction('downloaded')
    ->where('metadata->size_mb', '>', 500)
    ->get();
```

## Performance Considerations

- Audit logs written asynchronously via queue
- Old logs pruned after retention period
- Indexed on: studio_id, user_id, auditable_type, auditable_id, action, created_at
- Metadata stored as JSON (searchable in PostgreSQL)

## Compliance Support

- GDPR: Export all activity for specific user
- HIPAA: Full audit trail of who accessed what
- SOC2: Proof of access controls and monitoring

## Future Enhancements

- [ ] Real-time activity dashboard (WebSockets)
- [ ] Anomaly detection (unusual patterns)
- [ ] Export to SIEM systems
- [ ] Automated alerts on suspicious activity
- [ ] Activity replay (show exact sequence of events)

## Dependencies

- `prophoto/contracts` - Audit contracts and DTOs
- `prophoto/tenancy` - Studio/org context
- All ProPhoto packages (listens to their events)

## Testing

```bash
cd prophoto-audit
vendor/bin/pest
```

## Notes

- Every event listener creates an audit log
- Audit logs are immutable (never updated or deleted, except by retention policy)
- Metadata field stores arbitrary context (very flexible)
- User ID nullable because some events are system-triggered
- IP address and user agent captured automatically
