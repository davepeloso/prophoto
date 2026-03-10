# ProPhoto Storage

Storage abstraction layer for ProPhoto providing unified interface for local and cloud storage (ImageKit).

## Purpose

Provides flexible, swappable storage backend where:
- Development uses local filesystem
- Production uses ImageKit (or S3/CloudFlare R2)
- Signed URLs with expiry for secure downloads
- Path conventions enforce consistent organization
- Support for originals + multiple derivative types

## Key Responsibilities

### Storage Driver Abstraction
- Single interface for all storage operations
- Swap drivers via configuration (local, imagekit, s3)
- No driver-specific code in application layer
- Easy testing with local driver

### Signed URL Generation
- Time-limited URLs for secure downloads
- Expiry prevents link sharing
- Authorization metadata embedded in signature
- Support for different derivative types

### Path Conventions
- Consistent path structure across all storage
- Studio/organization/gallery/image hierarchy
- Original vs derivative separation
- Predictable asset URLs

### Asset Management
- Upload with automatic path generation
- Delete with cascade (remove all derivatives)
- Move/rename with reference updates
- Metadata extraction and storage

## Contracts Implemented

- `AssetStorageContract` - Store/retrieve/delete assets
- `SignedUrlGeneratorContract` - Generate time-limited URLs
- `PathResolverContract` - Resolve asset paths

## Storage Drivers

### Local Driver
- For development and testing
- Files stored in `storage/app/assets/`
- Signed URLs use Laravel signed route URLs
- Fast, simple, no external dependencies

### ImageKit Driver
- For production
- CDN-backed, globally distributed
- Automatic image optimization and transformation
- Real-time resizing via URL parameters
- Built-in signed URL support

### S3 Driver (Future)
- For self-hosted cloud storage
- Compatible with AWS S3, DigitalOcean Spaces, etc.

## Path Structure

```
{studio_id}/
  {organization_id}/
    {gallery_id}/
      originals/
        {image_id}.{ext}      // Original upload
      derivatives/
        thumb/
          {image_id}.jpg      // Thumbnail (300x300)
        preview/
          {image_id}.jpg      // Preview (1200px wide)
        web/
          {image_id}.jpg      // Web optimized (2400px wide)
```

Example:
```
5/                          // Studio ID 5
  12/                       // Organization ID 12
    789/                    // Gallery ID 789
      originals/
        abc123.cr3          // Original RAW file
      derivatives/
        thumb/
          abc123.jpg        // 300x300 thumbnail
        preview/
          abc123.jpg        // 1200px preview
        web/
          abc123.jpg        // 2400px web version
```

## Configuration

**config/storage.php**
```php
return [
    'default_driver' => env('STORAGE_DRIVER', 'local'), // local, imagekit, s3

    'drivers' => [
        'local' => [
            'root' => storage_path('app/assets'),
            'url' => env('APP_URL').'/storage/assets',
            'visibility' => 'private',
        ],

        'imagekit' => [
            'public_key' => env('IMAGEKIT_PUBLIC_KEY'),
            'private_key' => env('IMAGEKIT_PRIVATE_KEY'),
            'url_endpoint' => env('IMAGEKIT_URL_ENDPOINT'),
            'folder' => 'prophoto', // Base folder in ImageKit
        ],

        's3' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
        ],
    ],

    'signed_urls' => [
        'default_expiry_minutes' => 60,
        'max_expiry_minutes' => 10080, // 7 days
    ],

    'derivatives' => [
        'thumb' => ['width' => 300, 'height' => 300, 'fit' => 'cover'],
        'preview' => ['width' => 1200, 'height' => null, 'fit' => 'max'],
        'web' => ['width' => 2400, 'height' => null, 'fit' => 'max'],
    ],
];
```

## Usage Examples

### Store Asset
```php
use ProPhoto\Contracts\Asset\AssetStorageContract;

$storage = app(AssetStorageContract::class);

$result = $storage->store(
    file: $uploadedFile,
    type: 'original',
    studio: $studio,
    organization: $organization,
    gallery: $gallery,
    filename: 'wedding-001.jpg'
);

// Returns: StoragePath DTO
// Path: 5/12/789/originals/abc123.jpg
// URL: https://ik.imagekit.io/prophoto/5/12/789/originals/abc123.jpg
```

### Generate Derivatives
```php
// After storing original, generate derivatives
$derivatives = $storage->generateDerivatives($originalPath, [
    'thumb',
    'preview',
    'web',
]);

// Returns: DerivativeSet DTO with paths for each type
```

### Retrieve Asset
```php
$path = $storage->path($studio, $organization, $gallery, 'abc123.jpg', 'original');

$exists = $storage->exists($path);
$size = $storage->size($path);
$mimeType = $storage->mimeType($path);
$contents = $storage->get($path); // Binary content
```

### Generate Signed URL
```php
use ProPhoto\Contracts\Asset\SignedUrlGeneratorContract;

$urlGenerator = app(SignedUrlGeneratorContract::class);

$signedUrl = $urlGenerator->generate(
    path: $path,
    expiresInMinutes: 60,
    permissions: [
        'download' => true,
        'transformation' => 'thumb', // Use thumbnail derivative
    ]
);

// Returns: https://ik.imagekit.io/prophoto/5/12/789/originals/abc123.jpg?signature=...&expires=...
```

### Delete Asset
```php
// Delete original + all derivatives
$storage->delete($path, $includeDer ivatives = true);

// Cascade deletes:
// - originals/abc123.jpg
// - derivatives/thumb/abc123.jpg
// - derivatives/preview/abc123.jpg
// - derivatives/web/abc123.jpg
```

### ImageKit Transformations
```php
// Real-time transformations via URL (ImageKit feature)

// Thumbnail
$thumbUrl = $urlGenerator->withTransformation($path, [
    'width' => 300,
    'height' => 300,
    'crop' => 'center',
]);

// Watermark
$watermarkedUrl = $urlGenerator->withTransformation($path, [
    'overlay' => 'watermark.png',
    'overlay_x' => 10,
    'overlay_y' => 10,
]);

// Quality adjustment
$compressedUrl = $urlGenerator->withTransformation($path, [
    'quality' => 80,
]);
```

## Path Resolver

Automatic path generation following conventions:

```php
use ProPhoto\Contracts\Asset\PathResolverContract;

$pathResolver = app(PathResolverContract::class);

$path = $pathResolver->resolve(
    studio: $studio,
    organization: $organization,
    gallery: $gallery,
    filename: 'abc123.jpg',
    type: 'original'
);

// Returns: "5/12/789/originals/abc123.jpg"
```

## Download Authorization

Works with `prophoto-security` for authorized downloads:

```php
// In DownloadController
public function download(Request $request, $path)
{
    // Verify magic link token (from prophoto-security)
    $token = $request->token;
    if (!$tokenVerifier->authorizeDownload($token, $path)) {
        abort(403);
    }

    // Generate short-lived signed URL
    $signedUrl = $urlGenerator->generate($path, expiresInMinutes: 5);

    // Redirect to signed URL (or stream directly)
    return redirect($signedUrl);
}
```

## Migration Strategy

### Phase 1: Local (Development)
```env
STORAGE_DRIVER=local
```

### Phase 2: ImageKit (Production)
```env
STORAGE_DRIVER=imagekit
IMAGEKIT_PUBLIC_KEY=public_xxx
IMAGEKIT_PRIVATE_KEY=private_xxx
IMAGEKIT_URL_ENDPOINT=https://ik.imagekit.io/prophoto
```

### Migration Command
```bash
php artisan storage:migrate local imagekit

# Copies all assets from local to ImageKit
# Updates database references
# Validates integrity
```

## Testing Strategy

```php
// Use local driver in tests
config(['storage.default_driver' => 'local']);

// Or use fake driver
Storage::fake('assets');

$storage->store(...);

Storage::disk('assets')->assertExists('5/12/789/originals/abc123.jpg');
```

## Performance Considerations

- Use CDN URLs for public galleries (cacheable)
- Lazy-load derivatives (generate on first request)
- Cache signed URLs (short TTL)
- Batch operations for multiple assets

## Events

- `AssetStored` - Asset uploaded to storage
- `DerivativesGenerated` - Thumbnails/previews created
- `AssetDeleted` - Asset removed from storage
- `AssetMoved` - Asset path changed

## Future Enhancements

- [ ] Multi-region support (geographically distributed)
- [ ] Automatic failover between storage providers
- [ ] Storage quota enforcement
- [ ] Duplicate detection (perceptual hashing)
- [ ] Automatic format conversion (HEIC → JPEG)

## Dependencies

- `prophoto/contracts` - Storage contracts and DTOs
- `prophoto/tenancy` - Studio/org context
- `prophoto/security` - Signed URL verification
- ImageKit PHP SDK (or AWS SDK for S3)

## Testing

```bash
cd prophoto-storage
vendor/bin/pest
```

## Notes

- All public URLs are signed by default (prevent hotlinking)
- Originals stored forever, derivatives can be regenerated
- Path structure is immutable (changing it requires migration)
- Storage operations are synchronous (use jobs for bulk operations)
