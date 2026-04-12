# ProPhoto Phase 1: Epic Breakdown & Sprint Planning (Phase 4)

**Status:** ✅ PHASE 1 COMPLETE — Ready for Launch  
**Version:** 1.4  
**Created:** April 10, 2026 | **Last Updated:** April 11, 2026  
**Based on:** ARCH-phase-1-ingest-design.md + PRD-phase-1-epic-ingest-workflow.md  
**Duration:** 9 weeks (Weeks 1-9 of Phase 1)

## Sprint Progress Summary

| Sprint | Focus | Status | Points |
|--------|-------|--------|--------|
| Sprint 1 | Metadata Extraction + Calendar OAuth + DB Migrations | ✅ Complete | 19 |
| Sprint 2 | CalendarMatcherService + API Endpoint + UploadInitiator + CalendarMatchDisplay | ✅ Complete | 23 |
| Sprint 3 | IngestGallery + UploadManager + TaggingPanel + UploadStatusBar + 5 API endpoints | ✅ Complete | 23 |
| Sprint 4 | IngestEntrypoint + CalendarTab + ChartsTab + Retry Handler + Unlink endpoint | ✅ Complete | 21 |
| Sprint 5 | IngestSessionConfirmed event + AssetCreation listener + GenerateAssetThumbnail job + preview-status endpoint | ✅ Complete | 24 |
| Sprint 6 | GalleryContextProjectionListener + ingest context migration + Image model + 8 unit tests | ✅ Complete | 18 |
| Sprint 7 | N+1 fix (batchUpdateFiles) + 22 HTTP Feature tests + 6 performance indexes + launch checklist | ✅ Complete | 16 |

**Total delivered: 144 / 144 points (100%) 🎉**

---

## Executive Summary

This document breaks down the Phase 1 ingest workflow epic into **25 implementation-ready user stories**, organized into **3 major sprints** (each 3 weeks), with detailed acceptance criteria, estimates, and risk assessment.

**Phase 1 is divided into 3 sub-phases:**
- **Phase 1a (Weeks 1-3):** Metadata Extraction + Calendar Matching
- **Phase 1b (Weeks 4-6):** Gallery Ingest UI + Tagging
- **Phase 1c (Weeks 7-9):** Refinement + Asset Creation + Launch

Each sub-phase is broken into 1-2 two-week sprints with clear deliverables, success criteria, and go/no-go decisions.

---

## Part 1: Epic User Stories (Grouped by Sub-Phase)

### Phase 1a: Metadata Extraction + Calendar Matching (Weeks 1-3)

#### Story 1a.1 — Metadata Extraction Service (Frontend)
**Epic:** Phase 1a: Metadata Extraction + Calendar Matching  
**Priority:** P0  
**Story Points:** 8  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** the system to extract EXIF metadata from my files before uploading  
**So that** the system can understand shoot context without waiting for file transfer  

**Acceptance Criteria:**
- [ ] exifjs library integrated into frontend (npm install exifjs)
- [ ] `extractMetadata(files: File[])` service implemented and tested
- [ ] Extracts: ISO, aperture, shutter speed, focal length, camera model, timestamp, GPS (if available)
- [ ] Handles JPG, RAW (Canon CR2, Nikon NEF), TIFF, DNG formats
- [ ] Processes 100 files in < 5 seconds (measured on mid-range laptop)
- [ ] Graceful handling of missing EXIF (returns null fields, no error)
- [ ] Unit tests: ≥90% coverage for extraction logic
- [ ] Performance test: Benchmark with 50, 100, 250 file sets

**Technical Notes:**
- Use exifjs v0.6.0 or later
- Run extraction in Web Worker to avoid blocking UI
- TypeScript types defined in `services/MetadataExtractor.ts`
- Mock data available in `tests/fixtures/exif-samples.json`

**Definition of Done:**
- Code reviewed and merged to main
- Tests passing (unit + performance)
- No console warnings
- Documented in ADR-metadata-extraction.md

---

#### Story 1a.2 — Calendar OAuth Setup (Backend + Frontend)
**Epic:** Phase 1a: Metadata Extraction + Calendar Matching  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Backend Lead  

**As a** photographer  
**I want** to connect my Google Calendar to ProPhoto  
**So that** the system can automatically match images to my shoot events  

**Acceptance Criteria:**
- [ ] Google Calendar OAuth flow implemented (auth code grant flow)
- [ ] OAuth credentials stored securely in users table (encrypted token field)
- [ ] Token refresh mechanism working (auto-refresh on expiry)
- [ ] Scope: `calendar.readonly` only (read-only access)
- [ ] Disconnect calendar: User can revoke at any time
- [ ] Frontend: "Connect Calendar" button in settings; shows connected status
- [ ] Error handling: Clear message if token expires or is revoked
- [ ] Unit tests: Token refresh, expiry handling, scope validation

**Technical Notes:**
- Use Laravel's `spatie/laravel-google-calendar` or Google SDK directly
- Google Cloud Console project setup documented in SETUP.md
- Test credentials available in `.env.testing`

**Definition of Done:**
- OAuth flow tested end-to-end
- Security review passed (tokens encrypted, scopes minimal)
- Documentation: Setup.md includes Google Console instructions

---

#### Story 1a.3 — Calendar Matching Service (Backend)
**Epic:** Phase 1a: Metadata Extraction + Calendar Matching  
**Priority:** P0  
**Story Points:** 8  
**Owner:** Backend Lead  

**As a** photographer  
**I want** the system to compare my image metadata with calendar events  
**So that** I don't have to manually remember which shoot was which  

**Acceptance Criteria:**
- [ ] `CalendarMatcherService` implemented with scoring algorithm
- [ ] Scoring: time proximity (0.55), location (0.20), batch coherence (0.15), type (0.10)
- [ ] Query calendar for events within ±2 hours of image timestamp range
- [ ] Return top 5 matches ranked by confidence (0.0-1.0)
- [ ] Confidence tiers: HIGH (≥0.85), MEDIUM (0.55-0.84), LOW (<0.55)
- [ ] Evidence breakdown included in response (time_score, location_score, etc.)
- [ ] Handle zero matches gracefully
- [ ] Handle network errors (calendar API timeout)
- [ ] Unit tests: Scoring algorithm, time window logic, GPS distance calculation
- [ ] Integration test: Match 100 images against 10 calendar events

**Technical Notes:**
- Reuses `SessionMatchingService` from prophoto-assets (no duplication)
- GPS distance: Haversine formula (< 500m threshold)
- Time window: ±15 min default (configurable via env var)
- Calendar API calls cached for 5 minutes to avoid rate limiting

**Definition of Done:**
- Algorithm verified against manual test cases
- Performance: < 3 sec for 100 images, 10 events
- Tests passing (unit + integration)
- Performance benchmarks documented

---

#### Story 1a.4 — POST /api/ingest/match-calendar Endpoint
**Epic:** Phase 1a: Metadata Extraction + Calendar Matching  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Backend Lead  

**As a** frontend  
**I want** a REST endpoint to send metadata and receive calendar matches  
**So that** I can display matching suggestions to the photographer  

**Acceptance Criteria:**
- [ ] Endpoint: POST /api/ingest/match-calendar
- [ ] Request: { metadata[], studio_id, user_id }
- [ ] Response: { matches: [...], upload_session_id, no_match: boolean }
- [ ] Authentication: Requires auth token + studio ownership verification
- [ ] Rate limiting: Max 10 requests/minute per user
- [ ] Error responses: 400 (bad metadata), 401 (auth), 403 (studio), 500 (calendar API down)
- [ ] API documentation: OpenAPI/Swagger spec generated
- [ ] Integration tests: Valid/invalid payloads, auth checks, edge cases

**Technical Notes:**
- Use Laravel resource classes for response formatting
- Middleware: `auth:api`, `studio.authorize`
- Rate limiting: Laravel's `throttle` middleware
- OpenAPI: Use `spatie/laravel-openapi` or `darkaonline/l5-swagger`

**Definition of Done:**
- API tested with Postman/Insomnia
- OpenAPI spec passes validation
- Integration tests covering happy path + error cases
- Documentation: API endpoint documented in ADR-ingest-api.md

---

#### Story 1a.5 — Upload Session Model & Table
**Epic:** Phase 1a: Metadata Extraction + Calendar Matching  
**Priority:** P0  
**Story Points:** 3  
**Owner:** Backend Lead  

**As a** backend  
**I want** a database model to track upload sessions  
**So that** I can persist session state and metadata  

**Acceptance Criteria:**
- [ ] Laravel migration: `create_upload_sessions_table.php`
- [ ] Model: `UploadSession` with relationships (studio, user, calendar_event, gallery)
- [ ] Fields: id, studio_id, user_id, calendar_event_id, confidence, status, created_at, completed_at
- [ ] Validation rules (studio_id required, status enum, etc.)
- [ ] Eloquent relationships defined (hasMany ingest_files, belongsTo calendar_event)
- [ ] Indexes: (studio_id, user_id), (status, completed_at)
- [ ] Unit test: Model creation, relationships, scopes

**Technical Notes:**
- Migration file location: `prophoto-ingest/database/migrations/`
- Use UUID for id field
- Status enum: 'initiated', 'uploading', 'completed', 'failed'

**Definition of Done:**
- Migration runs/rolls back cleanly
- Model tests passing
- Database schema matches architecture spec

---

#### Story 1a.6 — Create UploadSessionService
**Epic:** Phase 1a: Metadata Extraction + Calendar Matching  
**Priority:** P0  
**Story Points:** 3  
**Owner:** Backend Lead  

**As a** IngestController  
**I want** a service to manage upload session lifecycle  
**So that** I can create, query, and complete sessions  

**Acceptance Criteria:**
- [ ] `UploadSessionService` class with methods:
  - `createSession(studio_id, user_id, calendar_event_id?): UploadSession`
  - `getSession(session_id): UploadSession`
  - `recordFileUpload(session_id, filename, metadata): void`
  - `completeSession(session_id): void`
- [ ] Idempotency: Duplicate file uploads ignored
- [ ] Error handling: Session not found, invalid status transition
- [ ] Unit tests: Happy path, error cases, state transitions

**Technical Notes:**
- Service binding in `IngestServiceProvider`
- Dependency injection: receives repositories, event dispatcher

**Definition of Done:**
- Service tested with various session states
- No logic duplication with other services

---

#### Story 1a.7 — Frontend: Upload Initiation UI
**Epic:** Phase 1a: Metadata Extraction + Calendar Matching  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** to drag files into an upload box  
**So that** the ingest process begins  

**Acceptance Criteria:**
- [ ] React component: `UploadInitiator` with drag-drop zone
- [ ] Accepts files via drag-drop or click-to-browse
- [ ] File type validation: JPG, RAW, TIFF, DNG only
- [ ] Max files: 1000 per upload
- [ ] Max file size: No limit (configured per deployment)
- [ ] UI feedback: Drop zone highlights on drag, file count displayed
- [ ] Loading state: "Analyzing X images... Reading metadata and matching to your calendar"
- [ ] Calls `extractMetadata()` service, then POST /api/ingest/match-calendar
- [ ] Component tests: File validation, drag-drop behavior, loading state

**Technical Notes:**
- Use React Dropzone library or native HTML5 drag-drop
- Loading indicator with progress (shows after 1 sec delay)
- Responsive design: Works on desktop + tablet

**Definition of Done:**
- Component renders and accepts files
- Loading state displays for metadata extraction
- Error handling (invalid files, network errors)
- Component tests passing

---

#### Story 1a.8 — Frontend: Calendar Match Display
**Epic:** Phase 1a: Metadata Extraction + Calendar Matching  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** to see matching calendar events and their confidence scores  
**So that** I can confirm the correct shoot was detected  

**Acceptance Criteria:**
- [ ] Display: "We found X calendar events"
- [ ] For each match, show:
  - Event title, date, time, location
  - Image count matched to this event
  - Confidence percentage + tier (HIGH/MEDIUM/LOW)
  - Evidence breakdown (time score, location score, etc.)
- [ ] Interactions:
  - Click event to select (checkbox + blue highlight)
  - Click "Continue with selection" button
  - Click "Skip calendar" to proceed without matching
- [ ] No match scenario: "No calendar events found. Continue without calendar binding?"
- [ ] Low confidence match: "Family Portrait Session (72% confidence) — are you sure?"
- [ ] Component tests: Rendering, selection, error states

**Technical Notes:**
- Component: `CalendarMatchDisplay`
- Reuse card/badge components from design system
- Responsive: Stacked on mobile, grid on desktop

**Definition of Done:**
- Component renders matches correctly
- Selection state manageable
- Accessibility: Keyboard navigation, ARIA labels

---

### Phase 1b: Gallery Ingest UI + Tagging (Weeks 4-6)

#### Story 1b.1 — Ingest Gallery Container & Layout
**Epic:** Phase 1b: Gallery Ingest UI + Tagging  
**Priority:** P0  
**Story Points:** 8  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** a three-pane gallery view while upload is in progress  
**So that** I can start working (filtering, tagging) immediately  

**Acceptance Criteria:**
- [ ] Layout: Left sidebar (thumbnails), center (preview + metadata), right (tagging + controls)
- [ ] Sidebar: Virtualized thumbnail list with 4-column grid
- [ ] Each thumbnail shows: image preview, file type badge (RAW/JPG), selection checkbox
- [ ] Center: Large image preview, EXIF metadata display below
- [ ] Right panel: Tag input, applied tags, bulk select controls
- [ ] Header: File count ("100 of 105 selected"), Select All / Clear buttons
- [ ] Tabs: Tags (active default), Calendar, Charts
- [ ] Responsive: Layout adapts to screen size (single column on mobile)
- [ ] Performance: Renders 100 thumbnails in < 2 sec, responsive interactions < 200ms

**Technical Notes:**
- Use `react-window` for virtualized list (performance)
- Use Tailwind for layout
- State management: Zustand store for gallery state
- Component structure: IngestGalleryContainer → ThumbnailBrowser + ImagePreview + RightPanel

**Definition of Done:**
- Layout renders correctly at various screen sizes
- Virtualization working (smooth scrolling 1000+ images)
- Performance benchmarks met
- Component tests passing

---

#### Story 1b.2 — Thumbnail Browser Component
**Epic:** Phase 1b: Gallery Ingest UI + Tagging  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** to see all my uploaded images as thumbnails  
**So that** I can quickly identify and select the ones I want  

**Acceptance Criteria:**
- [ ] Thumbnail grid: 4 columns, scrollable
- [ ] Each thumbnail: Image preview, file type badge, selection checkbox, visual indicators (star, cull, tagged)
- [ ] Interactions:
  - Click to select/deselect (checkbox toggles)
  - Double-click to focus preview
  - Right-click context menu (Cull, Star, View Metadata)
- [ ] Visual indicators:
  - Blue border on selected images
  - Red overlay on culled images
  - Gold star on favorited images
- [ ] Performance: Virtualized (only 20-40 visible at once)
- [ ] Loading state: Placeholder thumbnails while images upload
- [ ] Component tests: Selection logic, right-click menu, visual states

**Technical Notes:**
- Library: `react-window` for virtualization
- Right-click: Use native context menu or custom menu component
- Placeholder: Use `react-skeleton` or gradient placeholder
- Cull state: Toggle red overlay without re-fetching image

**Definition of Done:**
- Thumbnails render at correct resolution
- Selection/visual states accurate
- Scrolling smooth even with many images
- Tests passing

---

#### Story 1b.3 — Image Preview & Metadata Display
**Epic:** Phase 1b: Gallery Ingest UI + Tagging  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** to see a large preview of the selected image with its EXIF metadata  
**So that** I can understand the shot's technical parameters  

**Acceptance Criteria:**
- [ ] Large image preview: Centered, max width 600px, responsive
- [ ] EXIF metadata displayed below preview in readable format:
  ```
  ISO: 400 | f/1.8 | 1/1000s | 50mm
  Camera: Canon 5D Mark IV
  Timestamp: 2026-04-10 14:32:15
  Location: [GPS if available]
  ```
- [ ] Actions overlay on preview: Cull (red X), Star (gold star), Rate (1-5 stars, P1), Enhance Quality (P1)
- [ ] Navigation: Arrow keys to previous/next image
- [ ] Loading state: "No image selected. Click a thumbnail to preview."
- [ ] Component tests: Metadata parsing, display formatting, navigation

**Technical Notes:**
- Component: `ImagePreview`
- EXIF parsing: From image metadata extracted in Story 1a.1
- Responsive: Preview shrinks on mobile, metadata below
- No image rotation in Phase 1 (defer to P1)

**Definition of Done:**
- Metadata displays correctly for all file types
- Navigation works smoothly
- No console warnings

---

#### Story 1b.4 — Tagging Panel & Tag Application
**Epic:** Phase 1b: Gallery Ingest UI + Tagging  
**Priority:** P0  
**Story Points:** 8  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** to apply tags to selected images quickly  
**So that** I can organize and filter images by meaningful categories  

**Acceptance Criteria:**
- [ ] Tag input field with autocomplete
- [ ] Tag sources:
  - Metadata-derived: ISO 400, f/1.8, Canon 5D Mark IV (auto-populated)
  - Calendar-derived: Client name, event date, location (auto-populated from calendar match)
  - User-defined: Free-form text or predefined library (Portrait, Candid, Posed, Cull, Favorite, Retouch, etc.)
- [ ] Interactions:
  - Type in input → autocomplete suggestions
  - Click suggestion or press Enter → add tag
  - Click X on tag badge → remove tag
  - "Create new tag" option if doesn't exist
- [ ] Bulk tagging: Select multiple images → apply tag to all
- [ ] Applied tags displayed as colored badges
- [ ] Tag persistence: POST /api/ingest/tag on each application
- [ ] Error handling: Failed tag application shows toast notification
- [ ] Component tests: Tag autocomplete, bulk application, error handling

**Technical Notes:**
- Autocomplete library: `react-autocomplete` or custom implementation
- Predefined tags: Load from backend (GET /api/ingest/tags) + cache locally
- Tag colors: Assign based on tag_type (metadata=gray, calendar=blue, user=green)
- Debounce POST requests (300ms) to avoid race conditions

**Definition of Done:**
- Autocomplete working for all tag types
- Bulk tagging applies to all selected images
- Tags persist after tag application
- Error handling graceful

---

#### Story 1b.5 — Filter Sidebar
**Epic:** Phase 1b: Gallery Ingest UI + Tagging  
**Priority:** P0  
**Story Points:** 8  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** to filter images by metadata and tags  
**So that** I can quickly isolate the shots I'm interested in  

**Acceptance Criteria:**
- [ ] Filter types:
  - Metadata: ISO (checkboxes for values present), Aperture, Focal Length, Camera Model
  - Tags: Checkboxes for all applied tags
  - Timeline: Slider for time range (by hour)
  - Cull: Toggle "Show culled images"
- [ ] Interactions:
  - Check/uncheck filters → thumbnails re-render (< 100ms)
  - Combined filters use AND logic (all must match)
  - "Clear filters" button resets to all images
- [ ] Persistent state: Filters retained during session
- [ ] Performance: Re-filter 100 images in < 100ms
- [ ] Responsive: Sidebar collapses on mobile
- [ ] Component tests: Filter logic, performance, combined filters

**Technical Notes:**
- Component: `FilterSidebar`
- Filtering: Client-side (no network call) for performance
- Metadata values: Extract from image metadata during ingest
- Timeline: Group images by hour, display clickable bars

**Definition of Done:**
- Filters apply instantly
- Combined filters work correctly
- No lag on thumbnail re-render
- Tests passing

---

#### Story 1b.6 — Charts Tab (Metadata Distributions)
**Epic:** Phase 1b: Gallery Ingest UI + Tagging  
**Priority:** P0  
**Story Points:** 8  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** to see charts of metadata distributions  
**So that** I can understand my technical execution and identify patterns  

**Acceptance Criteria:**
- [ ] Charts (all interactive, clickable to filter):
  - ISO Distribution (bar chart: ISO values vs. image count)
  - Aperture Distribution (bar chart)
  - Focal Length Distribution (bar chart: focal length ranges)
  - Timeline (bar chart: images by hour/30-min window)
  - Camera Model Distribution (bar/pie chart)
- [ ] Interactions: Click a bar → filter thumbnail browser to only those images
- [ ] Charts update in real-time as filters are applied
- [ ] Responsive: Charts resize on mobile, stack vertically
- [ ] Performance: Render 100 images, 5 charts in < 1 sec
- [ ] Component tests: Chart rendering, click-to-filter, real-time updates

**Technical Notes:**
- Charting library: Recharts (supports interactive charts natively)
- Data prep: Compute distributions from image metadata in real-time
- Memoization: Prevent unnecessary re-renders of static charts
- Responsive: Use Recharts ResponsiveContainer

**Definition of Done:**
- Charts render correctly
- Click-to-filter works
- No lag on interaction
- Tests passing

---

#### Story 1b.7 — Calendar Tab
**Epic:** Phase 1b: Gallery Ingest UI + Tagging  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** to see the calendar event details and associated images  
**So that** I understand the context of this shoot  

**Acceptance Criteria:**
- [ ] Display matched event details:
  - Event title, date, time range, location
  - Custom fields if available
- [ ] Image count associated with this event
- [ ] Visual timeline: Event duration (colored band) vs. image timestamps (dots)
- [ ] Calendar view: Click date → see events for that date + associated images
- [ ] Interactions: Click calendar date → filter to images from that event
- [ ] Component tests: Event details rendering, date selection, filtering

**Technical Notes:**
- Component: `CalendarTab`
- Calendar library: `react-calendar` or custom mini-calendar
- Timeline visualization: Custom SVG or Recharts bar chart

**Definition of Done:**
- Calendar events displayed correctly
- Date selection filters thumbnails
- Timeline visualization clear

---

#### Story 1b.8 — Background Upload Progress Tracking
**Epic:** Phase 1b: Gallery Ingest UI + Tagging  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** to see upload progress while I'm working  
**So that** I know when the upload is complete  

**Acceptance Criteria:**
- [ ] Progress display: "Uploading X/100 files... Y% complete"
- [ ] Progress bar showing visual progress
- [ ] Estimated time remaining (if upload takes > 30 sec)
- [ ] Pause/Resume buttons (defer resume until P1)
- [ ] Upload status updates every 1-2 sec
- [ ] Completion notification: "Upload complete. 100 images ready."
- [ ] Error handling: Failed uploads show error message + retry option
- [ ] Performance: Progress updates don't block UI interaction

**Technical Notes:**
- Component: `UploadProgressBar` in header
- Updates: Poll GET /api/ingest/status/{sessionId} every 2 sec
- Estimated time: (completed_bytes / elapsed_time) * (total_bytes - completed_bytes)

**Definition of Done:**
- Progress updates accurately
- No UI blocking during uploads
- Error handling clear

---

#### Story 1b.9 — Culling Interface
**Epic:** Phase 1b: Gallery Ingest UI + Tagging  
**Priority:** P0  
**Story Points:** 3  
**Owner:** Frontend Lead  

**As a** photographer  
**I want** to mark unwanted images as culled  
**So that** they don't appear in the final gallery  

**Acceptance Criteria:**
- [ ] Cull indicator: Red X icon on thumbnail, red overlay on preview
- [ ] Toggle cull: Click red X → image marked as culled (persists)
- [ ] Keyboard shortcut: "C" key to cull current image
- [ ] Show Culled toggle: Default hidden; toggle shows/hides culled images
- [ ] Culled images: Still uploaded, just excluded from gallery delivery
- [ ] Visual feedback: Clear distinction between culled and selected
- [ ] Uncull: Click X again to restore image
- [ ] Component tests: Cull toggle, visibility, keyboard shortcuts

**Technical Notes:**
- Cull state tracked in Zustand store
- No API call needed (cull state is local; persisted on confirm)
- Keyboard shortcut: Listen for "c" key press

**Definition of Done:**
- Cull state toggles correctly
- Visual indicator clear
- Keyboard shortcut working

---

#### Story 1b.10 — File Upload Manager (Background Service)
**Epic:** Phase 1b: Gallery Ingest UI + Tagging  
**Priority:** P0  
**Story Points:** 8  
**Owner:** Frontend Lead  

**As a** frontend  
**I want** to upload files in the background while user interacts with gallery  
**So that** the upload doesn't block the tagging experience  

**Acceptance Criteria:**
- [ ] `UploadManager` service:
  - Queues files for parallel upload (3-5 concurrent)
  - Tracks progress (completed/total files)
  - Emits progress events for UI updates
  - Handles network errors (retry 3 times with exponential backoff)
  - Resumable: Tracks uploaded files; skips on retry
- [ ] Performance: 100 files uploaded in < 5 minutes (depends on connection speed)
- [ ] Error handling: Clear error message if upload fails completely
- [ ] Persistence: Upload continues if user closes tab (resume on return)
- [ ] Service tests: Parallel uploads, error handling, resumability

**Technical Notes:**
- Library: Fetch API with AbortController (no external dependency)
- Parallel uploads: Limit to 3-5 concurrent with queue management
- Resume: Store completed file hashes in localStorage
- Retry logic: Exponential backoff (1s, 2s, 4s, 8s)

**Definition of Done:**
- Upload service tested with various file counts
- Error handling working
- Resume capability verified
- Performance benchmarks met

---

### Phase 1c: Refinement + Asset Creation + Launch (Weeks 7-9)

#### Story 1c.1 — POST /api/ingest/upload Endpoint
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Backend Lead  

**As a** frontend  
**I want** to upload files to the server  
**So that** they can be stored and processed  

**Acceptance Criteria:**
- [ ] Endpoint: POST /api/ingest/upload (multipart/form-data)
- [ ] Payload: Files + upload_session_id + optional calendar_event_id
- [ ] Response: { upload_session_id, gallery_id, status: "uploading" }
- [ ] File storage: Files stored in S3 (or local filesystem for dev)
- [ ] Chunk handling: Accept files in chunks (resume-friendly)
- [ ] Validation: File type, size, session validity
- [ ] Authentication: Requires auth token + studio ownership
- [ ] Error handling: 400 (invalid file), 413 (too large), 500 (storage error)
- [ ] Integration tests: Valid/invalid uploads, chunking, error cases

**Technical Notes:**
- S3 bucket: `prophoto-ingest-uploads/{studio_id}/{session_id}/`
- Chunking: Accept X-Upload-Offset header for resume
- File validation: Check MIME type + file extension
- Response format: Return file_id for each uploaded file

**Definition of Done:**
- Upload endpoint tested with various file sizes
- S3 integration working (or local filesystem for testing)
- Error handling correct
- Integration tests passing

---

#### Story 1c.2 — GET /api/ingest/status Endpoint
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 3  
**Owner:** Backend Lead  

**As a** frontend  
**I want** to check upload progress  
**So that** I can update the progress display  

**Acceptance Criteria:**
- [ ] Endpoint: GET /api/ingest/status/{session_id}
- [ ] Response: { completed_files, total_files, percent_complete, status, estimated_time_remaining }
- [ ] Real-time tracking: Updates as files are uploaded
- [ ] Performance: < 1 sec response time
- [ ] Authentication: Requires auth token, session ownership verification
- [ ] Error handling: 404 if session not found, 403 if not owner
- [ ] Integration tests: Valid session, invalid session, in-progress updates

**Technical Notes:**
- Query: Count completed files from ingest_files table
- Estimation: Linear projection based on elapsed time
- Caching: Cache response for 2 sec (reduce DB queries)

**Definition of Done:**
- Status updates accurately during upload
- Response time meets target
- Tests passing

---

#### Story 1c.3 — POST /api/ingest/tag Endpoint
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 3  
**Owner:** Backend Lead  

**As a** frontend  
**I want** to persist tags applied during ingest  
**So that** tags are saved for later use  

**Acceptance Criteria:**
- [ ] Endpoint: POST /api/ingest/tag
- [ ] Request: { ingest_file_id, tag, tag_type: "user|metadata|calendar" }
- [ ] Response: { success: true, applied_tags: [...] }
- [ ] Database: Insert into ingest_image_tags table
- [ ] Idempotency: Duplicate tags ignored (unique constraint)
- [ ] Authentication: Requires auth token, ingest file ownership verification
- [ ] Error handling: 400 (invalid input), 404 (file not found)
- [ ] Integration tests: Tag creation, duplicates, error cases

**Technical Notes:**
- Tag creation: Use findOrCreate pattern
- Tag types: Auto-determined by system (metadata/calendar) or user-provided
- Response: Return all tags applied to this file

**Definition of Done:**
- Tags persist correctly
- Duplicate tag handling working
- Tests passing

---

#### Story 1c.4 — SessionAssociationResolved Event Emission
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Backend Lead  

**As a** backend  
**I want** to emit an event when user confirms ingest  
**So that** other packages (assets, gallery, intelligence) can process images  

**Acceptance Criteria:**
- [ ] Event: `SessionAssociationResolved`
- [ ] Trigger: POST /api/ingest/confirm-session called
- [ ] Payload: session_id, gallery_id, studio_id, user_id, ingest_files[], calendar_event_id
- [ ] Event dispatch: Fire event to Laravel event system
- [ ] Async listeners: All listeners run in queue (don't block response)
- [ ] Error handling: If listener fails, log error + continue (at least 1 listener must succeed)
- [ ] Event tests: Emission, payload accuracy, listener invocation

**Technical Notes:**
- Event class: `ProPhoto\Ingest\Events\SessionAssociationResolved`
- Define in prophoto-contracts
- Dispatch: `event(new SessionAssociationResolved(...))`
- Queue: Use Laravel job queue for listeners

**Definition of Done:**
- Event emitted correctly on session confirmation
- Event payload includes all necessary data
- Tests passing

---

#### Story 1c.5 — Asset Creation Listener
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 8  
**Owner:** Backend Lead (Assets Team)  

**As a** prophoto-assets  
**I want** to listen for SessionAssociationResolved events  
**So that** I can create Asset records for ingested images  

**Acceptance Criteria:**
- [ ] Listener: `AssetCreationListener` in prophoto-assets
- [ ] Trigger: `SessionAssociationResolved` event
- [ ] For each non-culled ingest_file:
  - Create Asset record with file metadata
  - Link to upload_session (for provenance)
  - Store applied tags as asset metadata
- [ ] Asset fields populated:
  - filename, file_size, file_type (from ingest_file)
  - exif_metadata (from ingest_files.exif_data)
  - studio_id, user_id (from session)
  - tags (from ingest_image_tags)
- [ ] Error handling: If asset creation fails for a file, log error + continue
- [ ] Performance: Create 100 assets in < 30 sec
- [ ] Tests: Asset creation, metadata population, error handling

**Technical Notes:**
- Listener location: `prophoto-assets/Listeners/AssetCreationListener.php`
- Register in `AssetServiceProvider::boot()`
- Bulk insert if possible (reduce DB round trips)
- Exclude culled images: WHERE ingest_files.culled = FALSE

**Definition of Done:**
- Assets created correctly for each non-culled file
- Metadata populated accurately
- Performance target met
- Tests passing

---

#### Story 1c.6 — Gallery Context Projection Listener
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Backend Lead (Gallery Team)  

**As a** prophoto-gallery  
**I want** to listen for SessionAssociationResolved events  
**So that** I can link assets to the gallery and set delivery status  

**Acceptance Criteria:**
- [ ] Listener: `GalleryContextProjectionListener` in prophoto-gallery
- [ ] Trigger: `SessionAssociationResolved` event
- [ ] Link assets to gallery: Create `gallery_asset` associations
- [ ] Update gallery status: Mark as "ready_for_delivery"
- [ ] Store upload context in gallery_asset_session_contexts
- [ ] Performance: Link 100 assets to gallery in < 10 sec
- [ ] Tests: Asset linking, context storage, error handling

**Technical Notes:**
- Listener location: `prophoto-gallery/Listeners/GalleryContextProjectionListener.php`
- Gallery already exists (created during calendar match confirmation)
- Use bulk insert for gallery_asset records
- Context includes: upload_session_id, calendar_event_id, applied_tags

**Definition of Done:**
- Assets linked to gallery correctly
- Gallery status updated
- Tests passing

---

#### Story 1c.7 — Intelligence Queue Listener
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 3  
**Owner:** Backend Lead (Intelligence Team)  

**As a** prophoto-intelligence  
**I want** to listen for SessionAssociationResolved events  
**So that** I can queue images for background processing  

**Acceptance Criteria:**
- [ ] Listener: `IntelligenceQueueListener` in prophoto-intelligence
- [ ] Trigger: `SessionAssociationResolved` event
- [ ] Queue each asset for processing:
  - Metadata extraction (if not already done)
  - Thumbnail generation (if needed)
  - AI tagging (deferred to Phase 4)
- [ ] Use async job queue (Redis/database jobs)
- [ ] Performance: Queue 100 jobs in < 5 sec
- [ ] Tests: Job queuing, error handling

**Technical Notes:**
- Listener location: `prophoto-intelligence/Listeners/IntelligenceQueueListener.php`
- Job class: `ProPhoto\Intelligence\Jobs\ProcessAssetJob`
- Queue: Use Laravel's default queue (configurable)

**Definition of Done:**
- Assets queued for processing
- Jobs execute without errors
- Tests passing

---

#### Story 1c.8 — POST /api/ingest/confirm-session Endpoint
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Backend Lead  

**As a** frontend  
**I want** to confirm ingest and trigger asset creation  
**So that** my images are ready for gallery delivery  

**Acceptance Criteria:**
- [ ] Endpoint: POST /api/ingest/confirm-session
- [ ] Request: { upload_session_id, gallery_id }
- [ ] Response: { success: true, gallery_id, asset_count, status: "assets_created" }
- [ ] Validation: Session exists, upload complete, gallery exists
- [ ] Emit event: SessionAssociationResolved (async)
- [ ] Update session status: "completed"
- [ ] Authentication: Requires auth token, session ownership
- [ ] Error handling: 400 (validation), 404 (not found), 409 (invalid state)
- [ ] Integration tests: Valid confirm, validation errors, async event firing

**Technical Notes:**
- Endpoint: `IngestController::confirmSession()`
- Async event dispatch ensures response is fast
- Return asset_count for user feedback

**Definition of Done:**
- Endpoint responds correctly
- Event emitted asynchronously
- Tests passing

---

#### Story 1c.9 — Performance Optimization & Load Testing
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 8  
**Owner:** DevOps/Backend Lead  

**As a** operations  
**I want** to verify Phase 1 performance meets targets  
**So that** the system can handle production load  

**Acceptance Criteria:**
- [ ] Performance benchmarks (all achieved):
  - Metadata extraction: < 5 sec for 100 files ✓
  - Calendar matching: < 3 sec ✓
  - Gallery render: < 2 sec ✓
  - Filtering: < 100ms ✓
  - Upload: Parallel 3-5 files ✓
  - Status polling: < 1 sec response ✓
  - Asset creation: < 30 sec for 100 files ✓
- [ ] Load test: 10 concurrent users uploading 100 files each
  - No timeouts or errors
  - Response times stable
  - Database queries optimized (no N+1 problems)
- [ ] Database indexing verified: All recommended indexes present
- [ ] Monitoring: CloudWatch/DataDog metrics configured
- [ ] Documentation: Performance benchmarks documented in ADR

**Technical Notes:**
- Load testing tool: Apache JMeter or k6
- Database profiling: Laravel Debugbar, MySQL SLOW_LOG
- Optimization focus: N+1 queries, missing indexes, unoptimized loops

**Definition of Done:**
- All benchmarks met on staging environment
- Load test passes without errors
- Indexes optimized
- Monitoring in place

---

#### Story 1c.10 — End-to-End Testing & UAT
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 8  
**Owner:** QA Lead  

**As a** QA  
**I want** to test the complete Phase 1 workflow end-to-end  
**So that** we catch bugs before launch  

**Acceptance Criteria:**
- [ ] E2E test scenarios (all passing):
  - **Sarah (commercial):** 150 product photos, auto-match, bulk tag, confirm
  - **James (event):** 250 images, multi-event, time-window filter
  - **Lisa (casual):** 80 family photos, simple star/cull
- [ ] Each scenario tests:
  - File upload
  - Metadata extraction
  - Calendar matching
  - Gallery interaction (filter, tag, cull)
  - Upload completion
  - Asset creation
- [ ] UAT with reference photographers: ≥5 users, positive feedback
- [ ] Bug tracking: All blocking issues resolved, P1 issues documented
- [ ] Test coverage: ≥90% for ingest module

**Technical Notes:**
- E2E framework: Playwright or Cypress
- Test data: Sample image files with known EXIF
- UAT feedback: Collected via survey + interviews
- Regression suite: All tests should pass before launch

**Definition of Done:**
- All E2E tests passing
- UAT sign-off from reference photographers
- Bug tracker clear of blocking issues
- Test coverage report generated

---

#### Story 1c.11 — Documentation & Support Materials
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Tech Writer / Product Lead  

**As a** user  
**I want** clear documentation on how to use Phase 1  
**So that** I can onboard and use the feature confidently  

**Acceptance Criteria:**
- [ ] User Guide: How to upload, tag, filter, cull images (in-app + web docs)
- [ ] Admin Guide: Calendar OAuth setup, troubleshooting
- [ ] API Documentation: OpenAPI/Swagger spec for all endpoints
- [ ] Video Tutorial: 5-minute walkthrough of ingest workflow
- [ ] FAQ: Common questions (e.g., "What happens to culled images?" "Can I undo tags?")
- [ ] Error Message Guide: What each error means + resolution
- [ ] Support playbook: How to help users with common issues
- [ ] In-app tooltips: Brief help text on key UI elements

**Technical Notes:**
- Documentation platform: GitHub Pages or static site
- Video platform: YouTube or Vimeo
- API docs: Generated from OpenAPI spec (auto-generated)

**Definition of Done:**
- Documentation complete and reviewed
- Video published
- All materials live before launch

---

#### Story 1c.12 — Launch & Monitoring
**Epic:** Phase 1c: Refinement + Asset Creation + Launch  
**Priority:** P0  
**Story Points:** 5  
**Owner:** Product Lead / DevOps  

**As a** operations  
**I want** to deploy Phase 1 to production and monitor its health  
**So that** we catch issues immediately and can roll back if needed  

**Acceptance Criteria:**
- [ ] Deployment:
  - Feature flag: Phase 1 behind feature flag (controlled rollout)
  - Database migrations: Applied successfully
  - Services: All new services running
  - Monitoring: Dashboards and alerts in place
- [ ] Health checks:
  - Upload success rate ≥98%
  - Calendar match accuracy ≥80%
  - Asset creation success ≥99%
  - Error rate ≤1%
- [ ] Monitoring metrics:
  - Ingest sessions created/completed per hour
  - Average upload time
  - Calendar match confidence distribution
  - Asset creation latency p50/p95/p99
  - Error logs (grouped by type)
- [ ] Runbooks: How to troubleshoot common issues, rollback procedure
- [ ] Incident response: On-call rotation, escalation path

**Technical Notes:**
- Feature flag: Use Laravel feature flag package (spatie/laravel-feature-flags)
- Deployment: Blue-green deployment recommended
- Monitoring: CloudWatch, DataDog, or Sentry
- Runbook template: Provided in ops docs

**Definition of Done:**
- Deployment successful
- Monitoring verified
- Runbooks written
- Team trained on monitoring

---

## Part 2: Sprint Breakdown

### Sprint 1 (Weeks 1-2): Metadata Extraction Foundation

**Sprint Goal:** Foundation for metadata extraction and calendar API integration; metadata service working end-to-end.

**Stories in Sprint:**
- 1a.1 — Metadata Extraction Service (8 pts)
- 1a.2 — Calendar OAuth Setup (5 pts)
- 1a.5 — Upload Session Model & Table (3 pts)
- 1a.6 — Create UploadSessionService (3 pts)

**Total Points:** 19 (estimate 4-5 dev days for small team)

**Deliverables:**
- Metadata extraction service (exifjs integration)
- Calendar OAuth working
- UploadSession model + service
- Unit tests passing (≥80% coverage)

**Risks:**
- exifjs compatibility with various RAW formats (mitigation: test with sample files early)
- Calendar API rate limits (mitigation: implement caching)

**Go/No-Go Criteria:**
- Metadata extraction < 5 sec for 100 files ✓
- Calendar OAuth tokens persisting correctly ✓
- UploadSession CRUD operations working ✓

---

### Sprint 2 (Weeks 2-3): Calendar Matching & API

**Sprint Goal:** Calendar matching algorithm working; POST /api/ingest/match-calendar endpoint live.

**Stories in Sprint:**
- 1a.3 — Calendar Matching Service (8 pts)
- 1a.4 — POST /api/ingest/match-calendar Endpoint (5 pts)
- 1a.7 — Frontend: Upload Initiation UI (5 pts)
- 1a.8 — Frontend: Calendar Match Display (5 pts)

**Total Points:** 23 (estimate 5-6 dev days)

**Deliverables:**
- Calendar matching algorithm implemented
- API endpoint functional
- Frontend UI for upload initiation + match display
- Integration tests for calendar matching
- API documentation (OpenAPI)

**Risks:**
- Scoring algorithm accuracy (mitigation: validate against manual test cases)
- UI responsiveness with large metadata arrays (mitigation: performance test early)

**Go/No-Go Criteria:**
- Calendar matching < 3 sec for 100 images + 10 events ✓
- API endpoint returns ranked matches with evidence ✓
- Frontend displays matches and handles user selection ✓

---

### Sprint 3 (Weeks 4-5): Gallery UI & Tagging Infrastructure

**Sprint Goal:** Complete gallery ingest interface; users can tag, filter, cull images during upload.

**Stories in Sprint:**
- 1b.1 — Ingest Gallery Container & Layout (8 pts)
- 1b.2 — Thumbnail Browser Component (5 pts)
- 1b.3 — Image Preview & Metadata Display (5 pts)
- 1b.4 — Tagging Panel & Tag Application (8 pts)
- 1b.5 — Filter Sidebar (8 pts)

**Total Points:** 34 (estimate 7-8 dev days)

**Deliverables:**
- Complete gallery ingest UI
- Thumbnail browser (virtualized)
- Image preview + metadata
- Tagging interface
- Filter sidebar
- Component tests (≥80% coverage)

**Risks:**
- Performance: Large images or virtualization issues (mitigation: profile and optimize early)
- Tag autocomplete responsiveness (mitigation: debounce + memoization)

**Go/No-Go Criteria:**
- Gallery renders 100 thumbnails in < 2 sec ✓
- Filtering re-renders in < 100ms ✓
- Tagging applies instantly ✓
- Culling visual state clear ✓

---

### Sprint 4 (Weeks 5-6): Charts, Calendar Tab & Upload Manager

**Sprint Goal:** Metadata visualization complete; background upload service working.

**Stories in Sprint:**
- 1b.6 — Charts Tab (Metadata Distributions) (8 pts)
- 1b.7 — Calendar Tab (5 pts)
- 1b.8 — Background Upload Progress Tracking (5 pts)
- 1b.9 — Culling Interface (3 pts)
- 1b.10 — File Upload Manager (Background Service) (8 pts)

**Total Points:** 29 (estimate 6-7 dev days)

**Deliverables:**
- Charts tab (ISO, aperture, focal length, timeline, camera distribution)
- Calendar tab (event details, date selection, filtering)
- Upload progress bar
- UploadManager service (parallel uploads, resumability)
- Integration tests for upload manager

**Risks:**
- Chart performance with large datasets (mitigation: test with 500+ images)
- Upload resumability complexity (mitigation: thorough testing of edge cases)

**Go/No-Go Criteria:**
- Charts render 5 distributions in < 1 sec ✓
- Click-to-filter from charts working ✓
- Upload manager handles parallel files + errors ✓
- Resume functionality tested ✓

---

### Sprint 5 (Weeks 6-7): Backend Upload & Event System

**Sprint Goal:** File upload endpoint, tagging persistence, and event emission infrastructure.

**Stories in Sprint:**
- 1c.1 — POST /api/ingest/upload Endpoint (5 pts)
- 1c.2 — GET /api/ingest/status Endpoint (3 pts)
- 1c.3 — POST /api/ingest/tag Endpoint (3 pts)
- 1c.4 — SessionAssociationResolved Event Emission (5 pts)

**Total Points:** 16 (estimate 4 dev days)

**Deliverables:**
- File upload endpoint (multipart)
- Status polling endpoint
- Tag persistence endpoint
- Event emission system
- Integration tests for all endpoints

**Risks:**
- File storage (S3 vs. local): Ensure dev/staging use same approach (mitigation: env config)
- Event async dispatch: Ensure no race conditions (mitigation: thorough testing)

**Go/No-Go Criteria:**
- Upload endpoint accepts files and chunks ✓
- Status endpoint updates in real-time ✓
- Tags persist correctly ✓
- Event emitted and received by listeners ✓

---

### Sprint 6 (Weeks 7-8): Asset Creation & Integration

**Sprint Goal:** Complete event listeners; assets created from ingested images.

**Stories in Sprint:**
- 1c.5 — Asset Creation Listener (8 pts)
- 1c.6 — Gallery Context Projection Listener (5 pts)
- 1c.7 — Intelligence Queue Listener (3 pts)
- 1c.8 — POST /api/ingest/confirm-session Endpoint (5 pts)

**Total Points:** 21 (estimate 5 dev days)

**Deliverables:**
- Asset creation listener (creates Asset records)
- Gallery context projection listener (links assets to gallery)
- Intelligence queue listener (queues for background processing)
- Confirm-session endpoint
- Integration tests for entire workflow (metadata → assets)

**Risks:**
- Listener execution order: Ensure assets created before intelligence processes (mitigation: explicit dependency)
- Error handling: One listener failing shouldn't break others (mitigation: error isolation)

**Go/No-Go Criteria:**
- Assets created for each non-culled ingest file ✓
- Assets linked to gallery ✓
- Background jobs queued ✓
- Complete workflow tested end-to-end ✓

---

### Sprint 7 (Weeks 8-9): Performance, Testing & Launch

**Sprint Goal:** Performance optimization, comprehensive testing, documentation, and launch preparation.

**Stories in Sprint:**
- 1c.9 — Performance Optimization & Load Testing (8 pts)
- 1c.10 — End-to-End Testing & UAT (8 pts)
- 1c.11 — Documentation & Support Materials (5 pts)
- 1c.12 — Launch & Monitoring (5 pts)

**Total Points:** 26 (estimate 6 dev days)

**Deliverables:**
- Performance benchmarks verified
- Load testing completed (10 concurrent users)
- E2E test suite (Playwright/Cypress)
- UAT sign-off from reference photographers
- User documentation + video
- Launch runbooks + monitoring dashboards

**Risks:**
- Tight timeline: If E2E testing reveals major bugs, may delay launch (mitigation: early E2E setup)
- UAT availability: Getting reference photographers on short notice (mitigation: recruit early)

**Go/No-Go Criteria:**
- All performance benchmarks met ✓
- Load test passes (10 concurrent, no errors) ✓
- E2E tests passing (Sarah, James, Lisa scenarios) ✓
- UAT sign-off received ✓
- Documentation reviewed + published ✓
- Monitoring dashboards functional ✓

---

## Part 3: Capacity Planning & Risk Assessment

### Team Composition (Recommended)

**Minimum team for 9-week delivery:**
- 1 Backend Lead (Laravel/PHP)
- 1 Frontend Lead (React/TypeScript)
- 1 QA / DevOps
- 1 Product/Tech Writer (part-time, weeks 7-9)

**Time Allocation:**
- Weeks 1-6: Both leads full-time (100%)
- Weeks 7-9: Both leads + QA full-time, Tech Writer part-time

**Estimated Effort:**
- Backend: ~120 dev days
- Frontend: ~100 dev days
- QA: ~40 days
- Product/Docs: ~20 days
- **Total: ~280 dev days (~9 weeks for 3-4 people)**

### Capacity Buffer

**Plan for interruptions:**
- Holidays/PTO: 5% buffer
- Bug fixes: 10% buffer
- Meetings/overhead: 15% buffer
- **Effective capacity: 70% of nominal time**

### Critical Path

**Dependencies (must complete in order):**
1. **Metadata + Calendar** (Weeks 1-3) → unblocks Gallery UI
2. **Gallery UI** (Weeks 4-5) → unblocks Tagging + Upload Manager
3. **Upload Manager + API** (Weeks 5-6) → unblocks Event System
4. **Event System** (Weeks 6-7) → unblocks Listeners + Asset Creation
5. **Asset Creation** (Weeks 7-8) → unblocks Testing + Launch

**Parallelizable work:**
- Frontend and backend can progress in parallel (API contracts defined upfront)
- Charts/Calendar tabs can be deferred to final sprint (not on critical path)
- Documentation can start in week 5 (doesn't block development)

---

## Part 4: Risk Assessment & Mitigation

### High-Risk Items

| Risk | Impact | Likelihood | Mitigation |
|------|--------|-----------|-----------|
| exifjs compatibility with RAW formats | Can't extract metadata from some files | Medium | Test with real RAW files from day 1; keep fallback handler for errors |
| Calendar API rate limiting | Can't match images during peak hours | Low | Implement response caching (5 min); use service account quota |
| File upload resumability | Can't recover from network interruptions | Medium | Thorough testing of resume logic; use file hash verification |
| Performance (large galleries) | UI lag or timeout on 500+ images | Medium | Profile early; use virtualization; optimize DB queries |
| Event listener failures | Asset creation fails, blocking delivery | Medium | Error isolation; retry logic; clear logging; runbooks |
| UAT timing | Reference photographers unavailable for testing | Low | Recruit early (weeks 1-3); offer compensation if needed |

### Medium-Risk Items

| Risk | Impact | Likelihood | Mitigation |
|------|--------|-----------|-----------|
| Chart rendering performance | Charts lag on 1000+ images | Low-Medium | Use memoization; lazy-load charts; consider pre-computation |
| Concurrent upload conflicts | Race condition with overlapping uploads | Low | Use database-level locking; thorough testing of edge cases |
| Calendar event matching false positives | Users confused by incorrect matches | Medium | Validate algorithm against manual test cases; show evidence; allow override |
| Browser tab closure during upload | Upload lost, user frustrated | Low-Medium | Use localStorage to track progress; resume on return |

---

## Part 5: Success Criteria & Metrics

### Sprint Success Criteria

**Each sprint must meet these criteria to proceed:**
- ✅ All P0 stories completed (not carried over)
- ✅ Unit test coverage ≥80% for new code
- ✅ Integration tests passing
- ✅ Zero critical/blocking bugs in backlog
- ✅ Code review approved by team lead
- ✅ Performance benchmarks met (if applicable)

### Phase 1 Launch Criteria

**Before going live, all must be true:**
- ✅ All 25 user stories completed and tested
- ✅ E2E tests passing (Sarah, James, Lisa scenarios)
- ✅ UAT sign-off from ≥5 reference photographers
- ✅ Performance benchmarks met:
  - Metadata extraction < 5 sec (100 files)
  - Calendar matching < 3 sec
  - Gallery render < 2 sec
  - Filtering < 100ms
  - Upload success rate ≥98%
- ✅ Documentation complete (user guide, admin guide, API docs)
- ✅ Monitoring dashboards live
- ✅ Runbooks written + team trained
- ✅ Zero critical bugs in production readiness checklist

### Post-Launch Metrics (First 4 Weeks)

| Metric | Target | How Measured |
|--------|--------|--------------|
| Upload success rate | ≥98% | CloudWatch logs: succeeded / total uploads |
| Metadata extraction success | ≥95% | Monitoring: parsed / total files |
| Calendar match accuracy | ≥80% | User survey: "Did auto-match find the right event?" |
| Feature adoption | ≥70% of active users | Analytics: users who completed ≥1 ingest |
| Average ingest time | <5 min | User telemetry: upload start to confirm |
| Support tickets | <5% increase | Support system: track ingest-related tickets |
| User satisfaction (NPS) | ≥40 | Post-ingest survey |

---

## Part 6: Timeline Summary

```
PHASE 1 TIMELINE (9 weeks)

Week 1-2 (Sprint 1)    | Metadata Extraction Foundation
  └─ exifjs, Calendar OAuth, UploadSession model

Week 2-3 (Sprint 2)    | Calendar Matching & API
  └─ Scoring algo, match-calendar endpoint, upload UI

Week 4-5 (Sprint 3)    | Gallery UI & Tagging
  └─ Thumbnail browser, preview, tagging, filters

Week 5-6 (Sprint 4)    | Charts, Calendar Tab & Upload Manager
  └─ Charts, calendar view, background uploads

Week 6-7 (Sprint 5)    | Backend Upload & Events
  └─ Upload endpoint, status polling, event system

Week 7-8 (Sprint 6)    | Asset Creation & Integration
  └─ Event listeners, asset creation, confirm-session

Week 8-9 (Sprint 7)    | Performance, Testing & Launch
  └─ Performance optimization, E2E testing, UAT, launch

DELIVERY: End of Week 9 (Phase 1 Live)
```

---

## Part 7: Implementation Handoff Checklist

**Before coding begins:**
- [ ] Team assembled and capacity confirmed
- [ ] Development environment set up (laptops, S3, Redis, PostgreSQL)
- [ ] Google Cloud Console credentials created + documented
- [ ] Sample RAW files available for testing (10+ files, various cameras)
- [ ] Design system components finalized (buttons, inputs, cards, badges)
- [ ] Database schema reviewed + approved
- [ ] API contracts finalized (request/response payloads)
- [ ] Feature flags configured (Laravel feature flag package)
- [ ] Monitoring dashboards created (CloudWatch/DataDog)
- [ ] Runbook template created (ops docs)
- [ ] Team training completed (architecture walk-through, patterns)

**During development:**
- [ ] Daily standup (15 min)
- [ ] Sprint planning + review (Friday, 1 hour)
- [ ] Backlog refinement (Tuesday, 30 min)
- [ ] Architecture discussion (as needed)
- [ ] Performance profiling (weekly)

**Before each sprint end:**
- [ ] Sprint retrospective
- [ ] Go/no-go decision for next sprint
- [ ] Updated burndown chart
- [ ] Risk assessment update

---

**End of Phase 4: Epic Breakdown & Sprint Planning**

Ready to begin Sprint 1?
