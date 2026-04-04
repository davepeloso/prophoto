# Booking Operational Context
Date: March 13, 2026
Status: Architecture proposal (no code/migrations)

## 1) Purpose and Boundary
`prophoto-booking` should be treated as ProPhoto's operational/session context foundation, not a narrow scheduling package.

It owns operational truth for work execution:
- bookings and booking intent
- sessions and session lifecycle state
- session time windows (including setup/teardown/travel buffers)
- location and shoot context
- client/job references
- calendar linkage and reconciliation metadata

It does not own media truth:
- canonical media files
- raw metadata truth
- normalized metadata truth
- gallery asset ownership

Boundary rule:
- `prophoto-assets` owns canonical media identity and metadata.
- `prophoto-booking` owns operational session context.
- Downstream packages reconcile both via contracts/events.

Anchor rule:
- `Booking` is the commercial/operational umbrella.
- `Session` is the primary execution unit and the main downstream context anchor for ingest, asset organization context, galleries, and delivery workflows.

## 2) Why Booking Is Foundational in ProPhoto
In real workflows, the calendar often captures decisive context before ingest starts: who the shoot is for, where it is, when it occurs, what type of deliverable is expected, and how the day is sequenced.

That context is high-value operational signal:
- later ingest decisions are better when batch imports are pre-labeled with likely session context
- asset grouping gets better when capture time/GPS is evaluated against known session windows/locations
- gallery naming and delivery defaults are stronger when job/session intent exists before curation
- billing starts faster when session type and deliverable expectations are already known

`prophoto-booking` is therefore not an island package. It is a context spine that informs ingest, assets-adjacent organization, galleries, billing, and automation.

## 3) Package Placement in Architecture
Recommendation: model two foundational spines with clear ownership and one-way dependencies.

Foundational spines:
- `prophoto-assets`: canonical media truth
- `prophoto-booking`: operational/session truth

Downstream consumers:
- `prophoto-ingest` consumes both spines to classify imports and propose session assignment.
- `prophoto-gallery` consumes session context for defaults/grouping, while still owning presentation/delivery structures.
- `prophoto-invoicing` consumes session context for commercial defaults and fulfillment linkage.
- `prophoto-intelligence` remains downstream of canonical assets; optional booking context enrichment should be contract-based and non-canonical.

Dependency direction (compile-time intent):
- `prophoto-booking` -> `prophoto-contracts`, `prophoto-access` (and future `prophoto-core`)
- `prophoto-booking` -X-> `prophoto-assets`, `prophoto-ingest`, `prophoto-gallery`, `prophoto-invoicing`, `prophoto-intelligence`
- `prophoto-ingest` -> `prophoto-assets` + booking contracts/events
- `prophoto-gallery` -> `prophoto-assets` + booking contracts/events
- `prophoto-invoicing` -> booking contracts/events (and gallery where required by delivery workflow)

Practical implication:
- booking/session context and assets are both foundational, but in different dimensions.
- downstream packages reconcile context + media; neither foundational package should absorb the other's ownership.
- downstream context linkage should default to `session_id` (not `booking_id`) for execution-facing flows.

## 4) Core Domain Concepts
Suggested minimal domain model for `prophoto-booking`:

- `Booking`
Role: operational commitment record (job-level intent, parties, shoot type, high-level status).

- `Session`
Role: executable unit within a booking (single shoot block, possibly one of many per booking/day) and the primary downstream anchor for operational/media-adjacent workflows.

- `SessionTimeWindow`
Role: normalized operational window fields (`planned_start`, `planned_end`, optional setup/travel/teardown buffers).

- `SessionLocation`
Role: normalized location context (freeform label + geocodable address + optional lat/lng confidence).

- `ClientReference`
Role: client identity linkage for booking/session context without turning booking into a CRM owner. v1 may keep this external-reference-first, but the data model must explicitly define whether this remains external-only or supports a lightweight internal identity with outward mappings.

- `CalendarEventLink`
Role: linkage record to provider event IDs, sync tokens, and reconciliation state.

- `DeliverableExpectation`
Role: lightweight expectation context (for example engagement session, headshots, wedding highlights) used for defaults downstream, not final fulfillment ownership.

- `SessionStatus`
Role: operational lifecycle (`tentative`, `confirmed`, `in_progress`, `completed`, `cancelled`, etc.) used by automation and downstream gating.

## 5) Calendar as Operational Context
Calendar data should be treated as strong operational context, not absolute truth.

High-value calendar inputs:
- event start/end and timezone
- title/description notes
- address/location fields
- linked participants/contacts
- explicit buffers or adjacent travel blocks
- provider metadata (source, sync revision, last seen)

Mapping approach:
- one calendar event may map to one booking + one session (simple case)
- one calendar event may map to one booking + multiple sessions (multi-location/segment day)
- multiple calendar events can map to one booking when a provider splits prep/travel/main shoot

Trust model:
- calendar is authoritative for intent and planning context at ingest-time decisions
- calendar is fallible (last-minute changes, stale events, bad addresses)
- booking should preserve provenance and reconciliation markers (imported_at, provider_revision, manually_confirmed flags) so the system can prefer corrected operational truth when conflicts exist

## 6) Interaction with Assets and Ingest
This is the key integration boundary.

`prophoto-booking` should inform ingest and organization logic through context services/events:
- likely session assignment for ingest batches
- default logical paths and naming suggestions
- job/session grouping hints
- upload batch labels and operator prompts

Concrete matching examples:
- Capture time inside `SessionTimeWindow` and GPS near `SessionLocation` -> high-confidence session candidate.
- Capture time inside planned window but GPS far away -> medium-confidence candidate, request operator confirmation.
- Capture time between two nearby sessions with travel buffer overlap -> ranked candidates using proximity + camera/upload source + title keyword hints.
- Upload source tagged "Smith Wedding card A" and booking title contains "Smith Wedding" for same date -> boost match confidence even with missing GPS.

Operational rules:
- booking context influences assignment and organization suggestions.
- final asset identity remains owned by `prophoto-assets`.
- ingest writes/links `asset_id`; booking contributes `session_id` context association as the default execution linkage, never media ownership.

## 7) Interaction with Galleries and Delivery
Session/booking context should shape gallery defaults:
- gallery naming seeds (`{client_or_job}-{session_date}-{location}`)
- default grouping by booking/session
- default access/publish windows based on session lifecycle
- deliverable expectation hints for gallery structure

Ownership boundary:
- `prophoto-gallery` remains presentation/delivery owner (curation, sharing, access experiences).
- booking provides context inputs and suggested defaults; it does not become gallery owner.
- gallery-to-context linkage should typically be session-oriented first, with booking retained as optional umbrella context.

## 8) Interaction with Billing / Quotes / Orders
`prophoto-booking` should supply billing context, not billing ownership.

What booking should provide:
- quote/invoice defaults from booking/session type and expected deliverables
- client/job references for prefill
- planned session date/location context for line-item descriptors
- session fulfillment linkage signals (`session_completed`, expected deliverables pending)

What booking should not do:
- issue invoices
- own payment state
- become order ledger of record

`prophoto-invoicing` remains source of truth for commercial documents and payment lifecycle.

## 9) Automation Opportunities
Treat booking as the operational signal source for automation:

- infer likely `session_id` from capture timestamp + GPS + session window/buffer
- auto-label ingest batches ("Likely: Downtown Engagement Session")
- suggest logical organization defaults before operator review
- propose gallery creation when a session becomes `completed` and qualifying assets exist
- prefill invoice/quote drafts from deliverable expectations and session type
- flag missing deliverables when session is completed but expected outputs are incomplete
- detect schedule drift (captures far outside planned window/location) and surface reconciliation prompts

These automations should be confidence-based and reviewable, not silent irreversible writes.

## 10) Minimal v1 Recommendation
Keep v1 intentionally narrow.

Build only:
- `Booking`
- `Session`
- `SessionLocation`
- `SessionTimeWindow`
- `ClientReference` (lightweight reference, not CRM ownership)
- `CalendarEventLink`

v1 outcomes:
- persist operational session truth with calendar linkage
- expose contract-first read APIs/events for ingest/gallery/invoicing defaults
- support session matching inputs (time, location, status, job context)

Do not build in v1:
- full CRM/contact management suite
- advanced scheduling UI suite
- provider-specific feature sprawl
- billing or gallery logic inside booking

## 11) Guardrails / What Not To Do
- Do not make booking own media or metadata truth.
- Do not let assets own booking/session truth.
- Do not reintroduce circular dependencies between booking and downstream domains.
- Do not couple architecture to one calendar vendor/provider.
- Do not build a giant CRM first.
- Do not move presentation/scheduling UI concerns into foundational package internals.
- Do not bypass contracts/events with peer concrete model imports across boundaries.
- Do not store irreversible automation outcomes without operator review when confidence is low.

## 12) Recommended Next Artifact
Create `docs/architecture/BOOKING-DATA-MODEL.md` next.

Why this is the right immediate follow-up:
- `RULES.md` requires explicit table ownership and migration boundaries.
- downstream integration depends on stable IDs, foreign key direction, and status semantics.
- session-matching logic quality depends on the exact shape of time window/location/calendar linkage fields.

`BOOKING-DATA-MODEL.md` should freeze:
- booking/session tables and ownership
- calendar linkage schema and provenance fields
- status/state transition rules
- contract DTO/event payload shapes needed by ingest/gallery/invoicing
- explicit context-linking policy (`session_id` as primary downstream anchor, `booking_id` as umbrella)
- `ClientReference` identity mode:
  - external-reference-only, or
  - lightweight internal identity with optional external mappings

## Proposed Package Role
`prophoto-booking` should be the platform's operational context spine: it owns booking/session truth (who, what, where, when, and planning state), publishes that context to downstream domains through contracts/events, and enables high-confidence automation without ever owning canonical media identity, metadata truth, gallery ownership, or billing ownership.
