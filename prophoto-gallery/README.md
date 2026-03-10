# ProPhoto Gallery

**Gallery domain package - Core gallery models, collections, sharing, templates, and advanced features**

---

## Package Scope

This package owns the **gallery domain** for the ProPhoto system.

### Core Models
- **Gallery** - Gallery/album container
- **Image** - Image records with metadata
- **ImageVersion** - Image edit versions and crops

### Advanced Features
- **GalleryCollection** - Organized collections of galleries
- **GalleryShare** - Secure sharing with access control
- **GalleryTemplate** - Reusable gallery templates
- **GalleryComment** - Comments on galleries
- **GalleryAccessLog** - Audit trail for gallery access
- **ImageTag** - Tagging system for images

### Migrations
- `galleries` table (extended)
- `images` table (extended)
- `image_versions` table
- `gallery_collections` table
- `collection_gallery` pivot table
- `gallery_shares` table
- `gallery_comments` table
- `gallery_access_logs` table
- `gallery_templates` table
- `image_tags` table
- `image_tag` pivot table

### Policies
- **GalleryPolicy** - Authorization for gallery access and management
- **GalleryCollectionPolicy** - Collection access control
- **GallerySharePolicy** - Share link authorization
- **GalleryTemplatePolicy** - Template management authorization

---

## Dependencies

- `prophoto/contracts` - Shared interfaces and DTOs
- `prophoto/access` - RBAC permissions and tenancy

---

## Upstream Dependencies (Laravel-Realistic)

This package is referenced by:
- **prophoto-booking** - Session.gallery relationship
- **prophoto-ai** - AiGeneration.gallery relationship
- **prophoto-ingest** - StagingImage transforms to Image
- **prophoto-interactions** - ImageInteraction.image relationship
- **prophoto-notifications** - Message references Gallery/Image

---

## Installation

In sandbox/composer.json:
```json
{
  "require": {
    "prophoto/gallery": "dev-main"
  }
}
```

```bash
composer require prophoto/gallery:dev-main
php artisan migrate
```

---

## Usage

### Gallery Model

```php
use ProPhoto\Gallery\Models\Gallery;

$gallery = Gallery::create([
    'studio_id' => $studio->id,
    'organization_id' => $org->id,
    'subject_name' => 'John Doe',
    'status' => 'active',
]);
```

### Image Model

```php
use ProPhoto\Gallery\Models\Image;

$image = Image::create([
    'gallery_id' => $gallery->id,
    'filename' => 'photo.jpg',
    'imagekit_url' => 'https://...',
    'file_size' => 1024000,
    'width' => 1920,
    'height' => 1080,
    'metadata' => ['camera' => 'Canon EOS R5'],
]);
```

### Authorization

```php
use ProPhoto\Access\Permissions;

// Check permission
if ($user->hasContextualPermission(Permissions::VIEW_GALLERIES, $gallery)) {
    // Allow access
}

// Use policy
$this->authorize('view', $gallery);
```

---

## Testing

```bash
cd prophoto-gallery
composer test
```

---

## Architecture Notes

- **Eloquent Reality**: Downstream packages MAY reference Gallery/Image models
- **Foreign Keys**: Allowed from downstream packages (e.g., `photo_sessions.gallery_id → galleries.id`)
- **No Circular Dependencies**: Gallery does NOT import from booking/invoicing/ai
- **Event-Driven**: Emits events for cross-package integration

---

## Related Packages

- **prophoto-interactions** - Image ratings, approvals, comments
- **prophoto-booking** - Sessions that create galleries
- **prophoto-ai** - AI training from gallery images
- **prophoto-ingest** - Upload pipeline that creates images
- **prophoto-notifications** - Notifications about gallery events
