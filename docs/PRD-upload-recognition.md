# Upload Recognition Moment: Automatic Session Detection
## Feature Product Requirements Document

**Version:** 1.0  
**Date:** April 10, 2026  
**Status:** Phase 1 – Ready for Engineering  
**Feature Owner:** Dave Peloso  
**Related to:** Story 01 (Upload Recognition Moment), Project-Level PRD

---

## Executive Summary

The Upload Recognition Moment is the **defining user experience** of ProPhoto. A photographer uploads 200+ files from a shoot. Within 2 seconds, ProPhoto recognizes the session ("This looks like the Alma Mater Footwear Lifestyle Shoot on April 9") and suggests "Ready to review? [Yes] [No] [Reassign Session]."

This feature solves the $12k-$78k annual productivity loss that photographers face manually organizing files. It is also the **foundation** for all downstream features—proofing, delivery, and intelligence are only valuable with accurate session context.

**Success definition:** ≥90% accuracy when calendar context exists. ≥70% without. <2 second response time. ≥70% user confirmation rate (photographers trust and accept the suggestion).

**Technical scope:** Leverage existing prophoto-ingest session matching engine (deterministic scoring, tested). Build new: upload trigger flow, confidence display UI, user confirmation + override handling, session context flow to gallery.

---

## User Problem

### The Photographer's Moment of Pain
After a shoot, a photographer imports 247 RAW files from a day that included two sessions:
- 9:00 AM: Alma Mater Footwear Lifestyle (200 files)
- 2:00 PM: Local Nonprofit Gala (47 files)

**Current workflow (manual):**
1. Import files to computer (5 min)
2. Open file manager, manually rename/move by session (30 min)
3. Create gallery for each session in ProPhoto (10 min)
4. Upload gallery to client (5 min)
**Total: ~50 minutes per shoot. For 2 shoots/day × 250 working days = 125 hours/year = $12,500 in lost billable time.**

**With ProPhoto (auto-matching):**
1. Import files via upload dialog
2. System recognizes both sessions automatically
3. Confirm each session (30 seconds)
4. Galleries created automatically
**Total: 5 minutes per shoot. Time saved: ~120 hours/year = $12,000 in recovered time.**

### Who Experiences This Problem
- **High-volume commercial photographers:** 50+ shoots/year. Pain is acute. Time cost is highest.
- **Multi-session shooters:** Corporate events, conferences, weddings with multiple sessions. File organization is complex.
- **Time-bound deliveries:** When photographer needs to deliver to client within 24 hours, organization is the bottleneck.

### Why This Matters
This is not a nice-to-have convenience feature. This is the **core value proposition** of ProPhoto. Without automatic recognition, photographers must manually organize before any downstream processing (proofing, intelligence, delivery) can happen. Every competitor still makes photographers do this manually.

---

## Goals

### Primary Goal
**Enable photographers to go from upload completion to ready-for-review in <5 minutes, saving 2-4 hours per shoot day.**

Success metrics:
- Adoption: ≥70% of uploads use auto-matched sessions (vs. manual entry)
- Time saved: Measured via photographer feedback and usage patterns
- Confidence: ≥70% of suggested matches are confirmed by photographer (photographer trusts the system)

### Secondary Goals
1. **Establish ProPhoto as the "magic moment" photographers remember**
   - NPS for this feature alone: ≥8/10
   - Social proof: "I uploaded and it just knew which shoot it was"

2. **Reduce friction to adoption of downstream features**
   - Photographers who use auto-match → gallery creation immediately after
   - Session context available for proofing, payment, intelligence phases

3. **Gather data to improve matching algorithm over time**
   - Photographer feedback on matches (confirm/reject/reassign with reason)
   - Data feeds back into matching scoring (GPS accuracy, time window calibration, batch coherence patterns)

---

## User Stories

### Persona 1: High-Volume Commercial Photographer (Primary)
**"Sarah" — Corporate event photographer, 30+ shoots/month, $80k/year revenue**

- "As Sarah, I want ProPhoto to automatically recognize which session my uploaded files belong to, so that I can spend 5 minutes confirming matches instead of 45 minutes manually organizing."

- "As Sarah, I want to see WHY ProPhoto matched files to a session (time range, GPS location, calendar event, batch coherence), so that I can quickly assess whether the match is correct."

- "As Sarah, when ProPhoto is unsure (low confidence), I want to see multiple suggestions and pick the right one, so that I am not locked into a wrong match."

- "As Sarah, when ProPhoto gets it wrong, I want to quickly reassign files to a different session, so that I can correct the match without starting over."

- "As Sarah, I want ProPhoto to learn from my feedback (confirmations, rejections, reassignments), so that matching improves over time for my specific workflow."

### Persona 2: Multi-Session Shooter (Secondary)
**"James" — Wedding photographer, 2-3 shoots/week, $50k/year revenue**

- "As James, I want ProPhoto to split files from a single upload into multiple sessions when I shoot multiple events in one day, so that galleries are organized correctly without extra work."

- "As James, I want ProPhoto to use my Google Calendar (when it's synced), so that it knows about morning ceremony + evening reception and can split files accordingly."

- "As James, when I shoot a session not on my calendar (last-minute shoot, referred client), I want to create a new session on the fly during upload, so that I don't lose context."

### Persona 3: Casual Part-Time Photographer (Tertiary)
**"Lisa" — Part-time lifestyle photographer, 5-10 shoots/month, $20k/year revenue**

- "As Lisa, I want ProPhoto to work even when I don't have my calendar synced, so that I can still benefit from automatic organization."

- "As Lisa, I want the upload process to be simple — drag and drop files, let ProPhoto figure it out — so that I don't need to configure anything."

---

## Feature Scope and Behavior

### Core User Flow

**Step 1: Upload Initiation**
- Photographer opens ProPhoto gallery app
- Clicks "Upload New Session" or drags files into drop zone
- File picker or drag-drop shows file count
- Upload begins (async, photographer can close browser if needed)

**Step 2: Automatic Recognition (Backend)**
- System receives upload, extracts metadata from each file (EXIF timestamp, GPS, filename patterns)
- Queries available sessions from prophoto-booking (today, +/- 1 day, considering travel buffers)
- Runs SessionMatchingService for each file/session combination
  - Scoring: Time proximity (0.55 weight), location (0.20), batch coherence (0.15), operational context (0.10)
  - Produces candidates with confidence tier (HIGH ≥0.85, MEDIUM 0.55-0.84, LOW <0.55)
- Groups files by best-match session
- Classifies outcome: auto_assign (HIGH confidence, no conflicts) | propose_for_review (MEDIUM or HIGH with contention) | no_match (LOW confidence)

**Step 3: Display Recognition Results (UI)**
Depending on outcome classification:

**Outcome A: Auto-assign (HIGH confidence, no conflicts)**
- Display: "✓ Recognized 247 files as Alma Mater Footwear Lifestyle, April 9"
- Show evidence: "Time: 9:00 AM - 1:30 PM | Location: [City] | Calendar: Session confirmed"
- Action buttons: [Create Gallery] [Reassign] [Manual Review]
- Default: Proceed to create gallery

**Outcome B: Propose for review (MEDIUM confidence or multiple suggestions)**
- Display: "Found matching sessions. Please confirm:"
- List top 3 candidates with confidence score and evidence:
  - "📌 Alma Mater Lifestyle (April 9, 9-1:30 PM) — High confidence: Time match, location, calendar"
  - "❓ Alma Mater Indoor Portraits (April 9, 2-4 PM) — Medium confidence: Time window overlaps, same location"
  - "🆕 Create new session…"
- Evidence summary for each:
  - Time delta, buffer class (in window vs. in buffer), location distance
  - Calendar event name, GPS proximity
  - Batch coherence ("247 files uploaded together")
- Action buttons: [Confirm Session] [Pick Different] [Create New]

**Outcome C: No match (LOW confidence)**
- Display: "Couldn't automatically recognize these files. Let's create a session:"
- Suggest: "When were these taken? [date picker] [time range] Location? [address search]"
- Allow: Quick create → gallery creation
- Option: "Find existing session" (photographer manually searches)

### Manual Override Capability
If photographer clicks "Reassign" or "Create New" at any point:
- Modal opens to search existing sessions or create new
- Session search: By date, session name, client name
- Create new: Date, time window, location, session name
- On confirm: Re-run matching for reassigned files, create/update session context
- Persist decision: Log as manual override with reason (optional user feedback: "wrong time window", "multiple sessions in upload", "new client", etc.)

### Confidence Display (Transparency)
When photographer hovers over a suggestion or taps "Why this session?":
- Show matching evidence breakdown:
  - "⏰ Time: Files from 9:05-1:47 PM. Session window 9:00-1:30 PM (+/- 15 min buffer). Score: 0.85"
  - "📍 Location: Files geotagged to [Lat/Lng]. Session location [Lat/Lng]. Distance: 0.2 mi. Score: 0.90"
  - "📦 Batch coherence: 247 files uploaded together. Session expected 150-300 files. Score: 0.75"
  - "📅 Calendar: Google Calendar event 'Alma Mater Footwear Lifestyle' confirmed for this time. Score: 1.0"
  - "🎯 Overall confidence: 0.90 (HIGH)"

### Session Context Flow to Gallery
Once photographer confirms session(s):
- prophoto-ingest emits SessionAssociationResolved event
- prophoto-assets creates AssetSessionContext projection for each asset
- Emits AssetSessionContextAttached event
- prophoto-gallery listener creates/updates gallery with session context
  - Gallery inherits session name, date, location, client (from booking)
  - Image metadata (GPS, timestamp, exif) attached to each image
  - Ready for photographer to review, caption, and share

### Metadata Extraction (Dependency)
For matching to work, system must extract:
- **EXIF timestamp** (capture time) — primary time signal
- **GPS coordinates** (latitude, longitude, altitude) — primary location signal
- **File properties** (create/modify time fallback) — secondary time signal
- **Filename patterns** (if photographer uses naming conventions) — tertiary signals

Currently using NullAssetMetadataExtractor (placeholder). Must implement real extractors for:
- JPEG: EXIF metadata extraction (dcraw, exiftool, or PHP-exif library)
- RAW formats: Canon (CR2), Nikon (NEF), Sony (ARW) — likely require external tool (Exiftool)
- Video: ffmpeg metadata, MP4/MOV properties
- Fallback: File modification time, filename analysis

### Performance Requirements
- **Response time:** <2 seconds from upload completion to recognition suggestion (p95)
  - Current: synchronous matching in SessionMatchingService
  - Scaling: May require async queue (Laravel queue, Redis) for high-volume uploads (100+ files at once)
- **Upload time:** Not dictated by this feature; depends on file size and network
- **Database queries:** Minimize N+1 queries; use eager loading for session/booking context

---

## Non-Goals

1. **Real-time matching during upload (not required)**
   - Non-goal: Show matches before upload completes
   - Rationale: Adding complexity, diminishing returns on UX
   - Alternative: Match after upload completes; still deliver <2 second response

2. **ML/AI-based matching (not yet)**
   - Non-goal: Train custom models on photographer's historical matches
   - Rationale: Deterministic matching is proven, fast, interpretable; ML adds complexity
   - Phasing: Consider in phase 3+ if deterministic approach has accuracy ceiling

3. **Cross-photographer deduplication (not in scope)**
   - Non-goal: Detect when multiple photographers shot the same session
   - Rationale: Rare, complex privacy/permission model; out of scope for v1
   - Use case: Future feature if studios do multi-photographer sessions

4. **Automatic gallery creation and publishing (not in this feature)**
   - Non-goal: Auto-create and share gallery with client without review
   - Rationale: Photographer must review, select, caption before client sees
   - Alternative: Gallery creation available immediately after confirmation; photographer reviews before sharing

5. **Video file handling (initial MVP)**
   - Non-goal: Support video uploads in phase 1
   - Rationale: Metadata extraction complexity; start with photos (JPEG, RAW)
   - Phasing: Add video support in phase 2 if demand is clear

---

## Requirements by Priority

### MUST-HAVE (P0 — Cannot launch without)

1. **Automatic session recognition on upload completion**
   - Behavior: Upload completes → backend matches files to sessions → UI displays suggestions
   - Acceptance criteria:
     - Files matched to sessions with ≥55% confidence score (MEDIUM or above)
     - Matching completes within 2 seconds (p95) for 50-300 files
     - Decision persists to asset_session_assignments table (ingest ownership)

2. **Session matching accuracy ≥90% with calendar context, ≥70% without**
   - Behavior: When calendar sync is available, matching includes calendar event matching (highest weight)
   - Acceptance criteria:
     - False positive rate: <5% (wrong session assigned and photographer catches it)
     - False negative rate: <10% (session exists but not matched, photographer must create)
     - Tested against 10+ real photographer workflows (variety of time windows, locations, batch sizes)

3. **Confidence display showing evidence (time, location, calendar, batch)**
   - Behavior: Photographer can see why a session was suggested
   - Acceptance criteria:
     - Evidence includes: Time window, location/distance, calendar event name, batch coherence score
     - Evidence is readable (not technical jargon; photographer understands)
     - Evidence updates when photographer hovers/taps "Why this session?"

4. **User confirmation flow (confirm/reject/reassign)**
   - Behavior: Photographer confirms suggested session, or rejects to pick different/create new
   - Acceptance criteria:
     - Confirmation persists match decision (SessionAssociationResolved event)
     - Rejection allows photographer to search/create alternative session
     - Manual override is logged for feedback/improvement

5. **Session context flows to gallery automatically**
   - Behavior: After confirmation, gallery inherits session name, date, location, client
   - Acceptance criteria:
     - Gallery reflects session context immediately after confirmation
     - Client reference and session metadata available for proofing, invoicing phases
     - No manual re-entry of session info

6. **Metadata extraction for 90% of common file types**
   - Behavior: EXIF, GPS, timestamps extracted from uploaded files
   - Acceptance criteria:
     - JPEG support: 100% (standard EXIF)
     - RAW support: Canon (CR2), Nikon (NEF), Sony (ARW) ≥95% of test files
     - Video: Defer to phase 2
     - Fallback: If metadata missing, use time-based matching only

7. **Multi-session handling in single upload**
   - Behavior: When upload contains files from multiple sessions (e.g., morning + afternoon shoot), system splits automatically
   - Acceptance criteria:
     - Files grouped by matched session before display
     - Photographer sees "File 1-200: Session A | File 201-247: Session B"
     - Each group can be confirmed/modified independently

8. **No changes to prophoto-ingest matching logic**
   - Behavior: Use existing SessionMatchingService as-is; no modifications to scoring, ranking, decision classification
   - Acceptance criteria:
     - SessionMatchingService tests remain green
     - Existing ingest clients (other packages, future features) unaffected
     - Matching algorithm is deterministic and reproducible

### SHOULD-HAVE (P1 — High priority, likely in v1 but not hard stop)

1. **Batch feedback / learning loop**
   - Behavior: System learns from photographer's confirmations, rejections, reassignments
   - Acceptance criteria:
     - Manual overrides logged with metadata (why: wrong session, new client, time wrong, etc.)
     - Data available for analysis to improve thresholds (e.g., "this photographer's sessions often have longer travel buffer")
     - Not yet automated, but data foundation ready

2. **Fallback to GPS + timestamp matching when calendar unavailable**
   - Behavior: When photographer hasn't synced calendar, matching still works using time + location
   - Acceptance criteria:
     - Accuracy drops from 90% to 70% (acceptable degradation)
     - System gracefully handles missing metadata (no crashes)
     - Photographer understands why accuracy is lower (transparency)

3. **Time window visualization (calendar, time range)**
   - Behavior: Photographer sees calendar event visualization and time window when confirming
   - Acceptance criteria:
     - Calendar event name, start/end time displayed
     - Visual timeline showing file timestamps vs. session window
     - Helps photographer understand matching logic

4. **Manual session creation during upload flow**
   - Behavior: If no match and photographer wants to create session on the fly, allowed without leaving upload flow
   - Acceptance criteria:
     - "Create new session" flow available directly in upload confirmation
     - Photographer enters: session name, date, time window, location
     - New session created in prophoto-booking and matched immediately

5. **Upload progress and async job visibility**
   - Behavior: For large uploads, show progress and allow photographer to leave page
     - Acceptance criteria:
     - Upload queued to async job (Laravel queue)
     - Photographer gets notification when complete
     - Completion link takes them to confirmation screen

### COULD-HAVE (P2 — Nice to have, v1+ fast follow)

1. **Photographer analytics dashboard (time saved, accuracy stats)**
   - Behavior: Dashboard shows cumulative time saved, accuracy metrics, matching trends
   - Acceptance criteria:
     - Time saved per shoot calculated and displayed
     - Accuracy metrics (% confirmed, % rejected, % reassigned) tracked
     - Feeds into photographer education and product stickiness

2. **Calendar sync with Outlook / Apple Calendar (in addition to Google)**
   - Behavior: Support other calendar providers
   - Acceptance criteria:
     - OAuth with Outlook, Apple Calendar working
     - Synced events used in matching algorithm
     - Fallback graceful if sync fails

3. **Duplicate detection within upload**
   - Behavior: Warn if upload contains duplicate files from previous uploads
   - Acceptance criteria:
     - Hash-based comparison of files
     - Warn before confirming session match
     - Allow photographer to skip duplicates

4. **Geolocation history learning (for photographers without GPS)**
   - Behavior: Over time, system learns photographer's favorite locations and suggests based on history
   - Acceptance criteria:
     - Track approved locations per photographer
     - Suggest "usual location" if no GPS available
     - Improve over time with photographer feedback

---

## Technical Implementation Notes

### Architecture Integration
- **Entry point:** Gallery upload route (`POST /api/v1/gallery/upload` or similar)
- **Workflow:**
  1. Upload handler receives files, extracts metadata (prophoto-assets handles extraction)
  2. For each file, calls prophoto-ingest SessionMatchingService
  3. Groups results by session, classifies outcome (auto_assign / propose / no_match)
  4. Returns to client with suggestions
  5. Client displays confirmation UI
  6. Photographer confirms → emits SessionAssociationResolved
  7. prophoto-assets listener creates AssetSessionContext
  8. prophoto-gallery listener updates/creates gallery with context

- **Packages involved:**
  - prophoto-ingest: SessionMatchingService (existing, no changes)
  - prophoto-assets: Metadata extraction, asset creation, listener for context projection
  - prophoto-gallery: Listener for gallery creation/update on AssetSessionContextAttached
  - prophoto-booking: Already provides sessions for matching

### Database Considerations
- **No new tables required.** Uses existing:
  - asset_session_assignments (ingest owns)
  - asset_session_assignment_decisions (ingest owns, append-only)
  - assets, asset_session_contexts (assets owns)
  - galleries, images (gallery owns)
  - photo_sessions (booking owns)

- **Indexes critical for performance:**
  - asset_session_assignments: (asset_id, effective_state) for latest lookup
  - photo_sessions: (studio_id, session_status, session_start, session_end) for time-based queries
  - asset_metadata_normalized: (asset_id, captured_at, gps) for time/location lookups

### Testing Strategy
- **Unit tests:**
  - SessionMatchingService scoring logic (already exists, keep passing)
  - Metadata extraction per file type
  - Outcome classification (auto_assign / propose / no_match)

- **Integration tests:**
  - Upload → matching → confirmation flow
  - Multi-session detection in single upload
  - Event emission and listener reaction (assets, gallery)
  - Session context propagation

- **End-to-end tests:**
  - With 3 reference photographers
  - Real uploads (JPEG + RAW), real calendar sync
  - Edge cases: Timezone mismatches, GPS gaps, multi-day shoots

### Error Handling & Graceful Degradation
- **File extraction fails for a file:** Skip that file, continue matching others. Notify photographer.
- **Calendar sync fails:** Fall back to time + location matching.
- **Metadata missing (no GPS, no EXIF):** Use fallback time-based matching only.
- **No matching session found:** Show "no match" outcome, allow manual creation.
- **Network timeout during matching:** Queue job for retry; notify photographer when complete.

---

## Success Criteria (Acceptance)

Feature is complete and ready to hand off to product when:

1. ✓ ≥90% matching accuracy with calendar context (measured against 10+ reference photographers)
2. ✓ ≥70% matching accuracy without calendar context
3. ✓ <2 second response time (p95) for 50-300 file uploads
4. ✓ <5% false positive rate (files matched to wrong session)
5. ✓ Confidence display shows evidence (time, location, calendar, batch)
6. ✓ Manual override flow (reassign, create new) works and persists
7. ✓ Session context flows to gallery automatically post-confirmation
8. ✓ Metadata extraction works for 90% of common file types (JPEG, RAW formats)
9. ✓ Multi-session uploads handled (files split by matched session)
10. ✓ No changes to existing prophoto-ingest matching logic
11. ✓ Test coverage ≥70% for new upload/matching flow code
12. ✓ Three reference photographers have tested and approved flow
13. ✓ Documentation complete (API contracts, data models, flow diagrams)
14. ✓ Performance benchmarks met under load (100 concurrent uploads)
15. ✓ Error handling tested (missing metadata, timezone mismatches, network failures)

---

## Risks & Mitigations

### Technical Risks
| Risk | Impact | Mitigation |
|------|--------|-----------|
| Metadata extraction fails for some RAW formats | Accuracy drops without extracted data | Support common formats first (Canon, Nikon); gracefully handle missing metadata |
| Matching response time > 2 seconds at scale | UX degraded for large uploads | Pre-optimize queries; implement async processing if needed; test with 1000-file uploads |
| Calendar sync unreliable (API issues, permissions) | Fallback to time-only matching (accuracy drops) | Robust error handling; clear error messages; test with real Calendar APIs |
| GPS coordinates missing or inaccurate | Location-based scoring fails | Weight time matching higher when GPS unavailable; graceful degradation |

### Product Risks
| Risk | Impact | Mitigation |
|------|--------|-----------|
| Photographers don't trust auto-matching | Low adoption, continue manual organization | Display evidence transparently; start with high confidence only; allow easy override |
| False positive (wrong session matched) | Photographer uses wrong gallery, creates confusion | Keep false positive rate <5%; easy reassign; test against diverse photographer workflows |
| Edge case: Photographer shoots same location at same time on different days | Matching ambiguity | Design confidence display to help photographer disambiguate; manual override available |

---

## Appendix: Data Flow Diagram

```
Photographer uploads 247 files
    ↓
Upload handler receives, extracts metadata (EXIF, GPS, timestamp)
    ↓
For each file, call SessionMatchingService:
  - Time score (0.55): File timestamp vs session time window
  - Location score (0.20): GPS vs session location
  - Batch score (0.15): File batch coherence
  - Operational score (0.10): Calendar event, session status
    ↓
Group files by best-match session:
  - Alma Mater Lifestyle (HIGH confidence, no conflicts) → auto_assign
  - Local Nonprofit Gala (MEDIUM confidence, secondary option) → propose
    ↓
Classify outcome: auto_assign / propose / no_match
    ↓
Return to client UI:
  - Outcome A: Display "✓ Recognized 200 files as Alma Mater Lifestyle"
  - Outcome B: Display "Found matches. Please confirm:" with top 3 candidates
    ↓
Photographer clicks [Confirm Alma Mater] [Confirm Local Nonprofit]
    ↓
Emit SessionAssociationResolved event (x2)
    ↓
prophoto-assets listener creates AssetSessionContext for each asset
Emit AssetSessionContextAttached event
    ↓
prophoto-gallery listener creates/updates galleries with session context
    ↓
UI shows "Gallery ready! [Review] [Share with Client]"
```

---

## Related Documents
- Project-Level PRD: `PRD-project-level.md`
- Story 01: Upload Recognition Moment (original narrative)
- SYSTEM.md: Architecture overview
- INGEST-SESSION-ASSOCIATION-DATA-MODEL.md: Session matching persistence model
- BOOKING-DATA-MODEL.md: Session truth model

---

**Document version history:**
- v1.0 (2026-04-10): Feature PRD from Phase 2A analysis, ready for engineering breakdown
