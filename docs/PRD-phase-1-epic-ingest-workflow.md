# ProPhoto Phase 1 Epic: Complete Ingest & Post-Upload Workflow

**Status:** Epic-Level Product Requirement Document  
**Version:** 1.0  
**Created:** April 10, 2026  
**Reference:** Project Brief (v1), Ingest-Pro V0, BMAD Project Scan

---

## Executive Summary

Phase 1 is the operational heart of ProPhoto. It redefines how photographers handle the critical period between "photos captured" and "ready for delivery." Instead of scattered tools and manual organization, photographers upload images once and immediately enter a unified ingest experience where metadata, calendar context, and user intelligence flow together.

The Phase 1 epic spans six interconnected workflows:
1. **Metadata Extraction** — Parse EXIF/IPTC/XMP before upload completes
2. **Calendar Matching** — Suggest shoot context using calendar + metadata
3. **Immediate Tagging** — Apply granular tags in real time (ISO, aperture, pose, client, etc.)
4. **Intelligent Filtering** — Explore and cull using metadata distributions and calendar context
5. **Background Upload** — Files transfer while user works
6. **Post-Upload Workflow Guidance** — Suggest next actions (client attachment, gallery template, quoting, AI services)

**Outcome:** A photographer uploads 100+ raw files and within seconds is in a fully interactive ingest environment, able to filter, tag, and organize images while the upload completes in the background. No waiting. No manual file organization. No lost context.

---

## Problem Statement

Photographers waste 45-60 minutes per shoot on file organization after shooting:
- Transferring files from camera to computer
- Dragging images into folders by shoot/client
- Renaming files according to studio naming conventions  
- Cross-referencing calendar to remember which client, which date, which deliverables
- Exporting to shared drives or galleries

This "dead time" is:
- **Lost productivity**: 50 shoots/year × 50 minutes = 42 hours wasted annually per photographer
- **Context loss**: By the time culling/editing begins, photographers forget shoot intent, client requests, and composition decisions
- **Operational friction**: Cannot begin delivery work until files are "organized"
- **Cognitive overhead**: Manual matching of images to calendar events is tedious and error-prone

ProPhoto eliminates this friction by:
- Extracting metadata immediately
- Matching images to calendar automatically
- Launching the photographer directly into an interactive ingest environment
- Allowing work to begin while uploads complete

---

## Strategic Goals

1. **Reduce shoot-to-ingest time by 80%** — From 50 minutes manual organization to <5 minutes automated matching + tagging
2. **Make metadata a first-class UI element** — Users see and filter by ISO, aperture, shutter speed, focal length, camera model at all times
3. **Eliminate the "upload and wait" paradigm** — Background upload + immediate work capability
4. **Establish calendar as operational spine** — All images connected to shoot context (who, where, what, when) before any other work begins
5. **Enable micro-decisions in bulk** — Users apply decisions to filtered groups (all ISO 400 shots, all portraits, all posed) not individual images

---

## Non-Goals

- **Real-time image enhancement or preview rendering** — Thumbnails are pre-generated or displayed at lower quality; we do not render full-resolution RAW previews
- **AI-powered image analysis (Phase 1)** — Automatic pose detection, face grouping, composition scoring deferred to Phase 4
- **Retouching or editing tools** — Proofing and approval happen in gallery layer; editing is external
- **Multi-photographer shoot merging** — Each upload is a single photographer's session; team shooting features deferred to Phase 3
- **Backup or archive workflow** — Storage management (cold storage, compression, archival) is Phase 2 scope
- **Direct camera tethering** — Images must be exported from camera first; real-time capture ingest is future scope

---

## The 3 Pillars

### 1. Metadata-First Ingest

Every image carries rich metadata from the camera (EXIF), photographer (IPTC), or embedded systems (XMP). ProPhoto makes this metadata **visible and actionable** throughout ingest.

Users can:
- See ISO, aperture, shutter speed, focal length for every image
- Filter by any metadata field
- Create tags derived from metadata (e.g., "all shots at f/1.8")
- Build saved filter rules for recurring patterns

### 2. Calendar as Context Engine

If the photographer maintains a calendar (Google Calendar, Apple Calendar, Outlook), ProPhoto uses it to establish shoot context **before any manual input**.

Matching logic:
- Compare image timestamps to calendar event times (±15 min default window, configurable)
- Compare image location data (if available) to calendar event location
- Suggest matching calendar events ranked by confidence
- Allow photographer to confirm, override, or skip calendar matching

If matched:
- Gallery is pre-populated with event metadata (client name, location, event title)
- Images are scoped to that context for filtering and tagging
- Post-upload workflow offers calendar-relevant next actions (invoice, quote, client gallery)

### 3. Immediate Tagging Interface

The ingest gallery is not a passive viewer. It is an active workspace where users apply decisions to images.

Tagging mechanisms:
- **Metadata-derived tags**: Automatically available (ISO 400, f/1.8, Canon 5D Mark IV)
- **Calendar-derived tags**: Automatically available (Client name, event type, date)
- **User tags**: Custom free-form or predefined (Portrait, Posed, Candid, Cull, Favorite, Retouch, etc.)
- **Bulk tagging**: Apply tags to filtered groups (e.g., "all images shot at ISO 400 → tag 'ISO400'")

Applied tags affect:
- Image filtering and browsing
- Gallery generation and delivery (tags determine what clients see)
- Billing (tags can map to billable deliverables)
- AI service selection (portrait tags → trigger AI portrait generation options)

---

## Core User Workflows

### Workflow 1: Upload → Calendar Match → Ingest (Ideal Path)

**Precondition**: User has calendar connected and maintained  
**Time to value**: <2 minutes

1. User drags 100+ RAW files into ProPhoto upload area
2. Frontend extracts metadata and EXIF immediately (no upload yet)
3. System queries calendar for events within time window of first/last image timestamp
4. Results displayed: "We found 4 matching calendar events"
   - Johnson Wedding (47 images, 95% confidence)
   - Corporate Headshots (32 images, 87% confidence)
   - Family Portrait Session (18 images, 72% confidence)
   - Product Launch (3 images, 45% confidence)
5. User selects "Johnson Wedding" (or clicks "Continue with all" to load all images without calendar binding)
6. Upload begins in background
7. User immediately enters gallery view:
   - Left: Thumbnail browser (all images from session)
   - Center: Large preview + metadata display (ISO, aperture, focal length, camera)
   - Right: Tagging panel + filters
   - Top: Charts tab (metadata distributions) + Calendar tab (calendar context)
8. User begins filtering:
   - "Show only portraits" (filters by tag)
   - "Show only f/1.8 shots" (filters by ISO metadata)
   - "Exclude culled" (filters by cull status)
9. User tags images:
   - Bulk culls (clicks red X on unwanted images)
   - Applies tags to favorites (star icon)
   - Groups similar shots ("all group photos → tag 'group'")
10. Once satisfied, user closes ingest or proceeds to post-upload workflow (client attachment, gallery template, etc.)

### Workflow 2: Upload → No Calendar Match → Manual Tagging

**Precondition**: User has no calendar or calendar has no matching events  
**Time to value**: <2 minutes (tagging as primary organization method)

1. User drags 100+ files into ProPhoto
2. System extracts metadata; queries calendar; finds no confident matches
3. System prompts: "No calendar events found. Continue to ingest?" or "Would you like to create a new calendar event?"
4. User opts to continue without calendar binding
5. Upload begins; user enters gallery view (same as above)
6. User tags images exclusively by metadata and custom tags
7. Metadata filters are particularly useful (e.g., "all high ISO shots" → review for noise, decide culling)
8. At end of ingest, gallery has rich metadata context even without calendar connection

### Workflow 3: Upload → Calendar Match Found But Low Confidence → Manual Verification

**Precondition**: Calendar match exists but below confidence threshold (< 70%)  
**Time to value**: <3 minutes

1. User drags files in; system finds potential match but with moderate confidence
2. System displays match with evidence: "Family Portrait Session (72% confidence — 18 of 100 images match timestamp, location data unavailable)"
3. User can:
   - **Accept match**: "Yes, these are from Family Portrait Session"
   - **Reject match**: "No, different shoot"
   - **Override**: "Actually, these are from Corporate Headshots"
4. User's decision is recorded and used to refine matching algorithm
5. Upload + ingest proceeds as above

### Workflow 4: Multi-Calendar-Event Upload (Batch Shoot)

**Precondition**: User shoots multiple events in one session (common for event photographers)  
**Time to value**: <3 minutes

1. User drags 200+ files from a full day of shooting
2. System detects multiple calendar events within file timestamp range
3. Displays all matches: "Wedding Ceremony (95% — 120 images), Reception (92% — 80 images)"
4. User opts to "Load all without splitting" (images marked with both events) or "Create separate galleries" (images split by event)
5. Upload + ingest proceeds; if separated, user works through one gallery at a time
6. Tags/decisions applied per-gallery context

---

## User Stories

### Personas

**Sarah — High-Volume Commercial Photographer**
- 25-35 shoots per month (corporate headshots, product, lifestyle)
- Works with assistants on large shoots
- Highly organized; maintains detailed calendar with client/location data
- Values speed and repeatability
- Uses structured tagging for billing and delivery

**James — Wedding & Event Photographer**
- 2-3 shoots per week (weddings, corporate events, portraits)
- Often shoots multiple events per day
- Moderate calendar maintenance; sometimes shoots unscheduled
- Values flexible filtering to find best moments quickly
- Needs ability to segment images by event or time within single upload

**Lisa — Part-Time / Hobbyist Photographer**
- 5-10 shoots per month (family portraits, small events)
- Minimal calendar use; relies on memory and metadata
- Values simplicity and visual browsing over complex tagging
- Wants fast upload without friction

### User Stories (Grouped by Persona)

#### Sarah — Commercial Workflows

- **As Sarah, I want to upload 150 product photos and have them automatically matched to the "Acme Corp Catalog Shoot" calendar event, so that I skip 20 minutes of manual file organization and immediately start culling and tagging.**
- **As Sarah, I want to bulk-tag all images shot at ISO 1600 with an "high-iso" tag, so that I can quickly review them for noise and decide if retouching is needed.**
- **As Sarah, I want to filter images by metadata (camera model, focal length, aperture) to review my technical execution, so that I can identify patterns (e.g., "all 50mm shots are sharper than 85mm") and improve.**
- **As Sarah, I want to see a chart of aperture distribution across the shoot, so that I can verify I hit my intended exposure targets.**
- **As Sarah, I want to tag images with "deliver-to-client," "archive," and "delete," so that I can batch-process decisions downstream (delivery to gallery, storage, cleanup).**

#### James — Multi-Event Workflows

- **As James, I want to upload 250 images from a wedding and have them automatically segmented into "ceremony," "reception," and "portraits" based on timestamp boundaries, so that I can work through distinct galleries without mixing contexts.**
- **As James, I want to quickly jump to the timeline view and click on a specific hour (e.g., "4:00 PM reception start") to see only images from that time window, so that I can find moments without scrolling through thumbnails.**
- **As James, I want to tag images with a star (favorite) while browsing, and later filter to show only starred images, so that I can separate best moments for delivery without needing formal tagging.**
- **As James, I want to apply the same tag (e.g., "cull") to multiple images at once by selecting a group and clicking a button, so that culling 50 rejected images takes seconds, not minutes.**

#### Lisa — Simple & Fast Workflows

- **As Lisa, I want to drag my 80 family photos into ProPhoto and immediately see them in a clean gallery view with large previews, so that I feel confident the upload is working.**
- **As Lisa, I want to click a star on my 5 favorite images, then filter to show only starred images, so that I quickly narrow down to photos I want to edit/share.**
- **As Lisa, I want to see my images organized by time taken (earliest to latest), so that I can quickly find photos from a specific moment in the shoot.**
- **As Lisa, I want a simple "upload and do nothing" experience for casual shoots, without being overwhelmed by tagging options.**

---

## Core Requirements

### Must-Have (P0) — Phase 1 Cannot Ship Without These

**P0.1 — Metadata Extraction**
- [ ] Frontend extracts EXIF from JPG, RAW (Canon CR2, Nikon NEF), TIFF, DNG before upload begins
- [ ] Extracted metadata includes: ISO, aperture, shutter speed, focal length, camera model, timestamp, location (GPS if available)
- [ ] Metadata extraction completes within 5 seconds for 100 images
- [ ] Users see a loading indicator: "Analyzing X images... Reading metadata and matching to your calendar"

**P0.2 — Calendar Matching**
- [ ] Backend accepts calendar OAuth (Google, Apple, Outlook) during setup
- [ ] On upload, system queries calendar for events within image timestamp window (±15 min, configurable)
- [ ] Matching algorithm scores events by confidence (time proximity weight 0.55, location 0.20, batch coherence 0.15, event type 0.10)
- [ ] Results displayed as: "We found matches! [Event A: 95%, Event B: 87%, Event C: 70%]"
- [ ] User can select one or multiple events, or skip calendar binding
- [ ] Confidence tiers: HIGH (≥85%), MEDIUM (55-84%), LOW (<55%); UI displays evidence for each tier
- [ ] System handles zero matches gracefully: "No calendar events found. Continue without calendar binding?"

**P0.3 — Gallery Ingest Interface**
- [ ] Upload begins immediately (background process)
- [ ] User enters interactive gallery view simultaneously with upload start
- [ ] Gallery layout: Left sidebar (thumbnail browser), center (large preview + metadata), right (tagging/filtering panel)
- [ ] Thumbnail browser shows all images with selection checkboxes
- [ ] Large preview displays image + EXIF metadata (ISO, aperture, shutter, focal length, camera model)
- [ ] Images load in gallery as thumbnails as upload progresses; user can begin tagging before upload completes
- [ ] Gallery displays selection count: "X of Y selected"
- [ ] Performance: Gallery interaction remains responsive (< 200ms response time) even while upload is active

**P0.4 — Immediate Tagging**
- [ ] Users can apply tags to individual images or bulk groups via checkboxes + tag button
- [ ] Tag types:
  - Metadata-derived (auto-populated): ISO 400, f/1.8, Canon 5D Mark IV, etc.
  - Calendar-derived (auto-populated): Client name, event date, location (if calendar matched)
  - User-defined: Free-form text or predefined library (Portrait, Candid, Posed, Cull, Favorite, Retouch, Approve, etc.)
- [ ] Tagging UI: Tag input field with autocomplete + "Create [tag]" option, tags appear as colored badges
- [ ] Bulk tagging: Select multiple images → apply tag to all; deselect → tag removed from all
- [ ] Tags persist across ingest session and sync to backend
- [ ] Users can remove tags by clicking X on badge

**P0.5 — Intelligent Filtering**
- [ ] Filter sidebar provides:
  - Metadata filters: Checkboxes for ISO values, apertures, focal lengths, camera models present in upload
  - Tag filters: Checkboxes for all applied tags
  - Timeline filter: Slider to select time window (e.g., "Show only images from 2:00–3:00 PM")
  - Cull filter: Toggle "Show culled images"
- [ ] Filters work immediately (< 100ms re-render) on thumbnail list
- [ ] Combined filters use AND logic: "ISO 400" AND "Favorite" AND "Portrait" shows only images matching all three
- [ ] "Clear filters" button resets to all images
- [ ] Filter state persists during ingest session

**P0.6 — Culling Interface**
- [ ] Users can mark images as "culled" (rejected) by clicking a red X icon on thumbnail or large preview
- [ ] Culled images show visual indicator (red overlay/strikethrough)
- [ ] "Show culled" toggle reveals/hides culled images; default hidden
- [ ] Culled images remain in upload but are excluded from gallery delivery unless explicitly included
- [ ] Users can uncull images by clicking the X again

**P0.7 — Background Upload**
- [ ] File transfer begins immediately after user selects calendar event (or opts to skip)
- [ ] Upload progress visible as: "Uploading 100 images... 45 complete" or similar progress bar
- [ ] Users can interact with gallery (tag, filter, cull) without blocking upload
- [ ] Upload persists if user closes ingest UI (resume on return or complete in background)
- [ ] If upload fails, clear error message: "Upload paused — [reason]. Retry? Cancel?"
- [ ] On completion: "Upload complete. Ready to proceed to [next action]"

**P0.8 — Metadata Display & Validation**
- [ ] Large preview shows EXIF metadata in readable format:
  ```
  ISO: 400 | f/1.8 | 1/1000s | 50mm
  Camera: Canon 5D Mark IV
  Timestamp: 2026-04-10 14:32:15
  Location: [GPS if available]
  ```
- [ ] If metadata is corrupt/missing, fallback to "Metadata unavailable" (no error state)
- [ ] Metadata is read-only in ingest (editable later in gallery settings if needed)

**P0.9 — Calendar Tab**
- [ ] After calendar match, "Calendar" tab shows:
  - Matched event details (title, date, time, location, any custom fields)
  - List of images associated with this event + count
  - Visual timeline showing upload duration vs. event duration
- [ ] Users can click calendar dates to see events and associated image counts
- [ ] Clicking an event filters to show only images from that event

**P0.10 — Charts Tab (Metadata Distributions)**
- [ ] "Charts" tab displays interactive charts:
  - ISO Distribution (bar chart, click to filter)
  - Aperture Distribution (bar chart)
  - Focal Length Distribution (bar chart)
  - Timeline (bar chart by hour/30-min window, click to filter)
  - Camera Model Distribution (pie/bar chart)
- [ ] Charts are clickable filters (click a bar → filter to those images)
- [ ] Charts update in real time as user applies/removes tags
- [ ] All charts are responsive and render within 1 second

**P0.11 — No New Metadata Extraction**
- [ ] Metadata extraction happens **only** in frontend (browser-side)
- [ ] Backend receives metadata as part of upload payload; does not re-parse
- [ ] Rationale: Speed (user sees results instantly) + privacy (metadata processed locally)

**P0.12 — Error Handling & Edge Cases**
- [ ] Large uploads (500+ images): Thumbnail browser stays responsive via virtualization
- [ ] Metadata-less files (e.g., screenshots): Handled gracefully; displayed without ISO/aperture
- [ ] Mixed file types (RAW + JPG): All shown in gallery; metadata extracted where available
- [ ] Network interruption during upload: Resume capability; clear communication of status
- [ ] Browser back/close during ingest: Warn user; option to resume on return

**P0.13 — Performance Targets**
- [ ] Metadata extraction: < 5 seconds for 100 images
- [ ] Calendar matching: < 3 seconds for 100 images
- [ ] Gallery render: < 2 seconds initial; responsive interactions < 200ms
- [ ] Filter/tag application: < 100ms re-render
- [ ] Upload: Parallel file transfer; no blocking on UI

---

### Nice-to-Have (P1) — High-Priority Fast Follows

**P1.1 — Batch Feedback & Learning Loop**
- [ ] At end of ingest, system asks: "How accurate was calendar matching?" (Yes/No/Partial)
- [ ] User feedback trains matching algorithm
- [ ] Accuracy improvements reflected in future uploads for same photographer

**P1.2 — GPS/Location Fallback**
- [ ] If calendar event has no time match but GPS coordinates are close (< 500m), suggest weak match
- [ ] User can confirm location-based match if useful

**P1.3 — Time Window Visualization**
- [ ] Timeline shows two ranges: event time window (colored band) and actual image timestamps (dots)
- [ ] User can adjust time window dynamically via slider before confirming match

**P1.4 — Manual Calendar Event Creation**
- [ ] During ingest, user can create a new calendar event: "I didn't plan this shoot; create a calendar entry"
- [ ] Event details auto-populated from image timestamps and location
- [ ] Event saved to calendar + images bound to it

**P1.5 — Async Job Visibility**
- [ ] If background upload takes >30 seconds, show status: "Uploading in background... [resume/close]"
- [ ] Allow user to continue work or check status later
- [ ] Notification when upload completes

**P1.6 — Smart Tag Suggestions**
- [ ] Based on metadata + calendar context, suggest tags: "These are all portraits (50mm+ focal length). Apply 'portrait' tag?"
- [ ] Based on user's tagging history, suggest reusable tags

**P1.7 — Saved Filter Presets**
- [ ] Users can save filter combinations: "Portrait filter" = (ISO < 3200) AND (focal length > 50mm) AND (f/1.4–f/2.8)
- [ ] One-click access to favorite filters

**P1.8 — Keyboard Shortcuts**
- [ ] Arrow keys: Navigate thumbnails
- [ ] Space: Toggle favorite (star)
- [ ] X: Mark cull
- [ ] C: Clear filters
- [ ] T: Focus tag input

---

### Future Considerations (P2) — Architectural Insurance

**P2.1 — AI-Powered Image Analysis**
- [ ] Automatic pose detection (standing, sitting, group, solo)
- [ ] Expression detection (smiling, neutral, candid)
- [ ] Composition scoring (rule of thirds, leading lines)
- [ ] Scene classification (indoor, outdoor, landscape, portrait)
- Deferred to Phase 4

**P2.2 — Duplicate Detection**
- [ ] System flags potential duplicates (near-identical shots within 2 seconds)
- [ ] Users can bulk-remove duplicates with one click
- Deferred to Phase 2

**P2.3 — Outlook & Apple Calendar Support**
- [ ] Currently: Google Calendar only
- [ ] Expand to Outlook, iCloud, CalDAV
- Deferred to Phase 2

**P2.4 — Multi-Session Batch Upload**
- [ ] Users can select multiple uploads from previous sessions and batch-process tags/decisions
- Deferred to Phase 3

**P2.5 — Geolocation History Learning**
- [ ] Track locations where photographer works; improve matching for recurring venues
- Deferred to Phase 4

---

## Technical Integration Notes

### No Changes to Existing Architecture Required

- **SessionMatchingService** remains unchanged; ingest flow uses existing scoring algorithm
- **Asset ownership** still follows prophoto-assets package model
- **Event system** still drives event-driven architecture
- **Multi-tenancy** still studio-scoped

### New Components

- **Metadata Extractor (Frontend)** — Browser-native EXIF parsing (exifjs or similar)
- **Calendar Matcher (Backend)** — Uses existing SessionMatchingService; returns ranked candidates
- **Gallery Ingest UI** — React components for thumbnail browser, preview, tagging, filtering, charts
- **Upload Manager** — Handles background file transfer + session tracking
- **Filter Engine** — Real-time filtering on client side (performance)

### Database

- **No new tables required**
- Uses existing: `asset_session_assignments`, `asset_session_assignment_decisions`, `assets`, `galleries`, `images`, `photo_sessions`, `asset_session_contexts`

### API Endpoints (New or Modified)

- `POST /api/ingest/match-calendar` — Takes image metadata array + user studio ID; returns ranked calendar matches
- `POST /api/ingest/upload` — Receives file payload + session ID + applied tags
- `GET /api/ingest/status/{sessionId}` — Returns upload progress, tag status, filtering state
- `POST /api/ingest/confirm-session` — User confirms session match; triggers SessionAssociationResolved event

---

## Success Metrics

### Leading Indicators (Measured within 1-2 weeks of Phase 1 launch)

- **Metadata Extraction Success Rate**: % of uploads where EXIF is extracted successfully
  - Target: ≥95% for JPG/RAW, ≥80% for TIFF
- **Calendar Matching Accuracy**: % of auto-matched sessions user accepts without change
  - Target: ≥80% for uploads from users with active calendars
- **Feature Adoption**: % of users who use filtering/tagging in first ingest session
  - Target: ≥70%
- **Time to Ingest**: Duration from upload completion to first tag applied
  - Target: <2 minutes median, <5 minutes p95
- **Tag Count per Session**: Average number of tags applied per upload
  - Target: ≥8 tags per session (mix of metadata, calendar, user-defined)

### Lagging Indicators (Measured over 4-8 weeks)

- **Retention Impact**: Do users who complete ≥1 ingest session have higher retention?
  - Target: +15% retention vs. control
- **Gallery Creation Rate**: % of completed ingest sessions that result in gallery delivery
  - Target: ≥60%
- **User Confidence**: NPS on ingest experience
  - Target: ≥40 (strong)
- **Support Ticket Reduction**: Decrease in "How do I organize photos?" support inquiries
  - Target: -40% from baseline
- **Workflow Speed**: Actual time saved vs. manual organization (derived from user surveys)
  - Target: 45+ minutes saved per 50-shoot cycle

---

## Open Questions

### Blocking (Must answer before implementation starts)

- **Calendar OAuth Integration**: Which calendar providers are in scope for MVP? (Google only, or Apple/Outlook too?)
  - *Owner: Product + Engineering*
- **File Format Support**: Which RAW formats must we support in MVP? (Canon CR2 + Nikon NEF only, or Sony, Fuji, etc.?)
  - *Owner: Engineering*
- **GPS Data Handling**: If image contains GPS coordinates, how do we match against calendar location? What's an acceptable distance threshold (100m, 500m, 1km)?
  - *Owner: Engineering + Product*

### Non-Blocking (Can resolve during implementation)

- **Tag Autocomplete Database**: Should tag suggestions be global (all users' tags) or studio-scoped (this studio's tags)?
  - *Owner: Product*
- **Bulk Upload Size Limits**: What's the max file count per upload? (100, 500, 1000, unlimited?)
  - *Owner: Engineering*
- **Metadata Privacy**: Should we log/store extracted metadata on backend, or discard after matching?
  - *Owner: Security + Privacy*
- **Failed File Handling**: If a single file fails during upload, do we fail the entire session or skip that file and continue?
  - *Owner: Engineering*

---

## Timeline & Phasing

### Phase 1a (Weeks 1-3) — Metadata Extraction + Calendar Matching

**Deliverables:**
- Metadata extraction working end-to-end (frontend + backend)
- Calendar OAuth integrated (Google Calendar MVP)
- Calendar matching algorithm wired into ingest flow
- Upload begins; basic progress indicator

**Dependencies:**
- Calendar OAuth setup (Google Cloud console access)
- Frontend EXIF library selection (exifjs or similar)

### Phase 1b (Weeks 4-6) — Gallery Ingest UI + Tagging

**Deliverables:**
- Gallery layout (thumbnail browser, preview, tagging panel)
- Tagging interface (metadata-derived, calendar-derived, user-defined)
- Bulk tagging and filtering
- Charts tab (metadata distributions)

**Dependencies:**
- Phase 1a completion
- Design system components finalized

### Phase 1c (Weeks 7-9) — Refinement + Testing + Launch

**Deliverables:**
- Performance optimization (metadata extraction < 5s, gallery render < 2s)
- Error handling (network failures, corrupt metadata, edge cases)
- User testing and iteration
- Documentation + support materials

**Estimated total Phase 1 effort: 8-12 weeks**

---

## Reference Implementation

See archived `/Sites/prophoto/_archive/ingest-pro/` for V0 UI prototype:
- `components/thumbnail-browser.tsx` — Left sidebar image browser
- `components/image-preview.tsx` — Center preview + metadata display
- `components/tags-tab.tsx` — Tagging interface
- `components/calendar-tab.tsx` — Calendar context tab
- `components/filter-sidebar.tsx` — Filter controls
- `app/page.tsx` — Gallery layout orchestration

---

## Success Criteria Checklist

### At Phase 1 Launch

- [ ] ≥95% metadata extraction success rate for JPG/RAW
- [ ] ≥80% calendar matching accuracy (user confirms auto-match without change)
- [ ] Gallery ingest UI renders in <2 seconds
- [ ] Upload in background while user tags
- [ ] Filtering/tagging responsive (<100ms)
- [ ] All P0 requirements implemented and tested
- [ ] User testing: ≥5 photographers, positive feedback on speed/usability
- [ ] 100% test coverage on calendar matching algorithm
- [ ] Documentation complete (user guide + admin calendar setup)

### 4 Weeks Post-Launch

- [ ] ≥70% of users use filtering/tagging in first session
- [ ] ≥80% of users with active calendars accept auto-match
- [ ] NPS ≥40 on ingest experience
- [ ] Support tickets <5% of baseline

---

## Appendix A: Confidence Scoring Algorithm

**Inputs:**
- Image timestamp(s) (first + last image)
- Image location (GPS, if available)
- Calendar event(s) within time window

**Scoring:**

```
confidence = (
  (timeDist score × 0.55) +
  (locationDist score × 0.20) +
  (batchCoherence score × 0.15) +
  (eventTypeAlignment score × 0.10)
)
```

- **timeDist score**: How close are image timestamps to event start/end? Max 1.0 if within 15 min, decays beyond
- **locationDist score**: How close is image location to event location? Max 1.0 if <100m, 0.5 if <500m, decays beyond
- **batchCoherence score**: Do all images in batch cluster around same timestamp/location? Higher score if tight cluster
- **eventTypeAlignment score**: Does event type match inferred shoot type (portrait, product, event)? 0.5–1.0 based on match

**Output**: Confidence percentage (0–100%) + tier (HIGH/MEDIUM/LOW)

---

## Appendix B: User Stories Acceptance Criteria

### Story: Sarah Uploads 150 Product Photos

**Given:** Sarah has uploaded 150 JPG images from "Acme Corp Catalog Shoot" event (09:00–13:30, March 15, 2026)  
**When:** ProPhoto extracts metadata and queries calendar  
**Then:**
- [ ] Metadata extraction completes within 5 seconds
- [ ] Calendar shows match: "Acme Corp Catalog Shoot — 95% confidence"
- [ ] Sarah clicks "Continue with Acme Corp Catalog Shoot"
- [ ] Upload begins; gallery view loads simultaneously
- [ ] Sarah immediately sees all 150 images as thumbnails
- [ ] Sarah can apply tags while upload is active
- [ ] Upon completion, gallery displays "Upload complete. 150 images ready."

---

## Appendix C: Known Limitations (Phase 1)

1. **Single-event uploads only** — If images span multiple calendar events, user must choose one or skip. Multi-event splitting is P1.
2. **Calendar OAuth with one provider** — Google Calendar MVP. Apple/Outlook in Phase 2.
3. **Read-only metadata** — EXIF data cannot be edited in ingest; editing deferred to gallery settings.
4. **No RAW rendering** — Thumbnails only; full resolution preview deferred pending performance optimization.
5. **No face detection** — AI analysis (faces, poses, expressions) is Phase 4.
6. **Batch operations limited to tagging** — Future: bulk edits, bulk exports.

---

**End of Phase 1 Epic PRD**
