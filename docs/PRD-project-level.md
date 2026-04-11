# ProPhoto: Intelligent Photography Workflow Platform
## Project-Level Product Requirements Document

**Version:** 1.0  
**Date:** April 10, 2026  
**Status:** Phase 2 Strategic Foundation  
**Owner:** Dave Peloso

---

## Executive Summary

ProPhoto is a modular, event-driven photography operations platform designed to eliminate post-shoot decision fatigue through intelligent session-to-asset matching. Unlike every competitor in the $720M+ photography software market, ProPhoto automatically recognizes which session a photographer's uploaded images belong to—saving photographers 15-20% of their working time ($12,000–$78,000 annually per photographer in lost productivity).

**The defensible differentiator:** Automatic session matching driven by metadata, timestamps, GPS context, and calendar information. Every competitor (Pixieset, ShootProof, Sprout Studio, Aftershoot) still requires manual file organization before any downstream processing. ProPhoto makes this automatic.

**Target market:** High-volume commercial photographers (corporate events, conferences, headshots, lifestyle) where file volume and decision fatigue are highest.

**Strategic vision:** Become the infrastructure layer that photographers trust for the entire post-capture workflow: automatic organization → intelligent culling/selection → client proofing → delivery → payment collection.

---

## Market Context

### Market Size and Opportunity
- **Total addressable market (2025):** $720M
- **Projected growth (2030):** $1.36B
- **CAGR:** 13.56%
- **Key growth drivers:** Increased demand for AI-assisted workflows, shift from one-time vendors to integrated platforms, subscription economics

### Target Segment
**Primary:** Commercial photographers with 100+ shoots/year
- Corporate/event photographers (highest pain point: file volume)
- Conference photographers (time-sensitive, high volume)
- Headshot studios (repetitive sessions, high throughput)

**Why this segment:**
- Highest pain from manual organization (20-50 hours/month for high-volume studios)
- Most receptive to automation and workflow integration
- Fastest decision cycles (need to deliver within days)
- Highest revenue per photographer (can afford subscriptions)

### Competitive Landscape
**Tier 1 (Gallery delivery):** Pixieset, ShootProof, Pic-Time
- Strengths: Beautiful gallery, client experience
- Weakness: Fully manual asset organization upstream

**Tier 2 (All-in-one CRM):** Sprout Studio, HoneyBook
- Strengths: End-to-end workflow
- Weakness: Still manual on asset side, not integrated

**Tier 3 (AI tooling):** Aftershoot, Narrative Select, Imagen
- Strengths: AI culling, editing suggestions
- Weakness: Point solutions, disconnected from workflow

**ProPhoto's advantage:** Only platform that connects **planning** (booking/calendar) → **capture** (metadata context) → **organization** (automatic matching) → **intelligence** (AI-assisted selection) → **delivery** (proofing) → **revenue** (invoicing/payments)

---

## Problem Statement

### The Photographer's Pain
After a shoot, photographers face a bottleneck: organizing hundreds or thousands of raw files. This process involves:
1. Importing files to disk or cloud
2. Manually reviewing and organizing by session (when shooting multiple sessions in one day)
3. Creating delivery galleries for each session
4. Managing client feedback and approvals
5. Creating invoices and payment requests

**Current state:** No system automatically knows "these 247 files from today's upload belong to the Acme Corp headshot session" without manual labeling. Every solution requires the photographer to manually organize *before* any intelligent processing can happen.

**Impact:** Photographers spend 15-20% of billable time on post-capture organization. For a photographer earning $100k/year, this is $12k-$20k in lost productivity. For a high-volume studio billing $500/shoot with 50+ shoots/month, it's $30k-$50k in lost time value annually.

**Competitive risk:** As other platforms add AI features (culling, editing, enhancement), the photographer still must manually organize first. The photographer who gets automatic matching has a 2-3 hour advantage per shoot day.

---

## Strategic Goals

### Primary Goals (Outcomes, not outputs)
1. **Become the only platform with automatic session-to-asset matching**
   - 90%+ accuracy when calendar context exists
   - 70%+ accuracy without calendar (GPS + time + batch coherence only)
   - < 2 seconds from upload completion to suggestion

2. **Enable photographers to go from upload → ready-for-delivery in minutes, not hours**
   - Current time: 2-4 hours per shoot (manual organization)
   - Target time: 20-30 minutes (auto-match + review + delivery setup)
   - Metric: Time saved per shoot, adoption rate of auto-matched assets

3. **Build defensible moat through intelligent matching + downstream intelligence**
   - Session matching is foundation for all downstream features
   - Intelligence layer (AI tagging, scene detection, auto-culling) is only viable with matched context
   - Matching accuracy improves with data (photographer calendar sync, location history, batch patterns)
   - Competitors cannot easily replicate without session context

4. **Establish ProPhoto as the operational spine for photography studios**
   - All downstream features (proofing, delivery, invoicing, payments) reference the session-matched asset
   - Switching costs increase as photographers invest in galleries, client relationships, integration setup
   - By phase 4 (payments), ProPhoto becomes the platform of record for studio operations

5. **Achieve 30%+ adoption among target segment within 18 months**
   - Net ARR: $50k-$100k from primary segment
   - Case study: 1-2 studios publicly using ProPhoto for complete workflows
   - Retention: 85%+ for photographers who integrate with booking system

### Secondary Goals
- Establish technical credibility through public GitHub and transparent roadmap
- Build API-first architecture enabling integrations with calendar (Google, Outlook), file sync (Dropbox, Google Drive), and gallery platforms (WordPress, custom)
- Create extensible intelligence layer enabling partners to build AI capabilities on top

---

## Non-Goals (Explicitly Out of Scope for ProPhoto Platform)

1. **AI-generated images or content creation**
   - Not in scope: Training custom AI models, generative photography features
   - Rationale: This is a separate product (could be a partner integration or future acquisition)
   - Alternative: Partner with platforms like Narrative Select or custom AI providers

2. **Direct camera integration or tethered shooting**
   - Not in scope: Connecting to cameras during the shoot, real-time ingest
   - Rationale: Requires specialized hardware support, complex driver management; better solved by existing solutions
   - Alternative: Support import from card readers, smartphones, cloud sync (Dropbox, Google Drive)

3. **Print fulfillment or physical product ordering**
   - Not in scope: Print lab integration, product manufacturing, shipping
   - Rationale: Requires logistics partnerships, inventory management, regulatory complexity
   - Alternative: Integrate with existing print fulfillment APIs (Mpix, Artifact Uprising) in phase 4

4. **CRM or client contact management**
   - Not in scope: Lead generation, sales pipeline, email marketing
   - Rationale: Not core to photographer workflow; better handled by dedicated CRM tools
   - Alternative: Expose APIs for CRM integration; focus on client relationship at delivery stage

5. **Image editing or post-processing**
   - Not in scope: Lightroom-like adjustments, batch editing tools
   - Rationale: Photographers have established editing workflows; ProPhoto's value is upstream (organization, selection)
   - Alternative: Integrate as metadata inputs; allow plugins for editing tools

6. **Multi-photographer collaboration on shoots**
   - Not in scope: Real-time asset sharing during capture, collaboration features
   - Rationale: Requires real-time sync, conflict resolution, permission models; better solved by dedicated collaboration tools
   - Phasing: Consider in phase 3 if customer demand demonstrates value

---

## Architecture Overview

### System Architecture
**Pattern:** Event-driven modular monolith (11 Laravel packages)
- **Foundation:** prophoto-contracts (shared DTOs, events, interfaces)
- **Operational spine:** prophoto-booking (sessions, time windows, locations, calendar sync)
- **Core event loop:** ingest → assets → intelligence
  - prophoto-ingest: Session matching decisions
  - prophoto-assets: Canonical asset ownership, metadata, session context projection
  - prophoto-intelligence: Derived intelligence (AI tagging, embeddings, scene detection)
- **Supporting:** access (RBAC), gallery (presentation), invoicing, notifications, interactions, ai (portrait training)

### Dependency Flow
```
prophoto-contracts (foundation)
    ↓
prophoto-access (identity/RBAC)
    ↓
[Core] ingest → [Core] assets → [Core] intelligence
    ↓           ↓                  ↓
[Domain] gallery, invoicing, interactions, notifications, ai
```

### Key Architectural Principles
1. **Event-driven communication** — Packages communicate via published events, not direct calls
2. **Contracts-first interfaces** — Shared DTOs, enums, events prevent tight coupling
3. **One-directional dependencies** — No circular dependencies; information flows predictably
4. **Package ownership** — Each package owns its data; other packages reference by ID only
5. **Immutable events** — Event payloads never change; shape changes require new versioned events

### Data Ownership Model
- **prophoto-booking** owns: Sessions, time windows, locations, booking requests, calendar links
- **prophoto-assets** owns: Canonical asset identity, raw/normalized metadata, derivatives, session context projections
- **prophoto-ingest** owns: Asset/session association decisions (append-only history)
- **prophoto-intelligence** owns: Intelligence runs, generated tags/embeddings, analysis results
- **prophoto-gallery** owns: Gallery presentation, image grouping, sharing policies

---

## Current Implementation State

### What's Built and Production-Ready
- **Architecture:** Clean, event-driven, acyclic dependency graph ✓
- **Core loop:** Ingest → assets → intelligence fully wired and functional ✓
- **Session matching:** Deterministic scoring algorithm with confidence tiers ✓
- **Asset spine:** Canonical asset ownership with metadata pipeline (raw + normalized) ✓
- **Contracts:** 13 interfaces, 20+ DTOs, 14 events, all immutable ✓
- **RBAC:** Multi-tenant with 4 roles, 58+ permissions, contextual grants ✓
- **Test coverage:** 4 packages well-tested (assets, contracts, ingest, intelligence) ✓

**Lines of code:** 280+ files, 28+ models, 22 services, 42 migrations, ~15k LOC

### Known Gaps (Honest Assessment from Code Scan)

**Critical (must address before feature launch):**
1. **Test coverage missing for core operational packages**
   - prophoto-booking: 0 tests (operational spine, no tests)
   - prophoto-gallery: 0 tests (major feature, no tests)
   - Impact: Risk for regressions in session management and gallery features
   - Priority: Add tests before phase 1 launch

2. **Metadata extraction stubbed**
   - Currently using NullAssetMetadataExtractor (placeholder)
   - Needed: Real extractors for EXIF, XMP, file properties
   - Impact: Metadata-driven features (GPS matching, time matching) depend on real data
   - Priority: Implement real extractors in parallel with Upload Recognition phase

3. **Some event listeners not wired**
   - ServiceProviders declared but not fully implemented
   - May cause missing event listeners for notifications, analytics
   - Priority: Audit and complete all listener registrations

**High priority (address in phase 2-3):**
1. **No performance optimization strategy**
   - No caching layer, no query optimization, no pagination strategy
   - Impact: Scaling concerns for high-volume studios (100+ assets/day)
   - Phase: Implement caching, query optimization, async processing queue in phase 2

2. **No storage strategy**
   - File storage not architected (local vs. cloud, retention policies, lifecycle)
   - Impact: Scaling concerns, cost management, compliance
   - Phase: Design storage layer and lifecycle management in phase 1, implement in phase 2

3. **Manual override system partially implemented**
   - Manual locks exist but not fully wired to UI
   - Impact: Photographers cannot correct mismatches
   - Phase: Complete manual assignment UI in Upload Recognition phase

**Medium priority (can address in phase 3+):**
- Advanced AI integration (currently demo generators only)
- Performance monitoring and alerting
- Advanced reporting and analytics
- Bulk operations on galleries/assets

---

## Roadmap and Phasing

### Phase 1: Upload Recognition Moment (8-12 weeks)
**Goal:** Automatic session detection on media import. Ship the core user moment that defines ProPhoto.

**Deliverables:**
- Upload UI with real-time session recognition
- Confidence display (why was this session chosen?)
- User confirmation flow with reject/reassign/manual entry
- Session context flows through to gallery
- Metadata extraction working (real EXIF/XMP parsing)
- Test coverage for booking and gallery packages

**Success metrics:**
- ≥90% accuracy with calendar context, ≥70% without
- <2 second response time from upload to suggestion
- ≥70% of photographers confirm auto-match (adoption metric)
- <5% false positive rate (wrong session assigned)

**Risks:**
- Metadata extraction complexity (file format variations)
- Performance scaling with large uploads (100+ files)
- User adoption of auto-matching vs. manual organization
- Calendar sync reliability (Google Calendar API, timezone edge cases)

### Phase 2: Proofing System (12-16 weeks)
**Goal:** Enable photographers to get client feedback on selected assets. Highest ROI feature after Upload Recognition.

**Deliverables:**
- Client-facing proofing galleries (view, rate, comment, approve)
- Photographer dashboard for approval workflows
- Notification system for client feedback
- Integration with gallery presentation
- Payment collection flow (invoice from approved assets)

**Success metrics:**
- ≥80% of photographers use proofing galleries
- Average time from shoot → client approval: <24 hours
- NPS on proofing workflow: >8/10

**Why this phase:** Proofing drives revenue (gets client input) and retention (photographers re-engage after upload).

### Phase 3: Payment & Delivery (16-20 weeks)
**Goal:** Complete the photographer's workflow: invoice → payment → delivery.

**Deliverables:**
- Automated invoice generation from approved assets/sessions
- Payment processing (Stripe integration)
- Final delivery galleries (whitelabel, no branding)
- Photographer dashboard with revenue tracking

**Success metrics:**
- ≥90% of photographers collect payment via ProPhoto
- Average days from shoot → payment collected: <7 days
- Payment capture rate: >95%

### Phase 4: Intelligence and Optimization (20+ weeks)
**Goal:** Leverage matched sessions and metadata for intelligent automation.

**Deliverables:**
- AI-assisted culling (scene detection, focus quality, expression analysis)
- Auto-organization suggestions (by scene, by person, by quality)
- Batch processing optimization
- Advanced reporting and analytics

**Success metrics:**
- Time from upload → delivery ready: <30 minutes
- Photographer satisfaction with AI suggestions: >7/10
- Adoption of auto-culling features: ≥60%

---

## Success Metrics

### Phase 1 (Upload Recognition) KPIs

**Leading Indicators** (measure weekly):
- Upload volume and frequency (% of photographers using upload feature)
- Recognition accuracy by context type (with calendar, without, GPS, batch)
- False positive rate (wrong session assigned)
- User interaction rates (% confirming, rejecting, reassigning)
- Response time percentiles (p50, p95, p99 latency)

**Lagging Indicators** (measure monthly):
- Adoption rate: % of photographers with ≥1 auto-matched asset
- Retention: % of photographers returning for second upload
- Feature NPS: "How satisfied are you with automatic matching?" (target: 8+/10)
- Time saved: Self-reported hours saved per shoot vs. manual organization baseline
- Churn prevention: Do photographers who use auto-matching have lower churn? (target: 20% lower churn vs. manual-only)

**Targets for phase 1 launch:**
- ≥90% accuracy with calendar, ≥70% without
- <2 second response time (p95)
- ≥1% false positive rate
- ≥70% user confirmation rate (photographers trust the match)

### Phase 2+ (Proofing, Payment) KPIs
- Client engagement rate (% viewing proofing galleries)
- Average approval time (days from gallery shared → approval)
- Revenue per shoot (average invoice amount)
- Payment collection rate (% of invoices paid within 30 days)
- Photographer lifetime value (revenue per photographer, churn rate)

---

## Risks and Mitigations

### Technical Risks
| Risk | Impact | Mitigation |
|------|--------|-----------|
| Metadata extraction fails for some file formats | Matching accuracy drops | Build format coverage incrementally; start with JPEG, RAW (Canon/Nikon) |
| Calendar sync unreliable (API rate limits, permission issues) | Matching degrades without calendar context | Fallback to GPS + timestamp matching; graceful degradation |
| Performance issues at scale (1000s of assets/day) | Latency exceeds 2 seconds | Implement async processing, caching, query optimization in phase 1 |
| Event system complexity grows | Bugs in listener registration, event ordering | Strong test coverage, event contract versioning, state management tests |

### Product Risks
| Risk | Impact | Mitigation |
|------|--------|-----------|
| Photographers don't trust auto-matching | Low adoption of feature | Start with high confidence only (auto-assign ≥0.85); let photographers confirm; transparent evidence |
| Competitors add auto-matching quickly | Moat erodes | Build on top of matching (intelligence, proofing, payments) to compound advantage |
| Photographers continue manual organization habit | Adoption stalls | Educate via onboarding, testimonials, case studies showing time savings |

### Business Risks
| Risk | Impact | Mitigation |
|------|--------|-----------|
| Market is smaller than $720M estimate | TAM shrinks | Start with high-volume commercial segment (smallest, most profitable); expand upmarket |
| Pricing resistance (photographers price-sensitive) | Revenue per customer too low | Build to payment phase early; show ROI (time savings = revenue) |
| Churn if photographers don't see value | CAC not recovered | Focus on retention metrics from day 1; NPS ≥8/10 target; build loyalty through proofing → payment loop |

---

## Open Questions

### Blocking (must resolve before phase 1 launch)
- **Metadata extraction:** Which file formats to prioritize in v1? (JPEG, RAW formats, video?)
- **Calendar sync:** How to handle photographers without Google Calendar? (Fallback to manual session entry?)
- **Storage:** On-device upload, cloud sync (Dropbox, Google Drive), or direct-to-ProPhoto? (Affects architecture)

### Non-blocking (resolve during implementation)
- **Pricing model:** Per-shoot, per-studio, per-photographer, hybrid?
- **Monetization timeline:** Freemium, trial, direct to paid?
- **AI provider:** Build custom taggers or partner with existing API (AWS Rekognition, Google Vision)?
- **Reporting:** What analytics matter most to photographers? (Time saved, revenue, client satisfaction?)

---

## Success Criteria (Launch Readiness)

Phase 1 is ready to launch when:
1. ✓ Upload recognition achieves ≥90% accuracy with calendar, ≥70% without
2. ✓ Response time is <2 seconds (p95)
3. ✓ Metadata extraction works for 90% of uploaded file types
4. ✓ Manual override flow is complete and tested
5. ✓ Booking and gallery packages have ≥70% test coverage
6. ✓ Session context flows through to gallery correctly
7. ✓ Documentation is complete for features, API contracts, data models
8. ✓ Three reference photographers have tested and validated the workflow
9. ✓ Performance benchmarks meet SLA (no regression under 100 concurrent uploads)

---

## Appendix: Strategic Positioning

### Why This Matters
Photography is a $720M+ software market, but it's fragmented. Photographers use 5-8 separate tools:
- Booking: HoneyBook, Sprout Studio, Acuity
- Gallery: Pixieset, ShootProof, Pic-Time
- AI: Aftershoot, Narrative Select
- Payments: Stripe, PayPal
- Email: Mailchimp, ConvertKit

Each integration point is a friction point. ProPhoto's opportunity is to become the spine these tools connect to—the source of truth for sessions, assets, and outcomes.

By winning upload recognition (phase 1), then proofing (phase 2), then payments (phase 3), ProPhoto becomes the operational platform photographers cannot leave. That's the moat.

---

**Document version history:**
- v1.0 (2026-04-10): Initial project-level PRD from BMAD Deep Scan analysis
