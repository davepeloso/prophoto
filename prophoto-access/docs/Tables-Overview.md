# Overview
## Design Principles
* 	•	Single-tenant with multi-tenant ready design - Built for Peloso Photography, structured for future SaaS expansion
* 	•	Soft deletes - Most tables use deleted_at for data recovery
* 	•	JSON flexibility - Settings and metadata stored as JSON for extensibility
* 	•	Foreign key constraints - Maintain referential integrity
* 	•	Contextual permissions - RBAC with resource-level scoping

⠀Naming Conventions
* 	•	Tables: Plural, snake_case (e.g., galleries, organization_documents)
* 	•	Columns: snake_case (e.g., created_at, imagekit_url)
* 	•	Foreign keys: {singular_table}_id (e.g., gallery_id, user_id)
* 	•	Pivot tables: {table1}_table2 alphabetically (e.g., organization_user)
* 	•	Polymorphic: {name}able_type and {name}able_id (e.g., itemable_type, itemable_id)

⠀
# Entity Relationship Diagram


``` mermaid
erDiagram
    studios ||--o{ users : has
    studios ||--o{ organizations : has
    
    users ||--o{ galleries : creates
    users ||--o{ sessions : creates
    users ||--o{ invoices : creates
    users ||--o{ staging_images : uploads
    users }o--o{ organizations : "belongs to"
    users }o--o{ roles : has
    users ||--o{ permission_contexts : has
    
    organizations ||--o{ sessions : books
    organizations ||--o{ galleries : owns
    organizations ||--o{ invoices : receives
    organizations ||--o{ booking_requests : requests
    organizations ||--o{ organization_documents : has
    
    sessions ||--o| galleries : generates
    
    galleries ||--o{ images : contains
    galleries ||--o| ai_generations : has
    
    images ||--o{ image_versions : has
    images ||--o{ image_interactions : receives
    
    ai_generations ||--o{ ai_generation_requests : has
    ai_generation_requests ||--o{ ai_generated_portraits : produces
    
    invoices ||--o{ invoice_items : contains
    invoice_items }o--|| sessions : references
    invoice_items }o--|| custom_fees : references
    
    staging_images }o--o| galleries : "assigned to"

```
# Core Tables
## Primary Entities
| **Table** | **Purpose** | **Key Relationships** |
|---|---|---|
| studios | Studio/tenant information | Root entity, has users & organizations |
| users | All user types (photographer, clients, subjects) | Belongs to studio, has roles |
| organizations | Client companies/entities | Belongs to studio, has users |
| galleries | Photo collections | Belongs to organization, has images |
| images | Individual photos | Belongs to gallery |
| sessions | Photo shoots | Links organization to galleries |
| invoices | Billing documents | Belongs to organization |
```

# Table Definitions
## 1. Core Studio & Users
### studios


sql
CREATE TABLE studios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subdomain VARCHAR(100) UNIQUE,
    business_name VARCHAR(255) NOT NULL,
    business_address VARCHAR(255),
    business_city VARCHAR(100),
    business_state VARCHAR(50),
    business_zip VARCHAR(20),
    business_phone VARCHAR(50),
    business_email VARCHAR(255),
    logo_url VARCHAR(500),
    website_url VARCHAR(500),
    timezone VARCHAR(100) DEFAULT 'UTC',
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_subdomain (subdomain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
### Settings JSON Structure:

json
{
  "invoice_prefix": "PELOSO-",
  "default_payment_terms": 30,
  "mileage_rate": 0.66,
  "rates": {
    "headshot": 100.00,
    "half_day": 450.00,
    "full_day": 800.00
  },
  "features": {
    "ai_portraits": true,
    "client_booking": true
  }
}


### users


sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    studio_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255),
    phone VARCHAR(50),
    avatar_url VARCHAR(500),
    timezone VARCHAR(100),
    role VARCHAR(50), *-- Cached from Spatie for queries*
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_email_per_studio (studio_id, email, deleted_at),
    INDEX idx_role (role),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


### roles (Spatie Package)


sql
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY roles_name_guard_name_unique (name, guard_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
### Predefined Roles:
* 	•	studio_user - Photographer/admin
* 	•	client_user - Organization contacts
* 	•	guest_user - Subjects (magic link access)
* 	•	vendor_user - External collaborators

⠀
### permissions (Spatie Package)


sql
CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY permissions_name_guard_name_unique (name, guard_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
### Permission Categories: See Role-Permission Matrix document

### model_has_roles (Spatie Package)


sql
CREATE TABLE model_has_roles (
    role_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    
    PRIMARY KEY (role_id, model_id, model_type),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    INDEX idx_model (model_type, model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


### permission_contexts (Custom - Context-Aware Permissions)


sql
CREATE TABLE permission_contexts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    contextable_type VARCHAR(255) NOT NULL,
    contextable_id BIGINT UNSIGNED NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    INDEX idx_contextable (contextable_type, contextable_id),
    INDEX idx_user_permission (user_id, permission_id),
    UNIQUE KEY unique_context (user_id, permission_id, contextable_type, contextable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
### Example Context:
* 	•	User #42 has can_view_gallery for Gallery #445 (subject access)
* 	•	User #15 has can_approve_images for Organization #3 (client access)

⠀
## 2. Organizations (Clients)
### organizations


sql
CREATE TABLE organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    studio_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50), *-- corporate, individual, agency*
    billing_email VARCHAR(255),
    billing_phone VARCHAR(50),
    billing_address VARCHAR(255),
    billing_city VARCHAR(100),
    billing_state VARCHAR(50),
    billing_zip VARCHAR(20),
    vendor_number VARCHAR(100), *-- Client's vendor # for photographer*
    insurance_code VARCHAR(100), *-- Client's insurance code*
    payment_terms VARCHAR(50), *-- Net 30, Net 60, etc.*
    tax_exempt BOOLEAN DEFAULT FALSE,
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id) ON DELETE CASCADE,
    INDEX idx_studio (studio_id),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
### Settings JSON Structure:

json
{
  "email_notifications": {
    "gallery_ready": true,
    "invoice_sent": false
  },
  "ai_portraits_enabled": true,
  "default_session_type": "headshot",
  "po_required": true
}


### organization_user (Pivot)


sql
CREATE TABLE organization_user (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(100), *-- marketing_contact, billing_contact, admin*
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_org_user (organization_id, user_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


### organization_documents


sql
CREATE TABLE organization_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL, *-- insurance, w9, contract, terms, branding, other*
    description TEXT,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100),
    file_size INT UNSIGNED,
    expires_at DATE NULL,
    requires_renewal BOOLEAN DEFAULT FALSE,
    reminded_at DATE NULL,
    is_required BOOLEAN DEFAULT FALSE,
    client_visible BOOLEAN DEFAULT TRUE,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id),
    INDEX idx_organization (organization_id),
    INDEX idx_type (type),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


## 3. Sessions & Calendar
### sessions


sql
CREATE TABLE sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    studio_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    session_type VARCHAR(50) NOT NULL, *-- headshot, half_day, full_day, event*
    scheduled_at TIMESTAMP,
    completed_at TIMESTAMP NULL,
    location VARCHAR(500),
    status VARCHAR(50) DEFAULT 'tentative', *-- tentative, scheduled, completed, processing, delivered, cancelled*
    google_event_id VARCHAR(255),
    rate DECIMAL(10, 2),
    notes TEXT,
    created_by_user_id BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_status (status),
    INDEX idx_organization (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


### booking_requests


sql
CREATE TABLE booking_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    studio_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    client_user_id BIGINT UNSIGNED NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    session_type VARCHAR(50) NOT NULL,
    requested_datetime TIMESTAMP NOT NULL,
    duration_minutes INT UNSIGNED DEFAULT 30,
    location VARCHAR(500),
    notes TEXT,
    status VARCHAR(50) DEFAULT 'pending', *-- pending, confirmed, denied, cancelled*
    session_id BIGINT UNSIGNED NULL,
    google_event_id VARCHAR(255),
    denial_reason TEXT,
    confirmed_at TIMESTAMP NULL,
    confirmed_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (client_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (confirmed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_requested_datetime (requested_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


## 4. Galleries & Images
### galleries


sql
CREATE TABLE galleries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    studio_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    session_id BIGINT UNSIGNED NULL,
    subject_name VARCHAR(255) NOT NULL,
    access_code VARCHAR(100) UNIQUE,
    magic_link_token VARCHAR(255) UNIQUE,
    magic_link_expires_at TIMESTAMP NULL,
    status VARCHAR(50) DEFAULT 'active', *-- active, completed, archived*
    ai_enabled BOOLEAN DEFAULT FALSE,
    ai_training_status VARCHAR(50), *-- null, ready, training, trained*
    image_count INT UNSIGNED DEFAULT 0,
    approved_count INT UNSIGNED DEFAULT 0,
    download_count INT UNSIGNED DEFAULT 0,
    last_activity_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    archived_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL,
    INDEX idx_organization (organization_id),
    INDEX idx_access_code (access_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


### images


sql
CREATE TABLE images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gallery_id BIGINT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    imagekit_file_id VARCHAR(255),
    imagekit_url VARCHAR(1000),
    imagekit_thumbnail_url VARCHAR(1000),
    file_size INT UNSIGNED,
    mime_type VARCHAR(100),
    width INT UNSIGNED,
    height INT UNSIGNED,
    metadata JSON,
    sort_order INT UNSIGNED DEFAULT 0,
    uploaded_at TIMESTAMP,
    uploaded_by_user_id BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_gallery (gallery_id),
    INDEX idx_sort_order (gallery_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
### Metadata JSON Structure (EXIF Data):

json
{
  "date_time_original": "2025-09-24 10:30:00",
  "camera_model": "Nikon Z6",
  "lens": "NIKKOR Z 40mm f/2",
  "iso": 250,
  "aperture": "f/6.7",
  "exposure_time": "1/80",
  "focal_length": "40.0mm",
  "focal_length_35mm": "40.0mm",
  "white_balance": "Auto"
}


### image_versions


sql
CREATE TABLE image_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    imagekit_file_id VARCHAR(255),
    imagekit_url VARCHAR(1000),
    file_size INT UNSIGNED,
    notes TEXT,
    created_by_user_id BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_image (image_id),
    UNIQUE KEY unique_image_version (image_id, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


### image_interactions


sql
CREATE TABLE image_interactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED,
    interaction_type VARCHAR(50) NOT NULL, *-- rating, note, approval, download, edit_request*
    rating TINYINT UNSIGNED, *-- 1-5 stars*
    note TEXT,
    approved_for_marketing BOOLEAN,
    edit_requested BOOLEAN,
    edit_notes TEXT,
    downloaded_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_image (image_id),
    INDEX idx_interaction_type (interaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


## 5. Staging & Ingest
### staging_images


sql
CREATE TABLE staging_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    studio_id BIGINT UNSIGNED NOT NULL,
    batch_id CHAR(36) NOT NULL, *-- UUID for upload batch*
    filename VARCHAR(255) NOT NULL,
    original_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500),
    file_size INT UNSIGNED,
    mime_type VARCHAR(100),
    metadata JSON,
    assigned_to_gallery_id BIGINT UNSIGNED NULL,
    assigned_at TIMESTAMP NULL,
    uploaded_by_user_id BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to_gallery_id) REFERENCES galleries(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_batch (batch_id),
    INDEX idx_assigned (assigned_to_gallery_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


## 6. AI Portrait Generation
### ai_generations


sql
CREATE TABLE ai_generations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gallery_id BIGINT UNSIGNED NOT NULL,
    subject_user_id BIGINT UNSIGNED NULL,
    fine_tune_id VARCHAR(255), *-- Astria model ID*
    training_image_count INT UNSIGNED,
    model_status VARCHAR(50) DEFAULT 'pending', *-- pending, training, trained, failed, expired*
    fine_tune_cost DECIMAL(8, 2) DEFAULT 1.50,
    model_created_at TIMESTAMP NULL,
    model_expires_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_gallery (gallery_id),
    INDEX idx_status (model_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


### ai_generation_requests


sql
CREATE TABLE ai_generation_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ai_generation_id BIGINT UNSIGNED NOT NULL,
    request_number INT UNSIGNED NOT NULL, *-- 1-5*
    custom_prompt TEXT,
    used_default_prompt BOOLEAN DEFAULT TRUE,
    generated_portrait_count INT UNSIGNED DEFAULT 8,
    generation_cost DECIMAL(8, 2),
    background_removal BOOLEAN DEFAULT FALSE,
    super_resolution BOOLEAN DEFAULT FALSE,
    status VARCHAR(50) DEFAULT 'pending', *-- pending, processing, completed, failed*
    error_message TEXT,
    liability_accepted_at TIMESTAMP,
    requested_by_user_id BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (ai_generation_id) REFERENCES ai_generations(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ai_generation (ai_generation_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


### ai_generated_portraits


sql
CREATE TABLE ai_generated_portraits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ai_generation_request_id BIGINT UNSIGNED NOT NULL,
    imagekit_file_id VARCHAR(255),
    imagekit_url VARCHAR(1000),
    imagekit_thumbnail_url VARCHAR(1000),
    file_size INT UNSIGNED,
    sort_order INT UNSIGNED DEFAULT 0,
    downloaded_by_subject BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (ai_generation_request_id) REFERENCES ai_generation_requests(id) ON DELETE CASCADE,
    INDEX idx_request (ai_generation_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


## 7. Invoicing & Payments
### invoices


sql
CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    studio_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    invoice_number VARCHAR(100) UNIQUE NOT NULL,
    quote_number VARCHAR(100),
    status VARCHAR(50) DEFAULT 'draft', *-- draft, quote, sent, paid, overdue, cancelled*
    stripe_invoice_id VARCHAR(255),
    issued_at DATE,
    due_at DATE,
    paid_at DATE NULL,
    subtotal DECIMAL(10, 2) DEFAULT 0.00,
    tax_rate DECIMAL(5, 2) DEFAULT 0.00,
    tax_amount DECIMAL(10, 2) DEFAULT 0.00,
    total DECIMAL(10, 2) DEFAULT 0.00,
    payment_method VARCHAR(50), *-- stripe, bank_transfer, check, wire, cash*
    payment_reference VARCHAR(255),
    payment_notes TEXT,
    po_number VARCHAR(100),
    notes TEXT,
    client_notes TEXT,
    created_by_user_id BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_organization (organization_id),
    INDEX idx_status (status),
    INDEX idx_invoice_number (invoice_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


### invoice_items


sql
CREATE TABLE invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    itemable_type VARCHAR(255), *-- Session, CustomFee (polymorphic)*
    itemable_id BIGINT UNSIGNED,
    description TEXT NOT NULL,
    quantity DECIMAL(10, 2) DEFAULT 1.00,
    unit_price DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice (invoice_id),
    INDEX idx_itemable (itemable_type, itemable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


### custom_fees


sql
CREATE TABLE custom_fees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL, *-- mileage, post_processing, travel, second_shooter, assistant, equipment, insurance*
    description TEXT NOT NULL,
    quantity DECIMAL(10, 2) DEFAULT 1.00,
    unit_price DECIMAL(10, 2) NOT NULL,
    calculation_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
### Calculation Data Example (Mileage):

json
{
  "miles": 109,
  "rate": 0.66,
  "from": "Studio",
  "to": "UCLA Medical Center"
}


## 8. Notifications & Messages
### notifications (Laravel Default)


sql
CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id BIGINT UNSIGNED NOT NULL,
    data JSON NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_notifiable (notifiable_type, notifiable_id),
    INDEX idx_read_at (read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


### messages (Optional - Persistent Messages)


sql
CREATE TABLE messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    studio_id BIGINT UNSIGNED NOT NULL,
    sender_user_id BIGINT UNSIGNED,
    recipient_user_id BIGINT UNSIGNED NULL,
    gallery_id BIGINT UNSIGNED NULL,
    image_id BIGINT UNSIGNED NULL,
    subject VARCHAR(255),
    body TEXT NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (studio_id) REFERENCES studios(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE,
    FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE,
    INDEX idx_recipient (recipient_user_id),
    INDEX idx_gallery (gallery_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


# Indexes & Performance
## Primary Indexes
### All tables have:
* 	•	Primary key on id (auto-increment)
* 	•	Foreign key indexes automatically created
* 	•	Timestamp indexes on commonly queried date fields

⠀Additional Recommended Indexes


sql
*-- Frequently queried combinations*
CREATE INDEX idx_gallery_organization_status ON galleries(organization_id, status);
CREATE INDEX idx_invoice_org_status ON invoices(organization_id, status);
CREATE INDEX idx_session_scheduled_status ON sessions(scheduled_at, status);

*-- Full-text search (if needed)*
ALTER TABLE galleries ADD FULLTEXT idx_subject_search (subject_name);
ALTER TABLE organizations ADD FULLTEXT idx_org_search (name);
## Query Optimization Notes
1. 	1	Soft Deletes: Always include deleted_at IS NULL in queries
2. 	2	Studio Scoping: Most queries should filter by studio_id first
3. 	3	JSON Columns: Use JSON_EXTRACT() for querying nested data
4. 	4	Large Tables: images and image_interactions will grow large - monitor query performance

⠀
# Data Types & Conventions
## Common Patterns
| **Column Pattern** | **Data Type** | **Notes** |
|---|---|---|
| id | BIGINT UNSIGNED | Auto-increment primary key |
| {table}_id | BIGINT UNSIGNED | Foreign key reference |
| name, title | VARCHAR(255) | Standard text fields |
| description, notes | TEXT | Longer text content |
| email | VARCHAR(255) | Email addresses |
| phone | VARCHAR(50) | Phone numbers (flexible format) |
| url, path | VARCHAR(500-1000) | File paths and URLs |
| status, type | VARCHAR(50) | Enum-like values |
| settings, metadata | JSON | Flexible structured data |
| amount, price | DECIMAL(10, 2) | Currency values |
| is_{flag} | BOOLEAN | Boolean flags |
| {action}_at | TIMESTAMP | Datetime stamps |
| created_at, updated_at | TIMESTAMP | Standard timestamps |
| deleted_at | TIMESTAMP NULL | Soft delete timestamp |
## Decimal Precision
* 	•	Currency: DECIMAL(10, 2) - Up to $99,999,999.99
* 	•	Percentages: DECIMAL(5, 2) - Up to 999.99%
* 	•	Quantities: DECIMAL(10, 2) - Flexible for fractional values

⠀
# Migration Order
### When running migrations, follow this dependency order:
1. 	1	studios
2. 	2	users
3. 	3	roles, permissions (Spatie)
4. 	4	model_has_roles, model_has_permissions (Spatie)
5. 	5	permission_contexts
6. 	6	organizations
7. 	7	organization_user
8. 	8	organization_documents
9. 	9	sessions
10. 	10	booking_requests
11. 	11	galleries
12. 	12	images
13. 	13	image_versions
14. 	14	image_interactions
15. 	15	staging_images
16. 	16	ai_generations
17. 	17	ai_generation_requests
18. 	18	ai_generated_portraits
19. 	19	invoices
20. 	20	invoice_items
21. 	21	custom_fees
22. 	22	notifications
23. 	23	messages

⠀
# Next Steps
### After reviewing this schema:
1. 	1	Generate Laravel migrations from these definitions
2. 	2	Create Eloquent models with relationships
3. 	3	Set up seeders for roles, permissions, and test data
4. 	4	Configure Spatie permissions package
5. 	5	Test relationships in Tinker

⠀
### End of Database Schema Document
### Looks good!, lets move on to the next steps.. should i copy these as we go or will you create a artifact repo to download?
### I'll create downloadable artifacts as we go!

# Here's the plan:
## Option 1: Individual Artifacts (What I'll do)
### I'll create each document as a separate artifact that you can:
* 	•	Download individually
* 	•	Copy/paste into your IDE
* 	•	Save to your repo as markdown files

⠀Option 2: Bulk Download (At the end)
### Once we've created all the docs, I can also:
* 	•	Create a single ZIP structure
* 	•	Organize into folders
* 	•	Include a README with the doc hierarchy

⠀
# Recommended File Structure for Your Repo


solo/
├── docs/
│   ├── README.md (index of all docs)
│   ├── 01-database-schema.md ✅ (just created)
│   ├── 02-role-permission-matrix.md
│   ├── 03-application-map.md
│   ├── 04-system-architecture.md
│   ├── 05-api-integrations.md
│   ├── 06-cicd-pipeline.md
│   ├── 07-ingest-interface.md
│   ├── 08-product-requirements.md
│   └── 09-user-journeys.md
├── database/
│   └── migrations/ (we'll generate these next)
├── app/
│   └── Models/ (Eloquent models)
└── ...


# What's Next?
### Priority Order:
1. 	1	✅ Database Schema - Done!
2. 	2	Role-Permission Matrix - Complete RBAC reference
	* 	•	All permissions defined
	* 	•	Role assignments
	* 	•	Context scoping examples
	* 	•	Policy examples
1. 	3	Application Map - All views/pages documented
	* 	•	URL structure
	* 	•	Page components
	* 	•	User flows
	* 	•	Navigation hierarchy
1. 	4	System Architecture - How everything connects
	* 	•	Technology stack
	* 	•	Service integrations
	* 	•	Data flow diagrams
	* 	•	Security model
1. 	5	API Integration Guide - External services
	* 	•	Google Calendar setup
	* 	•	Stripe configuration
	* 	•	ImageKit integration
	* 	•	Astria AI setup
1. 	6	CI/CD Pipeline - Deployment guide
	* 	•	GitHub Actions workflows
	* 	•	Environment setup
	* 	•	Deployment process
1. 	7	Ingest Interface Spec - Staging system technical details
2. 	8	Product Requirements (PRD) - High-level goals & features

⠀
# Should I create #2 next (Role-Permission Matrix)?
### Or would you prefer to:
* 	•	Generate the Laravel migrations from the schema we just created?
* 	•	Jump to a different document?

⠀What feels like the right next step?
### Laravel migrations then the Role-Permission Matrix 🎯

### Perfect! Let's generate the migrations based on our schema:

```bash
# Core authentication tables
php artisan make:migration create_studios_table --create=studios
php artisan make:migration create_users_table --create=users
php artisan make:migration create_roles_and_permissions_tables --create=roles
php artisan make:migration create_permission_contexts_table --create=permission_contexts

# Business entities
php artisan make:migration create_organizations_table --create=organizations
php artisan make:migration create_organization_documents_table --create=organization_documents
php artisan make:migration create_organization_user_table --create=organization_user

# Core workflow
php artisan make:migration create_sessions_table --create=sessions
php artisan make:migration create_booking_requests_table --create=booking_requests
php artisan make:migration create_galleries_table --create=galleries

# Content and media
php artisan make:migration create_images_table --create=images
php artisan make:migration create_image_versions_table --create=image_versions
php artisan make:migration create_image_interactions_table --create=image_interactions

# Ingest system
php artisan make:migration create_staging_images_table --create=staging_images

# AI features
php artisan make:migration create_ai_generations_table --create=ai_generations
php artisan make:migration create_ai_generation_requests_table --create=ai_generation_requests
php artisan make:migration create_ai_generated_portraits_table --create=ai_generated_portraits

# Billing
php artisan make:migration create_invoices_table --create=invoices
php artisan make:migration create_invoice_items_table --create=invoice_items
php artisan make:migration create_custom_fees_table --create=custom_fees

# Communication
php artisan make:migration create_messages_table --create=messages
```

**Ready to proceed to the Role-Permission Matrix document next?** This will define all the RBAC permissions and roles.
