# ProPhoto Data Models

Complete database schema documentation covering all tables, relationships, and migrations across 11 packages.

## Overview

**Total Migrations:** 42 (across all packages)  
**Total Tables:** ~30+ tables (estimated)  
**Multi-tenancy:** All tables include studio_id, organization_id  
**ORM:** Eloquent (Laravel)

## Migration Summary by Package

| Package | Migrations | Key Tables |
|---------|-----------|-----------|
| prophoto-access | 6 | organizations, studios, roles, permissions |
| prophoto-assets | 6 | assets, asset_metadata_raw, asset_metadata_normalized, asset_derivatives, asset_session_contexts |
| prophoto-gallery | 15 | galleries, images, image_versions, image_tags, gallery_collections, gallery_shares, gallery_templates, gallery_comments, gallery_access_logs |
| prophoto-booking | 2 | photo_sessions, booking_requests |
| prophoto-ingest | 2 | session_assignments, session_assignment_decisions |
| prophoto-intelligence | 3 | intelligence_runs, asset_labels, asset_embeddings |
| prophoto-ai | 3 | ai_generations, ai_generation_requests, ai_generated_portraits |
| prophoto-invoicing | 3 | invoices, invoice_items, custom_fees |
| prophoto-interactions | 1 | image_interactions |
| prophoto-notifications | 1 | messages |
| prophoto-contracts | 0 | (interfaces only) |

---

## Table Schemas (By Package)

### prophoto-access

#### organizations
```sql
CREATE TABLE organizations (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    logo_url VARCHAR(255),
    website VARCHAR(255),
    timezone VARCHAR(60),
    
    -- Audit
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

**Model:** `Organization`  
**Relationships:**
- `studios()` - HasMany → Studio
- `documents()` - HasMany → OrganizationDocument

#### studios
```sql
CREATE TABLE studios (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    phone VARCHAR(20),
    email VARCHAR(255),
    website VARCHAR(255),
    
    -- Audit
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
);
```

**Model:** `Studio`  
**Relationships:**
- `organization()` - BelongsTo → Organization
- `sessions()` - HasMany → Session
- `galleries()` - HasMany → Gallery
- `assets()` - HasMany → Asset

#### roles (Spatie/laravel-permission)
```sql
CREATE TABLE roles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    guard_name VARCHAR(255) DEFAULT 'web',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Spatie Package** - Standard Laravel permission role

#### permissions (Spatie/laravel-permission)
```sql
CREATE TABLE permissions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    guard_name VARCHAR(255) DEFAULT 'web',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Spatie Package** - Standard Laravel permission definition

#### role_has_permissions
```sql
CREATE TABLE role_has_permissions (
    permission_id BIGINT,
    role_id BIGINT,
    
    PRIMARY KEY (permission_id, role_id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);
```

**Spatie Package** - Role→Permission mapping

#### model_has_roles
```sql
CREATE TABLE model_has_roles (
    role_id BIGINT,
    model_id BIGINT,
    model_type VARCHAR(255),
    
    PRIMARY KEY (role_id, model_id, model_type),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);
```

**Spatie Package** - User/Model→Role mapping

#### organization_documents
```sql
CREATE TABLE organization_documents (
    id UUID PRIMARY KEY,
    organization_id UUID NOT NULL,
    title VARCHAR(255) NOT NULL,
    document_type VARCHAR(100),
    content LONGTEXT,
    version INT DEFAULT 1,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
);
```

**Model:** `OrganizationDocument`

---

### prophoto-assets

#### assets
```sql
CREATE TABLE assets (
    id UUID PRIMARY KEY,
    studio_id UUID NOT NULL,
    organization_id UUID NOT NULL,
    
    -- File Info
    type ENUM('image', 'video', 'audio', 'document'),
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    bytes BIGINT NOT NULL,
    checksum_sha256 VARCHAR(64) UNIQUE NOT NULL,
    
    -- Storage Location
    storage_driver VARCHAR(100),  -- 's3', 'local', etc.
    storage_key_original VARCHAR(500),  -- Path in storage
    logical_path VARCHAR(500),    -- Display path
    
    -- Processing Status
    status VARCHAR(50),           -- 'processing', 'ready', 'failed'
    
    -- Timestamps
    captured_at TIMESTAMP,
    ingested_at TIMESTAMP,
    
    -- Metadata JSON (denormalized)
    metadata JSON,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id),
    INDEX (studio_id, organization_id),
    INDEX (checksum_sha256),
    INDEX (status)
);
```

**Model:** `Asset`  
**Relationships:**
- `derivatives()` - HasMany → AssetDerivative
- `rawMetadata()` - HasMany → AssetMetadataRaw
- `normalizedMetadata()` - HasMany → AssetMetadataNormalized
- `sessionContexts()` - HasMany → AssetSessionContext

#### asset_metadata_raw
```sql
CREATE TABLE asset_metadata_raw (
    id UUID PRIMARY KEY,
    asset_id UUID NOT NULL,
    
    -- Metadata Source
    source VARCHAR(50),  -- 'exif', 'iptc', 'xmp', 'custom'
    
    -- Raw Key-Value Pairs
    key VARCHAR(255),
    value LONGTEXT,  -- JSON or serialized
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    INDEX (asset_id),
    INDEX (source, key)
);
```

**Model:** `AssetMetadataRaw`

#### asset_metadata_normalized
```sql
CREATE TABLE asset_metadata_normalized (
    id UUID PRIMARY KEY,
    asset_id UUID NOT NULL,
    
    -- Normalized Fields
    title VARCHAR(500),
    description TEXT,
    copyright VARCHAR(255),
    date_created TIMESTAMP,
    
    -- Location (JSON: {lat, lng, altitude})
    location JSON,
    
    -- Camera/Device Info (JSON)
    device_info JSON,
    
    -- Custom Metadata (JSON)
    custom_fields JSON,
    
    -- Provenance
    extracted_from_version VARCHAR(50),
    normalized_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    INDEX (asset_id)
);
```

**Model:** `AssetMetadataNormalized`

#### asset_derivatives
```sql
CREATE TABLE asset_derivatives (
    id UUID PRIMARY KEY,
    asset_id UUID NOT NULL,
    
    -- Derivative Type
    type ENUM('thumbnail', 'preview', 'watermarked', 'compressed'),
    
    -- Storage Location
    storage_key VARCHAR(500),
    mime_type VARCHAR(100),
    bytes BIGINT,
    width INT,
    height INT,
    
    -- Generation Info
    generator_version VARCHAR(50),
    generated_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    INDEX (asset_id, type)
);
```

**Model:** `AssetDerivative`

#### asset_session_contexts
```sql
CREATE TABLE asset_session_contexts (
    id UUID PRIMARY KEY,
    asset_id UUID NOT NULL,
    session_id UUID NOT NULL,
    
    -- Source Decision
    source_decision_id UUID,
    decision_type VARCHAR(50),    -- 'auto_assign', 'manual_assign'
    
    -- Subject Info
    subject_type VARCHAR(50),     -- 'asset', 'ingest_item'
    subject_id UUID,
    ingest_item_id UUID,
    
    -- Confidence
    confidence_tier VARCHAR(50),  -- 'high', 'medium', 'low'
    confidence_score INT,
    
    -- Algorithm
    algorithm_version VARCHAR(50),
    
    -- Timing
    occurred_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    FOREIGN KEY (session_id) REFERENCES photo_sessions(id),
    INDEX (asset_id),
    INDEX (session_id),
    INDEX (confidence_tier)
);
```

**Model:** `AssetSessionContext`

---

### prophoto-gallery

#### galleries
```sql
CREATE TABLE galleries (
    id UUID PRIMARY KEY,
    studio_id UUID NOT NULL,
    organization_id UUID NOT NULL,
    session_id UUID NOT NULL,
    
    -- Display Info
    subject_name VARCHAR(255),
    status ENUM('active', 'completed', 'archived'),
    
    -- Access Control
    access_code VARCHAR(20) UNIQUE,      -- e.g., "JOHN-2024-XYZW"
    magic_link_token VARCHAR(64) UNIQUE,
    magic_link_expires_at TIMESTAMP,
    
    -- AI Features
    ai_enabled BOOLEAN DEFAULT FALSE,
    ai_training_status ENUM('ready', 'training', 'trained'),
    
    -- Stats
    image_count INT DEFAULT 0,
    approved_count INT DEFAULT 0,
    download_count INT DEFAULT 0,
    
    -- Timestamps
    last_activity_at TIMESTAMP,
    delivered_at TIMESTAMP,
    completed_at TIMESTAMP,
    archived_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id),
    FOREIGN KEY (session_id) REFERENCES photo_sessions(id),
    INDEX (studio_id, organization_id),
    INDEX (access_code),
    INDEX (magic_link_token),
    INDEX (status)
);
```

**Model:** `Gallery`  
**Relationships:**
- `session()` - BelongsTo → Session
- `studio()` - BelongsTo → Studio
- `images()` - HasMany → Image
- `collections()` - HasMany → GalleryCollection
- `shares()` - HasMany → GalleryShare
- `comments()` - HasMany → GalleryComment

#### images
```sql
CREATE TABLE images (
    id UUID PRIMARY KEY,
    gallery_id UUID NOT NULL,
    asset_id UUID NOT NULL,
    
    -- Display
    display_order INT,
    approved BOOLEAN DEFAULT FALSE,
    approved_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE,
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    INDEX (gallery_id),
    INDEX (asset_id),
    INDEX (approved)
);
```

**Model:** `Image`

#### image_versions
```sql
CREATE TABLE image_versions (
    id UUID PRIMARY KEY,
    image_id UUID NOT NULL,
    
    -- Version Type
    type VARCHAR(50),  -- 'original', 'watermarked', 'thumb'
    
    -- Storage
    asset_derivative_id UUID,
    storage_key VARCHAR(500),
    
    created_at TIMESTAMP,
    
    FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE,
    INDEX (image_id)
);
```

**Model:** `ImageVersion`

#### image_tags
```sql
CREATE TABLE image_tags (
    id UUID PRIMARY KEY,
    image_id UUID NOT NULL,
    tag VARCHAR(100),
    
    FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE,
    INDEX (image_id),
    INDEX (tag)
);
```

**Model:** `ImageTag`

#### gallery_collections
```sql
CREATE TABLE gallery_collections (
    id UUID PRIMARY KEY,
    gallery_id UUID NOT NULL,
    
    -- Collection Info
    name VARCHAR(255),
    description TEXT,
    display_order INT,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE,
    INDEX (gallery_id)
);
```

**Model:** `GalleryCollection`

#### gallery_shares
```sql
CREATE TABLE gallery_shares (
    id UUID PRIMARY KEY,
    gallery_id UUID NOT NULL,
    
    -- Share Info
    email VARCHAR(255),
    access_level ENUM('view', 'download', 'comment'),
    shared_at TIMESTAMP,
    
    -- Track
    first_accessed_at TIMESTAMP,
    last_accessed_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE,
    INDEX (gallery_id),
    INDEX (email)
);
```

**Model:** `GalleryShare`

#### gallery_templates
```sql
CREATE TABLE gallery_templates (
    id UUID PRIMARY KEY,
    organization_id UUID,
    
    name VARCHAR(255),
    layout VARCHAR(100),
    theme VARCHAR(100),
    
    config JSON,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id),
    INDEX (organization_id)
);
```

**Model:** `GalleryTemplate`

#### gallery_comments
```sql
CREATE TABLE gallery_comments (
    id UUID PRIMARY KEY,
    gallery_id UUID NOT NULL,
    image_id UUID,
    
    email VARCHAR(255),
    name VARCHAR(255),
    content TEXT,
    
    created_at TIMESTAMP,
    
    FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE,
    FOREIGN KEY (image_id) REFERENCES images(id),
    INDEX (gallery_id)
);
```

**Model:** `GalleryComment`

#### gallery_access_logs
```sql
CREATE TABLE gallery_access_logs (
    id UUID PRIMARY KEY,
    gallery_id UUID NOT NULL,
    
    -- Access Info
    ip_address VARCHAR(45),
    user_agent TEXT,
    accessed_at TIMESTAMP,
    
    FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE,
    INDEX (gallery_id, accessed_at)
);
```

**Model:** `GalleryAccessLog`

---

### prophoto-booking

#### photo_sessions
```sql
CREATE TABLE photo_sessions (
    id UUID PRIMARY KEY,
    studio_id UUID NOT NULL,
    organization_id UUID NOT NULL,
    
    -- Session Info
    subject_name VARCHAR(255) NOT NULL,
    session_type VARCHAR(100),  -- 'portrait', 'family', 'wedding', etc.
    location VARCHAR(255),
    
    -- Scheduling
    scheduled_at TIMESTAMP,
    completed_at TIMESTAMP,
    
    -- Status
    status ENUM('tentative', 'scheduled', 'completed', 'processing', 'delivered', 'cancelled'),
    
    -- Integration
    google_event_id VARCHAR(255),
    
    -- Pricing
    rate DECIMAL(10,2),
    notes TEXT,
    
    -- Tracking
    created_by_user_id UUID,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id),
    INDEX (studio_id, organization_id),
    INDEX (status),
    INDEX (scheduled_at)
);
```

**Model:** `Session`  
**Relationships:**
- `studio()` - BelongsTo → Studio
- `organization()` - BelongsTo → Organization
- `gallery()` - HasOne → Gallery
- `createdBy()` - BelongsTo → User

#### booking_requests
```sql
CREATE TABLE booking_requests (
    id UUID PRIMARY KEY,
    studio_id UUID NOT NULL,
    
    -- Client Info
    email VARCHAR(255),
    name VARCHAR(255),
    phone VARCHAR(20),
    
    -- Request
    requested_date DATE,
    session_type VARCHAR(100),
    notes TEXT,
    
    -- Status
    status ENUM('pending', 'approved', 'rejected', 'converted'),
    
    -- Link to Session
    photo_session_id UUID,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id),
    FOREIGN KEY (photo_session_id) REFERENCES photo_sessions(id),
    INDEX (studio_id, status)
);
```

**Model:** `BookingRequest`

---

### prophoto-ingest

#### session_assignments
```sql
CREATE TABLE session_assignments (
    id UUID PRIMARY KEY,
    ingest_item_id UUID,
    asset_id UUID NOT NULL,
    session_id UUID NOT NULL,
    
    -- Decision Type
    decision_type VARCHAR(50),  -- 'auto_assign', 'manual_assign', 'unassigned'
    
    -- Confidence
    confidence_score INT,
    confidence_tier VARCHAR(50),
    
    -- Status
    status VARCHAR(50),  -- 'active', 'replaced', 'reverted'
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    FOREIGN KEY (session_id) REFERENCES photo_sessions(id),
    INDEX (asset_id, session_id),
    INDEX (decision_type)
);
```

**Model:** `SessionAssignment`

#### session_assignment_decisions
```sql
CREATE TABLE session_assignment_decisions (
    id UUID PRIMARY KEY,
    ingest_item_id UUID,
    asset_id UUID,
    
    -- Decision Info
    selected_session_id UUID,
    decision_type VARCHAR(50),    -- 'auto_assign', 'manual_assign'
    
    -- Algorithm Info
    algorithm_version VARCHAR(50),
    candidate_count INT,
    top_score INT,
    
    -- Audit
    decided_by_user_id UUID,
    decided_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (selected_session_id) REFERENCES photo_sessions(id),
    INDEX (asset_id),
    INDEX (decision_type)
);
```

**Model:** `SessionAssignmentDecision`

---

### prophoto-intelligence

#### intelligence_runs
```sql
CREATE TABLE intelligence_runs (
    id UUID PRIMARY KEY,
    asset_id UUID NOT NULL,
    
    -- Run Info
    generator_type VARCHAR(100),  -- 'labels', 'embeddings', 'classification'
    status VARCHAR(50),           -- 'pending', 'running', 'complete', 'failed'
    
    -- Results
    results JSON,
    error_message TEXT,
    
    -- Timing
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    INDEX (asset_id, generator_type),
    INDEX (status)
);
```

**Model:** `IntelligenceRun`

#### asset_labels
```sql
CREATE TABLE asset_labels (
    id UUID PRIMARY KEY,
    asset_id UUID NOT NULL,
    intelligence_run_id UUID,
    
    label VARCHAR(255),
    score FLOAT,  -- Confidence 0-1
    
    created_at TIMESTAMP,
    
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    FOREIGN KEY (intelligence_run_id) REFERENCES intelligence_runs(id),
    INDEX (asset_id),
    INDEX (label)
);
```

**Model:** `AssetLabel`

#### asset_embeddings
```sql
CREATE TABLE asset_embeddings (
    id UUID PRIMARY KEY,
    asset_id UUID NOT NULL,
    intelligence_run_id UUID,
    
    -- Vector Embedding (stored as JSON array or binary)
    embedding LONGBLOB,  -- Serialized vector
    dimension INT,
    
    -- Metadata
    embedding_model VARCHAR(100),
    generated_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    FOREIGN KEY (intelligence_run_id) REFERENCES intelligence_runs(id),
    INDEX (asset_id)
);
```

**Model:** `AssetEmbedding`

---

### prophoto-ai

#### ai_generations
```sql
CREATE TABLE ai_generations (
    id UUID PRIMARY KEY,
    studio_id UUID NOT NULL,
    gallery_id UUID,
    
    -- Model Info
    model_name VARCHAR(100),
    status VARCHAR(50),  -- 'training', 'ready', 'failed'
    
    -- Training Data
    trained_on_asset_count INT,
    training_started_at TIMESTAMP,
    training_completed_at TIMESTAMP,
    
    -- Cost Tracking
    training_cost_usd DECIMAL(10,2),
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id),
    FOREIGN KEY (gallery_id) REFERENCES galleries(id),
    INDEX (studio_id, status)
);
```

**Model:** `AiGeneration`

#### ai_generation_requests
```sql
CREATE TABLE ai_generation_requests (
    id UUID PRIMARY KEY,
    ai_generation_id UUID NOT NULL,
    gallery_id UUID NOT NULL,
    
    -- Request
    style VARCHAR(100),
    quantity INT DEFAULT 1,
    prompt TEXT,
    
    -- Status
    status VARCHAR(50),  -- 'pending', 'processing', 'complete', 'failed'
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (ai_generation_id) REFERENCES ai_generations(id),
    FOREIGN KEY (gallery_id) REFERENCES galleries(id),
    INDEX (status)
);
```

**Model:** `AiGenerationRequest`

#### ai_generated_portraits
```sql
CREATE TABLE ai_generated_portraits (
    id UUID PRIMARY KEY,
    request_id UUID NOT NULL,
    asset_id UUID,
    
    -- Output
    storage_key VARCHAR(500),
    generation_seed INT,
    
    approved BOOLEAN DEFAULT FALSE,
    approved_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (request_id) REFERENCES ai_generation_requests(id),
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    INDEX (request_id)
);
```

**Model:** `AiGeneratedPortrait`

---

### prophoto-invoicing

#### invoices
```sql
CREATE TABLE invoices (
    id UUID PRIMARY KEY,
    studio_id UUID NOT NULL,
    session_id UUID NOT NULL,
    
    -- Invoice Info
    invoice_number VARCHAR(50) UNIQUE,
    status VARCHAR(50),  -- 'draft', 'sent', 'paid', 'overdue'
    
    -- Client
    client_email VARCHAR(255),
    client_name VARCHAR(255),
    
    -- Amounts
    subtotal DECIMAL(10,2),
    tax DECIMAL(10,2),
    total DECIMAL(10,2),
    
    -- Dates
    issue_date DATE,
    due_date DATE,
    paid_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id),
    FOREIGN KEY (session_id) REFERENCES photo_sessions(id),
    INDEX (studio_id, status)
);
```

**Model:** `Invoice`

#### invoice_items
```sql
CREATE TABLE invoice_items (
    id UUID PRIMARY KEY,
    invoice_id UUID NOT NULL,
    
    -- Item
    description VARCHAR(500),
    quantity INT,
    unit_price DECIMAL(10,2),
    amount DECIMAL(10,2),
    
    created_at TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX (invoice_id)
);
```

**Model:** `InvoiceItem`

#### custom_fees
```sql
CREATE TABLE custom_fees (
    id UUID PRIMARY KEY,
    invoice_id UUID NOT NULL,
    
    -- Fee
    name VARCHAR(255),
    type VARCHAR(50),  -- 'rush', 'printing', 'delivery', 'custom'
    amount DECIMAL(10,2),
    
    created_at TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX (invoice_id)
);
```

**Model:** `CustomFee`

---

### prophoto-interactions

#### image_interactions
```sql
CREATE TABLE image_interactions (
    id UUID PRIMARY KEY,
    image_id UUID NOT NULL,
    
    -- Interaction
    type VARCHAR(50),  -- 'rating', 'approval', 'comment', 'flag'
    
    -- Data
    rating INT,            -- 1-5
    approved BOOLEAN,
    comment TEXT,
    
    -- Client Info
    email VARCHAR(255),
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (image_id) REFERENCES images(id),
    INDEX (image_id, type)
);
```

**Model:** `ImageInteraction`

---

### prophoto-notifications

#### messages
```sql
CREATE TABLE messages (
    id UUID PRIMARY KEY,
    
    -- Message Info
    recipient_email VARCHAR(255),
    subject VARCHAR(500),
    body LONGTEXT,
    
    -- Metadata
    message_type VARCHAR(50),  -- 'gallery_ready', 'invoice_sent', etc.
    related_id UUID,           -- gallery_id, invoice_id, etc.
    
    -- Delivery Status
    sent_at TIMESTAMP,
    delivered_at TIMESTAMP,
    failed_at TIMESTAMP,
    failure_reason TEXT,
    
    -- Retry
    retry_count INT DEFAULT 0,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX (recipient_email, message_type),
    INDEX (sent_at)
);
```

**Model:** `Message`

---

## Key Relationships (Entity Diagram)

```
Organization (1)
  ├── Studios (M)
  │   ├── Sessions (M)
  │   │   ├── Galleries (1)
  │   │   │   ├── Images (M) → Assets (1)
  │   │   │   ├── Collections (M)
  │   │   │   ├── Shares (M)
  │   │   │   ├── Comments (M)
  │   │   │   └── AccessLogs (M)
  │   │   ├── Invoices (M)
  │   │   └── BookingRequests (M)
  │   │
  │   ├── Assets (M)
  │   │   ├── Metadata (M) [raw, normalized]
  │   │   ├── Derivatives (M)
  │   │   ├── SessionContexts (M) → Sessions
  │   │   ├── Labels (M) [intelligence]
  │   │   └── Embeddings (M) [intelligence]
  │   │
  │   └── AiGenerations (M)
  │       └── GenerationRequests (M)
  │           └── GeneratedPortraits (M) → Assets
  │
  ├── Templates (M)
  ├── Documents (M)
  └── Roles (M) [Spatie]

IngestItems (orphaned, ephemeral)
  ├── → Assets (ingest processing)
  └── → SessionAssignments (matching result)

SessionAssignments
  ├── → Assets
  └── → Sessions

Permissions & Roles
  └── Model → Role → Permission
```

---

## Indexing Strategy

### Primary Indexes (Used in Queries)
- `(studio_id, organization_id)` - Tenant isolation
- `(studio_id, status)` - Status filtering
- `(asset_id)` - Asset lookups
- `(gallery_id)` - Gallery display
- `(session_id)` - Session queries
- `(decision_type)` - Matching decisions

### Search/Filter Indexes
- `(asset_id, type)` - Derivative queries
- `(image_id, type)` - Interaction queries
- `(label)` - Intelligence label search

---

## Multi-Tenancy Model

**Isolation Level:** Studio & Organization

All key tables include:
```
studio_id UUID NOT NULL
organization_id UUID NOT NULL
```

**Query Pattern:**
```php
Asset::where('studio_id', $studioId)
     ->where('organization_id', $orgId)
     ->get();
```

---

## Related Documentation

- [Component Inventory](./component-inventory.md) - Models and relationships
- [API Contracts](./api-contracts.md) - DTOs used in queries
- [Source Tree Analysis](./source-tree-analysis.md) - Migration file locations
