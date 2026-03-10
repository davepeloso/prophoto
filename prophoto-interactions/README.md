# ProPhoto Interactions

Image interaction engine for ProPhoto enabling ratings, marketing approvals, comments, and edit requests from subjects and clients.

## Purpose

Enables rich gallery interactions where:
- Subjects rate images (favorites, stars, likes)
- Marketing use approvals with legal disclaimer
- Comments and notes on specific images
- Edit requests for retouching
- Full interaction history per image

## Key Responsibilities

### Rating System
- Subject ratings: favorite, like, star (1-5)
- Client ratings: separate from subject ratings
- Aggregate ratings for photographer review
- Filter/sort by rating in gallery view

### Marketing Approval System
- Subjects approve/decline marketing use per image
- Legal disclaimer displayed and accepted
- Timestamp + IP address capture (compliance)
- Approval cannot be revoked (immutable)
- Expiry support (approve for X years)

### Comments & Notes
- Threaded comments on images
- Subject comments (public to photographer + client)
- Client notes (private, photographer only)
- Photographer notes (private)
- @mentions support

### Edit Requests
- Subject/client requests specific edits
- Examples: "lighten face", "remove background person"
- Status tracking: pending, in_progress, completed, declined
- Attach edited version when complete

## Contracts Implemented

- `RatingContract` - Submit/retrieve ratings
- `ApprovalContract` - Submit/retrieve marketing approvals
- `CommentContract` - Add/edit/delete comments

## Database Tables

- `image_ratings` - Star/like/favorite ratings
- `marketing_approvals` - Marketing use approvals
- `image_comments` - Comments and notes
- `edit_requests` - Retouching requests

## Configuration

**config/interactions.php**
```php
return [
    'ratings' => [
        'types' => ['favorite', 'like', 'star'],
        'star_max' => 5,
        'allow_change' => true, // Can change rating
    ],

    'approvals' => [
        'require_disclaimer' => true,
        'capture_ip' => true,
        'default_expiry_years' => null, // Permanent
        'immutable' => true, // Cannot revoke
    ],

    'comments' => [
        'max_length' => 1000,
        'allow_edit' => true,
        'edit_window_minutes' => 15,
        'allow_delete' => false, // Soft delete only
        'mentions_enabled' => true,
    ],

    'edit_requests' => [
        'max_length' => 500,
        'attach_reference_image' => true,
        'statuses' => ['pending', 'in_progress', 'completed', 'declined'],
    ],
];
```

## Usage Examples

### Submit Rating
```php
use ProPhoto\Contracts\Interaction\RatingContract;

$ratings = app(RatingContract::class);

$ratings->rate([
    'image' => $image,
    'user' => $subject,
    'type' => 'favorite', // or 'like', 'star'
    'value' => 5, // For star rating
]);

// Returns: Rating DTO
```

### Get Image Ratings
```php
$imageRatings = $ratings->forImage($image);

// {
//   favorites: 1,
//   likes: 3,
//   stars: { average: 4.5, count: 2, distribution: {5: 1, 4: 1} }
// }
```

### Submit Marketing Approval
```php
use ProPhoto\Contracts\Interaction\ApprovalContract;

$approvals = app(ApprovalContract::class);

$approval = $approvals->approve([
    'image' => $image,
    'subject' => $subject,
    'approved' => true,
    'disclaimer_accepted' => true,
    'disclaimer_text' => 'I grant permission for this image to be used for marketing...',
    'ip_address' => $request->ip(),
    'expires_at' => now()->addYears(5), // Optional
]);

// Immutable - cannot be changed once submitted
```

### Check Approval Status
```php
$status = $approvals->getStatus($image, $subject);

// {
//   approved: true,
//   approved_at: '2024-06-15 14:30:00',
//   expires_at: '2029-06-15',
//   ip_address: '192.168.1.1'
// }
```

### Add Comment
```php
use ProPhoto\Contracts\Interaction\CommentContract;

$comments = app(CommentContract::class);

$comment = $comments->add([
    'image' => $image,
    'user' => auth()->user(),
    'text' => 'Love this shot! Can we use it for the website?',
    'visibility' => 'shared', // 'shared' or 'private'
    'parent_id' => null, // For threaded replies
]);
```

### Get Image Comments
```php
$imageComments = $comments->forImage($image, visibleTo: auth()->user());

// Returns collection of comments with replies
```

### Submit Edit Request
```php
$editRequest = EditRequest::create([
    'image_id' => $image->id,
    'gallery_id' => $gallery->id,
    'requested_by' => $subject->id,
    'request_text' => 'Can you brighten my face and remove the person in the background?',
    'reference_image_path' => $referencePath, // Optional
    'status' => 'pending',
]);

// Photographer gets notified
event(new EditRequestReceived($editRequest));
```

### Update Edit Request Status
```php
$editRequest->update([
    'status' => 'completed',
    'completed_at' => now(),
    'edited_image_id' => $editedImage->id, // Link to edited version
    'photographer_notes' => 'Brightened face, removed background person',
]);

// Subject gets notified
event(new EditRequestCompleted($editRequest));
```

## Blade Components

```blade
<x-image-ratings :image="$image" :user="$subject" />
<!-- Shows star rating UI, updates via Livewire/Alpine -->

<x-marketing-approval :image="$image" :subject="$subject" />
<!-- Shows approval UI with disclaimer -->

<x-image-comments :image="$image" />
<!-- Shows comment thread, add comment form -->

<x-edit-request-button :image="$image" />
<!-- "Request Edit" button -->
```

## Filament Integration

Gallery resource shows interaction summary:

- "5 favorites, 12 likes"
- "Marketing approved: 8/15 images"
- "3 pending edit requests"
- "12 comments across all images"

## API Endpoints

```php
POST   /images/{image}/rate
POST   /images/{image}/approve-marketing
GET    /images/{image}/approval-status
POST   /images/{image}/comments
GET    /images/{image}/comments
POST   /images/{image}/edit-requests
PATCH  /edit-requests/{request}/status
```

## Events

- `ImageRated` - Subject rated image
- `MarketingApproved` - Marketing approval given
- `MarketingDeclined` - Marketing approval declined
- `CommentAdded` - Comment posted
- `CommentMentioned` - User mentioned in comment
- `EditRequestReceived` - Edit request submitted
- `EditRequestCompleted` - Edit request fulfilled

## Legal Compliance (Marketing Approvals)

### Captured Data
- Subject identity (user ID + email)
- Image identifier
- Approval decision (yes/no)
- Timestamp (when approved)
- IP address (where approved from)
- Disclaimer text (what they agreed to)
- Expiry date (if applicable)

### Immutability
- Approvals cannot be edited or deleted
- Soft delete only (keeps record for legal purposes)
- Audit trail of all approval actions

### Export for Compliance
```php
// Export all approvals for gallery (e.g., for client legal team)
$approvals = MarketingApproval::where('gallery_id', $gallery->id)
    ->where('approved', true)
    ->get();

// Generate PDF with all approvals + signatures
$pdf = $approvalService->generateComplianceReport($gallery);
```

## Statistics & Reporting

```php
// Gallery-level stats
$stats = $gallery->interactionStats();

// {
//   ratings: {
//     favorites: 45,
//     likes: 120,
//     stars_average: 4.3
//   },
//   marketing_approvals: {
//     approved: 80,
//     declined: 5,
//     pending: 15,
//     approval_rate: 94.1
//   },
//   comments: {
//     total: 42,
//     by_subjects: 30,
//     by_clients: 12
//   },
//   edit_requests: {
//     total: 8,
//     pending: 2,
//     completed: 5,
//     declined: 1
//   }
// }
```

## Future Enhancements

- [ ] Collaborative editing (multiple subjects approve together)
- [ ] Video comments (record video feedback)
- [ ] Drawing annotations on images
- [ ] Comparison view (side-by-side original vs edited)
- [ ] Bulk approval (approve all images at once)

## Dependencies

- `prophoto/contracts` - Interaction contracts and DTOs
- `prophoto/galleries` - Gallery and image models
- `prophoto/notifications` - Notify on interactions
- `prophoto/audit` - Log all interactions

## Testing

```bash
cd prophoto-interactions
vendor/bin/pest
```

## Notes

- Ratings can be changed/removed by user
- Marketing approvals are immutable (legal requirement)
- Comments are soft-deleted (preserve history)
- Edit requests track complete lifecycle
- All interactions visible in Filament activity tab
