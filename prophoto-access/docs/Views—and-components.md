# #SOLO/Views—and-components
# SOLO Application Map - All Views
### I'll organize by user role and panel.
# 1. Public Pages (No Auth Required)
## Marketing Site (https://solo.com)

```
├── Landing Page
├── Pricing
├── Features
├── About
├── Contact
└── Studio Signup (future SaaS)
```

---

### **Subject Gallery Access** (`/gallery/{access_code}`)
```
Gallery Access Page
├── Enter access code or click magic link
├── Email collection (if magic link)
└── Redirects to Subject Gallery View
```

---

## **2. Subject Panel** (Guest Users - Magic Link Auth)

**Base URL:** `/subject/gallery/{gallery_id}`

### **Subject Gallery View** (Main page)
```
Subject Gallery View
├── Gallery header (subject name, photographer contact)
├── Image grid with thumbnails
├── Filter/sort options
│   ├── By date
│   ├── By rating (their ratings)
│   └── By approval status
├── Per-image actions:
│   ├── Click image → Image Detail Modal
│   ├── Quick rate (1-5 stars)
│   └── Quick approve checkbox
└── Toolbar:
    ├── Download all approved
    ├── Share gallery
    └── Generate AI Portraits (if enabled)
```

---

### **Image Detail Modal** (Subject)
```
Image Detail Modal
├── Large image preview
├── Image metadata display
├── Rating (1-5 stars)
├── Approve for marketing (checkbox)
├── Image notes field
│   └── "whiten teeth", "remove glare", etc.
├── Download this image
├── Navigation (prev/next image)
└── Close modal
```

---

### **AI Portrait Generation** (Subject)
```
AI Portrait Generation Page
├── "Generate AI Portraits" intro
├── Trained model status
│   └── "Model ready" or "Training in progress"
├── Custom prompt field
│   └── Default: "Professional business portrait, neutral background"
├── Options:
│   ├── Background removal (+$0.08)
│   └── Super-resolution (+$0.10)
├── Cost display
├── Terms acceptance
├── Generation limit tracker (X of 5 remaining)
└── Generated portraits grid
    └── Download buttons per portrait
```

---

## **3. Client Panel** (`/client/*`)

**Base URL:** `/client`

### **Client Dashboard**
```
Client Dashboard
├── Welcome message
├── Quick stats cards
│   ├── Active galleries
│   ├── Pending approvals
│   ├── Unread messages
│   └── Outstanding invoices
└── Recent activity feed
```

---

### **Client Galleries List** (Main view we designed)
```
Galleries List
├── Search bar
├── Filter dropdown (All/Active/Completed/Archived)
├── Data table:
│   ├── Preview thumbnail
│   ├── Gallery name + date
│   ├── Photographer
│   ├── Client contact
│   ├── Image count
│   ├── Activity icons (✓💬✏️🤖🎨⬇️)
│   ├── Invoice status icon (📄📋✅⚠️➕)
│   ├── Status badge
│   ├── Share link
│   ├── Download approved
│   └── Actions menu
└── Pagination
```

---

### **Client Gallery Detail View**
```
Gallery Detail View
├── Header
│   ├── Subject name
│   ├── Session date
│   ├── Status badge
│   └── Actions:
│       ├── Share with subject
│       ├── Download all approved
│       ├── Mark complete
│       └── Archive
├── Stats row
│   ├── Total images
│   ├── Approved for marketing
│   ├── Subject selections
│   └── AI portraits generated
├── Image grid
│   ├── Thumbnails
│   ├── Approval indicators
│   ├── Click → Image detail
│   └── Bulk actions toolbar
└── Activity timeline
    └── Recent interactions
```

---

### **Client Image Detail Modal**
```
Image Detail Modal (Client)
├── Large image preview
├── Subject's rating (view only)
├── Subject's notes (view only)
├── Approval status
│   └── Can override approval
├── Download options
├── Message subject button
├── Navigation (prev/next)
└── Close modal
```

---

### **Client Messages/Notifications**
```
Messages Page
├── Notification center (bell dropdown expanded)
├── Filter tabs:
│   ├── All
│   ├── Unread
│   ├── Gallery updates
│   ├── Edit requests
│   └── AI generations
├── Notification list
│   ├── Icon + message
│   ├── Timestamp
│   ├── Action button (View Gallery, etc.)
│   └── Mark read/unread
└── Archive all read
```

---

### **Client Selections View**
```
Selections Page
├── All approved images across all galleries
├── Filter by:
│   ├── Gallery
│   ├── Date range
│   └── Subject
├── Image grid
└── Bulk download options
```

---

### **Client Invoices List**
```
Invoices Page
├── Filter by status (All/Sent/Paid/Overdue)
├── Data table:
│   ├── Invoice number
│   ├── Date issued
│   ├── Due date
│   ├── Total amount
│   ├── Status badge
│   └── Actions:
│       ├── View PDF
│       ├── Download
│       └── Pay (if unpaid + Stripe)
└── Payment history
```

---

### **Client Invoice Detail**
```
Invoice Detail Page
├── Invoice header
│   ├── Invoice number
│   ├── Issue/due dates
│   ├── Status
│   └── Download PDF
├── Line items table
│   ├── Description
│   ├── Quantity
│   ├── Unit price
│   └── Total
├── Totals section
│   ├── Subtotal
│   ├── Tax (if applicable)
│   └── Total
├── Payment information
│   └── If unpaid: Pay button
└── Notes section
```

---

### **Client Booking Calendar**
```
Booking Calendar Page
├── FullCalendar interface
├── Your availability shown
│   ├── Available slots (clickable)
│   └── Busy slots (grayed out)
├── Click slot → Booking request modal
│   ├── Session type dropdown
│   ├── Subject name
│   ├── Location
│   ├── Notes
│   └── Submit request
└── Pending requests list
    ├── Request details
    └── Status (Pending/Confirmed/Denied)
```

---

### **Client Settings**
```
Client Settings Page
├── Organization Info
│   ├── Name, address, contact
│   └── Edit form
├── Notification Preferences
│   ├── Email toggles per notification type
│   └── Email template customization
├── Billing Preferences
│   ├── Payment terms
│   ├── PO requirements
│   └── Tax exempt status
├── Gallery Defaults
│   ├── AI portraits enabled
│   └── Auto-archive settings
└── Team Management
    ├── User list
    ├── Roles
    └── Invite new user
```

---

## **4. Photographer Panel** (`/admin/*`)

**Base URL:** `/admin` (Filament)

### **Photographer Dashboard**
```
Admin Dashboard
├── Welcome + quick stats
│   ├── Active sessions
│   ├── Pending approvals
│   ├── Unread messages
│   ├── Unpaid invoices
│   └── AI generations in progress
├── Calendar widget
│   └── Upcoming sessions
├── Recent activity
└── Quick actions
    ├── Create gallery
    ├── New invoice
    └── Upload images
```

---

### **Photographer Galleries List** (Same as client, but more columns)
```
Galleries List
├── Same as client view, PLUS:
│   ├── Organization column
│   ├── Session link
│   ├── Delete action
│   └── AI enable toggle
└── Shows ALL galleries (all clients)
```

---

### **Photographer Gallery Detail**
```
Gallery Detail (Photographer)
├── Everything client sees, PLUS:
├── Upload images button
├── Enable/disable AI portraits
├── Select AI training images (if needed)
├── Edit gallery details
├── Delete gallery
└── Advanced options
```

---

### **Image Ingest/Staging Interface** (We designed this)
```
Ingest Interface
├── Left Panel: Gallery List
│   ├── Existing galleries
│   ├── Create new gallery
│   └── Selected gallery highlight
├── Center Panel: Staging Grid
│   ├── Uploaded image thumbnails
│   ├── Filename under each
│   ├── Multi-select checkboxes
│   ├── Drag to gallery
│   └── Actions: Delete, Assign, Upload more
└── Right Panel: Metadata Filters
    ├── Date/time
    ├── ISO
    ├── Exposure
    ├── Aperture
    ├── Lens
    └── Camera model
```

---

### **Sessions Management**
```
Sessions List (Filament Resource)
├── Data table:
│   ├── Organization
│   ├── Subject name
│   ├── Session type
│   ├── Scheduled date
│   ├── Location
│   ├── Status
│   ├── Gallery link
│   └── Invoice link
└── Create/Edit actions
```

---

### **Session Detail/Edit**
```
Session Form
├── Client dropdown
├── Subject name
├── Session type
├── Date/time pickers
├── Location
├── Status dropdown
├── Rate (overridable)
├── Notes
├── Linked gallery (if exists)
└── Save/Cancel
```

---

### **Organizations (Clients) Management**
```
Organizations List (Filament Resource)
├── Data table:
│   ├── Organization name
│   ├── Type
│   ├── Primary contact
│   ├── Active galleries count
│   ├── Total invoiced
│   └── Last activity
└── Create/Edit actions
```

---

### **Organization Detail/Edit**
```
Organization Form
├── Basic Info tab
│   ├── Name, type
│   ├── Contact info
│   └── Billing address
├── Billing tab
│   ├── Vendor number
│   ├── Insurance code
│   ├── Payment terms
│   └── Tax exempt
├── Settings tab
│   ├── Email preferences
│   ├── AI enabled
│   └── PO required
├── Users tab (Relation manager)
│   ├── Assigned users
│   ├── Roles
│   └── Add/remove users
└── Save
```

---

### **Booking Requests Management**
```
Booking Requests List (Filament Resource)
├── Data table:
│   ├── Organization
│   ├── Subject name
│   ├── Requested date/time
│   ├── Session type
│   ├── Status
│   └── Actions:
│       ├── Confirm (creates session + calendar event)
│       ├── Deny (with reason)
│       └── Suggest alternative
└── Filter by status
```

---

### **Invoice Management**
```
Invoices List (Filament Resource)
├── Data table:
│   ├── Invoice number
│   ├── Organization
│   ├── Issue date
│   ├── Due date
│   ├── Total
│   ├── Status
│   └── Payment method
└── Create/Edit actions
```

---

### **Invoice Builder/Edit** (We designed this)
```
Invoice Form
├── Step 1: Select Client
│   └── Organization dropdown
├── Step 2: Add Line Items
│   ├── Add Session button
│   │   └── Select from completed sessions
│   └── Add Custom Item button
│       ├── Type dropdown (mileage, travel, etc.)
│       ├── Description
│       ├── Quantity
│       └── Unit price
├── Step 3: Invoice Details
│   ├── Invoice number (auto or manual)
│   ├── Quote number (optional)
│   ├── Issue date
│   ├── Due date
│   ├── Payment terms
│   └── PO number
├── Step 4: Notes
│   ├── Internal notes
│   └── Client-facing notes
├── Tax section (optional)
├── Total calculation
└── Actions:
    ├── Save as draft
    ├── Send to client
    ├── Mark as paid
    └── Record payment
```

---

### **Payment Recording Modal**
```
Record Payment Modal
├── Payment date
├── Amount received
├── Payment method dropdown
├── Reference number
├── Bank account dropdown
├── Notes
└── Save
```

---

### **AI Generations Management**
```
AI Generations List (Filament Resource)
├── Data table:
│   ├── Gallery
│   ├── Subject
│   ├── Model status
│   ├── Generation count (X of 5)
│   ├── Total cost
│   ├── Created date
│   └── Actions:
│       ├── View portraits
│       └── Regenerate (if failed)
└── Cost tracking summary
```

---

### **Users Management**
```
Users List (Filament Resource)
├── Data table:
│   ├── Name
│   ├── Email
│   ├── Role
│   ├── Organization (if client)
│   ├── Last login
│   └── Status
└── Create/Edit actions
```

---

### **User Edit Form**
```
User Form
├── Personal Info
│   ├── Name
│   ├── Email
│   ├── Phone
│   └── Avatar
├── Role & Permissions
│   ├── Role dropdown
│   └── Contextual permissions (if needed)
├── Organization assignment (if client)
└── Save
```

---

### **Studio Settings** (Your business settings)
```
Studio Settings Page
├── Business Information
│   ├── Name, address, contact
│   ├── Logo upload
│   └── Website
├── Default Rates
│   ├── Headshot rate
│   ├── Half-day rate
│   ├── Full-day rate
│   └── Mileage rate
├── Invoice Settings
│   ├── Invoice prefix
│   ├── Default payment terms
│   └── Next invoice number
├── Feature Toggles
│   ├── AI portraits enabled
│   ├── Client booking enabled
│   └── Automated emails enabled
└── Integration Settings
    ├── Google Calendar (connect/disconnect)
    ├── Stripe (connect/disconnect)
    ├── ImageKit (API keys)
    └── Astria (API key)
```

---

### **Notifications Center** (Photographer)
```
Notifications Page
├── Same as client, PLUS:
├── Booking request notifications
├── AI training complete
├── Payment received
└── System alerts
```

---

## **Flow Chart - Key User Journeys**

### **Journey 1: Subject Views Gallery**
```
1. Receive email with access link
2. Click link → Gallery Access Page
3. Enter email (magic link)
4. Check email → Click magic link
5. → Subject Gallery View
6. Click image → Image Detail Modal
7. Rate 5 stars, add note "whiten teeth"
8. Approve for marketing
9. Download image
10. Click "Generate AI Portraits"
11. → AI Generation Page
12. Enter custom prompt
13. Accept terms → Processing
14. Receive email when complete
15. View generated portraits
16. Download favorites
```

---

### **Journey 2: Client Books Session**
```
1. Login → Client Dashboard
2. Click "Book Session"
3. → Booking Calendar Page
4. View photographer availability
5. Click available slot
6. → Booking Request Modal
7. Fill form:
   - Session type: Headshot
   - Subject: Dr. Smith
   - Location: UCLA Medical
8. Submit request
9. Receive notification: "Pending confirmation"
10. ---
11. Photographer confirms
12. Receive email: "Booking confirmed"
13. Session appears in calendar
```

---

### **Journey 3: Photographer Processes Shoot**
```
1. Complete photo shoot
2. Return to studio
3. Upload images to computer
4. Login → Admin Dashboard
5. Click "Ingest Images"
6. → Staging Interface
7. Upload batch (150 images)
8. Wait for metadata extraction
9. Filter by date → 9/24/25
10. Select all filtered images
11. Drag to "Dr. Smith" gallery
12. Filter by next date → 9/25/25
13. Select and assign to "Dr. Lee" gallery
14. Repeat until all assigned
15. Images auto-upload to ImageKit
16. → Gallery Detail
17. Review images
18. Click "Send to Subject"
19. Select email template
20. Send notification
```

---

### **Journey 4: Create Invoice**
```
1. Admin Dashboard
2. Click "Invoices"
3. Click "Create Invoice"
4. → Invoice Builder
5. Select Client: UCLA Health
6. Add line items:
   - Add Session → Select "Dr. Smith 9/24"
   - Add Custom → Mileage: 109 miles
7. Enter PO number from client
8. Set due date: Net 30
9. Review total
10. Save and send
11. Stripe creates invoice
12. Email sent to billing contact
13. ---
14. Payment received in Wells Fargo
15. Open invoice
16. Click "Record Payment"
17. → Payment Modal
18. Enter details
19. Mark as paid
```

---

## **View Hierarchy Summary**
```
Public
  └── Landing
  └── Subject Gallery Access
      └── Subject Gallery View
          └── Image Detail Modal
          └── AI Generation Page

Client Panel (/client)
  ├── Dashboard
  ├── Galleries List
  │   └── Gallery Detail
  │       └── Image Detail Modal
  ├── Messages/Notifications
  ├── Selections
  ├── Invoices
  │   └── Invoice Detail
  ├── Booking Calendar
  └── Settings

Photographer Panel (/admin)
  ├── Dashboard
  ├── Galleries
  │   └── Gallery Detail
  │   └── Ingest/Staging Interface
  ├── Sessions
  │   └── Session Form
  ├── Organizations
  │   └── Organization Form
  ├── Booking Requests
  ├── Invoices
  │   └── Invoice Builder
  ├── AI Generations
  ├── Users
  ├── Studio Settings
  └── Notifications

#solo