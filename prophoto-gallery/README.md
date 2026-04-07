# ProPhoto Gallery

## Purpose

Gallery domain package owning the presentation layer for photography deliverables. Manages gallery containers, image records, collections, sharing, templates, comments, access logging, and tagging. This package is the primary consumer of asset data for client-facing display but does not own asset truth — it references assets via `asset_id` on the Image model.

## Responsibilities

- Gallery model (album containers scoped to studio + organization)
- Image model (image records within galleries, linked to assets via `asset_id`)
- ImageVersion model (edited versions and crops of images)
- GalleryCollection model (organized groupings of galleries)
- GalleryShare model (secure sharing links with access control)
- GalleryTemplate model (reusable gallery configuration templates)
- GalleryComment model (comments on galleries)
- GalleryAccessLog model (audit trail for gallery views)
- ImageTag model (tagging system for images)
- API routes for gallery CRUD, image management, collections, sharing
- Policies for gallery, collection, share, and template authorization (using `prophoto-access` permissions)
- `GalleryServiceProvider` with route loading and policy registration

## Non-Responsibilities

- Does NOT own asset truth — canonical asset data lives in prophoto-assets
- Does NOT own ingest logic — image creation from uploads is handled by prophoto-ingest
- Does NOT own session/booking associations — that is prophoto-ingest and prophoto-booking
- Does NOT own AI generation — AI portrait models live in prophoto-ai
- Does NOT own interaction data (ratings, approvals) — that is prophoto-interactions
- Does NOT mutate ingest or asset state
- Does NOT query booking tables directly

## Integration Points

- **Events listened to:** None currently
- **Events emitted:** None currently (future: gallery-level events for notifications)
- **Contracts depended on:** `prophoto/contracts` (shared DTOs/enums), `prophoto/access` (Permissions, UserRole, Studio, Organization models), `prophoto/assets` (asset references)
- **Referenced by:** prophoto-ai (AiGeneration→Gallery), prophoto-interactions (ImageInteraction→Image), prophoto-notifications (Message→Gallery/Image), prophoto-booking (Session→Gallery)

## Data Ownership

| Table | Model | Purpose |
|---|---|---|
| `galleries` | Gallery | Gallery/album containers |
| `images` | Image | Image records (references assets via `asset_id`) |
| `image_versions` | ImageVersion | Edited versions of images |
| `gallery_collections` | GalleryCollection | Grouped galleries |
| `collection_gallery` | (pivot) | Collection-gallery membership |
| `gallery_shares` | GalleryShare | Sharing links and access grants |
| `gallery_comments` | GalleryComment | Comments on galleries |
| `gallery_access_logs` | GalleryAccessLog | View/access audit trail |
| `gallery_templates` | GalleryTemplate | Reusable gallery configurations |
| `image_tags` | ImageTag | Tag definitions |
| `image_tag` | (pivot) | Image-tag associations |

## Notes

- Gallery.images is the presentation layer; Asset is the canonical source of truth for media files
- Image.asset_id links gallery images to prophoto-assets canonical records
- Multiple downstream packages hold foreign keys to `galleries.id` and `images.id` — this is intentional and allowed
- No circular dependencies: gallery does NOT import from booking, invoicing, or ingest
- ServiceProvider: `ProPhoto\Gallery\GalleryServiceProvider` (auto-discovered)
