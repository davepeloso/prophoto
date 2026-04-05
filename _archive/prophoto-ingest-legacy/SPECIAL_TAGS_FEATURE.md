# Special Tags Feature: Project and Filename Tags

## Overview
This feature adds two special tag types to the ingest system that affect file organization during final ingest:

- **Project Tag**: Used in the file path pattern (e.g., organize by project location like "123-Main-Street")
- **Filename Tag**: Used in the filename pattern (e.g., name files by room like "Living-Room")

## Database Changes

### New Migrations
1. **`2026_01_07_000001_add_tag_type_to_ingest_tags_table.php`**
   - Adds `tag_type` column to `ingest_tags` table
   - Values: `normal`, `project`, `filename`
   - Indexed for performance

2. **`2026_01_07_000002_create_ingest_proxy_image_tag_table.php`**
   - Creates pivot table `ingest_proxy_image_tag`
   - Links proxy images to tags via proper relationship
   - Replaces the legacy `tags_json` column approach

3. **`2026_01_07_000003_migrate_tags_json_to_relationship.php`**
   - Data migration to move existing `tags_json` data to the new relationship
   - Backwards compatible - doesn't remove `tags_json` column

## Model Changes

### Tag Model (`src/Models/Tag.php`)
- Added constants: `TYPE_NORMAL`, `TYPE_PROJECT`, `TYPE_FILENAME`
- Added `tag_type` to fillable fields
- New relationship: `proxyImages()` - links to ProxyImage
- New scopes: `project()`, `filename()`, `normal()`, `ofType()`
- New helper methods: `isProject()`, `isFilename()`, `isNormal()`
- Updated `findOrCreateByName()` to accept tag type parameter

### ProxyImage Model (`src/Models/ProxyImage.php`)
- New relationship: `tags()` - links to Tag via pivot table
- New helper method: `getProjectTag()` - retrieves the project tag
- New helper method: `getFilenameTag()` - retrieves the filename tag

## Service Changes

### IngestProcessor (`src/Services/IngestProcessor.php`)
- Updated `replacePlaceholders()` method to support:
  - `{project}` placeholder - uses Project tag name (slugified)
  - `{filename}` placeholder - uses Filename tag name (slugified)
- Updated tag syncing to use both:
  - New tags relationship (preferred)
  - Legacy `tags_json` (backwards compatibility)

## Configuration Changes

### Config File (`config/ingest.php`)
Updated schema documentation to include:
- `{project}` - Project tag name (slugified) - requires Project tag
- `{filename}` - Filename tag name (slugified) - requires Filename tag

### Example Usage

**Path Pattern with Project Tag:**
```php
'path' => 'shoots/{date:Y}/{date:m}/{project}'
// Output: shoots/2025/01/123-main-street/
```

**Filename Pattern with Filename Tag:**
```php
'filename' => '{sequence}-{filename}'
// Output: 001-living-room.jpg
```

**Combined Example:**
```php
'path' => 'projects/{project}/{date:Y-m-d}',
'filename' => '{filename}-{sequence}'
// Output: projects/123-main-street/2025-01-07/living-room-001.jpg
```

## How It Works

1. **Tag Assignment**: Users assign tags to proxy images just like normal tags
2. **Tag Type**: Tags are marked as `project`, `filename`, or `normal` type
3. **During Ingest**: 
   - IngestProcessor reads the project and filename tags from the proxy
   - Replaces `{project}` and `{filename}` placeholders in path/filename patterns
   - If no special tag is assigned, the placeholder is replaced with empty string
4. **Multiple Projects**: Each proxy image can have one project tag, allowing different images in the same ingest to go to different project folders

## Backwards Compatibility

- Existing `tags_json` column is preserved and still works
- IngestProcessor checks both tags relationship and `tags_json`
- Migration automatically converts existing `tags_json` data to relationships
- All existing functionality continues to work unchanged

## Migration Instructions

1. Run migrations:
   ```bash
   php artisan migrate
   ```

2. The data migration will automatically convert existing tags to the new system

3. Update your schema patterns in settings or config to use `{project}` and `{filename}` placeholders as needed

## API/UI Integration Notes

To fully utilize this feature, the UI should:
1. Allow users to create tags with specific types (normal, project, filename)
2. Show visual indicators for special tag types
3. Limit assignment to one project tag and one filename tag per image
4. Provide quick-select for project/filename tags during tagging

## Database Schema

```
ingest_tags
├── id
├── name
├── slug
├── color
└── tag_type (NEW: 'normal', 'project', 'filename')

ingest_proxy_image_tag (NEW)
├── proxy_image_id
└── tag_id
```
