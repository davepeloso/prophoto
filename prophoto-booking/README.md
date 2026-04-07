# ProPhoto Booking

## Purpose

Owns session and operational scheduling truth for the ProPhoto system. The Session model is the canonical record of "when, where, and what type of shoot is happening." This data is consumed by ingest for session-to-asset matching and by intelligence (indirectly, via SessionContextSnapshot). Booking also owns the BookingRequest model for the client-facing request workflow and Google Calendar integration.

## Core Loop Role

Booking is a **supporting core package**. It does not sit in the linear event flow — it provides the session truth that ingest reads during matching.

```
  prophoto-booking  ◄──(reads session data)──  prophoto-ingest
  prophoto-ingest   ──(SessionAssociationResolved)──►  prophoto-assets
  prophoto-assets   ──(AssetSessionContextAttached)──►  prophoto-intelligence
```

Ingest queries booking's Session model to get session windows, locations, types, and travel buffers for candidate generation and scoring. Intelligence never queries booking directly — it receives session context via the `SessionContextSnapshot` DTO attached to the asset by the assets package.

If this package is removed, ingest has no session data to match against. The matching pipeline produces no candidates. No `SessionAssociationResolved` events are emitted. The entire downstream pipeline stops.

## Responsibilities

- Session model (session/shoot records: type, start/end times, location, travel buffers, status)
- BookingRequest model (client-facing booking request workflow: request → review → confirm/decline)
- SessionPolicy (authorization using prophoto-access permissions and roles)
- Google Calendar two-way sync (create/update/delete calendar events on session changes)
- Conflict detection (overlapping session windows)

## Non-Responsibilities

- MUST NOT perform session-to-asset matching — that is prophoto-ingest
- MUST NOT own asset truth — that is prophoto-assets
- MUST NOT perform intelligence operations — that is prophoto-intelligence
- MUST NOT be queried directly by intelligence — intelligence uses SessionContextSnapshot only
- MUST NOT own gallery presentation — sessions may reference galleries, but gallery logic lives in prophoto-gallery
- Booking data MUST NOT be mutated by any package other than booking itself

## Integration Points

- **Events listened to:** None currently
- **Events emitted:** None currently (future: BookingConfirmed, BookingCancelled, SessionRescheduled)
- **Contracts depended on:** `prophoto/contracts` (shared DTOs/enums)
- **Consumed by:** prophoto-ingest (reads Session model for matching), prophoto-gallery (Session→Gallery relationship), prophoto-access (Session references Studio/Organization)

## Data Ownership

| Table | Model | Purpose |
|---|---|---|
| `photo_sessions` | Session | Canonical session/shoot records with time windows, location, type, travel buffers, status |
| `booking_requests` | BookingRequest | Client booking request workflow with status tracking |

## Notes

- BookingServiceProvider is declared in composer.json (`ProPhoto\Booking\BookingServiceProvider`) but the file does not yet exist — needs implementation
- No tests exist yet — this is a gap that should be addressed
- Session.start_at and Session.end_at define the core time window. Travel buffers (`travel_buffer_before_minutes`, `travel_buffer_after_minutes`) extend the effective matching window in ingest.
- Session status values (confirmed, tentative, in_progress, cancelled, no_show) are used by ingest's candidate generator to filter terminal statuses before matching
- Composer dependency on `google/apiclient` is for calendar sync functionality
- The Session model is referenced by Gallery (gallery belongs to session), but the relationship is defined in prophoto-gallery, not here
