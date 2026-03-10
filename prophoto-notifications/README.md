# ProPhoto Notifications

Notification system for ProPhoto handling email delivery, templates, preferences, and delivery tracking.

## Purpose

Centralized notification management where:
- All packages send notifications through this system
- Template-based emails with branding
- User preferences (opt-in/opt-out per type)
- Delivery tracking and retry logic
- Multi-channel support (email, SMS, push - email first)

## Key Features

- Event-driven (listens to domain events)
- Queueable for performance
- Template inheritance (studio branding)
- Delivery logs and failure tracking
- User preferences per notification type
- Rate limiting (prevent spam)

## Email Templates

- `GalleryReadyNotification` - Gallery shared with subject
- `BookingConfirmedNotification` - Booking confirmed
- `InvoiceSentNotification` - Invoice generated
- `InvoicePaidNotification` - Payment received
- `DownloadReadyNotification` - ZIP ready
- `EditRequestNotification` - Edit request received
- `MarketingApprovalReminderNotification` - Reminder to approve images
- `MagicLinkNotification` - Passwordless access link

## Configuration

```php
return [
    'channels' => ['mail'], // Add 'sms', 'push' later
    'queue' => 'notifications',
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS'),
        'name' => env('MAIL_FROM_NAME'),
    ],
    'rate_limit' => [
        'max_per_hour' => 50,
        'max_per_day' => 200,
    ],
];
```

## Dependencies

- All ProPhoto packages (listens to events)
- `prophoto/tenancy` - Studio context
- `prophoto/audit` - Log delivery

