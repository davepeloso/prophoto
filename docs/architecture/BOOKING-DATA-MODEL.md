# Booking Data Model
Date: March 13, 2026
Status: Frozen pre-migration v1 data model (no code/migrations yet)

## Purpose
Freeze the concrete database ownership model for `prophoto-booking` as ProPhoto's operational/session context spine before migration implementation.

Core v1 rules:
- `prophoto-booking` owns booking/session operational truth.
- `Session` is the primary downstream context anchor.
- `Booking` is the commercial/operational umbrella.
- Booking does not own media identity, media storage, or canonical metadata.
- `prophoto-assets` does not own booking/session truth.
- v1 stays intentionally small and avoids CRM sprawl.

## Ownership and Boundary
- Owning package for all tables in this document: `prophoto-booking`
- Canonical media owner: `prophoto-assets`
- Cross-package rule: no booking migration in this phase modifies `prophoto-assets` tables
- Integration rule: downstream packages consume booking context via IDs/contracts/events, not concrete model coupling

---

## ID Strategy and Relationship Map
ID strategy recommendation:
- Use stable opaque IDs (`ULID` preferred; `bigint` acceptable if consistent with existing package conventions).
- Keep foreign key types aligned exactly across booking-owned tables.

Recommended v1 relationships:
- `bookings.client_reference_id -> client_references.id` (nullable)
- `sessions.booking_id -> bookings.id` (required)
- `session_time_windows.session_id -> sessions.id` (required, unique for 1:1 in v1)
- `session_locations.session_id -> sessions.id` (required, unique for 1:1 in v1)
- `calendar_event_links.session_id -> sessions.id` (required, 1:many)

v1 simplification note:
- v1 uses strict 1:1 `session -> session_time_window` and `session -> session_location` for speed/simplicity.
- Future versions may allow segmented windows and multiple locations per session for split or multi-location shoots.

Anchor policy:
- Downstream execution flows should link to `sessions.id` first.
- `bookings.id` is an umbrella reference for rollups/reporting/commercial grouping.

---

## 1) `bookings`
### Purpose
Represent job-level operational/commercial umbrella context that can contain one or more execution sessions.

### Owning Package
`prophoto-booking`

### Columns (v1)
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | ULID / bigint PK | Booking identifier |
| `studio_id` | yes | FK-compatible ID | Tenant scope |
| `organization_id` | nullable | FK-compatible ID | Optional org scope |
| `client_reference_id` | nullable | FK to `client_references.id` | Lightweight client linkage |
| `title` | yes | string | Human-facing booking label |
| `job_type` | nullable | string | Shoot category (wedding, headshot, etc.) |
| `booking_status` | yes | enum/string | `tentative|confirmed|in_progress|completed|cancelled` |
| `source_type` | yes | enum/string | `manual|calendar_import|api` |
| `notes` | nullable | text | Operational notes only (non-authoritative for automation) |
| `created_at` | yes | timestamp | Creation time |
| `updated_at` | yes | timestamp | Last update |
| `cancelled_at` | nullable | timestamp | Set when cancelled |

### Relationship Direction
- Upstream from `sessions` (one booking, many sessions).
- Optional upstream reference from booking to `client_references`.

### Mutability Rules
- Planning fields are mutable while non-terminal.
- Terminal state (`completed`, `cancelled`) is historical; edits should be constrained to correction paths.
- `studio_id` is immutable after creation.

### Status Semantics
- `tentative`: intake/hold, not yet committed.
- `confirmed`: committed job umbrella.
- `in_progress`: at least one session underway.
- `completed`: should usually mean all child sessions are terminal (`completed|cancelled|no_show`).
- `cancelled`: umbrella cancelled.

---

## 2) `sessions`
### Purpose
Represent executable shoot units and the primary downstream anchor for ingest, galleries, and delivery workflows.

### Owning Package
`prophoto-booking`

### Columns (v1)
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | ULID / bigint PK | Session identifier (primary downstream anchor) |
| `booking_id` | yes | FK to `bookings.id` | Parent umbrella booking |
| `name` | yes | string | Session label used in UI/default naming |
| `session_type` | nullable | string | Engagement, ceremony, portraits, etc. |
| `session_status` | yes | enum/string | `tentative|confirmed|in_progress|completed|cancelled|no_show` |
| `primary_timezone` | yes | string | IANA timezone used for operational interpretation |
| `sequence_index` | nullable | integer | Ordering inside booking/day |
| `started_at` | nullable | timestamp | Actual start (optional operational telemetry) |
| `ended_at` | nullable | timestamp | Actual end |
| `created_at` | yes | timestamp | Creation time |
| `updated_at` | yes | timestamp | Last update |
| `cancelled_at` | nullable | timestamp | Set when cancelled |

### Relationship Direction
- `sessions` belongs to `bookings`.
- `sessions` is upstream for:
  - `session_time_windows`
  - `session_locations`
  - `calendar_event_links`
  - downstream package references (ingest/gallery/invoicing context links)

### Mutability Rules
- Session planning attributes are mutable until terminal states.
- `booking_id` should be treated as immutable in normal flow; explicit reparenting is exceptional and audited.
- Terminal sessions remain referenceable for historical traceability.

### Status Semantics
- `tentative`: proposed execution unit.
- `confirmed`: planned and active for downstream automation.
- `in_progress`: live/actively executing.
- `completed`: execution finished.
- `cancelled`: execution cancelled.
- `no_show`: explicit failed attendance/attendance mismatch state.

### Status Precedence (v1)
- `session_status` is the operationally trusted signal for ingest matching, gallery defaults, and delivery automation.
- `booking_status` can be manually set in v1, but should normally track aggregate child session state.
- A booking should not normally be set to `completed` while any child session is non-terminal (`tentative|confirmed|in_progress`) unless an explicit manual override path is used.

---

## 3) `session_time_windows`
### Purpose
Store normalized time-window context used for ingest matching, scheduling semantics, and downstream defaults.

### Owning Package
`prophoto-booking`

### Columns (v1)
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | ULID / bigint PK | Time window row identifier |
| `session_id` | yes | FK to `sessions.id` | Unique in v1 (1:1 with session) |
| `planned_start_at` | yes | timestamp | Planned session start (UTC storage) |
| `planned_end_at` | yes | timestamp | Planned session end (UTC storage) |
| `timezone` | yes | string | IANA timezone for local interpretation |
| `setup_buffer_minutes` | yes | integer (default 0) | Pre-start setup buffer |
| `travel_buffer_before_minutes` | yes | integer (default 0) | Pre-session travel allowance |
| `travel_buffer_after_minutes` | yes | integer (default 0) | Post-session travel allowance |
| `teardown_buffer_minutes` | yes | integer (default 0) | Post-end teardown buffer |
| `window_source` | yes | enum/string | `manual|calendar|api` |
| `last_reconciled_at` | nullable | timestamp | Last calendar/manual reconciliation timestamp |
| `created_at` | yes | timestamp | Creation time |
| `updated_at` | yes | timestamp | Last update |

### Relationship Direction
- `session_time_windows` belongs to `sessions`.
- No direct relationship to assets or galleries.
- v1 cardinality is 1:1 per session; future versions may support multiple/segmented windows.

### Mutability Rules
- Mutable until session terminal state.
- `planned_start_at < planned_end_at` is required.
- Buffer fields are operationally mutable; keep changes auditable through standard update trails.

### Status/State Semantics
- No separate status enum in v1; window validity derives from parent `session_status`.

---

## 4) `session_locations`
### Purpose
Store normalized location context for session execution and matching signals (address and optional geospatial coordinates).

### Owning Package
`prophoto-booking`

### Columns (v1)
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | ULID / bigint PK | Location row identifier |
| `session_id` | yes | FK to `sessions.id` | Unique in v1 (1:1 with session) |
| `label` | nullable | string | Human-friendly location label |
| `address_line_1` | nullable | string | Street address |
| `address_line_2` | nullable | string | Secondary line |
| `city` | nullable | string | Locality |
| `region` | nullable | string | State/region |
| `postal_code` | nullable | string | Postal/ZIP code |
| `country_code` | nullable | string | ISO country code |
| `latitude` | nullable | decimal | Optional coordinate |
| `longitude` | nullable | decimal | Optional coordinate |
| `location_source` | yes | enum/string | `manual|calendar|geocoded|api` |
| `location_confidence` | nullable | decimal | Confidence for inferred/geocoded location |
| `last_reconciled_at` | nullable | timestamp | Last location reconciliation |
| `created_at` | yes | timestamp | Creation time |
| `updated_at` | yes | timestamp | Last update |

### Relationship Direction
- `session_locations` belongs to `sessions`.
- No direct relationship to assets; used as matching context only.
- v1 cardinality is 1:1 per session; future versions may support multiple locations.

### Mutability Rules
- Mutable while planning/execution context evolves.
- At least one location signal should exist (`label` or address components or lat/lng).
- Coordinate corrections are allowed; source/confidence should be updated with provenance.

### Status/State Semantics
- No dedicated status enum in v1.

---

## 5) `client_references`
### Purpose
Provide lightweight client identity anchoring for bookings without creating a full CRM domain.

### Owning Package
`prophoto-booking`

### v1 Identity Decision
Use lightweight internal identity with optional external mapping.

Why:
- avoids early identity drift
- keeps a stable local key for booking/session linkage
- does not require CRM feature scope

### Columns (v1)
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | ULID / bigint PK | Internal stable client reference ID |
| `studio_id` | yes | FK-compatible ID | Tenant scope |
| `display_name` | yes | string | Operational display label |
| `email` | nullable | string | Contact hint (not CRM authority) |
| `phone` | nullable | string | Contact hint |
| `external_system` | nullable | string | Source system key (`google_contacts`, etc.) |
| `external_reference` | nullable | string | External provider/client identifier |
| `source_type` | yes | enum/string | `manual|calendar_import|api` |
| `is_active` | yes | boolean | Soft activity marker |
| `created_at` | yes | timestamp | Creation time |
| `updated_at` | yes | timestamp | Last update |

### Relationship Direction
- `client_references` is upstream for `bookings`.
- Sessions access client context through booking linkage.

### Mutability Rules
- `display_name`, `email`, `phone` are mutable.
- `id` remains stable and is never recycled.
- External mapping fields are mutable only via reconciliation paths; do not repurpose one row for a different real-world person.

### Status/State Semantics
- `is_active=true`: active operational reference.
- `is_active=false`: retained historical reference (do not delete if linked records exist).

### Anti-CRM Guardrail
Out of scope in v1:
- contact ownership workflows
- lead/pipeline/deal tracking
- communication history/timeline
- multi-address CRM profile models

---

## 6) `calendar_event_links`
### Purpose
Link sessions to provider calendar events with provenance and reconciliation metadata.

### Owning Package
`prophoto-booking`

### Columns (v1)
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | ULID / bigint PK | Link row identifier |
| `session_id` | yes | FK to `sessions.id` | Linked session |
| `provider` | yes | enum/string | `google|microsoft|ical|internal` |
| `provider_account_ref` | nullable | string | Linked account identifier |
| `provider_calendar_id` | yes | string | Source calendar identifier |
| `provider_event_id` | yes | string | Source event identifier |
| `provider_event_version` | nullable | string | ETag/sequence/version token |
| `provider_updated_at` | nullable | timestamp | Provider-side updated timestamp |
| `event_title_snapshot` | nullable | string | Snapshot of event title at sync |
| `event_start_at_snapshot` | yes | timestamp | Snapshot start used for reconciliation |
| `event_end_at_snapshot` | yes | timestamp | Snapshot end used for reconciliation |
| `event_timezone_snapshot` | yes | string | Snapshot timezone |
| `event_location_snapshot` | nullable | text | Snapshot location text |
| `sync_state` | yes | enum/string | `linked|stale|conflict|detached|sync_error` |
| `reconciliation_state` | yes | enum/string | `unreviewed|auto_matched|manually_confirmed|manual_override` |
| `linked_at` | yes | timestamp | Initial link timestamp |
| `last_synced_at` | nullable | timestamp | Last successful sync |
| `drift_detected_at` | nullable | timestamp | Detected mismatch timestamp |
| `manual_override_at` | nullable | timestamp | Manual override timestamp |
| `sync_error_code` | nullable | string | Last sync error code |
| `sync_error_message` | nullable | text | Last sync error message |
| `unlinked_at` | nullable | timestamp | Soft unlink marker |
| `created_at` | yes | timestamp | Creation time |
| `updated_at` | yes | timestamp | Last update |

### Relationship Direction
- `calendar_event_links` belongs to `sessions`.
- Booking relationship is derived through `sessions.booking_id`.

### Mutability Rules
- Provider identity tuple (`provider`, `provider_calendar_id`, `provider_event_id`) is immutable after creation.
- Sync/reconciliation fields are mutable.
- Unlink should be soft (`unlinked_at`) to preserve provenance.
- Keep historical link rows for audit; avoid destructive deletion.

### State Semantics
- `sync_state` reflects technical synchronization health.
- `reconciliation_state` reflects operational confidence/truth alignment.
- `manual_override` state means booking truth intentionally diverges from provider event content.

---

## Recommended Downstream Referencing
Downstream packages should reference booking context as follows:

- Primary execution link: `session_id`
- Optional umbrella/reporting link: `booking_id` (when needed for aggregation)
- Client linkage: derive from `session -> booking -> client_reference`
- Calendar linkage: derive from `session -> calendar_event_links`

Package guidance:
- `prophoto-ingest`: persist `session_id` on ingest/import batches and assignment outputs.
- `prophoto-gallery`: persist `session_id` for gallery/session grouping and naming defaults.
- `prophoto-invoicing`: prefer `session_id` for fulfillment linkage; include `booking_id` only when commercial aggregation requires it.

Boundary enforcement:
- Do not add `booking_id` or `session_id` ownership fields to canonical `prophoto-assets` tables as source-of-truth booking state.
- Do not embed booking/session truth into canonical asset-owned tables.
- If durable asset-to-session linkage is needed, model it through an explicitly owned association structure (for example `asset_session_assignments`) rather than mutating foundational ownership boundaries.
- Do not treat booking tables as media ownership records.

---

## Automation-Safe Field Set for Ingest/Session Matching
Use these fields as deterministic/weighted inputs in v1 automation:

- From `sessions`:
  - `id`
  - `booking_id`
  - `session_status`
  - `session_type`
  - `primary_timezone`
  - `sequence_index`

- From `session_time_windows`:
  - `planned_start_at`
  - `planned_end_at`
  - `timezone`
  - `setup_buffer_minutes`
  - `travel_buffer_before_minutes`
  - `travel_buffer_after_minutes`
  - `teardown_buffer_minutes`
  - `window_source`

- From `session_locations`:
  - `latitude`
  - `longitude`
  - `location_confidence`
  - `city`
  - `region`
  - `country_code`
  - `location_source`

- From `calendar_event_links`:
  - `event_start_at_snapshot`
  - `event_end_at_snapshot`
  - `event_timezone_snapshot`
  - `event_location_snapshot`
  - `event_title_snapshot`
  - `provider_event_version`
  - `sync_state`
  - `reconciliation_state`

- From `bookings`:
  - `job_type`
  - `booking_status`

Fields to treat as low-confidence hints only:
- `bookings.notes`
- free-form labels without normalized location/time support

Gating recommendation:
- Only auto-assign when `session_status` is compatible (`confirmed` or `in_progress`) and matching confidence exceeds threshold.
- Treat `session_status` as higher-precedence signal than `booking_status` for assignment decisions.
- Fall back to proposal/manual confirmation when calendar reconciliation state is stale/conflict.

---

## Guardrails
- Do not add media ownership columns to booking tables.
- Do not write booking truth into canonical asset identity tables.
- Do not expand `client_references` into CRM features in v1.
- Do not couple data model to one calendar vendor.
- Do not skip provenance fields for calendar-linked records.
