# UI Implementation Notes: Special Tags Feature

## Overview
This document describes the UI changes made to support Project and Filename tag types in the ingest system.

## Changes Made

### 1. TypeScript Types (`resources/js/types.ts`)
- Added `TagType` type: `'normal' | 'project' | 'filename'`
- Added `Tag` interface with full tag properties including `tag_type`
- Backend now returns full tag objects instead of just tag names

### 2. TagsModal Component (`resources/js/Components/TagsModal.tsx`)
- Added radio button group for selecting tag type when creating new tags
- Three options: Normal, Project, Filename
- Visual indicators:
  - Normal: Tag icon (gray)
  - Project: Folder icon (blue)
  - Filename: File icon (green)
- Helper text explains what each tag type does
- Tag type resets to "normal" after adding a tag

### 3. FilterSidebar Component (`resources/js/Components/FilterSidebar.tsx`)
- Reorganized tags section into three subsections:
  - **Project Tags**: Blue colored, folder icon
  - **Filename Tags**: Green colored, file icon
  - **Other Tags**: Default styling
- Each section has visual indicators matching the TagsModal
- Quick access to special tags for filtering

### 4. Radio Group Component (`resources/js/Components/ui/radio-group.tsx`)
- Created new UI component for radio button groups
- Native HTML implementation (no external dependencies)
- Follows existing UI component patterns

### 5. Backend Controller Update (`src/Http/Controllers/IngestController.php`)
- Updated `index()` method to return full tag objects with `tag_type`
- Changed from `pluck('name')` to `get()->map()` with all tag properties

## Current State

### What Works
✅ Database schema supports tag types
✅ Models have tag type functionality
✅ IngestProcessor uses special tags in path/filename generation
✅ UI displays tag type selection in TagsModal
✅ FilterSidebar shows special tags in separate sections
✅ Backend returns tag objects with tag_type

### What Needs Additional Work
⚠️ **Tag Creation**: The current implementation allows selecting tag type in the UI, but the actual API call to create/assign tags needs to be updated to:
  1. Accept tag type parameter when creating new tags
  2. Store tags in the relationship table instead of just `tags_json`
  3. Properly handle tag type when syncing to proxy images

⚠️ **API Endpoints**: Need to create/update endpoints for:
  - Creating tags with specific types
  - Assigning tags with types to proxy images
  - Updating existing tags' types

⚠️ **Frontend Integration**: The `Panel.tsx` component needs updates to:
  - Accept `Tag[]` instead of `string[]` for availableTags
  - Pass tag type when calling API to add tags
  - Handle tag objects throughout the component

## Recommended Next Steps

1. **Create Tag Management API Endpoints**:
   ```php
   // POST /ingest/tags - Create new tag with type
   // POST /ingest/photos/{id}/tags - Assign tags to photo
   // PUT /ingest/tags/{id} - Update tag properties
   ```

2. **Update Panel.tsx**:
   - Change `availableTags` prop type from `string[]` to `Tag[]`
   - Update `handleAddTags` to accept tag objects or create them with type
   - Update all tag-related state and functions

3. **Update ProxyImage API Response**:
   - Include full tag objects (not just names) in `toReactArray()`
   - Return tag type information with each photo

4. **Add Tag Type Validation**:
   - Limit one project tag per image
   - Limit one filename tag per image
   - Show warnings in UI when limits are reached

## Usage Example

Once fully implemented, users will be able to:

1. **Create Special Tags**:
   - Open Tags Modal
   - Select "Project" or "Filename" radio button
   - Enter tag name (e.g., "123 Main Street")
   - Tag is created with the selected type

2. **Use in Schema**:
   - Configure path: `shoots/{date:Y}/{date:m}/{project}`
   - Configure filename: `{sequence}-{filename}`
   - Images with project tag "123 Main Street" go to: `shoots/2025/01/123-main-street/`
   - Images with filename tag "Living Room" become: `001-living-room.jpg`

3. **Filter by Special Tags**:
   - Use FilterSidebar to see Project and Filename tags separately
   - Click to filter images by project or filename
   - Visual indicators show tag types

## Visual Design

### Tag Type Indicators
- **Project**: 🗂️ Blue folder icon
- **Filename**: 📄 Green file icon  
- **Normal**: 🏷️ Gray tag icon

### Color Scheme
- Project tags: Blue (#3b82f6)
- Filename tags: Green (#10b981)
- Normal tags: Default theme colors
