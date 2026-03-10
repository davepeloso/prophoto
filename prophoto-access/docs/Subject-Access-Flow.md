# #SOLO/Subject-Access-Flow-Delivery 

## Tier 1: Friends & Family Link
### Use Case: Casual sharing (weddings, events, products)
### Access Method:
* •	Public URL with token: /gallery/{token}
* •	No email required
* •	No user creation

⠀Permissions:
* •	View images only
* •	NO ratings, comments, downloads, or any interaction
* •	Read-only gallery browsing

⠀Perfect for: Quick shares where you don't need feedback

## Tier 2: Subject Link (Magic Link)
### Use Case: Professional subjects (Dr. Haslam, employees, professionals)
### Access Method:
1. 1	Navigate to /gallery/{token}
2. 2	System prompts for email address
3. 3	Magic link sent to email
4. 4	Click link → auto-logged in
5. 5       Approve images for marketing ✅ (ADDED)
6. 5	User record created (not authenticated against database)

⠀Permissions:
* •	View images
* •	Rate (1-5 stars)
* •	Comment/add image notes
* •	Download images
* •	Can request: AI generation access, image manipulation tools

⠀User Creation:
* •	Email captured but not password
* •	Guest user role with contextual permissions
* •	Session-based access tied to gallery token
⠀
## Tier 3: Client/Marketing Login
### Use Case: UCLA marketing dept, corporate clients
### Access Method:
* •	Login page: /client/login
* •	Email + password authentication
* •	Full database user authentication
* •	Needs registration page: /client/register

⠀Permissions:
* •	View all organizational galleries
* •	Approve images for marketing
* •	Download approved images
* •	Message photographer & subjects
* •	Manage team members (add/remove users)
* •	Access invoices (if billing role)
* •	Request AI model training

⠀User Type: client_user role

## Tier 4: Photographer Login
### Use Case: You (Dave) and any studio staff
### Access Method:
* •	Login page: /admin/login (or same as client)
* •	Email + password authentication
* •	Full database authentication

⠀Permissions:
* •	Everything (studio_user role)
* •	All galleries, all clients, all sessions
* •	Create galleries, upload images
* •	Generate invoices
* •	Manage all users
* •	Full system administration


⠀
## Gallery Delivery Workflow
### When ready to send gallery:
1. 1	Photographer/Client navigates to Delivery Page
2. 2	Select gallery to deliver
3. 3	Choose email template:
   * •	Friends & Family (public link only)
   * •	Subject (magic link instructions)
   * •	Client notification (login credentials if new)
4. 4	Customize message (optional)
5. 5	Send → automated email via Laravel Mail

⠀Email Templates Include:
* •	Gallery access link
* •	Instructions for their tier
* •	Deadline (if any)
* •	Your contact info
* •	Branding/logo