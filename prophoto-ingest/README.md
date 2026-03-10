# prophoto Ingest

**Professional photo ingestion and organization tool for Laravel** - Adobe Bridge-style media management with intelligent metadata extraction, interactive culling, and powerful tagging system.

![Version](https://img.shields.io/badge/version-0.1.0-blue)
![Laravel](https://img.shields.io/badge/laravel-11%2B-red)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Features

### 🎯 **Core Functionality**

- **Adobe Bridge-style Interface** - Professional three-panel layout for efficient photo culling
- **EXIF Metadata Extraction** - Automatic extraction of camera settings, lens info, GPS data
- **Interactive Charts** - Click metadata distribution charts to select matching photos
- **Three-Tier Image Sizing** - Smart proxy generation (thumbnail, preview, original) for blazing-fast browsing
- **Background Processing** - Queue-based final ingest for large photo sets
- **Flexible File Organization** - Customizable naming schemas and folder structures

### 📊 **Interactive Selection**

- Click-to-select on ISO, aperture, focal length, and camera charts
- Cumulative mode for building complex selections
- Keyboard shortcuts for rapid culling
- Drag-and-drop reordering

### 🏷️ **Tagging System**

- Tag photos individually or in batches
- Quick tags for frequently used labels
- Tag autocomplete and suggestions
- Apply tags during culling, before final ingest

### ⚙️ **Customizable Schema**

- Define custom file naming patterns: `{sequence}-{camera}-{date}`
- Organize by date, camera, project, or any metadata field
- Polymorphic associations with your Laravel models
- Multiple storage disk support (local, S3, etc.)

---

## Requirements

- PHP 8.2+
- Laravel 11+ (or 12+)
- React-based Inertia.js setup
- **PHP Extensions:**
  - `exif` - EXIF metadata extraction
  - `gd` or `imagick` - Image processing (Imagick preferred for RAW support)
- Queue worker (database, Redis, etc.)

---

## Installation

### **Option 1: Quick Install (Recommended for New Projects)**

If you're setting up a fresh Laravel project with the React starter kit:

```bash
# 1. Create Laravel project with React
laravel new my-photo-app --stack=react

cd my-photo-app

# 2. Add path repository to composer.json
# (Or use Git/Packagist - see below)
```

Add to `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "../prophoto-ingest"
    }
]
```

```bash
# 3. Install package
composer require prophoto/ingest:@dev

# 4. Run installer script
bash vendor/prophoto/ingest/install.sh

# 5. Start development server
php artisan serve
php artisan queue:work  # In separate terminal
```

### **Option 2: Manual Installation**

```bash
# 1. Install via Composer
composer require prophoto/ingest

# 2. Publish configuration
php artisan vendor:publish --tag=ingest-config

# 3. Publish frontend assets
php artisan vendor:publish --tag=ingest-assets

# 4. Run migrations
php artisan migrate

# 5. Create storage directories
mkdir -p storage/app/ingest-temp/thumbs
mkdir -p storage/app/ingest-temp/previews
mkdir -p storage/app/images

# 6. Link storage
php artisan storage:link

# 7. Configure queue (if not already)
php artisan queue:table
php artisan migrate

# 8. Start queue worker
php artisan queue:work
```

---

## Configuration

### **Basic Setup**

Edit `config/ingest.php`:

```php
return [
    // Route configuration
    'route_prefix' => 'ingest',
    'middleware' => ['web', 'auth'],  // Remove 'auth' for testing

    // Storage configuration
    'storage' => [
        'temp_disk' => 'local',
        'temp_path' => 'ingest-temp',
        'final_disk' => env('INGEST_DISK', 'local'),
        'final_path' => 'images',
    ],

    // File naming schema
    'schema' => [
        'path' => 'shoots/{date:Y}/{date:m}/{camera}',
        'filename' => '{sequence}-{original}',
        'sequence_start' => 1,
        'sequence_padding' => 3,
    ],

    // EXIF extraction & image sizing
    'exif' => [
        // Thumbnails for grid view (400x400)
        'thumbnail' => [
            'enabled' => true,
            'width' => 400,
            'height' => 400,
            'quality' => 80,
        ],

        // Preview images for preview panel (~2048px max dimension)
        // This dramatically improves performance vs loading full originals
        'preview' => [
            'enabled' => true,
            'max_dimension' => 2048,
            'quality' => 85,
        ],

        // Optional: Resize originals during final ingest
        'final' => [
            'enabled' => false,
            'max_dimension' => null,  // null = keep original size
            'quality' => 95,
        ],
    ],
];
```

### **Available Schema Placeholders**

Use these in your `path` and `filename` patterns:

- `{date:Y}`, `{date:m}`, `{date:d}` - Date components (from EXIF)
- `{camera}` - Camera make (slugified)
- `{model}` - Camera model (slugified)
- `{sequence}` - Auto-incrementing number (001, 002, etc.)
- `{original}` - Original filename (without extension)
- `{uuid}` - Unique identifier

**Examples:**

```php
// Organize by year/month
'path' => 'photos/{date:Y}/{date:m}',
'filename' => '{date:Y-m-d}_{sequence}_{original}',
// Result: photos/2025/12/2025-12-01_001_IMG_1234.jpg

// Organize by camera
'path' => 'shoots/{camera}/{date:Y-m-d}',
'filename' => '{camera}_{sequence}',
// Result: shoots/canon/2025-12-01/canon_001.jpg
```

---

## Dynamic Configuration System

### **Database-Driven Settings**

In addition to the static config file, you can override any configuration value using the database-driven settings system. This allows you to change settings at runtime without modifying files.

### **How It Works**

1. Settings are stored in the `ingest_settings` table
2. On application boot, database settings override config file defaults
3. Settings are cached for performance (1 hour TTL)

### **Managing Settings Programmatically**

```php
use prophoto\Ingest\Services\IngestSettingsService;

$settings = app(IngestSettingsService::class);

// Get a single setting
$quality = $settings->get('exif.thumbnail.quality', 80);

// Set a single setting
$settings->set('exif.thumbnail.quality', 90);

// Set multiple settings at once
$settings->setMultiple([
    'schema.path' => 'photos/{date:Y}/{camera}',
    'exif.preview.quality' => 85,
    'appearance.accent_color' => '#3B82F6',
]);

// Check if a setting exists
if ($settings->has('schema.path')) {
    // ...
}

// Delete a setting (reverts to config file default)
$settings->delete('exif.thumbnail.quality');

// Reset all settings to defaults
$settings->resetAll();

// Clear the cache manually
$settings->clearCache();
```

### **Available Dynamic Settings**

All configuration keys can be overridden via database. The following categories are supported:

**Core Settings:**

- `route_prefix` - Base URL path (default: `ingest`)
- `middleware` - Array of middleware to apply

**Schema Settings:**

- `schema.path` - Folder structure pattern
- `schema.filename` - File naming pattern
- `schema.sequence_start` - Starting sequence number
- `schema.sequence_padding` - Zero-padding length

**Storage Settings:**

- `storage.temp_disk` - Temporary files disk
- `storage.temp_path` - Temporary files path
- `storage.final_disk` - Final files disk
- `storage.final_path` - Final files path

**Processing Settings:**

- `exif.thumbnail.enabled` - Enable thumbnails
- `exif.thumbnail.width` - Thumbnail width
- `exif.thumbnail.height` - Thumbnail height
- `exif.thumbnail.quality` - JPEG quality (1-100)
- `exif.preview.enabled` - Enable previews
- `exif.preview.max_dimension` - Preview max size
- `exif.preview.quality` - Preview quality
- `exif.final.enabled` - Enable final resizing
- `exif.final.max_dimension` - Final max size
- `exif.final.quality` - Final quality

**Metadata Settings:**

- `metadata.chart_fields` - Array of EXIF fields for charts
- `tagging.quick_tags` - Array of quick tag labels
- `tagging.sources` - Object mapping labels to model classes
- `associations.models` - Array of associatable models

**Appearance Settings:**

- `appearance.accent_color` - Primary UI color (hex)
- `appearance.border_radius` - Corner roundness (pixels)
- `appearance.spacing_scale` - Size scale (small/medium/large)

### **Seeding Default Settings**

To populate the database with example settings:

```bash
php artisan db:seed --class=prophoto\\Ingest\\Database\\Seeders\\IngestSettingsSeeder
```

Edit the seeder file to customize which settings to populate.

### **Creating a Settings UI**

You can create a settings page in your Laravel application to allow users to modify these values through a web interface:

```php
// Example controller
use prophoto\Ingest\Services\IngestSettingsService;

public function update(Request $request, IngestSettingsService $settings)
{
    $validated = $request->validate([
        'exif.thumbnail.quality' => 'integer|min:1|max:100',
        'schema.path' => 'string',
        // ... other validation rules
    ]);

    $settings->setMultiple($validated);

    return back()->with('success', 'Settings updated!');
}
```

---

## Usage

### **Basic Workflow**

1. **Access the panel:** Navigate to `/ingest` in your Laravel app
2. **Upload photos:** Click "Add Photos" or drag-and-drop
3. **Review & cull:** Browse thumbnails, preview images, check metadata
4. **Tag photos:** Apply tags before final ingest
5. **Select keepers:** Mark photos to keep, cull rejects
6. **Ingest:** Click "Ingest Selected" to process into final storage

### **Three-Tier Image Sizing System**

For optimal performance, the module generates three versions of your images:

1. **Thumbnail** (400x400px) - Displayed in the grid view for quick browsing
2. **Preview** (2048px max) - Displayed in the preview panel for detailed inspection
3. **Original** (full size) - Stored for final ingest (optionally resized)

This architecture ensures blazing-fast preview browsing without downloading full-size RAW files (which can be 30-50MB each). A typical 30MB RAW file generates a ~2MB preview, resulting in **15x faster loading** and **~50% bandwidth reduction** for culling workflows.

### **Keyboard Shortcuts**

- `C` - Toggle cull on selected photo(s)
- `S` - Toggle star on selected photo(s)
- `→` - Next photo
- `←` - Previous photo
- `Enter` - Add tag (when typing in tag input)

### **Advanced Features**

#### **Chart-Based Selection**

Click on any chart segment (ISO, Aperture, Focal Length) to automatically select all photos matching that value. Use cumulative mode to build complex selections.

#### **Polymorphic Associations**

Associate ingested images with your Laravel models:

```php
// In your config
'associations' => [
    'models' => [
        'App\Models\Shoot',
        'App\Models\Project',
    ],
],

// When ingesting (via API)
POST /ingest/ingest
{
    "ids": ["uuid1", "uuid2"],
    "association": {
        "type": "App\\Models\\Shoot",
        "id": 123
    }
}
```

#### **Tag Sources**

Pull tag suggestions from your existing models:

```php
'tagging' => [
    'sources' => [
        'Clients' => 'App\Models\Client',
        'Locations' => 'App\Models\Location',
    ],
],
```

---

## Development

### **Package Structure**

```
prophoto-ingest/
├── src/
│   ├── Http/Controllers/     # Laravel controllers
│   ├── Services/              # Business logic
│   │   ├── MetadataExtractor.php
│   │   └── IngestProcessor.php
│   ├── Models/                # Eloquent models
│   │   ├── ProxyImage.php
│   │   ├── Image.php
│   │   └── Tag.php
│   ├── Jobs/                  # Queue jobs
│   │   └── ProcessImageIngestJob.php
│   └── IngestServiceProvider.php
├── resources/
│   ├── js/                    # React components
│   │   ├── Components/
│   │   └── Pages/
│   └── views/                 # Blade templates
├── database/migrations/       # Database schema
├── config/ingest.php          # Configuration
├── routes/web.php             # Package routes
└── dist/                      # Built assets
```

### **Building Frontend Assets**

When developing the package:

```bash
cd /path/to/prophoto-ingest

# Install dependencies (first time only)
npm install

# Build for production
npm run build

# In your Laravel project, republish assets
cd /path/to/laravel-project
php artisan vendor:publish --tag=ingest-assets --force
```

### **Path Repository Development**

For active development, use a path repository:

```json
// composer.json in your Laravel project
"repositories": [
    {
        "type": "path",
        "url": "../prophoto-ingest"
    }
]
```

This creates a symlink, so **PHP changes reflect immediately** without reinstalling. Only frontend changes need rebuilding.

---

## API Reference

### **Upload Photos**

```http
POST /ingest/upload
Content-Type: multipart/form-data

file: (binary)
```

**Response:**

```json
{
    "photo": {
        "id": "uuid",
        "filename": "IMG_1234.jpg",
        "thumbnail": "http://...",
        "camera": "Canon EOS R5",
        "iso": 400,
        "aperture": 2.8,
        ...
    }
}
```

### **Update Photo**

```http
PATCH /ingest/photos/{uuid}
Content-Type: application/json

{
    "is_culled": true,
    "is_starred": false,
    "rating": 5,
    "tags_json": ["portrait", "outdoor"]
}
```

### **Batch Update**

```http
POST /ingest/photos/batch
Content-Type: application/json

{
    "ids": ["uuid1", "uuid2"],
    "updates": {
        "is_starred": true,
        "tags_json": ["wedding", "ceremony"]
    }
}
```

### **Final Ingest**

```http
POST /ingest/ingest
Content-Type: application/json

{
    "ids": ["uuid1", "uuid2"],
    "association": {
        "type": "App\\Models\\Shoot",
        "id": 123
    }
}
```

**Response:**

```json
{
    "message": "Queued 2 photos for ingest",
    "count": 2
}
```

---

## Troubleshooting

### **Thumbnails not displaying**

1. Check storage link: `php artisan storage:link`
2. Verify directory exists: `storage/app/ingest-temp/thumbs/`
3. Check permissions: `chmod -R 775 storage/`
4. Ensure GD or Imagick is installed: `php -m | grep -E "(gd|imagick)"`

### **EXIF data not extracting**

1. Check EXIF extension: `php -m | grep exif`
2. Test with JPEG files (RAW requires Imagick)
3. Try with phone photos (guaranteed EXIF data)

### **Upload fails with 500 error**

1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify temp directory exists: `storage/app/ingest-temp/`
3. Check file upload limits: `php.ini` (`upload_max_filesize`, `post_max_size`)

### **Queue jobs not processing**

1. Verify queue driver in `.env`: `QUEUE_CONNECTION=database`
2. Run queue tables migration: `php artisan queue:table && php artisan migrate`
3. Start worker: `php artisan queue:work`
4. Check failed jobs: `php artisan queue:failed`

### **Assets not loading**

1. Verify assets published: `ls -la public/vendor/ingest/`
2. Republish: `php artisan vendor:publish --tag=ingest-assets --force`
3. Clear cache: `php artisan optimize:clear`

### **Host/URL configuration issues**

If the ingest panel loads but some requests fail with `ERR_CONNECTION_REFUSED` in the browser console:

1. Check that your `.env` `APP_URL` matches the URL you use in the browser.
   - Example: if you access the app at `http://pears.test`, set `APP_URL=http://pears.test`.
2. Clear caches if needed: `php artisan optimize:clear`.
3. Reload the ingest panel.

---

## Uninstalling

```bash
# Run uninstall script
bash vendor/prophoto/ingest/uninstall.sh

# Or manually:
composer remove prophoto/ingest
php artisan migrate:rollback --path=database/migrations/2025_01_01_*
rm -rf public/vendor/ingest
rm -rf storage/app/ingest-temp
rm config/ingest.php
```

---

## Roadmap

- [ ] Video support
- [ ] Advanced batch editing (crop, rotate, color adjustments)
- [ ] AI-powered auto-tagging
- [ ] Face recognition
- [ ] GPS mapping view
- [ ] Collections/albums
- [ ] Export presets
- [ ] RAW to JPEG conversion
- [ ] Watermarking

---

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## Credits

Built by [Dave Peloso](mailto:dave@prophoto.com) for professional photographers.

**Tech Stack:**

- Laravel 11+
- React + TypeScript
- Inertia.js
- Tailwind CSS
- Radix UI
- Intervention Image
- Recharts

---

## License

MIT License - see LICENSE file for details.

Using the CI/CD Workflow
Automatic Triggers
The workflow runs automatically on:

Push to main or develop branches
Pull requests to main
Local Development Commands
PHP Testing & Quality:

`composer test`             # Run all tests with Pest
`composer test:unit`        # Run only unit tests
`composer test:feature`     # Run only feature tests
`composer test:integration` # Run only integration tests
`composer test:coverage`    # Run tests with coverage report
`composer test:fast`       # Fast dev mode (unit + feature only)
`composer lint`         # Check code style (Pint) + static analysis (PHPStan)
`composer lint:fix`       # Auto-fix code style issues

Frontend Quality:

`npm run lint`              # ESLint check for TypeScript/React
`npm run lint:fix`           # Auto-fix ESLint issues
`npm run type-check`        # TypeScript type checking

Test Switchboard Profiles
Control which tests run via environment variables or the config/ingest-tests.php profiles:

**Profile Use Case**
- fast-dev Local development - only unit + feature tests
- pre-commit Quick smoke test - code quality + unit
- full-ci Everything enabled (runs in CI)
- pre-release Comprehensive without performance tests
Set individual suites with env vars:

`TEST_UNIT=true TEST_FEATURE=false composer test`

CI Pipeline Jobs
When a PR is opened or code is pushed, GitHub Actions runs:

Code Quality → Pint, PHPStan, ESLint, TypeScript
Unit Tests → PHP 8.2 + 8.3 matrix
Feature Tests → HTTP endpoint tests
Integration Tests → Full pipeline (after unit/feature pass)
Security Audit → Composer + NPM vulnerability scans
Performance Tests → Benchmarks

## Support

For issues, questions, or feature requests, please open an issue on GitHub.

**Need help with installation?** Check the troubleshooting section above or run the install script with verbose output.
