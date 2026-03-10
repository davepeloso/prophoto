# ### #SOLO/session-Lifecycle
1. 1	Scheduled - Calendar invite received, shoot booked
2. 2	Confirmed - Client confirmed, details locked
3. 3	Completed - Shoot happened
4. 4	Processing - Images being edited
5. 5	Ready for Review - Gallery created (this is where Gallerie starts)
6. 6	Delivered - Client/subject made selections
7. 7	Invoiced - Quote/invoice sent
8. 8	Paid - Payment received

⠀Question:
When you receive that calendar invite for Dr. Haslam tomorrow, what info would you want to capture in SOLO immediately?
* •	Subject name
* •	Date/time/location
* •	Client contact (Gabby)
* •	Session type (physician headshot)
* •	Special requirements (white coat, etc.)

⠀Anything else you'd want to log right away?

* •	Subject name (used for gallery title & invoice/quote)
* •	Date/time/location (used for invoice/quote and navigation to the job)
* •	Client contact (Gabby) (used for invoice/quote)
* •	Session type (physician headshot) (used for invoice/quote)

### Required at booking:
* •	Subject name → gallery title, invoice line item
* •	Date/time/location → scheduling, invoice reference, navigation
* •	Client contact → invoice recipient
* •	Session type → invoice description, pricing lookup

### SOLO Calendar Integration:
1. 1	OAuth connect Google Calendar (one-time setup)
2. 2	Laravel syncs events (cron job, webhooks, or manual refresh)
3. 3	Calendar events = Sessions (or potential Sessions)

### SOLO Calendar Flow:
1. 1	Google Calendar syncs via OAuth
2. 2	New calendar event appears as "Tentative Booking"
3. 3	You review and manually convert to Session
4. 4	Conversion captures:
   * •	Subject name
   * •	Session type
   * •	Client contact
   * •	Pricing details
5. 5	Session moves to "Scheduled" status

### Calendar Integration:
* •	Google Calendar syncs (read-only for now)
* •	Shows your availability visually
* •	Events appear in SOLO calendar view

⠀Session Creation:
* •	You create Sessions manually when ready
* •	Can reference calendar events for details
* •	Or create from scratch

⠀No automatic conversion - avoids:
* •	Personal events becoming Sessions
* •	Tentative bookings creating clutter
* •	Parsing errors from varied event formats
* •	Wrong data assumptions

⠀So the calendar is:
* •	Visual reference tool
* •	Availability checker
* •	Data source you can copy from
* •	NOT an automatic Session creator

### Gallerie's 3-Role System:
1. 1	Photographer (you) - uploads galleries, manages everything
2. 2	Client (Gabby at UCLA) - sees all their org's galleries, approves images
3. 3	Subject (Dr. Haslam) - access code login, sees only their photos, rates/approves

⠀Key Features to Port to SOLO:
* •	Gallery creation & image upload
* •	Unique access codes for subjects (not email/password)
* •	Star rating system (1-5 stars)
* •	Approval workflow (Subject approves → Client downloads)
* •	Organization-scoped visibility
* •	AI portrait generation (subject-only feature)

⠀This maps to your RBAC perfectly:
* •	studio_user = Photographer
* •	client_user = Client (org admin)
* •	guest_user = Subject (access code)

1. 1	Pre-Shoot: Calendar, session scheduling, client management
2. 2	Post-Shoot: Gallery creation, image upload, delivery
3. 3	Selection: Subject ratings, approvals, client oversight
4. 4	Billing: Invoice generation, payment tracking

⠀Filament Multi-Panel Architecture:
* •	/admin - Your studio control (everything)
* •	/client - Client portal (UCLA marketing sees their galleries)
* •	/gallery/{access_code} - Subject view (Dr. Haslam rates photos)
### 1. File Version Tracking
* •	Track edit requests on specific images
* •	Upload revised versions linked to original
* •	Show version history with before/after comparison
* •	Options: version slider, side-by-side, or both

⠀2. In-App Invoicing
* •	Quote/invoice builder inside SOLO
* •	Stripe integration for payments
* •	No need to leave the app or use external tools

⠀3. Subject Reviews
* •	Gentle prompt after selections are approved
* •	Collect testimonials/feedback

⠀Quick clarification on file versioning:
When a subject requests edits:
1. 1	They mark the image with edit notes?
2. 2	You retouch it externally (Photoshop, etc.)
3. 3	You upload the revised version to SOLO
4. 4	Both versions stay linked with visible history?

⠀Is that the flow?

* •	They mark the image with edit notes YES
* •	You retouch it externally (Photoshop, etc.) YES
* •	You upload the revised version to SOLO YES
* •	Both versions stay linked with visible history (no real history fearure , newly uploaded files, just appear as additional files. Many times I simply emailed them the new file.

### Got it - two more improvements:
### 4. File Versioning System
* •	Link revised files to originals
* •	Show edit request notes with each version
* •	Visual comparison (slider/side-by-side)
* •	Replaces: uploading as "additional files" or emailing

⠀5. Gallery Lifecycle & Archiving
* •	Galleries have status (Active, Completed, Archived)
* •	Auto-archive after X days of inactivity?
* •	Or manual close-out when done?
* •	Keeps dashboard clean, focuses on active work

⠀Question on archiving:
What triggers a gallery to be "done"?
* •	Client downloads all approved images?
* •	Invoice paid?
* •	Time-based (30 days after delivery)?
* •	Manual "mark complete"?

### Gallery Lifecycle - Manual Control
### Status Flow:
1. 1	Active - Subject selecting, client reviewing
2. 2	Completed (manual) - You mark it done when work is finished
3. 3	Archived (optional) - Move old completed galleries out of view

⠀Benefits:
* •	You decide when it's truly done
* •	Accounts for delayed client approvals
* •	Handles edge cases (reopening if needed)
* •	No automatic assumptions

⠀We've now mapped:
* •	Pre-shoot (calendar, sessions)
* •	Shoot logistics
* •	Post-shoot (galleries, selections, versioning)
* •	Invoicing (flexible line items, Stripe)
* •	Reviews (gentle prompts)
* •	Archiving (manual completion)

⠀What area should we explore next?
* •	Image organization/tagging?
* •	Communication features (messages between roles)?
* •	Reporting/analytics?
* •	Client onboarding flow?
* •	Something else?

#solo