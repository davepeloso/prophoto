#prophoto/AssetSpine

## **The “Asset Spine” pattern**

Think of it like this:

**The file is not the product object. The asset is.** 

An **asset** is the canonical identity that everything else points to:

* the original file
* every derivative
* all raw metadata
* normalized metadata
* audit trail
* permissions context
* later, AI references, export references, gallery usage, and download history

That fits your rules exactly: metadata is first-class and must persist end-to-end, raw metadata stays immutable, normalized metadata stays schema-versioned, and derivatives reference the canonical asset identity.

So the spine looks like this:

```
Original file
    ↓
Canonical Asset
    ├── Raw metadata
    ├── Normalized metadata
    ├── Derivatives
    ├── Storage references
    ├── Provenance
    ├── Usage references
    └── Access / audit / downstream consumers
```

## **Why this is the right move for ProPhoto**

Right now your current flow is basically:

upload → staging image → metadata job → derivative job → promote to gallery image → cleanup staging

That works for early progress, but it has a weak point: **gallery image starts becoming the de facto identity of the media**, and that’s too late in the pipeline. Gallery should be a curated presentation layer, not the canonical media owner.

Your docs already point toward storage abstraction, signed URLs, path conventions, derivative types, and normalized metadata DTOs as foundational needs.

The Asset Spine fixes that by inserting a stable middle layer:

```
Ingest  →  Assets  →  Galleries
```

Not:

```
Ingest  →  Galleries pretending to be storage
```

## **The core idea**

Every uploaded media object gets an **Asset ID immediately**.

From that moment on:

* the original file belongs to the asset
* derivatives belong to the asset
* metadata belongs to the asset
* galleries only reference the asset
* downloads are built from assets
* AI training sets are selected from assets or gallery-linked assets
* PDFs and videos fit the same model without special-case hacks

That aligns with your database ownership rule and your integration style rule: one package owns its tables and cross-package integration should happen through contracts/events rather than concrete coupling.

## **The asset spine layers**

### **1. Canonical Asset**

This is the heart.

One row, one identity, one original media object.

It should answer:

* What is this thing?
* Where is the original?
* What type is it?
* What tenant/studio/org does it belong to?
* What is its checksum?
* What is its lifecycle status?

This is your stable backbone even if filenames change, galleries change, or normalization evolves.

### **2. Raw metadata**

This is the immutable truth layer.

For images, that could be EXIF/XMP/IPTC extraction.

For PDFs, document info and embedded metadata.

For video, ffprobe-style stream/container metadata later.

Your rules are clear here: persist raw extracted metadata as immutable source truth and never destroy it because normalization changes.

That means if your EXIF normalization logic improves six months later, you do **not** re-invent truth. You re-project from raw.

### **3. Normalized metadata**

This is the queryable layer.

This is where you create your stable schema:

* captured_at
* width / height
* camera make/model
* lens
* orientation
* color profile
* keywords
* document page count
* video duration
* etc.

Your docs already call out an EXIF normalization layer and a normalized metadata DTO in contracts.

This is the layer galleries and UI should read most of the time.

### **4. Derivatives**

Thumbs, previews, web copies, maybe print exports later.

Derivatives are not their own truth. They are children of the asset.

That matters because your rules say derivatives must reference canonical asset identity.

So:

* delete an asset, you know what derivatives are affected
* regenerate derivatives, same asset ID
* move storage backend, same asset ID
* build download packages, same asset ID

### **5. Usage references**

This is where the spine becomes powerful.

An asset can be:

* included in one or more galleries
* selected for AI training
* included in a ZIP export
* attached to a booking/session context
* used in audit logs

The asset remains the same object while different packages build experiences around it.

## **The big advantage: you stop coupling “where media lives” to “where media is shown”**

That’s the architecture win.

Without an asset spine:

* gallery owns too much
* ingest becomes sticky
* metadata gets duplicated
* PDFs/videos become awkward
* AI has no clean media source of truth

With an asset spine:

* ingest is intake/workflow
* assets are canonical media + metadata
* galleries are curated views
* downloads are packaging
* AI is a consumer

That is much closer to the modular monolith you described in SYSTEM.md, where packages integrate through events and intentional upstream dependencies instead of turning one package into a god package.

## **What the lifecycle should look like**

Here’s the clean version.

### **Step 1: Intake**

A user uploads through ingest.

Ingest handles:

* upload UX
* temporary validation
* batch/staging concerns
* progress/errors

This matches your current role for ingest.

### **Step 2: Asset creation**

As soon as a file is accepted, create a canonical asset.

That means:

* assign asset_id
* store original
* register checksum
* record file type
* record tenant ownership
* mark status like pending_processing

### **Step 3: Metadata extraction**

Run extractor(s), persist raw metadata, then create normalized metadata projection.

Your rule explicitly requires provenance too, so each metadata record should carry extractor source, tool version, and timestamps.

### **Step 4: Derivative generation**

Generate preview assets and register them as asset derivatives.

### **Step 5: Promotion / association**

Now other packages can use the asset:

* gallery attaches asset IDs
* AI references asset IDs
* downloads package collects asset IDs

Notice what changed: **promotion no longer means “become the real object.”** 

It just means “become associated with a higher-level workflow.”

That’s a much safer design.

## **The table shape, conceptually**

Not code yet — just domain shape.

### **assets**

The canonical record.

Likely fields:

* id
* studio_id
* organization_id if needed
* type
* original_filename
* mime_type
* checksum_sha256
* storage_driver
* original_storage_key
* logical_path
* status
* captured_at
* ingested_at

### **asset_metadata_raw**

Immutable extraction payloads.

Fields like:

* asset_id
* extractor
* tool_version
* payload
* extracted_at

### **asset_metadata_normalized**

Queryable schema-versioned projection.

Fields like:

* asset_id
* schema_version
* payload
* normalized_at

### **asset_derivatives**

Child records for thumbs/previews/web/PDF previews/video posters later.

### **gallery_assets**

### **or existing gallery image association**

Gallery does not own originals. It references assets.

That respects Rule 2: each table has one owning package, and cross-package migrations are forbidden.

## **The metadata spine is the secret sauce**

For your project, this is bigger than storage.

You are not just building a file bucket browser. You are building a **metadata-driven media system**.

That means the asset spine is what enables:

* filter by camera/lens/date/orientation
* smart culling
* metadata CSV exports
* AI training validation
* reprocessing without data loss
* future search and ranking

Your docs already hint at “metadata CSV + README” in downloads and normalized metadata DTOs in contracts.

If metadata is the spine, the original file is only one part of the asset. That mindset matters.

## **The danger you should avoid**

The most common failure mode is this:

```
staging image → gallery image → derivative paths scattered everywhere
```

That creates:

* duplicated ownership
* path logic in multiple packages
* metadata split between staging and gallery
* painful support for PDFs/video later

Your rules already protect against some of this by requiring contracts/events for integration and keeping foundational packages UI-free.

The asset spine finishes the job by giving media a single canonical owner.

## **Where contracts should sit**

Based on your docs, these belong in prophoto-contracts:

* AssetStorageContract
* SignedUrlGeneratorContract
* PathResolverContract
* AssetMetadata DTO
* DerivativeType enum
* shared asset events

Then prophoto-assets implements them.

That fits Rule 3 exactly: shared events, interfaces, and DTOs live in prophoto-contracts.

## **The best mental model**

Think of the packages like this:

### **prophoto-ingest**

“Get media in safely.”

### **prophoto-assets**

“Own the media truth.”

### **prophoto-gallery**

“Present a curated subset of media.”

That’s the clean separation.

## **My blunt recommendation for your architecture**

If you do this package, make it responsible for **five things only**:

1. Canonical asset identity
2. Original + derivative storage registration
3. Raw metadata persistence
4. Normalized metadata projection
5. Query/browse services over assets

⠀
Do **not** let it absorb:

* upload UX
* gallery curation rules
* download packaging
* AI orchestration
* admin panel concerns

Keep it boring and central.

That is how backbone packages stay trustworthy.

## **The version I’d aim for first**

Not a huge filesystem clone. Just a disciplined v1:

* image/pdf/video asset types
* canonical asset record
* raw metadata JSON
* normalized metadata JSON
* derivative registry
* path resolver contract
* storage contract
* browse by logical path / date / type / status

That is enough to support the rest of the system without overbuilding.

## **The punchline**

The Asset Spine is basically this principle:

> **Every piece of media gets one canonical identity early, and every later workflow references that identity instead of inventing its own.** 

For ProPhoto, that is the right center of gravity because your rules already demand canonical ownership, headless foundational packages, contract/event boundaries, and end-to-end metadata persistence.

Next move should be turning this into a concrete repo-facing doc:

**docs/architecture/ASSET-SPINE.md** 

with:

* purpose
* lifecycle
* table ownership
* contract list
* event list
* what ingest owns vs assets owns vs gallery owns

Below is a **repo-ready architecture document** you can drop directly into:

```
docs/architecture/ASSET-SPINE.md
```

It follows the constraints in your **RULES.md**, especially:

* single table ownership
* headless foundational packages
* contracts/events for integration
* immutable raw metadata + normalized projections
---

# **ASSET-SPINE.md**

## **Purpose**

The **Asset Spine** defines the canonical identity and lifecycle of all media in the ProPhoto system.

An **Asset** represents a single uploaded media object (image, video, PDF, etc.) and becomes the central reference point for:

* original file storage
* derivative files
* metadata extraction
* metadata normalization
* downstream usage (galleries, downloads, AI pipelines)

Assets are created early in the ingestion pipeline and remain stable for the lifetime of the media.

All other packages reference assets by ID.

---

# **Architectural Role**

The asset spine introduces a foundational package:

```
prophoto-assets
```

This package sits between ingest and gallery layers.

```
capture
   ↓
prophoto-ingest
   ↓
prophoto-assets
   ↓
prophoto-galleries
   ↓
delivery / downloads / AI / exports
```

### **Responsibilities**

|  **Package**  |  **Responsibility**  | 
|---|---|
|  ingest  |  file intake, staging, validation  |
|  assets  |  canonical media identity + metadata  |
|  galleries  |  curated presentation and sharing  |
|  delivery  |  downloads / exports  |
---

# **Asset Definition**

An **Asset** represents a canonical media identity.

An asset includes:

* original file
* derivative files
* raw metadata
* normalized metadata
* provenance records
* lifecycle state

Assets exist independently of galleries.

Galleries only reference assets.

---

# **Asset Lifecycle**

## **1. Intake (Ingest Package)**

The ingest system receives uploads.

Responsibilities:

* upload validation
* batch/session grouping
* temporary staging
* user progress/errors

When a file passes validation:

```
AssetCreated
```

is emitted.

---

## **2. Asset Creation**

The assets package creates a canonical asset record.

Fields include:

* asset_id
* tenant/studio ownership
* original filename
* mime type
* file size
* checksum
* logical path
* ingestion timestamp
* lifecycle status

The original file is persisted through the storage contract.

Status begins as:

```
pending_processing
```

---

## **3. Metadata Extraction**

Metadata extractors analyze the original media.

Examples:

* EXIFTool (images)
* PDF metadata tools
* ffprobe (video)

Extractors produce a **raw metadata bundle**.

Raw metadata is stored exactly as extracted.

Raw metadata is immutable.

---

## **4. Metadata Normalization**

Raw metadata is converted into a normalized schema.

Normalization produces:

* standardized field names
* consistent types
* queryable fields

Normalization is versioned.

Raw metadata is never replaced.

Normalized metadata may be regenerated if schema evolves.

---

## **5. Derivative Generation**

Preview media is generated.

Examples:

* thumbnails
* preview images
* web-resolution copies
* PDF previews
* video posters

Each derivative references the canonical asset.

---

## **6. Downstream Usage**

Once processing is complete:

```
AssetReady
```

is emitted.

Other packages may now reference the asset.

Examples:

|  **Package**  |  **Usage**  | 
|---|---|
|  galleries  |  curated image sets  |
|  downloads  |  zip packaging  |
|  AI  |  training / inference  |
|  analytics  |  usage tracking  |
---

# **Data Model**

The asset spine uses a small number of core tables.

## **assets**

Canonical identity.

Typical fields:

```
id
studio_id
asset_type
original_filename
mime_type
bytes
checksum_sha256
storage_driver
original_storage_key
logical_path
status
captured_at
ingested_at
```

---

## **asset_metadata_raw**

Immutable metadata extraction.

```
asset_id
extractor
tool_version
extracted_at
payload_json
```

Payload contains raw extractor output.

---

## **asset_metadata_normalized**

Queryable metadata projection.

```
asset_id
schema_version
normalized_at
payload_json
```

Optional denormalized columns may be added for performance.

---

## **asset_derivatives**

Records generated preview files.

```
asset_id
derivative_type
storage_key
mime_type
bytes
width
height
created_at
```

---

# **Storage Model**

Assets own the physical media objects.

Other packages must not store independent copies.

Original file storage path example:

```
assets/originals/{studio_id}/{asset_id}/{filename}
```

Derivative path example:

```
assets/derivatives/{studio_id}/{asset_id}/{type}.jpg
```

Actual path generation is handled by the **Path Resolver Contract**.

No package should hardcode storage paths.

---

# **Metadata Spine**

Metadata is a first-class component of assets.

Rules:

* raw metadata is immutable
* normalized metadata is schema-versioned
* provenance must be recorded
* normalization may evolve over time
* derivatives must reference canonical asset identity

This ensures metadata persists across system evolution.

---

# **Contracts**

Shared contracts live in:

```
prophoto-contracts
```

Relevant interfaces include:

### **Storage**

```
AssetStorageContract
PathResolverContract
SignedUrlGeneratorContract
```

### **Metadata**

```
AssetMetadataExtractorContract
AssetMetadataNormalizerContract
```

### **Asset Repository**

```
AssetRepositoryContract
```

---

# **Events**

Domain events related to assets must live in prophoto-contracts.

Example events:

```
AssetCreated
AssetStored
AssetMetadataExtracted
AssetMetadataNormalized
AssetDerivativesGenerated
AssetReady
AssetDeleted
```

Events are immutable historical facts.

---

# **Query Model**

Assets support browsing similar to a filesystem.

Browsing is implemented through logical path prefixes.

Example:

```
2026/
2026/01/
2026/01/21/
2026/01/21/Whitset/
```

Queries may filter by:

* logical path
* date
* metadata fields
* asset type
* processing status
---

# **Integration Rules**

Assets must follow repository architecture rules:

* each table has a single owning package
* cross-package migrations are forbidden
* integration occurs through contracts and events
* foundational packages remain UI-free

These constraints are defined in RULES.md.

---

# **Design Principles**

The asset spine follows several key principles:

### **Canonical Identity**

Every piece of media has one stable identity.

### **Metadata Preservation**

Raw metadata is never destroyed.

### **Loose Coupling**

Other packages interact with assets through contracts/events.

### **Storage Abstraction**

Physical storage can change without affecting downstream systems.

### **Extensibility**

Future media types (video, audio, documents) fit the same model.

---

# **Future Capabilities**

The asset spine enables future features without redesign.

Examples:

* metadata search
* smart filtering
* AI training datasets
* automated tagging
* content deduplication
* cross-gallery asset reuse
* asset-level analytics
---

# **Summary**

The **Asset Spine** provides the canonical backbone for all media in the ProPhoto system.

Assets:

* own the media identity
* persist metadata permanently
* register storage and derivatives
* provide a stable reference for all downstream workflows

All higher-level features build on this foundation.

---

If you want, the **next thing I’d do** (and it will save you a lot of pain later) is build a **one-page diagram of the entire ProPhoto architecture** showing:

```
contracts
   ↓
access
   ↓
ingest → assets → galleries
                 ↓
           downloads / AI / exports
```

That diagram becomes the **mental model for the whole system** and helps prevent accidental rule violations when the codebase grows.