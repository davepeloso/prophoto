# ProPhoto Phase 2: Proofing System PRD

**Status:** Planning
**Version:** 2.0
**Created:** April 12, 2026
**Updated:** April 12, 2026 — Full rewrite incorporating gallery mode system, proofing
pipeline, identity gate, and gallery activity ledger
**Owner:** Dave Peloso
**Builds on:** PRD-phase-1-epic-ingest-workflow.md, PRD-project-level.md,
RBAC-phase-2-backlog.md
**Estimated duration:** 16 weeks (8 × 2-week sprints)

---

## Problem Statement

After a shoot, photographers spend significant time manually sharing images with clients,
collecting scattered feedback via text and email, and chasing approvals before they can
deliver or invoice. There is no structured way for multiple stakeholders — a marketing
manager, a legal reviewer, a subject — to interact with the same set of images and
produce a clear, attributed, auditable record of decisions.

Phase 1 solved the upstream problem: images are now automatically organized, matched to
sessions, and stored as canonical assets. Phase 2 closes the loop by turning those assets
into a structured, client-facing gallery experience — one that handles both simple
"here are your photos" presentation and complex multi-stakeholder proofing workflows.

---

## Goals

1. **Reduce time from shoot → client approval to under 24 hours** — measured as median
   time between `UploadSession.confirmed_at` and first client approval action.
2. **Every action in a Proofing Gallery is attributed** — no anonymous interactions.
   Who approved what, who requested what, who uploaded what version — all recorded.
3. **≥70% of shared Proofing Galleries receive at least one client interaction** within
   48 hours of the link being sent.
4. **Zero external tools required** — full shoot → proof → approve cycle inside ProPhoto.
5. **Presentation Galleries work for non-photography clients** — real estate, product
   showcases, public-facing tours — without any workflow overhead.

---

## Non-Goals (Phase 2)

- **Payment collection** — invoicing and Stripe are Phase 3.
- **AI-assisted culling** — Phase 4.
- **Custom domains / white-label portals** — Phase 3 polish.
- **Native mobile app** — galleries are mobile-responsive web only.
- **Full-resolution download** — Phase 3, gated on payment.
- **Multi-tenancy** — whole-app tenancy is a foundational infrastructure phase,
  deliberately deferred. The gallery model is designed to not require retrofitting
  when tenancy is added.

---

## Core Model: Two Gallery Types

Every gallery has a `type`. Type determines everything about what the gallery can do.

### Presentation Gallery

A Presentation Gallery is a viewing experience. Nothing more.

- No login, no email gate, no identity
- No approval, no rating, no comments, no pipeline
- No downloads
- Mobile-first layout
- The share link is the entire experience — click, view, done
- Used for: real estate tours, portfolio showcases, event previews, public-facing work

The photographer can still CRUD images in a Presentation Gallery from their Filament
dashboard. The public cannot interact with the images at all.

### Proofing Gallery

A Proofing Gallery is a shared workspace. It requires identity because every action
must be attributed.

- Share link is open (no pre-approved invite list) but requires email confirmation
  on first access — "What's your email?" before any interaction is allowed
- The email + share token together form the actor identity for all subsequent actions
- Supports the full image capability matrix (approve, retouch request, rate, comment,
  download, version, duplicate)
- Has a configurable proofing pipeline with min/max approvals, retouch caps,
  pending types, and sequential enforcement
- Has a Gallery Activity Ledger — a chronological, attributed record of every action
- Used for: headshots, portraits, product review, editorial proofing, any workflow
  where decisions need to be tracked

---

## Identity Gate (Proofing Galleries Only)

When a subject or client opens a Proofing Gallery share link for the first time:

1. They see a simple gate: gallery name, photographer name, and a single field:
   **"Enter your email address to view this gallery"**
2. They enter their email. No password. No account creation.
3. The system records `gallery_shares.identity_confirmed_at` and associates the
   email with this share token for all future actions.
4. On subsequent visits from the same browser, the identity is remembered via a
   signed cookie tied to the share token. No re-entry required.
5. If they open the link in a new browser/device, they re-enter their email —
   the same token, now confirmed again.

**What this enables:** Every `image_approval_state`, `gallery_activity_log` entry,
`gallery_comment`, and version upload is stamped with a real email address and a name
(derived from the email on first confirmation, editable). The photographer and all other
gallery participants can see the ledger and know exactly who did what.

**What this is not:** It is not authentication. It is identity confirmation. The email
is not verified with a magic link or OTP in Phase 2 — the friction would kill adoption.
Phase 3 can add OTP verification for high-stakes galleries if needed.

---

## Gallery Activity Ledger

Every Proofing Gallery has a live activity ledger visible to all participants with
gallery access. It is chronological, attributed, and immutable.

**Sample ledger:**
```
Apr 12, 2:14pm   Gabby Rodriguez (gabby@uclahealth.org)
                 ✅ Approved — Nirali_Sheth_0007.jpg

Apr 12, 2:16pm   Gabby Rodriguez
                 🔧 Retouch Requested — Nirali_Sheth_0012.jpg
                 "Please brighten the eyes slightly"

Apr 12, 3:02pm   Monica Chen (monica@craftfootwear.com)
                 📁 Version Uploaded — shoe_left_001.jpg → V2

Apr 12, 3:05pm   Dave Peloso (dave@pelostudio.com)
                 ➕ Added 3 images to gallery

Apr 12, 4:30pm   Dr. Jessica Haslam (jhaslam@uclahealth.org)
                 ✅ Approved — Nirali_Sheth_0003.jpg
                 ★★★★★ Rated 5 stars
```

The ledger is the source of truth for the proofing workflow. It answers "what happened,
who did it, and when" without the photographer having to chase anyone.

---

## The Proofing Pipeline

The proofing pipeline governs how images move through approval states. It is configured
per-gallery and enforced sequentially — a subject cannot request a retouch on an image
they have not approved.

### Image Approval States

```
Unapproved
    │
    ▼
Approved ──────────────────────────────────────────────────────────┐
    │                                                               │
    ▼                                                               │
Approved Pending {type}                                             │
  • Retouch                                                         │
  • Background Swap                                                 │
  • Awaiting Second Approval                                        │
  • [custom types defined per-gallery]                              │
    │                                                               │
    ▼                                                               │
[Photographer acts on pending state]                                │
    │                                                               │
    └──── resolved ──────────────────────────────────────────────────┘
                                                              Final Approved
```

**Rules enforced by the system:**
- An image must be Approved before it can be marked Approved Pending
- A subject can clear their own approval (revert to Unapproved) before submission
- After gallery submission is locked, all states become read-only for that share token
- Ratings are free — not tied to the pipeline, can be set on any image at any time

### Gallery Pipeline Configuration

Set by the photographer at gallery creation. All fields are nullable (null = no constraint).

| Field | Type | Meaning |
|---|---|---|
| `min_approvals` | int\|null | Client must approve at least this many before submitting |
| `max_approvals` | int\|null | Client cannot approve more than this many |
| `max_pending` | int\|null | Client cannot mark more than this many as Approved Pending |
| `ratings_enabled` | bool | Whether the star rating UI is shown (default true) |
| `pipeline_sequential` | bool | Must approve before pending (always true in Phase 2) |
| `pending_types` | array | Which pending types are available — configured via `gallery_pending_types` table |

### Pending Types

Pending types are configured per-gallery from a master list defined at the studio level.
The photographer defines their studio's standard pending types once, and each gallery
inherits them with the option to enable/disable individual types for that gallery.

Default studio pending types (seeded):
- Retouch
- Background Swap
- Awaiting Second Approval
- Colour Correction

Photographer can add custom pending types to their studio list at any time.

---

## Templates

Templates control the **presentation layer** of the gallery — layout, grid style,
aspect ratio, card dimensions. They do not control the pipeline.

Templates carry **default pipeline configurations** that pre-fill the gallery creation
form. The photographer can override any default. The template is a starting point,
not a constraint.

| Template | Layout | Default Pipeline Config |
|---|---|---|
| Portrait Template | Two-column, tall cards | Pipeline on, ratings on, max_pending = 2 |
| Editorial Template | Asymmetric, mixed ratios | Pipeline on, ratings on, no caps |
| Architectural Template | Three-column, landscape | Pipeline off (Presentation default) |
| Classic Template | Balanced grid | Pipeline on, ratings on |
| Profile Template | Centered header + portfolio grid | Pipeline on, ratings on, max_approvals = 1 |
| Single Column Template | Full-width vertical stack | Pipeline off (Presentation default) |

---

## Image Capabilities Matrix

| # | Capability | Proofing Gallery | Presentation Gallery | RBAC Permission | Notes |
|---|---|---|---|---|---|
| 1 | Add | studio_user only | studio_user only | `can_upload_images` | Photographer adds late selects |
| 2 | Delete | studio_user only | studio_user only | `can_delete_images` | Soft delete — asset preserved |
| 3 | Update (metadata) | studio_user only | studio_user only | `can_version_images` | Caption, tags |
| 4 | Replace (version) | studio_user only | studio_user only | `can_version_images` | New file → V2, prior preserved |
| 5 | Request Editing | guest_user (subject) | ❌ | `can_request_edits` | Must be Approved first |
| 6 | Approve | all roles (context-scoped) | ❌ | `can_approve_images` | Sequential: before pending |
| 7 | AI Consent | all roles (context-scoped) | ❌ | `can_consent_ai_use` | Boolean on `images.ai_consent_given` |
| 8 | Share | studio_user, client_user | studio_user only | `can_share_gallery` | Deep link or gallery link |
| 9 | Download | all roles (web-res, watermark per config) | ❌ | `can_download_images` | Original gated on Phase 3 payment |
| 10 | Duplicate | studio_user only | studio_user only | `can_duplicate_images` | Same asset, new gallery association |

---

## Data Model — New and Changed Tables

### `galleries` — new columns

```php
$table->enum('type', ['proofing', 'presentation'])->default('proofing');
$table->json('mode_config')->nullable();
// mode_config shape:
// {
//   "min_approvals": null,
//   "max_approvals": null,
//   "max_pending": null,
//   "ratings_enabled": true,
//   "pipeline_sequential": true
// }
```

### `gallery_shares` — new/changed columns

```php
$table->string('invitee_email')->nullable();     // null = open link
$table->string('invitee_name')->nullable();
$table->timestamp('identity_confirmed_at')->nullable();
$table->string('confirmed_email')->nullable();   // email entered at gate
$table->string('confirmed_name')->nullable();    // name entered at gate
$table->timestamp('last_accessed_at')->nullable();
$table->integer('min_approvals')->nullable();    // per-share override of gallery config
$table->integer('max_approvals')->nullable();
$table->integer('max_pending')->nullable();
$table->enum('interaction_level', ['full', 'view_only', 'download_only'])->default('full');
```

### `gallery_pending_types` — new table

```php
Schema::create('gallery_pending_types', function (Blueprint $table) {
    $table->id();
    $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
    $table->string('label');           // "Retouch", "Background Swap", custom
    $table->integer('sort_order')->default(0);
    $table->boolean('active')->default(true);
    $table->timestamps();
});
```

### `studio_pending_type_templates` — new table (master list)

```php
Schema::create('studio_pending_type_templates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
    $table->string('label');
    $table->integer('sort_order')->default(0);
    $table->boolean('is_default')->default(false); // auto-added to new galleries
    $table->timestamps();
});
```

### `image_approval_states` — new table

```php
Schema::create('image_approval_states', function (Blueprint $table) {
    $table->id();
    $table->foreignId('image_id')->constrained()->cascadeOnDelete();
    $table->foreignId('gallery_share_id')->constrained()->cascadeOnDelete();
    $table->enum('status', ['approved', 'approved_pending', 'cleared']);
    $table->foreignId('pending_type_id')
          ->nullable()
          ->constrained('gallery_pending_types')
          ->nullOnDelete();
    $table->text('pending_note')->nullable();  // subject's retouch note
    $table->timestamp('approved_at');
    $table->timestamp('updated_at');

    $table->unique(['image_id', 'gallery_share_id']); // one state per image per share
    $table->index(['gallery_share_id', 'status']);
});
```

### `gallery_activity_log` — new table (the ledger)

```php
Schema::create('gallery_activity_log', function (Blueprint $table) {
    $table->id();
    $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
    $table->foreignId('gallery_share_id')->nullable()
          ->constrained()->nullOnDelete(); // null = photographer action
    $table->string('actor_email');
    $table->string('actor_name')->nullable();
    $table->enum('action_type', [
        'approved',
        'approval_cleared',
        'pending_requested',
        'pending_resolved',
        'version_uploaded',
        'image_added',
        'image_deleted',
        'comment_added',
        'rating_set',
        'download',
        'gallery_submitted',
        'gallery_locked',
    ]);
    $table->unsignedBigInteger('subject_id')->nullable();   // image_id or asset_id
    $table->string('subject_label')->nullable();            // filename at time of action
    $table->json('metadata')->nullable();                   // action-specific details
    $table->timestamp('created_at');

    $table->index(['gallery_id', 'created_at']);
    $table->index('gallery_share_id');
});
```

---

## Affected Packages

| Package | Role in Phase 2 |
|---|---|
| `prophoto-gallery` | Core — Gallery type + mode_config, GalleryShare identity gate, gallery_pending_types, image_approval_states, gallery_activity_log migrations and models |
| `prophoto-access` | RBAC wiring — roles seeded, contextual grants created on share generation, 3 new permissions (can_version_images, can_consent_ai_use, can_duplicate_images) |
| `prophoto-interactions` | Rating storage (ImageInteraction TYPE_RATING) — ratings remain here as they are cross-gallery |
| `prophoto-notifications` | Email dispatch — approval submitted, first view, reminder |
| `prophoto-assets` | Asset resolution for thumbnails + derivatives; version chain for Replace capability |
| `prophoto-ingest` | Source of confirmed sessions → assets that feed gallery image selection |

**New packages needed:** None.

---

## Open Questions — Resolved

| # | Question | Decision |
|---|---|---|
| Q1 | Approval UI | Modal with sequential enforcement — Retouch disabled until Approved. Modal redesigned vs Gallerie. |
| Q2 | Downloads | Photographer chooses per-gallery — watermarked by default, can disable |
| Q3 | AI consent storage | Boolean on `images.ai_consent_given` |
| Q4 | Gallery URL | `/g/{token}` on main domain |
| Q5 | Canonical share token | `GalleryShare.token` |
| Q6 | Pending types scope | Per-gallery, seeded from studio master list |
| Q7 | Identity gate for proofing | Open link + email on first access. No OTP in Phase 2. |
| Q8 | Presentation vs Proofing | Two explicit gallery types. Presentation = view only, no identity, no pipeline. |
| Q9 | Approval constraints | All independently nullable: min_approvals, max_approvals, max_pending |
| Q10 | Templates and pipeline | Templates set layout + default pipeline config. Mode always independently overridable. |

---

## Success Metrics

### Leading Indicators (weekly after launch)

| Metric | Target |
|---|---|
| Proofing Galleries created per active photographer | ≥1 within 7 days of launch |
| Identity gate completion rate | ≥85% of subjects who open a link complete the email gate |
| Client approval submission rate | ≥50% of opened Proofing Galleries receive a submission |
| Median time: link sent → first approval action | < 4 hours |

### Lagging Indicators (30/60/90 days)

| Metric | Target |
|---|---|
| % of photographers with ≥1 completed proofing cycle | ≥80% at 30 days |
| Median time: session confirmed → client approved | < 24 hours |
| Proofing workflow NPS | ≥ 8/10 |
| Reduction in support tickets about "sharing images with clients" | ≥50% |

---

## Appendix: Existing Infrastructure to Leverage

- `galleries` table — has `approved_count`, `image_count`, `status`, `access_code`,
  `magic_link_token`, `magic_link_expires_at`. New `type` and `mode_config` columns added.
- `gallery_shares` — extended with identity gate fields and per-share pipeline overrides.
- `gallery_comments` — client comments on images already modelled. Phase 2 adds
  `type = 'edit_request'` for the retouch request flow.
- `gallery_access_logs` — view tracking already modelled. First-view notification uses this.
- `image_interactions` — TYPE_RATING remains here. TYPE_APPROVAL is superseded by the
  new `image_approval_states` table which carries richer state + pending types.
- `image_versions` — migration already exists in prophoto-gallery. Used by Replace capability.
- `asset_derivatives` — thumbnails from Phase 1 GenerateAssetThumbnail feed the gallery viewer.
- `prophoto-notifications` messages table — email dispatch infrastructure already in place.

---

## Tenancy Note

ProPhoto will eventually operate on a full multi-tenancy model, but that is a
whole-application infrastructure change, not a gallery feature. The gallery model
is designed to be tenancy-ready: every table has a `studio_id` at the root of its
ownership chain. When tenancy is added, the `studio_id` becomes the tenant discriminator
with no schema changes required to Phase 2 tables.
