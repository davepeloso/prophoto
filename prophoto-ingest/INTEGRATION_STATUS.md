# Special Tags Feature - Integration Status

## Completed Work

### Backend Implementation ✅

1. **Database Schema**
   - Added `tag_type` column to `ingest_tags` table
   - Created `ingest_proxy_image_tag` pivot table
   - Data migration to convert `tags_json` to relationships

2. **Models**
   - `Tag` model: Added tag type constants, scopes, and helper methods
   - `ProxyImage` model: Added tags relationship and helper methods
   - Updated `toReactArray()` to return full tag objects

3. **API Endpoints**
   - `GET /ingest/tags` - Returns full tag objects with filtering
   - `POST /ingest/tags` - Create tags with type and color
   - `POST /ingest/photos/{uuid}/tags` - Add tags (append mode)
   - `PUT /ingest/photos/{uuid}/tags` - Assign tags (replace mode)
   - `DELETE /ingest/photos/{uuid}/tags/{tagId}` - Remove tag
   - `POST /ingest/photos/batch` - Updated for tag relationships

4. **Validation**
   - Enforces one project tag per image
   - Enforces one filename tag per image
   - Returns 422 errors when limits exceeded

5. **IngestProcessor**
   - Uses `{project}` and `{filename}` placeholders in schema
   - Syncs tags from relationship to Image model

### Frontend Implementation ✅

1. **TypeScript Types**
   - Added `TagType` type
   - Added `Tag` interface
   - Updated `Photo.tags` to `Tag[]`
   - Updated `Filters.tags` to `Tag[]`

2. **UI Components**
   - **TagsModal**: Radio buttons for tag type selection with icons
   - **FilterSidebar**: Separate sections for Project/Filename/Normal tags
   - **radio-group**: New component for radio button groups

3. **Panel.tsx Updates**
   - Updated props to accept `Tag[]` for availableTags
   - Updated `handleAddTags` to work with Tag objects and call new API
   - Updated `handleRemoveTag` to use DELETE endpoint
   - Updated filtering logic to work with Tag objects
   - Updated `allTags` and `recentTags` to use Tag objects

### Documentation ✅

- `API_TAG_ENDPOINTS.md` - Complete API documentation
- `UI_IMPLEMENTATION_NOTES.md` - UI changes and next steps
- `SPECIAL_TAGS_FEATURE.md` - Overall feature documentation

## Current Issues to Resolve

### TypeScript Compilation Errors

The following files need minor fixes to resolve TypeScript errors:

1. **TagsModal.tsx** - Needs to be updated to work with Tag objects:
   ```typescript
   // Line 41: Update to pass Tag objects
   onAddTags(Array.from(selectedIds), [{ name: inputValue.trim(), ... }], tagType)
   
   // Line 46-48: Update handleQuickTag to accept Tag object
   const handleQuickTag = (tag: Tag) => {
     if (selectedIds.size === 0) return
     onAddTags(Array.from(selectedIds), [tag], tag.tag_type)
   }
   
   // Line 135-136: Update to use tag.name
   {recentTags.map((tag) => (
     <button key={tag.id} onClick={() => handleQuickTag(tag)}>
       {tag.name}
     </button>
   ))}
   ```

2. **FilterSidebar.tsx** - Needs to filter tags by type:
   ```typescript
   // Separate tags by type
   const projectTags = allTags.filter(t => t.tag_type === 'project')
   const filenameTags = allTags.filter(t => t.tag_type === 'filename')
   const normalTags = allTags.filter(t => t.tag_type === 'normal')
   ```

3. **Panel.tsx** - Minor fixes:
   - Remove unused `config` and `quickTags` props (or use them)
   - Fix `filters.tags` state initialization to use `Tag[]`

## Testing Checklist

Once TypeScript errors are resolved:

- [ ] Run `php artisan migrate` to apply database changes
- [ ] Test creating tags with different types via API
- [ ] Test assigning tags to photos
- [ ] Test tag type limits (one project, one filename)
- [ ] Test UI tag type selection in TagsModal
- [ ] Test FilterSidebar displays tags by type
- [ ] Test ingest process uses project/filename tags in paths
- [ ] Verify backwards compatibility with existing tags

## Migration Steps for Production

1. **Run migrations**:
   ```bash
   php artisan migrate
   ```

2. **Verify data migration**:
   - Check that existing tags were migrated to relationship table
   - Verify `tags_json` is still populated for backwards compatibility

3. **Update frontend build**:
   ```bash
   npm run build
   ```

4. **Test thoroughly** in staging environment before production

## API Usage Examples

### Create a Project Tag
```javascript
const response = await fetch('/ingest/tags', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    name: '123 Main Street',
    tag_type: 'project',
    color: '#3b82f6'
  })
});
```

### Add Tags to Photos
```javascript
await fetch('/ingest/photos/batch', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    ids: ['uuid-1', 'uuid-2'],
    updates: {
      tags: [
        { name: '123 Main Street', tag_type: 'project' },
        { name: 'Living Room', tag_type: 'filename' }
      ]
    }
  })
});
```

## Schema Configuration

Update `config/ingest.php` to use special tags:

```php
'schema' => [
    'path' => 'shoots/{date:Y}/{date:m}/{project}',
    'filename' => '{sequence}-{filename}',
],
```

Result: `shoots/2025/01/123-main-street/001-living-room.jpg`

## Known Limitations

1. **Tag Type Changes**: Once a tag is created with a type, changing its type requires manual database update
2. **Legacy Support**: `tags_json` column is maintained for backwards compatibility but will eventually be deprecated
3. **UI Validation**: Frontend doesn't yet prevent adding multiple project/filename tags (server validates)

## Next Session Priorities

1. Fix remaining TypeScript errors in TagsModal and FilterSidebar
2. Add client-side validation for tag type limits
3. Test complete flow end-to-end
4. Consider adding tag type indicators in thumbnail view
5. Add ability to edit tag types in settings/admin panel
