# ### Build single-tenant FIRST, but structure it tenant-ready:
1. 1	Use "Studio" terminology in code instead of hardcoding your business name

⠀
php
   *// Good (tenant-ready)*
   $studio = Studio::first();
   $studioName = $studio->name;
   
   *// Bad (hardcoded)*
   $studioName = "Peloso Photography";
1. 2	Design schema without global assumptions
   * •	Already doing this with Organizations (clients)
   * •	Your studio is just the first Organization
2. 3	Keep business logic tenant-agnostic
   * •	Invoice generation works for any studio
   * •	Gallery workflows are universal
3. 4	Test with fake second studio locally
   * •	Even in single-tenant, you can simulate another studio
   * •	Helps catch hardcoded assumptions

⠀
# Decision Time
### Path A: Pure Single-Tenant (Simpler Today)
* •	Build just for you
* •	Can add multi-tenancy later if needed
* •	Faster to launch
* •	Recommended if: You want to focus on your business first

⠀Path B: Multi-Tenant from Day 1 (Future-Proof)
* •	Design with multiple studios in mind
* •	Slightly more complex setup
* •	Easier to scale later
* •	Recommended if: You see SaaS potential and want to be ready

⠀
### I'd suggest Path A with tenant-aware design principles.
Build it for Peloso Photography, but write code like:

php
$studio->invoice_prefix  *// Not "PELOSO-"*
$studio->business_name   *// Not hardcoded*
$studio->settings        *// Flexible per studio*
### This way, if you decide to go SaaS in a year, it's a weekend project to add tenancy package, not a rewrite.
### What do you think? Build for yourself first, or go multi-tenant from the start?
### single-tenant FIRST
### Perfect choice! Single-tenant first, tenant-ready design.

# Single-Tenant Architecture with SaaS-Ready Principles
### Build for Peloso Photography, code like it's multi-tenant.

# Data Model Design - Core Principles
### Instead of hardcoding your business:

php
*// ❌ Bad*
'business_name' => 'Peloso Photography'

*// ✅ Good*
'business_name' => $studio->business_name
### Studio becomes your "tenant" concept:
* •	One Studio record (yours)
* •	All settings stored in database
* •	Easy to add more studios later

⠀
# Let's Design the Database Schema
### I'll organize by domain area:
# 1. Core Studio & Users
## studios table


php
id
name                    *// "Peloso Photography"*
subdomain               *// "peloso" (for future: peloso.solo.com)*
business_name           *// "Chas. Peloso Photography INC"*
business_address
business_city
business_state
business_zip
business_phone
business_email
logo_url
website_url
timezone                *// "America/Los_Angeles"*
settings (JSON)         *// Flexible settings*
created_at
updated_at
### Settings JSON example:

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


## users table


php
id
studio_id               *// Always 1 for now (your studio)*
name
email
email_verified_at
password
phone
avatar_url
timezone
role                    *// Will use Spatie roles, but helpful for queries*
remember_token
created_at
updated_at
deleted_at              *// Soft deletes*


## roles table (Spatie)


php
id
name                    *// studio_user, client_user, guest_user, vendor_user*
guard_name
created_at
updated_at


## permissions table (Spatie)


php
id
name                    *// can_upload, can_invoice, can_approve_gallery, etc.*
guard_name
created_at
updated_at


## permission_contexts table (Custom)


php
id
user_id
permission_id
contextable_type        *// Gallery, Organization, Project, etc.*
contextable_id
granted_at
expires_at              *// Nullable, for temporary access*
created_at
updated_at


# 2. Organizations (Clients)
## organizations table


php
id
studio_id
name                    *// "UCLA Health"*
type                    *// corporate, individual, agency*
billing_email
billing_phone
billing_address
billing_city
billing_state
billing_zip
vendor_number           *// Your vendor # in their system*
insurance_code          *// Their insurance code for you*
payment_terms           *// Net 30, Net 60, etc.*
tax_exempt              *// Boolean*
settings (JSON)         *// Per-org preferences*
created_at
updated_at
deleted_at
### Settings JSON example:

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


## organization_user table (Pivot)


php
id
organization_id
user_id
role                    *// marketing_contact, billing_contact, admin*
is_primary              *// Primary contact*
created_at


# 3. Sessions & Calendar
## sessions table


php
id
studio_id
organization_id         *// Client*
subject_name            *// "Dr. Jessica Haslam"*
session_type            *// headshot, half_day, full_day, event*
scheduled_at            *// DateTime*
completed_at            *// DateTime*
location
status                  *// tentative, scheduled, completed, processing, delivered, cancelled*
google_event_id         *// Google Calendar sync*
rate                    *// Overridable rate for this session*
notes
created_by_user_id
created_at
updated_at
deleted_at


## booking_requests table


php
id
studio_id
organization_id
client_user_id          *// Who requested*
subject_name
session_type
requested_datetime
duration_minutes
location
notes
status                  *// pending, confirmed, denied, cancelled*
session_id              *// Links to sessions table once confirmed*
google_event_id
denial_reason
confirmed_at
confirmed_by_user_id
created_at
updated_at


# 4. Galleries & Images
## galleries table


php
id
studio_id
organization_id
session_id              *// Nullable, can exist without session*
subject_name            *// "Dr. Jessica Haslam"*
access_code             *// Unique code for subject access*
magic_link_token        *// For magic link auth*
magic_link_expires_at
status                  *// active, completed, archived*
ai_enabled              *// Can generate AI portraits*
ai_training_status      *// null, ready, training, trained*
image_count             *// Cached count*
approved_count          *// Cached approved count*
download_count          *// Cached downloads*
last_activity_at        *// Last interaction*
delivered_at            *// When sent to client/subject*
completed_at            *// When manually marked complete*
archived_at
created_at
updated_at
deleted_at


## images table


php
id
gallery_id
filename
imagekit_file_id        *// ImageKit ID*
imagekit_url            *// Full URL*
imagekit_thumbnail_url  *// Thumbnail URL*
file_size               *// Bytes*
mime_type
width
height
metadata (JSON)         *// EXIF data*
sort_order              *// Custom ordering*
uploaded_at
uploaded_by_user_id
created_at
updated_at
deleted_at
### Metadata JSON example:

json
{
  "date_time_original": "2025-09-24 10:30:00",
  "camera_model": "Nikon Z6",
  "lens": "NIKKOR Z 40mm f/2",
  "iso": 250,
  "aperture": "f/6.7",
  "exposure_time": "1/80",
  "focal_length": "40.0mm"
}


## image_versions table (Edit tracking)


php
id
image_id
version_number          *// 1, 2, 3...*
imagekit_file_id
imagekit_url
file_size
notes                   *// What changed*
created_by_user_id
created_at


## image_interactions table (Subject actions)


php
id
image_id
user_id                 *// Subject or client*
interaction_type        *// rating, note, approval, download, edit_request*
rating                  *// 1-5 stars (nullable)*
note                    *// Subject's note*
approved_for_marketing  *// Boolean*
edit_requested          *// Boolean*
edit_notes              *// What to fix*
downloaded_at           *// Timestamp*
ip_address              *// For tracking*
created_at


# 5. Staging & Ingest
## staging_images table


php
id
studio_id
batch_id                *// Groups upload sessions*
filename
original_path           *// Laravel storage path*
thumbnail_path
file_size
mime_type
metadata (JSON)         *// EXIF*
assigned_to_gallery_id  *// Nullable until assigned*
assigned_at
uploaded_by_user_id
created_at
deleted_at              *// Auto-cleanup after 7 days*


# 6. AI Portrait Generation
## ai_generations table (One per trained model)


php
id
gallery_id
subject_user_id         *// Nullable (subjects don't have user accounts always)*
fine_tune_id            *// Astria model ID*
training_image_count    *// 8-20*
model_status            *// pending, training, trained, failed, expired*
fine_tune_cost          *// $1.50*
model_created_at
model_expires_at        *// 30 days from creation*
error_message
created_at
updated_at


## ai_generation_requests table (Multiple generations per model)


php
id
ai_generation_id
request_number          *// 1-5 (generation limit)*
custom_prompt
used_default_prompt     *// Boolean*
generated_portrait_count *// 8*
generation_cost         *// $0.23-0.41*
background_removal      *// Boolean*
super_resolution        *// Boolean*
status                  *// pending, processing, completed, failed*
error_message
liability_accepted_at   *// Timestamp*
requested_by_user_id
created_at
updated_at


## ai_generated_portraits table


php
id
ai_generation_request_id
imagekit_file_id
imagekit_url
imagekit_thumbnail_url
file_size
sort_order
downloaded_by_subject   *// Boolean*
created_at


# 7. Invoicing & Payments
## invoices table


php
id
studio_id
organization_id
invoice_number          *// UCLA3-3078*
quote_number            *// QT-UCLA3-0094-1 (nullable)*
status                  *// draft, quote, sent, paid, overdue, cancelled*
stripe_invoice_id       *// Stripe ID*
issued_at
due_at
paid_at
subtotal
tax_rate                *// Percentage*
tax_amount
total
payment_method          *// stripe, bank_transfer, check, wire, cash*
payment_reference       *// Check #, wire confirmation, etc.*
payment_notes
po_number               *// Client's PO*
notes                   *// Internal notes*
client_notes            *// Shows on invoice*
created_by_user_id
created_at
updated_at
deleted_at


## invoice_items table (Polymorphic)


php
id
invoice_id
itemable_type           *// Session, CustomFee*
itemable_id
description             *// "Physician Photo Shoot / Dr. Haslam"*
quantity
unit_price
total
sort_order
created_at
updated_at


## custom_fees table (For non-session line items)


php
id
type                    *// mileage, post_processing, travel, second_shooter, assistant, equipment, insurance*
description
quantity
unit_price
calculation_data (JSON) *// For mileage: {miles: 109, rate: 0.66}*
created_at


# 8. Notifications & Messages
## notifications table (Laravel default)


php
id
type                    *// Class name*
notifiable_type         *// User*
notifiable_id
data (JSON)
read_at
created_at
updated_at


## messages table (Optional - if you want persistent messages beyond notifications)


php
id
studio_id
sender_user_id
recipient_user_id       *// Nullable for broadcasts*
gallery_id              *// Context (nullable)*
image_id                *// Context (nullable)*
subject
body
read_at
created_at
updated_at
deleted_at
```

---

## **Relationships Summary**
```
Studio (1)
  └── Users (many)
  └── Organizations (many)
       └── Organization_Users (pivot)
       └── Sessions (many)
            └── Galleries (many)
                 └── Images (many)
                      └── Image_Versions (many)
                      └── Image_Interactions (many)
                 └── AI_Generations (one)
                      └── AI_Generation_Requests (many)
                           └── AI_Generated_Portraits (many)
       └── Invoices (many)
            └── Invoice_Items (many)
                 └── Itemable (polymorphic: Session or CustomFee)
       └── Booking_Requests (many)


#solo