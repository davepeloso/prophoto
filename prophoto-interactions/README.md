# ProPhoto Interactions

## Purpose

Image interaction tracking for client and subject engagement with gallery images. Records ratings, marketing approvals, comments, and edit requests. This package captures how subjects and clients respond to delivered photos — it does not own the images themselves.

## Responsibilities

- ImageInteraction model (ratings, favorites, approvals, comments, edit requests on images)
- Interaction persistence and retrieval per image

## Non-Responsibilities

- Does NOT own images or galleries — depends on prophoto-gallery for Image model
- Does NOT own asset truth — that is prophoto-assets
- Does NOT own permissions or authorization — uses prophoto-access
- Does NOT participate in the ingest → assets → intelligence event loop
- Does NOT send notifications directly — emits events for prophoto-notifications to consume (future)
- Does NOT mutate ingest, asset, or booking state

## Integration Points

- **Events listened to:** None currently
- **Events emitted:** None currently (future: ImageRated, MarketingApproved, EditRequestReceived)
- **Contracts depended on:** `prophoto/contracts` (shared DTOs/enums), `prophoto/gallery` (Image model)
- **Model relationships:** ImageInteraction→Image (belongs to), Image→ImageInteraction (has many, defined in prophoto-gallery)

## Data Ownership

| Table | Model | Purpose |
|---|---|---|
| `image_interactions` | ImageInteraction | All interaction types for gallery images |

## Notes

- ServiceProvider is declared in composer.json (`ProPhoto\Interactions\InteractionsServiceProvider`) but the file does not yet exist — needs implementation
- Currently a single-model package — may be a candidate for merging into prophoto-gallery long-term if it does not grow its own services or event contracts
- The existing README described multiple tables (image_ratings, marketing_approvals, image_comments, edit_requests) but only `image_interactions` exists as a migration — the single-table design may use a `type` column to differentiate interaction kinds
