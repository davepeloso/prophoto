# Architecture Pipeline Verification
## End-to-End Chain: Ingest → Assets → Gallery

**Verified:** April 14, 2026
**Purpose:** Permanent record confirming the three-package data pipeline is fully wired. This document exists so future agents (and Dave) don't have to re-discover these connections.

---

## The Big Picture

ProPhoto has three packages that form a chain. Data flows left to right — each package only talks to its neighbor, never skips ahead:

```
prophoto-ingest          prophoto-assets              prophoto-gallery
(decides which session   (attaches that decision      (displays images to
 an image belongs to)     as canonical truth)           clients for proofing)

      ──── event ────►          ──── read-only ────►
   SessionAssociationResolved     Image::asset() belongsTo
```

**The rule:** Each package owns its own tables. Gallery reads from Assets but never writes to it. Assets reads from Ingest's events but never writes back. This is enforced by architecture, not just convention.

---

## Connection 1: Ingest → Assets

### What happens
When an ingest item (uploaded photo) gets matched to a booking session, the ingest package figures out which session it belongs to and announces it.

### The wiring

| Step | Package | File | What it does |
|------|---------|------|-------------|
| 1 | prophoto-ingest | `Services/IngestItemSessionMatchingFlowService.php` | Runs session matching, then calls `dispatchResolvedEventIfApplicable()` |
| 2 | prophoto-contracts | `Events/Ingest/SessionAssociationResolved.php` | The event contract — carries `assetId`, `selectedSessionId`, `confidenceTier`, etc. |
| 3 | prophoto-assets | `AssetServiceProvider::boot()` | Registers the listener: `Event::listen(SessionAssociationResolved, HandleSessionAssociationResolved)` |
| 4 | prophoto-assets | `Listeners/HandleSessionAssociationResolved.php` | Filters for `AUTO_ASSIGN` decisions, inserts into `asset_session_contexts` table, emits `AssetSessionContextAttached` |
| 5 | prophoto-assets | `migrations/create_asset_session_contexts_table.php` | Join table linking assets to sessions with confidence scores and decision metadata |

### Safety features
- `insertOrIgnore()` makes the listener idempotent — replaying the event won't create duplicates
- Only `AUTO_ASSIGN` decisions trigger the write — proposals and other types are ignored
- Foreign key on `asset_id` with `cascadeOnDelete()` — if an asset is deleted, its context rows go too

### Events emitted by Assets
- **`AssetSessionContextAttached`** — emitted after successful insert. Consumed by prophoto-intelligence for embeddings.
- **`AssetReadyV1`** — emitted by `AssetCreationService` after original storage + metadata extraction. Also consumed by intelligence. Gallery does NOT listen to this (by design — see below).

**Status: ✅ Fully connected and functional**

---

## Connection 2: Assets → Gallery

### What happens
Gallery images are linked to assets via a foreign key. When a gallery needs to display images, it reads asset data (derivatives, thumbnails, metadata) but never modifies it.

### The wiring

| Step | What | File | Details |
|------|------|------|---------|
| FK | `images.asset_id` → `assets.id` | `migrations/add_asset_id_to_images_table.php` | Nullable FK, `nullOnDelete()` — asset deletion degrades gracefully |
| Read | `Image::asset()` | `Models/Image.php` (line 119) | `belongsTo(Asset::class, 'asset_id')` |
| Eager | `Gallery::imagesWithAssets()` | `Models/Gallery.php` (line 165) | `hasMany(Image::class)->with(['asset.derivatives'])` — prevents N+1 |
| Thumb | `Image::thumbnail()` | `Models/Image.php` | Resolves best derivative: prefers `thumbnail`, falls back to `preview` |
| URL | `Image::resolvedThumbnailUrl()` | `Models/Image.php` | Three-tier fallback: asset derivative → legacy ImageKit → legacy local path |

### Write paths (how images get INTO a gallery)

There are two paths, both respect the boundary:

**Path A — Event-driven (automatic)**
| Step | File | What |
|------|------|------|
| 1 | prophoto-gallery `GalleryServiceProvider` | Registers listener for `IngestSessionConfirmed` event |
| 2 | `Listeners/GalleryContextProjectionListener.php` | Queries `Asset::whereJsonContains('metadata->session_id', ...)` to find session assets |
| 3 | Same listener | Bulk-inserts `Image` rows with `asset_id` set (chunked, 50 per batch) |
| 4 | Same listener | Updates gallery aggregate counts |

**Path B — Manual (Filament admin)**
| Step | File | What |
|------|------|------|
| 1 | `Filament/Actions/AddImagesFromSessionAction.php` | Queries `asset_session_contexts` to find assets linked to the gallery's session |
| 2 | Same action | Calls `GalleryRepositoryContract::attachAsset()` for each asset |
| 3 | `Repositories/EloquentGalleryRepository.php` | Checks for duplicates, creates `Image` record with `asset_id`, updates gallery counts |

### The contract seam
- **`GalleryRepositoryContract`** lives in `prophoto-contracts` — defines `attachAsset(GalleryId, AssetId): void`
- **`EloquentGalleryRepository`** in `prophoto-gallery` implements it
- Bound as singleton in `GalleryServiceProvider::register()`
- This is how any package outside gallery can attach an asset to a gallery without knowing gallery internals

### Why Gallery doesn't listen to AssetReadyV1
Gallery projection is triggered by `IngestSessionConfirmed` instead. This is intentional — you want all session assets to land in the gallery at once (when the photographer confirms the session), not one-by-one as each asset finishes processing. It's a UX decision, not a technical limitation.

**Status: ✅ Fully connected and functional**

---

## Connection 3: The Full Chain in Action

Here's what happens when a photographer uploads photos and a client views them:

```
1. Photographer uploads photos
   → prophoto-ingest creates upload session + ingest files

2. Session matching runs
   → IngestItemSessionMatchingFlowService matches to booking session
   → Dispatches SessionAssociationResolved event

3. Assets receive the decision
   → HandleSessionAssociationResolved inserts asset_session_contexts row
   → Emits AssetSessionContextAttached

4. Photographer confirms the session
   → IngestSessionConfirmed event fires
   → GalleryContextProjectionListener bulk-creates Image rows with asset_id FKs

5. Photographer shares the gallery
   → Creates GalleryShare with share token
   → Client receives link: /g/{token}

6. Client opens the link
   → GalleryViewerController resolves token → share → gallery
   → Identity gate (if proofing) → confirms email
   → ProofingViewerController loads images via imagesWithAssets()
   → Image::resolvedThumbnailUrl() reads from asset derivatives
   → Client sees their photos, approves/rates/submits
```

**Every step in this chain exists in code and has been verified.**

---

## Boundary Rules (Non-Negotiable)

| Rule | Meaning |
|------|---------|
| Gallery never writes to Assets | `Image::asset()` is read-only. No `$asset->save()` calls from gallery code. |
| Assets never writes to Ingest | Listens to events, never calls back. |
| Gallery never writes to Contracts | Uses interfaces and DTOs, never modifies them. |
| One table = one owner | `images` owned by gallery, `assets` owned by assets, `asset_session_contexts` owned by assets |
| Events carry IDs, not models | `SessionAssociationResolved` carries `assetId` (string), not an Asset model instance |
| Write seams use contracts | `GalleryRepositoryContract::attachAsset()` is the only way to add images from outside gallery |

---

## Quick Diagnostic

If something breaks in the chain, check these in order:

1. **Images not appearing in gallery?** → Check `asset_session_contexts` has rows for that session. Check `GalleryContextProjectionListener` ran. Check `images` table has `asset_id` populated.
2. **Thumbnails not loading?** → Check `asset_derivatives` has rows for the asset. Check `Image::resolvedThumbnailUrl()` fallback chain.
3. **Session matching not working?** → Check `IngestItemSessionMatchingFlowService` ran. Check `SessionAssociationResolved` was dispatched. Check `HandleSessionAssociationResolved` listener is registered.
4. **Gallery empty after session confirm?** → Check `IngestSessionConfirmed` event fired. Check `GalleryContextProjectionListener` is registered in `GalleryServiceProvider`.

---

*This document is a point-in-time verification. If packages are refactored, re-verify the connections listed above.*

*Verified by: AI agent audit, April 14, 2026*
