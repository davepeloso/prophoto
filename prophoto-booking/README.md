# ProPhoto Booking

Booking workflow engine for ProPhoto with calendar integration and conflict detection.

## Purpose

Manages session booking lifecycle:
- Clients request sessions
- Photographer reviews and confirms/declines
- Calendar sync (Google Calendar)
- Conflict detection
- Status tracking and notifications

## Key Features

### Booking Workflow
- Request → Under Review → Confirmed/Declined
- Counteroffer support (suggest different time)
- Message thread per booking
- Automatic reminders

### Calendar Integration
- Google Calendar two-way sync
- Create events on confirmation
- Update on reschedule
- Delete on cancellation
- Conflict detection (existing bookings overlap)

### Session Management
- Multiple session types (portrait, wedding, event)
- Duration and location
- Pricing per session type
- Deposit tracking

## Configuration

```php
return [
    'session_types' => [
        'portrait' => ['duration' => 60, 'price' => 200],
        'wedding' => ['duration' => 480, 'price' => 3000],
        'event' => ['duration' => 120, 'price' => 500],
    ],
    'calendar' => [
        'provider' => 'google',
        'sync_enabled' => true,
    ],
];
```

## Dependencies

- `prophoto/contracts` - Booking contracts
- `prophoto/notifications` - Send confirmations
- Google Calendar API

