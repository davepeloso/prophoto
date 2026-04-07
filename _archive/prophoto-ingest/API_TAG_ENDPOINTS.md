# Tag Management API Endpoints

## Overview

This document describes the API endpoints for managing tags with support for special tag types (normal, project, filename).

## Tag Types

-   **normal**: Standard tags for general categorization
-   **project**: Special tags used in file path generation (e.g., "123-Main-Street")
-   **filename**: Special tags used in filename generation (e.g., "Living-Room")

**Constraints:** - Only **one project tag** allowed per image - Only **one filename tag** allowed per image - Unlimited normal tags per image

------------------------------------------------------------------------

## Endpoints

### 1. Get Available Tags

**GET** `/ingest/tags`

Retrieve tags for autocomplete or listing.

**Query Parameters:** - `q` (optional): Search query to filter tags by name - `type` (optional): Filter by tag type (`normal`, `project`, `filename`)

**Response:**

``` json
[
  {
    "id": 1,
    "name": "123 Main Street",
    "slug": "123-main-street",
    "color": "#3b82f6",
    "tag_type": "project"
  },
  {
    "id": 2,
    "name": "Living Room",
    "slug": "living-room",
    "color": "#10b981",
    "tag_type": "filename"
  }
]
```

------------------------------------------------------------------------

### 2. Create Tag

**POST** `/ingest/tags`

Create a new tag with optional type and color.

**Request Body:**

``` json
{
  "name": "123 Main Street",
  "tag_type": "project",
  "color": "#3b82f6"
}
```

**Validation:** - `name`: Required, string, max 50 characters - `tag_type`: Optional, one of: `normal`, `project`, `filename` (defaults to `normal`) - `color`: Optional, hex color format `#RRGGBB`

**Response:**

``` json
{
  "tag": {
    "id": 1,
    "name": "123 Main Street",
    "slug": "123-main-street",
    "color": "#3b82f6",
    "tag_type": "project"
  }
}
```

------------------------------------------------------------------------

### 3. Add Tags to Photo (Append Mode)

**POST** `/ingest/photos/{uuid}/tags`

Add tags to a photo without removing existing tags.

**Request Body:**

``` json
{
  "tags": [
    {
      "name": "123 Main Street",
      "tag_type": "project"
    },
    {
      "name": "Living Room",
      "tag_type": "filename"
    },
    {
      "name": "Interior"
    }
  ]
}
```

**Validation:** - `tags`: Required array - `tags.*.name`: Required, string, max 50 characters - `tags.*.tag_type`: Optional, one of: `normal`, `project`, `filename`

**Response (Success):**

``` json
{
  "message": "Tags added successfully",
  "tags": [
    {
      "id": 1,
      "name": "123 Main Street",
      "slug": "123-main-street",
      "color": "#3b82f6",
      "tag_type": "project"
    },
    {
      "id": 2,
      "name": "Living Room",
      "slug": "living-room",
      "color": "#10b981",
      "tag_type": "filename"
    },
    {
      "id": 3,
      "name": "Interior",
      "slug": "interior",
      "color": null,
      "tag_type": "normal"
    }
  ]
}
```

**Response (Error - Limit Exceeded):**

``` json
{
  "error": "Only one project tag allowed per image",
  "tag": "456 Oak Avenue"
}
```

Status: 422

------------------------------------------------------------------------

### 4. Assign Tags to Photo (Replace Mode)

**PUT** `/ingest/photos/{uuid}/tags`

Replace all tags on a photo with the provided tags.

**Request Body:**

``` json
{
  "tags": [
    {
      "name": "123 Main Street",
      "tag_type": "project"
    }
  ]
}
```

**Validation:** Same as Add Tags endpoint

**Response:** Same format as Add Tags endpoint

**Note:** This endpoint uses `sync()` which removes all existing tags and replaces them with the new set.

------------------------------------------------------------------------

### 5. Remove Tag from Photo

**DELETE** `/ingest/photos/{uuid}/tags/{tagId}`

Remove a specific tag from a photo.

**URL Parameters:** - `uuid`: Photo UUID - `tagId`: Tag ID to remove

**Response:**

``` json
{
  "message": "Tag removed successfully"
}
```

------------------------------------------------------------------------

### 6. Batch Update Photos

**POST** `/ingest/photos/batch`

Update multiple photos at once, including tag assignment.

**Request Body (New Format with Tag Types):**

``` json
{
  "ids": ["uuid-1", "uuid-2", "uuid-3"],
  "updates": {
    "tags": [
      {
        "name": "123 Main Street",
        "tag_type": "project"
      },
      {
        "name": "Exterior"
      }
    ]
  }
}
```

**Request Body (Legacy Format - Still Supported):**

``` json
{
  "ids": ["uuid-1", "uuid-2"],
  "updates": {
    "tags_json": ["Tag1", "Tag2"]
  }
}
```

**Other Update Fields:** - `is_culled`: boolean - `is_starred`: boolean - `rating`: integer (0-5) - `rotation`: integer - `order_index`: integer

**Response:**

``` json
{
  "photos": [
    {
      "id": "uuid-1",
      "tags": ["123 Main Street", "Exterior"],
      ...
    }
  ]
}
```

**Behavior:** - New `tags` format: Appends tags to existing tags (respects tag type limits) - Legacy `tags_json` format: Merges with existing tags, syncs to relationship table - Automatically maintains backwards compatibility with `tags_json` column

------------------------------------------------------------------------

## Usage Examples

### Example 1: Create and Assign Project Tag

``` javascript
// 1. Create a project tag
const createResponse = await fetch('/ingest/tags', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    name: '123 Main Street',
    tag_type: 'project',
    color: '#3b82f6'
  })
});

const { tag } = await createResponse.json();

// 2. Assign to photos
await fetch('/ingest/photos/uuid-123/tags', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    tags: [
      {
        name: tag.name,
        tag_type: tag.tag_type
      }
    ]
  })
});
```

### Example 2: Batch Tag Multiple Photos

``` javascript
await fetch('/ingest/photos/batch', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    ids: ['uuid-1', 'uuid-2', 'uuid-3'],
    updates: {
      tags: [
        { name: '123 Main Street', tag_type: 'project' },
        { name: 'Living Room', tag_type: 'filename' },
        { name: 'Interior' } // defaults to 'normal'
      ]
    }
  })
});
```

### Example 3: Filter Tags by Type

``` javascript
// Get only project tags
const projectTags = await fetch('/ingest/tags?type=project')
  .then(r => r.json());

// Get only filename tags
const filenameTags = await fetch('/ingest/tags?type=filename')
  .then(r => r.json());
```

------------------------------------------------------------------------

## Integration with Schema Placeholders

Tags created with special types are automatically used in file organization:

**Path Pattern:**

```         
shoots/{date:Y}/{date:m}/{project}
```

**Filename Pattern:**

```         
{sequence}-{filename}
```

**Example Result:** - Photo with project tag "123 Main Street" and filename tag "Living Room" - Date: 2025-01-07 - Sequence: 1

**Final Path:**

```         
shoots/2025/01/123-main-street/001-living-room.jpg
```

------------------------------------------------------------------------

## Error Handling

### 422 Unprocessable Entity

Returned when tag type limits are exceeded:

``` json
{
  "error": "Only one project tag allowed per image",
  "tag": "456 Oak Avenue"
}
```

### 404 Not Found

Returned when photo UUID doesn't exist or doesn't belong to the user.

### 400 Bad Request

Returned when validation fails (invalid tag_type, missing required fields, etc.).

------------------------------------------------------------------------

## Migration Notes

### Backwards Compatibility

The system maintains full backwards compatibility:

1.  **Legacy `tags_json` column** continues to work
2.  When tags are added via relationship, `tags_json` is automatically updated
3.  When tags are added via `tags_json`, they're synced to the relationship table
4.  Both methods can be used interchangeably

### Recommended Migration Path

1.  Update frontend to use new tag object format
2.  Use new endpoints (`POST /photos/{uuid}/tags`) for tag assignment
3.  Legacy code using `tags_json` will continue to work during transition
4.  Eventually deprecate `tags_json` in favor of relationship-based tags