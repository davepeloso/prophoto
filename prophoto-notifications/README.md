# ProPhoto Notifications

## Purpose

Centralized notification delivery for the ProPhoto system. Owns the Message model for tracking notification state and delivery. This package is intended to listen to domain events from other packages and translate them into user-facing notifications (email initially, multi-channel later). It does not own domain logic — it reacts to events and delivers messages.

## Responsibilities

- Message model (notification records scoped to studio, referencing galleries and images)
- Notification delivery tracking (sent, delivered, failed, retried)
- Template-based email rendering with studio branding
- User notification preferences (opt-in/opt-out per notification type)
- Rate limiting to prevent notification spam

## Non-Responsibilities

- Does NOT own galleries, images, bookings, invoices, or any domain models — references them via foreign keys only
- Does NOT own studio or organization models — depends on prophoto-access for Studio
- Does NOT define domain events — listens to events defined in other packages or prophoto-contracts
- Does NOT participate in the ingest → assets → intelligence event loop
- Does NOT mutate ingest, asset, or booking state
- Does NOT bypass the event system to query other packages' tables directly

## Integration Points

- **Events listened to:** None wired yet (future: domain events from gallery, booking, invoicing, interactions, ingest)
- **Events emitted:** None currently
- **Contracts depended on:** `prophoto/contracts` (shared DTOs/enums)
- **Model relationships:** Message→Studio (belongs to, from prophoto-access), Message→Gallery (belongs to, from prophoto-gallery), Message→Image (belongs to, from prophoto-gallery)

## Data Ownership

| Table | Model | Purpose |
|---|---|---|
| `messages` | Message | Notification records with delivery state |

## Notes

- ServiceProvider is declared in composer.json (`ProPhoto\Notifications\NotificationsServiceProvider`) but the file does not yet exist — needs implementation
- Uses `illuminate/mail` and `illuminate/notifications` for delivery infrastructure
- This package should only grow by adding event listeners and notification templates — domain logic belongs in the originating package
