# Component → Contracts Mapping

This document maps your planned components to specific contracts, DTOs, enums, and events that belong in `prophoto-contracts`.

## 1. Studio + Tenancy Core

### Package: `prophoto-tenancy`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Tenancy/
  StudioContextContract.php      // Resolve current studio
  OrgContextContract.php          // Resolve current organization
  ImpersonationContract.php       // Admin → client impersonation
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  StudioId.php
  OrganizationId.php
  ImpersonationSession.php
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  StudioStatus.php   // active, suspended, trial
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  StudioContextChanged.php
  ImpersonationStarted.php
  ImpersonationEnded.php
```

---

## 2. Identity & Permissions

### Package: `prophoto-permissions`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Access/
  PermissionCheckerContract.php    // Already exists! ✅
  PolicyMatrixContract.php          // Contextual permission checks
  InvitationContract.php            // Email invite → password setup
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  PermissionDecision.php   // Already exists! ✅
  InvitationRequest.php
  InvitationToken.php
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  Ability.php              // Already exists! ✅
  InvitationStatus.php     // pending, accepted, expired, revoked
  UserRole.php             // admin, photographer, client, subject
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  InvitationSent.php
  InvitationAccepted.php
  PermissionGranted.php
  PermissionRevoked.php
```

---

## 3. Feature Flags & Settings

### Package: `prophoto-settings`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Settings/
  SettingsRepositoryContract.php   // Get/set settings
  FeatureFlagContract.php           // Check if feature enabled
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  StudioSettings.php
  OrganizationSettings.php
  FeatureFlag.php
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  Feature.php   // ai_portraits, booking, stripe_invoicing, bulk_download
```

---

## 4. Ingest/Staging Pipeline

### Package: `prophoto-ingest` (already exists, needs contracts added)

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Ingest/
  IngestServiceContract.php        // Already exists! ✅
  StagingBatchContract.php          // Manage staging batches
  DerivativeGeneratorContract.php   // Generate thumbs/previews
  AssignmentEngineContract.php      // staging → gallery
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  IngestRequest.php        // Already exists! ✅
  IngestResult.php         // Already exists! ✅
  StagingBatch.php
  DerivativeSet.php        // Collection of thumbs/previews/web
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  IngestStatus.php         // Already exists! ✅
  DerivativeType.php       // Already exists! ✅
  StagingBatchStatus.php   // pending, processing, complete, assigned, expired
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  AssetIngested.php
  DerivativesGenerated.php
  StagingBatchCreated.php
  AssetsAssignedToGallery.php
  StagingBatchExpired.php
```

---

## 5. Storage Abstraction

### Package: `prophoto-storage`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Asset/
  AssetStorageContract.php         // Already exists! ✅
  SignedUrlGeneratorContract.php    // Generate signed URLs
  PathResolverContract.php          // Resolve asset paths
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  SignedUrl.php            // URL + expiry
  StoragePath.php          // Full path with conventions
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  StorageDriver.php        // local, imagekit, s3
  DownloadPermission.php   // subject_only, client_team, admin, public
```

---

## 6. Bulk Download System

### Package: `prophoto-downloads`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Download/
  BulkDownloadContract.php         // Create ZIP jobs
  ProgressTrackerContract.php       // Track download progress
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  DownloadRequest.php
  DownloadArchive.php      // ZIP location + expiry
  DownloadProgress.php     // Current status + percentage
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  DownloadStatus.php       // queued, building, ready, expired, failed
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  DownloadRequested.php
  DownloadReady.php
  DownloadExpired.php
```

---

## 7. Image Interactions Engine

### Package: `prophoto-interactions`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Interaction/
  RatingContract.php               // Subject ratings
  ApprovalContract.php             // Marketing approvals
  CommentContract.php              // Comments + edit requests
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  Rating.php
  Approval.php             // With timestamp + IP + disclaimer
  Comment.php
  EditRequest.php
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  RatingType.php           // star, like, favorite
  ApprovalStatus.php       // approved, declined, pending
  InteractionType.php      // rating, approval, comment, edit_request
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  ImageRated.php
  MarketingApproved.php
  CommentAdded.php
  EditRequested.php
```

---

## 8. Activity & Audit Logging

### Package: `prophoto-audit`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Audit/
  AuditLoggerContract.php          // Log activity
  ActivityQueryContract.php         // Query audit trail
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  AuditEntry.php           // actor + action + context + metadata
  ActivityFilter.php       // Filter by date/actor/resource
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  AuditAction.php          // created, updated, deleted, downloaded, viewed
  AuditResource.php        // gallery, asset, invoice, booking, etc.
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  ActivityLogged.php
```

**Note**: This package LISTENS to events from all other packages and logs them.

---

## 9. AI Orchestration

### Package: `prophoto-ai`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/AI/
  ModelTrainerContract.php         // Training lifecycle
  GenerationServiceContract.php     // Generate AI portraits
  QuotaManagerContract.php          // Rate limits + quotas
  CostTrackerContract.php           // Cost estimation + ledger
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  TrainingRequest.php
  TrainingResult.php
  GenerationRequest.php
  GenerationResult.php
  AiQuota.php
  AiCost.php
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  TrainingStatus.php       // pending, processing, complete, failed
  GenerationStatus.php     // queued, generating, complete, failed
  AiProvider.php           // stability, midjourney, dalle, custom
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  ModelTrainingStarted.php
  ModelTrainingComplete.php
  GenerationRequested.php
  GenerationComplete.php
  QuotaExceeded.php
  CostRecorded.php
```

---

## 10. Booking Workflow Engine

### Package: `prophoto-booking`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Booking/
  BookingEngineContract.php        // Request → confirm/deny
  ConflictDetectorContract.php      // Calendar conflicts
  CalendarSyncContract.php          // Google Calendar integration
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  BookingRequest.php
  BookingDetails.php
  BookingConflict.php
  CalendarEvent.php
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  BookingStatus.php        // requested, under_review, confirmed, declined, cancelled
  SessionType.php          // portrait, wedding, event, commercial
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  BookingRequested.php
  BookingConfirmed.php
  BookingDeclined.php
  BookingCancelled.php
  BookingRescheduled.php
  CalendarSynced.php
```

---

## 11. Invoice Domain Engine

### Package: `prophoto-invoicing`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Invoice/
  InvoiceGeneratorContract.php     // Create invoices
  PdfRendererContract.php           // HTML → PDF
  TaxCalculatorContract.php         // Tax rules
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  Invoice.php
  InvoiceLineItem.php
  TaxRule.php
  InvoiceTotal.php
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  InvoiceStatus.php        // draft, sent, paid, overdue, cancelled
  LineItemType.php         // session, print, digital, custom
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  InvoiceGenerated.php
  InvoiceSent.php
  InvoicePaid.php
  InvoiceOverdue.php
  InvoiceCancelled.php
```

---

## 12. Stripe Integration

### Package: `prophoto-payments`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Payment/
  PaymentProcessorContract.php     // Process payments
  WebhookHandlerContract.php        // Handle Stripe webhooks
  RefundProcessorContract.php       // Process refunds
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  PaymentRequest.php
  PaymentResult.php
  StripeWebhook.php
  RefundRequest.php
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  PaymentStatus.php        // pending, processing, succeeded, failed, refunded
  PaymentMethod.php        // card, bank_transfer, ach
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  PaymentProcessed.php
  PaymentFailed.php
  RefundProcessed.php
  WebhookReceived.php
```

---

## 13. Notification System

### Package: `prophoto-notifications`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Notification/
  NotificationDispatcherContract.php  // Send notifications
  TemplateRendererContract.php         // Render email templates
  PreferenceManagerContract.php        // User preferences
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  NotificationRequest.php
  NotificationResult.php
  NotificationPreferences.php
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  NotificationChannel.php  // email, sms, push, in_app
  NotificationType.php     // gallery_ready, invoice_sent, booking_confirmed, etc.
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  NotificationSent.php
  NotificationFailed.php
  NotificationDelivered.php
```

**Note**: This package LISTENS to events from other packages and sends notifications.

---

## 14. Magic Link Security

### Package: `prophoto-security`

#### Contracts to Add
```php
prophoto-contracts/src/Contracts/Security/
  MagicLinkGeneratorContract.php   // Generate secure tokens
  TokenVerifierContract.php         // Verify tokens
  RateLimiterContract.php           // Throttle requests
```

#### DTOs to Add
```php
prophoto-contracts/src/DTOs/
  MagicLinkToken.php       // Token + expiry + scope
  RateLimit.php            // Limit + window + remaining
```

#### Enums to Add
```php
prophoto-contracts/src/Enums/
  TokenScope.php           // gallery_access, download, approval
  TokenStatus.php          // valid, expired, revoked, used
```

#### Events to Add
```php
prophoto-contracts/src/Events/
  MagicLinkGenerated.php
  MagicLinkUsed.php
  RateLimitExceeded.php
  TokenRevoked.php
```

---

## Summary: Contract Growth Pattern

As you can see, for each new package you create:

1. **Define contracts** (interfaces) in `prophoto-contracts/src/Contracts/`
2. **Define DTOs** (data shapes) in `prophoto-contracts/src/DTOs/`
3. **Define enums** (constants) in `prophoto-contracts/src/Enums/`
4. **Define events** (integration points) in `prophoto-contracts/src/Events/`

Then in your implementation package (prophoto-booking, prophoto-ai, etc.):

5. **Implement contracts** with real logic
6. **Bind implementations** in service provider
7. **Emit events** at key moments
8. **Listen to events** from other packages

## Event-Driven Integration Example

Here's how packages integrate via events without tight coupling:

```php
// In prophoto-booking (emitter)
namespace ProPhoto\Booking\Services;

use ProPhoto\Contracts\Events\BookingConfirmed;

class BookingEngine
{
    public function confirm($booking)
    {
        // ... confirm the booking

        event(new BookingConfirmed($booking));  // Emit event
    }
}

// In prophoto-notifications (listener)
namespace ProPhoto\Notifications\Listeners;

use ProPhoto\Contracts\Events\BookingConfirmed;

class SendBookingConfirmationEmail
{
    public function handle(BookingConfirmed $event)
    {
        // Send email to client
    }
}

// In prophoto-audit (listener)
namespace ProPhoto\Audit\Listeners;

use ProPhoto\Contracts\Events\BookingConfirmed;

class LogBookingActivity
{
    public function handle(BookingConfirmed $event)
    {
        // Log to activity table
    }
}

// In prophoto-invoicing (listener)
namespace ProPhoto\Invoicing\Listeners;

use ProPhoto\Contracts\Events\BookingConfirmed;

class CreateInvoiceForBooking
{
    public function handle(BookingConfirmed $event)
    {
        // Auto-generate invoice
    }
}
```

**Key insight**: `prophoto-booking` doesn't know about notifications, audit, or invoicing. It just emits an event. Other packages react independently.

## The Flexibility You Asked About

**Q: "As I add new components, do I need to register new classes?"**

**A: No central registration needed.** Here's what happens automatically:

1. ✅ Laravel auto-discovers service providers via `extra.laravel.providers` in composer.json
2. ✅ Service providers register their own implementations
3. ✅ Event listeners register themselves via `EventServiceProvider`
4. ✅ Artisan commands register themselves via service providers
5. ✅ Routes load themselves in service providers
6. ✅ Migrations load themselves in service providers

**The only "registration" you do**:
- Add the package to `sandbox/composer.json` (one line)
- Document it in `SYSTEM.md` (for humans)

**Q: "Will it be flexible?"**

**A: Extremely flexible.** The architecture is:
- **Decentralized** (no bottleneck)
- **Event-driven** (loose coupling)
- **Contract-based** (clear boundaries)
- **Self-registering** (auto-discovery)

You can add all 20+ components from your roadmap without changing the core structure.

## Next Steps

1. **Keep expanding prophoto-contracts** as you build new packages
2. **Follow the event-driven pattern** for integration (don't call other packages directly)
3. **Document each package** in SYSTEM.md as you create it
4. **Use `./scripts/prophoto doctor`** to validate everything is wired correctly

The system scales horizontally forever. Each package is independent, testable, and swappable.
