# ProPhoto Phase 1: Architecture Design Document

**Status:** Phase 3 Architecture Design  
**Version:** 1.0  
**Created:** April 10, 2026  
**Based on:** PRD-phase-1-epic-ingest-workflow.md  
**Target Implementation:** Weeks 1-12 of Phase 1

---

## Executive Summary

This document translates the Phase 1 epic PRD into a detailed technical architecture. It specifies:

- **Data flows** — How metadata, calendar events, and user decisions flow through the system
- **Component interactions** — Frontend ↔ Backend ↔ Database communication
- **API contracts** — New endpoints and payload structures
- **Database operations** — Schema usage and data consistency patterns
- **Performance & scalability** — Concurrent uploads, real-time filtering, metadata extraction
- **Error handling** — Network failures, corrupt data, edge cases
- **Integration points** — How ingest connects to existing prophoto packages

This architecture maintains the event-driven modular monolith structure and respects all existing contracts. **No breaking changes to existing packages.**

---

## Part 1: High-Level Data Flow

### The Complete Phase 1 Journey

```
User Action → Frontend Processing → Backend Processing → Database → Event Emission → Package Integration

[Step 1: Upload Initiation]
User drags 100 RAW files into browser
    ↓
Frontend detects file inputs
    ↓
Trigger: "Metadata Extraction Phase"

[Step 2: Metadata Extraction (Frontend)]
Browser reads file EXIF using exifjs library
    ↓
Builds metadata map: {
  "img_001.cr2": { iso: 400, aperture: 1.8, shutter: 1/1000, focal: 50, camera: "Canon 5D Mark IV", timestamp: 2026-04-10T14:32:15Z, gps: [lat, lng] },
  "img_002.cr2": { ... },
  ...
}
    ↓
Sends to backend: POST /api/ingest/match-calendar { metadata[], studio_id, user_id }

[Step 3: Calendar Matching (Backend)]
Backend receives metadata array
    ↓
Queries calendar OAuth token for user
    ↓
Fetches calendar events for +/- 2 hours around image timestamp window
    ↓
Runs SessionMatchingService on metadata vs. events
    ↓
Returns ranked matches: [
  { event_id: "abc123", title: "Johnson Wedding", confidence: 0.95, evidence: {...} },
  { event_id: "def456", title: "Family Portrait", confidence: 0.72, evidence: {...} }
]
    ↓
Sends to frontend: { matches: [...], upload_session_id: "xyz789" }

[Step 4: User Selection & Upload Initiation]
Frontend displays matches: "We found 2 calendar events"
    ↓
User clicks "Johnson Wedding" (or "Skip calendar")
    ↓
Frontend begins file upload: POST /api/ingest/upload
    ↓
Backend creates upload session in database
    ↓
Returns: { upload_session_id: "xyz789", gallery_id: "new-gallery-id" }

[Step 5: Background Upload + Gallery Launch]
Upload begins (files transfer in background)
    ↓
Frontend simultaneously launches Gallery Ingest UI
    ↓
Shows: Thumbnail browser (empty initially), preview area, tagging panel
    ↓
As files complete upload, thumbnails appear in gallery

[Step 6: User Tags & Filters]
User applies tags while upload continues
    ↓
Each tag application: POST /api/ingest/tag { image_id, tag }
    ↓
Backend persists tag to database
    ↓
Frontend re-renders filtered thumbnails

[Step 7: Upload Completion]
All files complete
    ↓
Backend emits: SessionAssociationResolved event
    ↓
prophoto-assets processes event; creates Asset records for each image
    ↓
prophoto-gallery links assets to gallery
    ↓
prophoto-intelligence receives images for background AI processing

[Step 8: Post-Upload Guidance]
Frontend shows: "Upload complete. Next steps: [Attach clients] [Choose gallery template] [Configure quote]"
    ↓
User proceeds to gallery management or post-upload workflow
```

---

## Part 2: Component Architecture

### Frontend Components (React/Next.js)

**Location:** `packages/prophoto-web/app/ingest/` (new module)

#### 2.1 — Metadata Extractor Service
- **Purpose:** Extract EXIF from files before upload
- **Library:** exifjs (npm package)
- **Interface:**
  ```typescript
  async extractMetadata(files: File[]): Promise<ImageMetadata[]>
  
  interface ImageMetadata {
    filename: string
    fileSize: number
    exif: {
      iso: number
      aperture: number
      shutter: string // e.g., "1/1000"
      focalLength: number
      camera: string
      timestamp: ISO8601string
      gps?: { lat: number; lng: number }
    }
  }
  ```
- **Performance:** Process 100 files in < 5 seconds (browser-side, no network)
- **Error handling:** If EXIF parse fails for a file, log warning and continue

#### 2.2 — Gallery Ingest UI Components
- **IngestContainer** — Main layout orchestrator (split pane: thumbnails | preview | controls)
- **ThumbnailBrowser** — Virtualized list of image thumbnails with selection checkboxes
- **ImagePreview** — Large preview + EXIF metadata display
- **TaggingPanel** — Input field + applied tags + bulk tag application
- **FilterSidebar** — Metadata filters (ISO, aperture, focal length, camera), tag filters, timeline slider, cull toggle
- **ChartsTab** — ISO distribution, aperture distribution, focal length distribution, timeline histogram, camera model distribution
- **CalendarTab** — Matched event details, date/time range, associated images, image count

#### 2.3 — Upload Manager Service
- **Purpose:** Handle background file transfer
- **Interface:**
  ```typescript
  class UploadManager {
    startUpload(files: File[], session_id: string): void
    getProgress(): { completed: number; total: number; inProgress: boolean }
    on('complete', callback): void
    on('error', callback): void
    cancel(): void
  }
  ```
- **Strategy:** Parallel uploads (3-5 concurrent files)
- **Resumability:** Track completed files; resume on reconnect

#### 2.4 — Filter Engine
- **Purpose:** Real-time filtering on client side
- **Logic:**
  ```typescript
  interface Filter {
    iso?: number[]
    aperture?: number[]
    focalLength?: number[]
    camera?: string[]
    tags?: string[]
    timeWindow?: { start: ISO8601string; end: ISO8601string }
    showCulled?: boolean
  }
  
  function applyFilters(images: Image[], filters: Filter): Image[]
  ```
- **Performance:** < 100ms re-render for 100 images

#### 2.5 — State Management (Zustand or Context API)
- **IngestStore:**
  ```typescript
  interface IngestState {
    images: Image[]
    filters: Filter
    selectedImageIds: string[]
    appliedTags: { [imageId: string]: string[] }
    uploadProgress: { completed: number; total: number }
    calendarMatch?: CalendarMatch
    addImage(image: Image): void
    applyTag(imageId: string, tag: string): void
    removeTag(imageId: string, tag: string): void
    setCulled(imageId: string, culled: boolean): void
    setFilters(filters: Filter): void
    selectImages(imageIds: string[]): void
  }
  ```

---

### Backend Components (Laravel)

**Location:** `packages/prophoto-ingest/` (new package, follows existing modular monolith pattern)

#### 2.6 — Calendar Matcher Service
- **Purpose:** Match image metadata against calendar events
- **Input:** Metadata array + studio_id + user_id
- **Output:** Ranked calendar matches with confidence scores + evidence
- **Logic:**
  ```php
  class CalendarMatcherService {
    public function matchImages(array $metadata, string $studioId, string $userId): array {
      // 1. Get user's calendar OAuth token
      // 2. Query calendar API for events within timestamp window
      // 3. For each event, run scoring algorithm
      // 4. Return ranked results
    }
    
    private function scoreMatch(array $imageMetadata, CalendarEvent $event): float {
      $timeScore = $this->scoreTimeProximity($imageMetadata, $event);
      $locationScore = $this->scoreLocationProximity($imageMetadata, $event);
      $batchScore = $this->scoreBatchCoherence($imageMetadata);
      
      return ($timeScore * 0.55) + ($locationScore * 0.20) + ($batchScore * 0.15);
    }
  }
  ```
- **Reuses:** SessionMatchingService from prophoto-assets (no duplication)

#### 2.7 — Upload Session Manager
- **Purpose:** Track upload state and metadata
- **Model:** `UploadSession` (new database table, see Section 3)
- **Interface:**
  ```php
  class UploadSessionService {
    public function createSession(string $studioId, ?string $calendarEventId): UploadSession
    public function recordFileUpload(string $sessionId, string $filename, ?array $metadata): void
    public function completeSession(string $sessionId): void
  }
  ```

#### 2.8 — Ingest API Controller
- **Endpoints:**
  ```php
  POST /api/ingest/match-calendar
    Request: { metadata: [...], studio_id, user_id }
    Response: { matches: [...], upload_session_id }
  
  POST /api/ingest/upload
    Request: Multipart file upload
    Response: { upload_session_id, gallery_id, status: "uploading" }
  
  GET /api/ingest/status/{sessionId}
    Response: { completed: 100, total: 100, inProgress: false }
  
  POST /api/ingest/tag
    Request: { image_id, tag }
    Response: { success: true }
  
  POST /api/ingest/confirm-session
    Request: { upload_session_id, gallery_event_id }
    Response: { gallery_id, status: "confirmed" }
  ```

#### 2.9 — Asset Ingestion Pipeline
- **Purpose:** Convert uploaded images to Asset records
- **Trigger:** `SessionAssociationResolved` event (emitted after user confirms ingest)
- **Process:**
  ```php
  class IngestAssetProcessor {
    public function handle(SessionAssociationResolved $event): void {
      // For each uploaded file in session:
      // 1. Create Asset record (prophoto-assets)
      // 2. Attach to Gallery (prophoto-gallery)
      // 3. Store applied tags
      // 4. Store upload metadata
      // 5. Emit event to prophoto-intelligence
    }
  }
  ```

#### 2.10 — Tag Storage & Retrieval
- **Purpose:** Persist user-applied tags
- **Model:** `ImageTag` (new table, see Section 3)
- **Service:**
  ```php
  class TagService {
    public function applyTag(string $imageId, string $tag): void
    public function removeTag(string $imageId, string $tag): void
    public function getTags(string $imageId): array
  }
  ```

---

## Part 3: Database Schema

### New Tables

#### 3.1 — upload_sessions
```sql
CREATE TABLE upload_sessions (
  id UUID PRIMARY KEY,
  studio_id UUID NOT NULL,
  user_id UUID NOT NULL,
  
  -- Calendar matching
  calendar_event_id UUID NULLABLE,
  calendar_match_confidence FLOAT NULLABLE,
  calendar_match_evidence JSON NULLABLE,
  
  -- Session metadata
  file_count INT DEFAULT 0,
  total_size_bytes BIGINT DEFAULT 0,
  
  -- Status tracking
  status ENUM('initiated', 'uploading', 'completed', 'failed') DEFAULT 'initiated',
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULLABLE,
  
  -- Associations
  gallery_id UUID NULLABLE,
  
  FOREIGN KEY (studio_id) REFERENCES studios(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (calendar_event_id) REFERENCES calendar_events(id),
  FOREIGN KEY (gallery_id) REFERENCES galleries(id),
  
  INDEX (studio_id, user_id),
  INDEX (status, completed_at)
);
```

#### 3.2 — ingest_files
```sql
CREATE TABLE ingest_files (
  id UUID PRIMARY KEY,
  upload_session_id UUID NOT NULL,
  asset_id UUID NULLABLE, -- Populated after asset creation
  
  -- File metadata
  original_filename VARCHAR(255) NOT NULL,
  file_size_bytes BIGINT NOT NULL,
  file_type VARCHAR(50) NOT NULL, -- 'jpg', 'raw', 'tiff', etc.
  
  -- EXIF metadata (stored as JSON for flexibility)
  exif_data JSON, -- { iso, aperture, shutter, focal_length, camera, timestamp, gps }
  
  -- Upload status
  upload_status ENUM('pending', 'uploading', 'completed', 'failed') DEFAULT 'pending',
  uploaded_at TIMESTAMP NULLABLE,
  
  -- Ingest decisions
  culled BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (upload_session_id) REFERENCES upload_sessions(id),
  FOREIGN KEY (asset_id) REFERENCES assets(id),
  
  INDEX (upload_session_id, upload_status),
  INDEX (asset_id)
);
```

#### 3.3 — ingest_image_tags
```sql
CREATE TABLE ingest_image_tags (
  id UUID PRIMARY KEY,
  ingest_file_id UUID NOT NULL,
  tag VARCHAR(100) NOT NULL,
  
  -- Tag type for filtering
  tag_type ENUM('metadata', 'calendar', 'user') DEFAULT 'user',
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (ingest_file_id) REFERENCES ingest_files(id),
  
  INDEX (ingest_file_id),
  INDEX (tag),
  UNIQUE KEY (ingest_file_id, tag)
);
```

#### 3.4 — upload_session_metadata
```sql
CREATE TABLE upload_session_metadata (
  id UUID PRIMARY KEY,
  upload_session_id UUID NOT NULL,
  
  -- Aggregated metadata stats (for Charts display)
  iso_values JSON, -- { "400": 25, "800": 15, ... }
  aperture_values JSON,
  focal_lengths JSON,
  camera_models JSON,
  time_distribution JSON, -- { "14:00": 10, "14:30": 15, ... }
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (upload_session_id) REFERENCES upload_sessions(id),
  UNIQUE KEY (upload_session_id)
);
```

### Existing Tables (Usage)

#### 3.5 — assets (prophoto-assets)
**New fields:** None required
**Usage:** 
- After ingest completes, create one Asset record per uploaded file
- Link to UploadSession for provenance tracking

#### 3.6 — galleries (prophoto-gallery)
**New fields:** None required
**Usage:**
- UploadSession.gallery_id references a Gallery record
- Gallery is pre-created when calendar match confirmed
- Gallery metadata (title, description) derived from calendar event

#### 3.7 — images (prophoto-assets)
**New fields:** None required
**Usage:**
- Not used in ingest flow
- Populated during asset processing (Phase 2+)

#### 3.8 — photo_sessions (prophoto-booking)
**New fields:** None required
**Usage:**
- If calendar_event_id maps to a photo_session, link them
- This is optional; calendar events may not be photo_sessions

---

## Part 4: API Contract Specifications

### 4.1 — POST /api/ingest/match-calendar

**Purpose:** Match uploaded image metadata against user's calendar events

**Request:**
```json
{
  "metadata": [
    {
      "filename": "IMG_1001.CR2",
      "fileSize": 52428800,
      "exif": {
        "iso": 400,
        "aperture": 1.8,
        "shutter": "1/1000",
        "focalLength": 50,
        "camera": "Canon 5D Mark IV",
        "timestamp": "2026-04-10T14:32:15Z",
        "gps": { "lat": 40.7128, "lng": -74.0060 }
      }
    },
    ...
  ],
  "studio_id": "studio-uuid",
  "user_id": "user-uuid"
}
```

**Response (200):**
```json
{
  "matches": [
    {
      "event_id": "cal-event-abc123",
      "title": "Johnson Wedding",
      "date": "2026-04-10",
      "start_time": "2026-04-10T14:00:00Z",
      "end_time": "2026-04-10T22:00:00Z",
      "location": "The Plaza Hotel, NY",
      "confidence": 0.95,
      "image_count": 95,
      "evidence": {
        "time_proximity_score": 0.98,
        "location_proximity_score": 0.92,
        "batch_coherence_score": 0.95
      }
    },
    {
      "event_id": "cal-event-def456",
      "title": "Family Portrait Session",
      "confidence": 0.72,
      "image_count": 5,
      "evidence": { ... }
    }
  ],
  "upload_session_id": "session-xyz789",
  "no_match": false
}
```

**Response (204 — No matches found):**
```json
{
  "matches": [],
  "upload_session_id": "session-xyz789",
  "no_match": true,
  "message": "No calendar events found within 2 hours of your image timestamps. Continue without calendar binding?"
}
```

**Response (401 — Calendar OAuth token missing):**
```json
{
  "error": "calendar_oauth_required",
  "message": "Please connect your calendar to use auto-matching."
}
```

---

### 4.2 — POST /api/ingest/upload

**Purpose:** Initiate background file upload

**Request:**
```
Content-Type: multipart/form-data

Files: [IMG_1001.CR2, IMG_1002.CR2, ..., IMG_1100.CR2]
Fields: {
  "upload_session_id": "session-xyz789",
  "calendar_event_id": "cal-event-abc123" (optional, if matched),
  "skip_calendar": false (optional)
}
```

**Response (202 — Accepted, uploading):**
```json
{
  "upload_session_id": "session-xyz789",
  "gallery_id": "gallery-ghi789",
  "status": "uploading",
  "file_count": 100,
  "message": "Upload started. Files will be processed in background."
}
```

**Response (400 — Bad request):**
```json
{
  "error": "invalid_session",
  "message": "Upload session not found or expired."
}
```

---

### 4.3 — GET /api/ingest/status/{sessionId}

**Purpose:** Check upload progress

**Response (200):**
```json
{
  "upload_session_id": "session-xyz789",
  "status": "uploading",
  "file_count": 100,
  "completed_files": 47,
  "failed_files": 0,
  "pending_files": 53,
  "percent_complete": 47,
  "elapsed_time_seconds": 32,
  "estimated_time_remaining_seconds": 36,
  "last_updated": "2026-04-10T14:35:22Z"
}
```

**Response (200 — Complete):**
```json
{
  "status": "completed",
  "file_count": 100,
  "completed_files": 100,
  "failed_files": 0,
  "gallery_id": "gallery-ghi789",
  "message": "Upload complete. Gallery ready for review."
}
```

---

### 4.4 — POST /api/ingest/tag

**Purpose:** Apply a tag to an image during ingest

**Request:**
```json
{
  "ingest_file_id": "ingest-file-uuid",
  "tag": "portrait",
  "tag_type": "user"
}
```

**Response (201):**
```json
{
  "success": true,
  "ingest_file_id": "ingest-file-uuid",
  "tag": "portrait",
  "applied_tags": ["portrait", "favorite", "ISO400"]
}
```

---

### 4.5 — POST /api/ingest/confirm-session

**Purpose:** User confirms ingest; trigger asset creation

**Request:**
```json
{
  "upload_session_id": "session-xyz789",
  "gallery_id": "gallery-ghi789"
}
```

**Response (200):**
```json
{
  "success": true,
  "session_id": "session-xyz789",
  "gallery_id": "gallery-ghi789",
  "asset_count": 97, // Excluding culled images
  "status": "assets_created",
  "message": "Gallery ready. Next steps: [Attach clients] [Choose template]"
}
```

**Process:**
1. Validate session & gallery
2. Emit `SessionAssociationResolved` event
3. prophoto-assets listener creates Asset records
4. prophoto-gallery listener links assets to gallery
5. prophoto-intelligence queues images for background processing

---

## Part 5: Event-Driven Workflow Integration

### 5.1 — New Event: `SessionAssociationResolved`

**Purpose:** Signal that user has confirmed ingest; trigger downstream processing

**Event Payload:**
```php
class SessionAssociationResolved {
  public string $sessionId;
  public string $galleryId;
  public string $studioId;
  public string $userId;
  public array $ingestFiles; // [{ file_id, filename, exif, tags, culled }, ...]
  public ?string $calendarEventId;
}
```

**Listeners:**
- `prophoto-assets`: Create Asset records for each non-culled file
- `prophoto-gallery`: Attach assets to gallery; set gallery context
- `prophoto-intelligence`: Queue background metadata extraction & AI
- `prophoto-notifications`: Notify user of next steps (optional)

### 5.2 — Event Flow Diagram

```
User confirms ingest
    ↓
POST /api/ingest/confirm-session
    ↓
UploadSessionController::confirm()
    ↓
Emit: SessionAssociationResolved
    ↓
┌─────────────────────────────────────────┐
│ Async Queue (all in parallel)           │
├─────────────────────────────────────────┤
│ AssetCreationListener                   │
│   → Create Asset per file               │
│   → Link to studio                      │
│                                          │
│ GalleryContextProjectionListener        │
│   → Update gallery with session meta    │
│   → Set delivery status                 │
│                                          │
│ IntelligenceQueueListener               │
│   → Queue image for metadata extract    │
│   → Queue for thumbnail generation      │
└─────────────────────────────────────────┘
    ↓
Assets ready for delivery workflow
```

---

## Part 6: Frontend-Backend Communication Flow

### 6.1 — Detailed Sequence: Metadata Extraction → Calendar Match → Upload → Ingest

```
BROWSER (Frontend)                          SERVER (Backend)
─────────────────────────────────────────────────────────────

User drops files
    ↓
[exifjs parses files locally]
    ├─ Extracts EXIF in < 5 sec
    ├─ Builds metadata array
    └─ No network call yet
    ↓
POST /api/ingest/match-calendar
    metadata[], studio_id, user_id ─────→ [Calendar matcher runs]
                                          ├─ Query calendar API
                                          ├─ Run scoring algo
                                          └─ Return matches
                                          ↓
    ←───────────────────────────────────── { matches: [...], upload_session_id }
    ↓
[Display: "We found 2 calendar events"]
    ↓
User selects "Johnson Wedding"
    ↓
POST /api/ingest/upload
    files[], upload_session_id ────────→ [Start background upload]
                                          ├─ Create UploadSession
                                          ├─ Queue files
                                          └─ Parallel transfer begins
                                          ↓
    ←───────────────────────────────────── { upload_session_id, gallery_id, status }
    ↓
[Launch Gallery Ingest UI]
    ↓
[Thumbnails load as files complete]
    ↓
GET /api/ingest/status/session-xyz
    ────────────────────────────────────→ { completed: 47, total: 100 }
    ←────────────────────────────────────
    ↓
[Poll every 2 sec while uploading]
    ↓
User tags images while upload continues
    ↓
POST /api/ingest/tag
    { ingest_file_id, tag } ───────────→ [Persist tag]
                                          ↓
    ←────────────────────────────────────── { success: true }
    ↓
[Update UI; filter thumbnails]
    ↓
Upload completes (100/100 files)
    ↓
User clicks "Confirm & Continue"
    ↓
POST /api/ingest/confirm-session
    { upload_session_id, gallery_id } ─→ [Emit SessionAssociationResolved]
                                          ├─ Async: Create Assets
                                          ├─ Async: Link to gallery
                                          └─ Async: Queue intelligence
                                          ↓
    ←────────────────────────────────────── { success: true, gallery_id }
    ↓
[Redirect to gallery management]
```

---

## Part 7: Performance & Scalability

### 7.1 — Performance Targets

| Operation | Target | How Achieved |
|-----------|--------|--------------|
| Metadata extraction (100 files) | < 5 sec | Browser-side exifjs, no network |
| Calendar matching | < 3 sec | Cached calendar data; optimized scoring |
| Gallery render (100 thumbnails) | < 2 sec | Virtualized list; lazy loading |
| Filter re-render | < 100 ms | Client-side filtering logic |
| Upload speed | Parallel 3-5 files | HTTP/2 multipart uploads |
| Tag application | < 500 ms | Direct DB write; no re-render block |
| Status polling | < 1 sec API response | Lightweight query; indexed DB |

### 7.2 — Concurrency Handling

**Scenario:** 10 photographers upload simultaneously (1000 files total)

- **Frontend:** Each browser handles its own metadata extraction (no server load)
- **Backend:** Upload queue abstracts concurrent file processing
  - Database: Prepared statements prevent lock contention
  - Storage: Concurrent file writes to separate directories per studio/session
  - CPU: Async job queue for background processing (assets, intelligence)

### 7.3 — Database Indexes (Critical for Performance)

```sql
-- Fast session lookups
CREATE INDEX idx_upload_sessions_studio_user ON upload_sessions(studio_id, user_id);
CREATE INDEX idx_upload_sessions_status ON upload_sessions(status, completed_at);

-- Fast file lookups
CREATE INDEX idx_ingest_files_session ON ingest_files(upload_session_id, upload_status);

-- Fast tag queries
CREATE INDEX idx_tags_by_file ON ingest_image_tags(ingest_file_id);
CREATE INDEX idx_tags_by_tag ON ingest_image_tags(tag);

-- Gallery context
CREATE INDEX idx_upload_sessions_gallery ON upload_sessions(gallery_id);
```

---

## Part 8: Error Handling & Edge Cases

### 8.1 — Network Failures

**Scenario:** User's internet drops during file upload

**Solution:**
1. Frontend tracks which files completed successfully
2. On reconnect, resume from last completed file
3. Backend ignores duplicate uploads (file hash check)
4. User sees: "Upload paused. Reconnected. Resuming..."

**Implementation:**
```php
// In UploadSessionService
public function recordFileUpload(string $sessionId, string $filename, array $metadata): void {
  $file = IngestFile::where('upload_session_id', $sessionId)
    ->where('original_filename', $filename)
    ->first();
  
  if ($file && $file->upload_status === 'completed') {
    return; // Idempotent: ignore duplicate
  }
  
  // Create or update file record
}
```

### 8.2 — Corrupt Metadata

**Scenario:** RAW file has missing EXIF data

**Solution:**
1. exifjs returns partial metadata (null for missing fields)
2. Calendar matcher handles nulls gracefully (no match if timestamp null)
3. User can still tag and organize images without calendar binding
4. UI shows: "Metadata unavailable for some fields"

### 8.3 — Mixed File Types

**Scenario:** User uploads RAW + JPG + TIFF (different EXIF structures)

**Solution:**
1. exifjs handles all formats (uses different parsers internally)
2. Metadata extraction normalizes to common schema
3. Missing fields are null; filtering handles nulls gracefully

### 8.4 — Calendar Event Conflict

**Scenario:** Images match 2 events with equal confidence

**Solution:**
1. Confidence scores break ties (0.95 vs. 0.95 → return both)
2. Frontend displays: "We found 2 equally likely matches"
3. User must explicitly choose; cannot proceed without selection

### 8.5 — Culled Images Post-Upload

**Scenario:** User culls 30/100 images; asks how many are uploaded

**Solution:**
1. Culled images **are still uploaded** (stored as separate Asset)
2. Culled flag prevents gallery inclusion
3. Photographer can uncull later if needed
4. UI shows: "97 of 100 images in gallery (3 culled, hidden)"

### 8.6 — Large Uploads (500+ files)

**Scenario:** Photographer uploads 500 RAW files (100GB+)

**Solution:**
1. Metadata extraction still fast (all local)
2. Upload chunked; files processed as they arrive
3. Thumbnail browser virtualized (renders only 20 visible)
4. Filtering/tagging responsive (client-side)
5. Status polling every 5 sec (not overwhelming server)
6. Estimate: 500 files @ 200MB each = 8 hours @ 100Mbps (reasonable for workflow)

---

## Part 9: Testing Strategy

### 9.1 — Unit Tests

**Frontend:**
- Metadata extractor with various RAW formats
- Filter logic (combinations of filters)
- State management (tag application, selection, culling)

**Backend:**
- Calendar matcher scoring algorithm
- UploadSession lifecycle
- Tag persistence
- Event emission

### 9.2 — Integration Tests

- End-to-end: metadata extraction → calendar match → upload → asset creation
- Concurrent uploads (simulate 10 users)
- Network failure + resume
- Tag application during upload

### 9.3 — Performance Tests

- Extract metadata from 100 RAW files in < 5 sec
- Gallery render 100 thumbnails in < 2 sec
- Filter re-render in < 100 ms
- Handle 1000 concurrent uploads

### 9.4 — UAT Scenarios

- **Sarah (commercial):** 150-image product shoot; auto-calendar match; bulk tag by ISO
- **James (event):** 250-image wedding; multi-event upload; time-window filtering
- **Lisa (casual):** 80-image family session; simple star/cull workflow

---

## Part 10: Deployment & Infrastructure

### 10.1 — New Services Required

- **File Storage:** S3 or equivalent (ingest files live here during upload)
  - Bucket: `prophoto-ingest-uploads/{studio_id}/{session_id}/`
  - Lifecycle: Delete after asset creation (or 30 days if incomplete)

- **Job Queue:** Redis or equivalent (background asset creation, intelligence)
  - Queue: `ingest:session-association-resolved`

- **Calendar OAuth Providers:** Google Calendar API
  - Scope: `calendar.readonly`
  - Token stored securely in users table

### 10.2 — Environment Variables

```env
CALENDAR_OAUTH_GOOGLE_CLIENT_ID=...
CALENDAR_OAUTH_GOOGLE_CLIENT_SECRET=...

S3_INGEST_BUCKET=prophoto-ingest-uploads
S3_REGION=us-east-1

QUEUE_DRIVER=redis
REDIS_URL=redis://...

METADATA_EXTRACTION_TIMEOUT=5000 # ms
UPLOAD_CHUNK_SIZE=5242880 # 5MB
MAX_CONCURRENT_UPLOADS=5
```

### 10.3 — Monitoring & Observability

- **CloudWatch/DataDog metrics:**
  - Ingest upload success rate
  - Calendar match accuracy
  - Average time to confirm ingest
  - Asset creation latency

- **Logs:**
  - All upload sessions (start/complete/fail)
  - All calendar match queries + confidence scores
  - All asset creation events

---

## Part 11: Integration with Existing Packages

### 11.1 — prophoto-assets
- **Usage:** Create Asset records for each uploaded file
- **Event listener:** `SessionAssociationResolved` → `AssetCreationListener`
- **No changes required** to prophoto-assets package

### 11.2 — prophoto-gallery
- **Usage:** Link assets to gallery; pre-create gallery from calendar event
- **Event listener:** `SessionAssociationResolved` → `GalleryContextProjectionListener`
- **New field:** `gallery.ingest_session_id` (track provenance; optional)

### 11.3 — prophoto-intelligence
- **Usage:** Queue background metadata extraction & AI for ingested images
- **Event listener:** `SessionAssociationResolved` → `IntelligenceQueueListener`
- **No changes required** to prophoto-intelligence package

### 11.4 — prophoto-booking
- **Usage:** Link calendar event to photo_session if available
- **Optional:** If ingest provides calendar_event_id, check if it maps to photo_session
- **No changes required**

---

## Part 12: API Authentication & Authorization

### 12.1 — Auth Pattern

- All `/api/ingest/*` endpoints require:
  - Authenticated user (JWT or session token)
  - User must belong to requesting studio_id
  - User must have `photographer` or `admin` role

### 12.2 — CORS & Security

```php
// In middleware
Route::middleware('auth:api', 'studio.authorize')->group(function () {
  Route::post('/ingest/match-calendar', 'IngestController@matchCalendar');
  Route::post('/ingest/upload', 'IngestController@upload');
  Route::get('/ingest/status/{sessionId}', 'IngestController@status');
  Route::post('/ingest/tag', 'IngestController@tag');
  Route::post('/ingest/confirm-session', 'IngestController@confirmSession');
});
```

---

## Part 13: Known Limitations & Deferred Work

### 13.1 — Phase 1 (This Architecture)

- ✅ Google Calendar only (Outlook/Apple in Phase 2)
- ✅ Single calendar event per upload (multi-event split in Phase 1b)
- ✅ No AI analysis (deferred to Phase 4)
- ✅ No filename/directory renaming (export-time feature)
- ✅ No duplicate detection (Phase 2)

### 13.2 — Future Architectural Changes

- **AI-powered analysis (Phase 4):** Will add new event listeners to prophoto-intelligence
- **Multi-photographer collaboration (Phase 3):** May require updates to asset ownership model
- **Batch operations (Phase 2):** May require new API endpoints for bulk tagging/culling

---

## Part 14: Success Criteria (Phase 1 Launch)

**Technical:**
- [ ] Metadata extraction < 5 sec for 100 files (exifjs)
- [ ] Calendar matching < 3 sec (API + scoring)
- [ ] Gallery renders < 2 sec (virtualized list)
- [ ] Filtering responsive < 100 ms (client-side)
- [ ] Background upload doesn't block UI
- [ ] Upload resumability works after network reconnect
- [ ] SessionAssociationResolved event triggers correctly
- [ ] Asset creation completes within 30 sec of confirm
- [ ] 100% test coverage on calendar matcher (unit + integration)
- [ ] Zero data loss on partial uploads

**User Experience:**
- [ ] User testing: ≥5 photographers, positive feedback
- [ ] Time to ingest reduced from 50 min → <5 min (self-reported)
- [ ] NPS ≥40 on ingest experience
- [ ] Support questions about "how to organize photos" drop 40%

**Operational:**
- [ ] Logs show all upload sessions (audit trail)
- [ ] Metrics dashboard shows upload success rate ≥98%
- [ ] No unhandled errors in production for 2+ weeks post-launch

---

## Part 15: Implementation Roadmap

### Phase 1a (Weeks 1-3) — Metadata Extraction + Calendar Matching

**Frontend:**
- [ ] Set up ingest module structure
- [ ] Integrate exifjs library
- [ ] Build metadata extraction service
- [ ] Build calendar match display UI
- [ ] Wire upload initiation

**Backend:**
- [ ] Create upload_sessions table
- [ ] Implement CalendarMatcherService
- [ ] Implement POST /api/ingest/match-calendar endpoint
- [ ] Integrate calendar OAuth
- [ ] Write unit tests for calendar matcher

**Testing:**
- [ ] Manual: Test with 50, 100, 500 images
- [ ] Performance: Metadata extraction < 5 sec

### Phase 1b (Weeks 4-6) — Gallery Ingest UI + Tagging

**Frontend:**
- [ ] Build ThumbnailBrowser component (virtualized)
- [ ] Build ImagePreview component
- [ ] Build TaggingPanel component
- [ ] Build FilterSidebar component
- [ ] Build ChartsTab component
- [ ] Build CalendarTab component
- [ ] Integrate upload manager (background uploads)
- [ ] Implement tag state management

**Backend:**
- [ ] Create ingest_files table
- [ ] Create ingest_image_tags table
- [ ] Implement TagService
- [ ] Implement POST /api/ingest/upload endpoint
- [ ] Implement GET /api/ingest/status endpoint
- [ ] Implement POST /api/ingest/tag endpoint
- [ ] Wire status polling

**Testing:**
- [ ] Manual: Tagging, filtering, charts
- [ ] Integration: Upload + tag workflow

### Phase 1c (Weeks 7-9) — Refinement + Asset Creation + Launch

**Frontend:**
- [ ] Polish UI/UX
- [ ] Keyboard shortcuts
- [ ] Mobile responsiveness
- [ ] Error messaging

**Backend:**
- [ ] Implement POST /api/ingest/confirm-session endpoint
- [ ] Emit SessionAssociationResolved event
- [ ] Wire prophoto-assets listener
- [ ] Wire prophoto-gallery listener
- [ ] Performance optimization (indexes, caching)
- [ ] Error handling edge cases

**Testing:**
- [ ] E2E testing (5 photographers)
- [ ] Load testing (concurrent uploads)
- [ ] UAT with reference photographers

**Launch:**
- [ ] Documentation (user guide + admin setup)
- [ ] Support materials
- [ ] Analytics dashboard
- [ ] Beta deployment

---

## Appendix A: Code Examples

### A.1 — Metadata Extractor (Frontend)

```typescript
// services/MetadataExtractor.ts
import * as EXIF from 'exif-js';

export interface ImageMetadata {
  filename: string
  fileSize: number
  exif: {
    iso: number | null
    aperture: number | null
    shutter: string | null
    focalLength: number | null
    camera: string | null
    timestamp: string | null
    gps: { lat: number; lng: number } | null
  }
}

export async function extractMetadata(files: File[]): Promise<ImageMetadata[]> {
  const results: ImageMetadata[] = []
  
  for (const file of files) {
    try {
      const exifData = await extractExifFromFile(file)
      results.push({
        filename: file.name,
        fileSize: file.size,
        exif: parseExif(exifData)
      })
    } catch (error) {
      console.warn(`Failed to extract EXIF from ${file.name}:`, error)
      results.push({
        filename: file.name,
        fileSize: file.size,
        exif: { iso: null, aperture: null, shutter: null, focalLength: null, camera: null, timestamp: null, gps: null }
      })
    }
  }
  
  return results
}

function extractExifFromFile(file: File): Promise<any> {
  return new Promise((resolve, reject) => {
    EXIF.getData(file, function() {
      resolve(EXIF.getAllTags(this))
    })
  })
}

function parseExif(exifData: any): ImageMetadata['exif'] {
  return {
    iso: exifData['ISO'] || exifData['ISOSpeedRatings'] || null,
    aperture: exifData['FNumber'] ? parseFloat(String(exifData['FNumber'])) : null,
    shutter: exifData['ExposureTime'] ? String(exifData['ExposureTime']) : null,
    focalLength: exifData['FocalLength'] ? parseFloat(String(exifData['FocalLength'])) : null,
    camera: exifData['Model'] || null,
    timestamp: exifData['DateTime'] || null,
    gps: exifData['GPSLatitude'] && exifData['GPSLongitude'] ? {
      lat: exifData['GPSLatitude'],
      lng: exifData['GPSLongitude']
    } : null
  }
}
```

### A.2 — Calendar Matcher (Backend)

```php
// Packages/prophoto-ingest/Services/CalendarMatcherService.php
namespace ProPhoto\Ingest\Services;

use ProPhoto\Assets\Services\SessionMatchingService;

class CalendarMatcherService {
  protected SessionMatchingService $sessionMatcher;
  
  public function __construct(SessionMatchingService $sessionMatcher) {
    $this->sessionMatcher = $sessionMatcher;
  }
  
  public function matchImages(array $metadata, string $studioId, string $userId): array {
    // 1. Get calendar events
    $events = $this->getCalendarEvents($userId, $metadata);
    
    if (empty($events)) {
      return ['matches' => [], 'no_match' => true];
    }
    
    // 2. Score each event
    $scored = [];
    foreach ($events as $event) {
      $score = $this->scoreMatch($metadata, $event);
      if ($score['confidence'] > 0.45) { // Threshold for display
        $scored[] = array_merge($event->toArray(), $score);
      }
    }
    
    // 3. Sort by confidence (descending)
    usort($scored, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
    
    return ['matches' => $scored, 'no_match' => false];
  }
  
  protected function scoreMatch(array $metadata, $event): array {
    $timestamps = array_column($metadata, 'timestamp');
    $gpsCoords = array_column($metadata, 'gps');
    
    $timeScore = $this->scoreTimeProximity($timestamps, $event);
    $locationScore = $this->scoreLocationProximity($gpsCoords, $event);
    $batchScore = $this->scoreBatchCoherence($timestamps);
    
    $confidence = ($timeScore * 0.55) + ($locationScore * 0.20) + ($batchScore * 0.15);
    
    return [
      'confidence' => min(1.0, $confidence),
      'evidence' => [
        'time_proximity_score' => $timeScore,
        'location_proximity_score' => $locationScore,
        'batch_coherence_score' => $batchScore
      ]
    ];
  }
  
  protected function scoreTimeProximity(array $timestamps, $event): float {
    $eventStart = $event->start_time->timestamp();
    $eventEnd = $event->end_time->timestamp();
    $window = 900; // ±15 min
    
    $validCount = 0;
    foreach ($timestamps as $ts) {
      $time = strtotime($ts);
      if (($time >= $eventStart - $window) && ($time <= $eventEnd + $window)) {
        $validCount++;
      }
    }
    
    return $validCount / count($timestamps);
  }
  
  protected function scoreLocationProximity(array $gpsCoords, $event): float {
    if (empty($gpsCoords) || !$event->location_lat) {
      return 0.5; // No GPS data; neutral score
    }
    
    $threshold = 500; // meters
    $validCount = 0;
    
    foreach ($gpsCoords as $coord) {
      if ($coord && $this->distanceBetween($coord, $event->location_lat, $event->location_lng) < $threshold) {
        $validCount++;
      }
    }
    
    return $validCount / count($gpsCoords);
  }
  
  protected function distanceBetween(array $coord, $lat2, $lng2): float {
    // Haversine formula
    $lat1 = $coord['lat'];
    $lng1 = $coord['lng'];
    $R = 6371000; // Earth radius in meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $R * $c;
  }
  
  protected function scoreBatchCoherence(array $timestamps): float {
    // Are all timestamps clustered around a narrow time window?
    $times = array_map('strtotime', $timestamps);
    $min = min($times);
    $max = max($times);
    $span = $max - $min;
    
    if ($span === 0) return 1.0; // All same timestamp
    if ($span > 14400) return 0.3; // >4 hours: low coherence
    if ($span > 3600) return 0.6; // >1 hour: medium
    return 0.9; // < 1 hour: high coherence
  }
}
```

---

## Appendix B: Database Migration Example

```php
// Packages/prophoto-ingest/database/migrations/2026_04_10_000001_create_ingest_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIngestTables extends Migration {
  public function up() {
    Schema::create('upload_sessions', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->uuid('studio_id');
      $table->uuid('user_id');
      $table->uuid('calendar_event_id')->nullable();
      $table->float('calendar_match_confidence')->nullable();
      $table->json('calendar_match_evidence')->nullable();
      $table->integer('file_count')->default(0);
      $table->bigInteger('total_size_bytes')->default(0);
      $table->enum('status', ['initiated', 'uploading', 'completed', 'failed'])->default('initiated');
      $table->timestamp('started_at')->useCurrent();
      $table->timestamp('completed_at')->nullable();
      $table->uuid('gallery_id')->nullable();
      $table->timestamps();
      
      $table->index(['studio_id', 'user_id']);
      $table->index(['status', 'completed_at']);
    });
    
    // ... other table definitions
  }
  
  public function down() {
    Schema::dropIfExists('upload_sessions');
    Schema::dropIfExists('ingest_files');
    Schema::dropIfExists('ingest_image_tags');
    Schema::dropIfExists('upload_session_metadata');
  }
}
```

---

**End of Phase 1 Architecture Design Document**
