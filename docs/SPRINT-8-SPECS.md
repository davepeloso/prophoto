# Sprint 8 — AI Generation Provider Abstraction
## Spec Document

**Status:** ✅ Complete (2026-04-15) — all 5 stories landed, 152 tests / 396 assertions passing via `php artisan test --testsuite=AI` from the sandbox app.

**Sprint date:** April 2026
**Primary package:** `prophoto-ai` (provider contracts, registry, Astria implementation, ImageKit storage, Filament UI)
**Secondary package:** `prophoto-contracts` (shared AI generation contracts + DTOs)
**Read-only dependencies:** `prophoto-gallery`, `prophoto-assets`, `prophoto-access`
**Contracts modified:** Yes — new `AI/` contract namespace
**Assets modified:** None

---

## Sprint Goal

Turn the bare-bones `prophoto-ai` package into a provider-agnostic generation platform. The existing 3 models and 3 migrations define the data shape but have zero services, zero UI, and zero provider abstraction. Sprint 8 builds the abstraction layer, slots Astria in as the first generation provider and ImageKit as the delivery/storage layer, and wires up a working end-to-end flow: photographer selects images → trains a model → generates portraits → portraits stored in ImageKit → views/downloads results with URL-based post-processing.

**Why now:** ~100 active customers are asking for AI portrait generation from headshots and video generation from images. The modular architecture was designed specifically to pipe images to different packages and get results. Galleries are staging grounds for AI generation — this is the platform's core value proposition, not a feature.

---

## Provider Strategy

ProPhoto's AI image services are split into two distinct layers: **generation** (creative, model-driven, expensive) and **delivery** (deterministic, URL-driven, cheap). This separation is a core architectural principle — a provider may be capable of both, but ProPhoto always splits the concerns internally.

### Generation Providers (creative compute)
Providers that create new images or perform model-driven enhancements. Treated as compute engines — their output URLs are transient, never canonical.

| Provider | Role | Use For | Sprint 8? |
|----------|------|---------|-----------|
| **Astria** | Identity + training + consistency | Subject-based portrait generation, fine-tuned models per client/gallery, anything where identity matters | ✅ Yes |
| **Fal.ai** | Real-time / dev velocity | Instant previews, UI-driven generation (sliders, prompt tweaks), experimentation, non-critical outputs | Future |
| **Magnific** | High-end post-processing | Final hero exports, print-quality assets, marketing-grade "make this insanely detailed" outputs | Future |
| **Claid.ai** | Commercial/product workflows | E-commerce imagery, clean backgrounds, catalog consistency, product shots | Future |

### Delivery Layer (deterministic transforms + CDN)
ImageKit serves every ProPhoto-owned output. After a generation provider produces an image, it gets stored in ImageKit (backed by DigitalOcean Spaces) and all subsequent transforms happen via URL parameters — cheap, repeatable, instant.

| Capability | How | Example |
|------------|-----|---------|
| Resize / crop | URL transform | `tr:w-400,h-300` |
| Format conversion | URL transform | `tr:f-webp` or `tr:f-avif` |
| Background removal | URL AI extension | `tr:e-bgremove` |
| Retouch / enhance | URL AI extension | `tr:e-retouch` |
| Drop shadow | URL AI extension | `tr:e-shadow` |
| Upscale | URL AI extension | `tr:e-upscale` |
| Signed URLs | SDK method | `$imageKit->url([..., 'signed' => true])` |
| CDN delivery | Automatic | All URLs served via ImageKit CDN edge |

**Key insight:** Background removal, upscaling, and retouching are NOT generation-time options. They happen at the delivery layer on already-stored images. This means:
- The generation request to Astria is simpler (just prompt + num_images)
- Post-processing costs are ImageKit extension units, not Astria API costs
- Post-processing can be applied/removed/changed after generation without re-running the AI
- The same transforms work on ANY stored image, not just AI-generated ones

### Core Principle: Providers Are Compute, Not Truth

External providers are compute engines. ProPhoto must never treat a provider URL, provider-hosted asset, or provider-side state as canonical.

```
Always:  provider output → fetch → persist into ProPhoto-owned storage (ImageKit/DO Spaces) → serve via delivery layer
Never:   provider output URL → directly treated as production asset
```

---

## Architecture Overview

```
prophoto-contracts/src/Contracts/AI/
  AiProviderContract.php          ← what every generation provider must implement
  AiStorageContract.php           ← what the storage/delivery layer must implement

prophoto-contracts/src/DTOs/AI/
  (request/response DTOs, Money, capabilities, etc.)

prophoto-ai/src/
  Registry/
    AiProviderRegistry.php        ← discovers + resolves generation providers
  Providers/
    Astria/
      AstriaProvider.php          ← implements AiProviderContract
      AstriaApiClient.php         ← HTTP client for Astria API
      AstriaConfig.php            ← API key, endpoints, pricing
  Storage/
    ImageKitStorage.php           ← implements AiStorageContract (upload, URL generation, transforms)
    ImageKitConfig.php            ← API keys, URL endpoint, DO Spaces config
  Services/
    AiOrchestrationService.php    ← coordinates train → generate → store → persist
    AiCostService.php             ← tracks costs per provider + delivery transforms
  Jobs/
    TrainModelJob.php             ← queued: sends training images to provider
    PollTrainingStatusJob.php     ← queued: checks if model is ready
    GeneratePortraitsJob.php      ← queued: dispatches generation request
    PollGenerationStatusJob.php   ← queued: checks if generation is done, stores results
  Events/
    AiModelTrained.php            ← dispatched when training completes
    AiGenerationCompleted.php     ← dispatched when portraits are stored + ready
  Filament/
    RelationManagers/AiGenerationRelationManager.php  ← admin UI on EditGallery
```

---

## Stories

### Story 8.1 — AI Provider Contracts & Registry (5 pts)
**The foundation.** Define the contracts that generation providers and the storage layer must implement, plus the registry that manages generation providers. The contract must be smart enough to accommodate all five planned providers but narrow enough to implement in a sprint.

**Deliverables:**

- **`AiProviderContract`** in `prophoto-contracts` — interface every generation provider implements:
  - `providerKey(): string` — unique slug (e.g., `'astria'`, `'fal'`, `'magnific'`, `'claid'`)
  - `displayName(): string` — human-readable name for UI
  - `providerRole(): ProviderRole` — enum: `identity_generation`, `realtime_generation`, `enhancement`, `commercial_background`
  - `capabilities(): AiProviderCapabilities` — what this provider can do
  - `validateConfiguration(): bool` — check API key/connectivity
  - `submitTraining(TrainingRequest $request): TrainingResponse` — start model training (no-op for providers that don't train)
  - `getTrainingStatus(string $externalModelId): TrainingStatusResponse` — poll training
  - `submitGeneration(GenerationRequest $request): GenerationResponse` — request image generation
  - `getGenerationStatus(string $externalRequestId): GenerationStatusResponse` — poll generation
  - `estimateTrainingCost(int $imageCount): Money` — cost estimate before committing
  - `estimateGenerationCost(int $numImages): Money` — cost estimate per generation

- **`AiStorageContract`** in `prophoto-contracts` — interface for the delivery/storage layer:
  - `upload(string $sourceUrl, string $fileName, string $folder, array $tags = []): StorageResult` — fetch from provider URL, store permanently
  - `generateUrl(string $fileId, array $transforms = []): string` — build delivery URL with transforms
  - `generateSignedUrl(string $fileId, array $transforms = [], int $expireSeconds = 3600): string` — signed delivery URL
  - `delete(string $fileId): bool` — remove stored file
  - `validateConfiguration(): bool` — check API key/connectivity

- **DTOs** in `prophoto-contracts/src/DTOs/AI/`:
  - `AiProviderCapabilities` — supports_training (bool), supports_generation (bool), supports_video (bool), max_training_images (int), min_training_images (int), max_generations_per_model (?int, null = unlimited), supported_output_formats[]
  - `TrainingRequest` — provider_key, image_urls[], subject_name, callback_url?, metadata[]
  - `TrainingResponse` — external_model_id, estimated_duration_seconds, cost (Money)
  - `TrainingStatusResponse` — status (TrainingStatus enum), external_model_id, error_message?, completed_at?, expires_at?
  - `GenerationRequest` — external_model_id, prompt, num_images, metadata[]
  - `GenerationResponse` — external_request_id, estimated_duration_seconds, cost (Money)
  - `GenerationStatusResponse` — status (GenerationStatus enum), image_urls[], error_message?
  - `StorageResult` — file_id, url, thumbnail_url, file_size, metadata[]
  - `Money` — amount (int, cents), currency (string, default 'USD'), `toDollars(): float`, `add(Money): Money`

- **Enums** in `prophoto-contracts/src/Enums/AI/`:
  - `ProviderRole` — `IdentityGeneration`, `RealtimeGeneration`, `Enhancement`, `CommercialBackground`
  - `TrainingStatus` — `Pending`, `Training`, `Trained`, `Failed`, `Expired`
  - `GenerationStatus` — `Pending`, `Processing`, `Completed`, `Failed`

- **`AiProviderDescriptor`** in `prophoto-contracts/src/DTOs/AI/` — static metadata DTO:
  - provider_key, display_name, provider_role, capabilities, default_config[]

- **`AiProviderRegistry`** in `prophoto-ai/src/Registry/`:
  - `register(AiProviderDescriptor $descriptor, callable $resolver): void`
  - `resolve(string $providerKey): AiProviderContract`
  - `all(): array` — all registered descriptors
  - `has(string $providerKey): bool`
  - `forRole(ProviderRole $role): array` — descriptors matching a role
  - `default(): AiProviderContract` — resolves the configured default provider
  - Lazy resolution via callables (same pattern as IntelligenceGeneratorRegistry)
  - Duplicate registration throws InvalidArgumentException

- **Config** — `prophoto-ai.providers` section:
  ```php
  'default_provider' => env('AI_PROVIDER', 'astria'),
  'providers' => [
      'astria' => [
          'enabled' => true,
          'api_key' => env('ASTRIA_API_KEY'),           // starts with sd_
          'api_base_url' => env('ASTRIA_API_URL', 'https://api.astria.ai/v1'),
          'max_generations_per_model' => 5,              // our business limit
          'default_images_per_prompt' => 8,              // Astria max is 8
          'model_expiry_days' => 30,                     // Astria auto-deletes after 30d
          'training_cost_cents' => 150,                  // $1.50 per fine-tune
          'generation_cost_cents' => 23,                 // $0.23 per prompt (up to 8 images)
          'preset' => 'flux-lora-portrait',              // optimized for portraits
          'model_type' => 'lora',                        // faster, smaller models
          'face_crop' => true,                           // recommended for headshots
          'default_negative_prompt' => 'double torso, totem pole, old, wrinkles, mole, blemish, (oversmoothed, 3d render), scar, sad, severe, 2d, sketch, painting, digital art, drawing, disfigured, elongated body, text, cropped, out of frame',
      ],
      // Future: fal, magnific, claid — same structure, enabled => false
  ],
  'storage' => [
      'driver' => env('AI_STORAGE_DRIVER', 'imagekit'),
      'imagekit' => [
          'public_key' => env('IMAGEKIT_PUBLIC_KEY'),
          'private_key' => env('IMAGEKIT_PRIVATE_KEY'),
          'url_endpoint' => env('IMAGEKIT_URL_ENDPOINT'),
      ],
  ],
  ```

**Technical notes:**
- The contract is intentionally two-phase (train + generate) because that's how fine-tuning providers work. Providers that don't require training (Fal, Magnific, Claid) return a `TrainingResponse` with `external_model_id = 'none'` and `cost = Money(0)` — their `submitTraining` is a no-op.
- `GenerationRequest` is deliberately simple — no bg_removal, no super_resolution, no output_size. Those are delivery-layer concerns handled by ImageKit URL transforms AFTER storage. The generation contract only asks the provider to make images.
- `Money` uses integer cents to avoid floating-point issues. The existing `fine_tune_cost` decimal columns stay as-is (no migration needed), but the service layer works in cents.
- `AiStorageContract` is separate from `AiProviderContract` because storage/delivery is architecturally distinct from generation. ImageKit implements storage; Astria implements generation. They never cross.
- `ProviderRole` enum enables future routing: "I need identity generation" → registry returns Astria. "I need real-time preview" → registry returns Fal. Not used for routing in Sprint 8 (only one provider), but the data model supports it from day one.

**Tests:**
- Registry registers and resolves providers
- Duplicate registration throws exception
- `resolve()` throws for unknown provider
- `default()` returns the configured default
- `has()` returns true/false correctly
- `forRole()` filters correctly
- DTO construction and validation
- Money arithmetic (cents ↔ dollars, add)
- AiProviderCapabilities validation
- TrainingStatus and GenerationStatus enum values

---

### Story 8.2 — Astria Provider + ImageKit Storage (5 pts)
**The first real provider and the storage layer.** Implement `AiProviderContract` for Astria.ai and `AiStorageContract` for ImageKit. After this story, we can train models, generate portraits, and store them permanently.

**Deliverables:**

- **`AstriaProvider`** — `prophoto-ai/src/Providers/Astria/AstriaProvider.php`
  - Implements `AiProviderContract`
  - `providerRole()` returns `ProviderRole::IdentityGeneration`
  - Maps Astria API responses to contract DTOs
  - Handles Astria's specific quirks: tune = training, prompt = generation
  - **Status mapping** (Astria uses timestamps, not status strings):
    - `getTrainingStatus()` → checks `trained_at` non-null = `TrainingStatus::Trained`, `started_training_at` non-null = `TrainingStatus::Training`, otherwise `TrainingStatus::Pending`. Webhook `status: 'failed'` = `TrainingStatus::Failed`
    - `getGenerationStatus()` → checks `images` array populated = `GenerationStatus::Completed`, otherwise `GenerationStatus::Processing`. Webhook `status: 'failed'` = `GenerationStatus::Failed`
  - `estimateTrainingCost()` — flat $1.50 per model from config
  - `estimateGenerationCost()` — $0.23 per prompt (covers up to 8 images) from config
  - `capabilities()` returns: supports_training=true, min_training_images=8, max_training_images=20, max_generations_per_model=5 (our limit, not Astria's)

- **`AstriaApiClient`** — `prophoto-ai/src/Providers/Astria/AstriaApiClient.php`
  - Guzzle-based HTTP client for Astria's REST API
  - Base URL: `https://api.astria.ai/v1/`
  - Auth: `Authorization: Bearer {api_key}` (keys start with `sd_`)
  - Methods:
    - `createTune(array $imageUrls, string $className, string $title): array` — POST /tunes
      - Payload: `image_urls`, `name` (class: 'man'/'woman'/'person'), `title` (idempotency key), `face_crop: true`, `preset: 'flux-lora-portrait'`, `model_type: 'lora'`, `callback` (webhook URL)
      - Response: JSON with `id` (integer tune ID), `eta`, `started_training_at: null`, `trained_at: null`
      - Note: No `status` field returned — status tracked via timestamps
    - `getTune(int $tuneId): array` — GET /tunes/{id}
      - Response: Full tune object. Training complete when `trained_at` is non-null. Failed tunes have error info.
    - `createPrompt(int $tuneId, string $prompt, string $negativePrompt, int $numImages = 8): array` — POST /prompts
      - Payload: `tune_id`, `prompt`, `negative_prompt`, `num_images` (1-8, default 8), `w: 1024`, `h: 1024`, `callback`
      - Response: JSON with `id` (integer prompt ID), `images: []` (empty until complete)
    - `getPrompt(int $promptId): array` — GET /prompts/{id}
      - Response: Full prompt object. Complete when `images` array is populated with URL strings (e.g., `["https://...", "https://..."]`)
  - Retry logic: 3 attempts with exponential backoff for 429/5xx
  - Request/response logging at debug level
  - Timeout: 30s for requests, 60s for training submission (larger payload)
  - Webhook support: Astria sends full entity object to callback URL on completion. No signature header — use query params for routing (e.g., `?generation_id=123`). JSON callback format (enabled in Astria API settings).

- **`AstriaConfig`** — `prophoto-ai/src/Providers/Astria/AstriaConfig.php`
  - Reads from `prophoto-ai.providers.astria` config
  - Typed accessors: apiKey(), baseUrl(), maxGenerationsPerModel(), trainingCostCents(), generationCostCents(), modelExpiryDays() (default 30), defaultPromptImageCount() (default 8)
  - Validates API key presence (must start with `sd_`)
  - Generates callback URLs: `{app_url}/api/webhooks/astria?type={tune|prompt}&id={local_record_id}`

- **`ImageKitStorage`** — `prophoto-ai/src/Storage/ImageKitStorage.php`
  - Implements `AiStorageContract`
  - Uses `imagekit/imagekit` PHP SDK
  - `upload()` — passes provider's transient URL directly to ImageKit (ImageKit fetches it — no intermediate download needed):
    ```php
    $result = $this->imageKit->uploadFile([
        'file' => $sourceUrl,           // ImageKit accepts URLs as file source
        'fileName' => $fileName,
        'folder' => $folder,
        'tags' => $tags,
    ]);
    ```
  - `generateUrl()` — builds ImageKit URL with transformation parameters:
    ```php
    $this->imageKit->url([
        'path' => $filePath,
        'transformation' => $transforms,
    ]);
    ```
  - `generateSignedUrl()` — same but with `'signed' => true, 'expireSeconds' => $expireSeconds`
  - `delete()` — removes file from ImageKit media library
  - Returns `StorageResult` with fileId, url, thumbnailUrl, fileSize from ImageKit response

- **`ImageKitConfig`** — `prophoto-ai/src/Storage/ImageKitConfig.php`
  - Reads from `prophoto-ai.storage.imagekit` config
  - Typed accessors: publicKey(), privateKey(), urlEndpoint()
  - Validates required keys present

**Existing infrastructure:**
- `guzzlehttp/guzzle ^7.0` already in prophoto-ai composer.json (for Astria)
- `imagekit/imagekit` needs to be added to composer.json (for ImageKit SDK)
- The 3 existing models already have Astria-specific fields (fine_tune_id) and ImageKit fields (imagekit_file_id, imagekit_url, imagekit_thumbnail_url)
- Gallery model has `ai_enabled`, `ai_training_status`, `canGenerateAiPortraits()`

**Technical notes:**
- Astria API terminology: tune = model training, prompt = generation request.
- Astria uses timestamps for status, not string fields: `trained_at` non-null means training is done, `images` array populated means generation is done. The provider maps these to our enum-based status system.
- Astria returns image URLs that expire — ImageKit's upload-by-URL handles this cleanly (ImageKit fetches the image from Astria's URL before it expires, stores it permanently).
- Astria strongly prefers webhooks over polling (will 429 if you poll too aggressively). Our v1 uses polling with generous backoff intervals (30s → 60s → 120s). Webhook receiver is a v2 optimization — the `callback` param is already in the API client for when we add it.
- Astria has idempotency support: POST requests with the same `title`/`text` only create one entity. We should use our local record ID as the title for safety.
- Training requires 8-20 images, 1:1 aspect ratio, under 3MB, face prominently centered. The orchestration service should validate these before submitting.
- Models auto-expire after 30 days. We store `model_expires_at` on AiGeneration and can show a warning in the UI.
- The API client is intentionally a separate class from the provider — the client handles HTTP, the provider handles business logic mapping.
- ImageKit AI transforms (bg removal, retouch, upscale, shadow) are NOT part of the storage layer's `upload()` — they're applied via `generateUrl()` with transform params at display time. Priced per ImageKit extension unit.
- All Astria API calls should be made from queued jobs, never synchronously from web requests. ImageKit uploads happen inside job handlers (after Astria returns results).
- Astria also offers bg removal ($0.01/image) and super-resolution ($0.0125/image) as generation-time params. We've moved these to the ImageKit delivery layer for architectural cleanliness, but could revisit if ImageKit extension units prove more expensive for Dave's volume.

**Tests:**
- AstriaProvider implements AiProviderContract
- submitTraining maps request to Astria tune API format
- getTrainingStatus maps Astria statuses to TrainingStatus enum
- submitGeneration maps request to Astria prompt format
- getGenerationStatus maps Astria response to GenerationStatus enum
- estimateTrainingCost returns correct Money from config
- estimateGenerationCost returns correct Money from config
- validateConfiguration returns false when API key missing
- AstriaApiClient retry logic (mock Guzzle)
- ImageKitStorage.upload() calls SDK with correct params
- ImageKitStorage.upload() returns StorageResult with correct fields
- ImageKitStorage.generateUrl() builds transform URLs correctly
- ImageKitStorage.generateSignedUrl() adds signature params
- ImageKitStorage.delete() calls SDK delete
- ImageKitConfig validates required keys

---

### Story 8.3 — AI Orchestration Service & Jobs (5 pts)
**The engine.** Wire together the provider abstraction and storage layer with the existing database models and queue jobs. This is where train → poll → generate → poll → store → persist actually happens.

**Deliverables:**

- **`AiOrchestrationService`** — `prophoto-ai/src/Services/AiOrchestrationService.php`
  - `initiateTraining(Gallery $gallery, Collection $images, ?int $userId = null): AiGeneration`
    - Validates gallery.ai_enabled
    - Validates image count against provider capabilities (min/max)
    - Creates AiGeneration record (status: pending, provider_key from registry default)
    - Resolves image URLs from assets (uses derivative URLs, not originals)
    - Dispatches TrainModelJob
    - Logs to gallery activity ledger: `ai_training_started`
  - `initiateGeneration(AiGeneration $generation, ?string $prompt, int $numImages = 8, ?int $userId = null): AiGenerationRequest`
    - Validates model is trained
    - Validates generation quota (remaining > 0)
    - Creates AiGenerationRequest record (status: pending, provider_key from generation)
    - Calculates and stores cost via provider's estimateGenerationCost
    - Dispatches GeneratePortraitsJob
    - Logs: `ai_generation_started`
  - `handleTrainingComplete(AiGeneration $generation, TrainingStatusResponse $status): void`
    - Updates model_status to trained/failed
    - Sets model_created_at, model_expires_at (from status response)
    - Updates gallery.ai_training_status
    - Dispatches AiModelTrained event
  - `handleGenerationComplete(AiGenerationRequest $request, GenerationStatusResponse $status): void`
    - For each image URL in status response:
      - Calls storage.upload() (ImageKit fetches from provider URL, stores permanently)
      - Creates AiGeneratedPortrait record with ImageKit file_id, url, thumbnail_url
    - Updates request status to completed
    - Updates request portrait count
    - Dispatches AiGenerationCompleted event

- **Queue jobs:**
  - `TrainModelJob` — resolves provider from registry, calls provider.submitTraining(), stores external_model_id on AiGeneration, dispatches PollTrainingStatusJob with 30s delay
  - `PollTrainingStatusJob` — calls provider.getTrainingStatus(). If still training: re-dispatches self with backoff (30s → 60s → 120s → 120s..., max 24h total). If complete/failed: calls orchestration service's handleTrainingComplete()
  - `GeneratePortraitsJob` — resolves provider, calls provider.submitGeneration(), stores external_request_id, dispatches PollGenerationStatusJob with 15s delay
  - `PollGenerationStatusJob` — calls provider.getGenerationStatus(). Same backoff pattern, max 2h. If complete: calls handleGenerationComplete() which stores each portrait in ImageKit

- **Events:**
  - `AiModelTrained` — galleryId, generationId, providerKey, modelStatus, trainedAt
  - `AiGenerationCompleted` — galleryId, generationId, requestId, portraitCount, providerKey

- **`AiCostService`** — `prophoto-ai/src/Services/AiCostService.php`
  - `estimateTrainingCost(string $providerKey, int $imageCount): Money` — delegates to provider
  - `estimateGenerationCost(string $providerKey, int $numImages): Money` — delegates to provider
  - `totalSpentForGallery(Gallery $gallery): Money` — aggregates from database (training + generation costs)
  - `totalSpentForStudio(int $studioId): Money` — studio-wide aggregate
  - Note: ImageKit transform costs (bg removal, upscale, etc.) are billed by ImageKit directly and are NOT tracked here — they're per-request URL transforms, not discrete operations we can meter.

**Existing infrastructure:**
- 3 Eloquent models with all the right columns (AiGeneration, AiGenerationRequest, AiGeneratedPortrait)
- Gallery.aiGeneration() HasOne relationship
- Gallery.ai_enabled, ai_training_status fields
- Gallery.canGenerateAiPortraits() method
- `illuminate/queue` already in composer.json
- GalleryActivityLogger::log() for audit trail

**Technical notes:**
- Polling jobs use self-dispatching with increasing delay. Max poll duration: 24 hours for training (Astria can take 15-60 minutes), 2 hours for generation (typically 30-90 seconds). After max duration: mark as failed with timeout error.
- The generation-to-storage handoff: Astria returns temporary image URLs → PollGenerationStatusJob detects completion → calls handleGenerationComplete() → loops through image URLs → calls ImageKitStorage.upload(providerUrl, ...) for each → ImageKit fetches from Astria URL and stores permanently → creates AiGeneratedPortrait row with ImageKit URLs. This is the critical path that turns transient provider output into permanent ProPhoto-owned assets.
- All jobs should be on a dedicated `ai` queue to avoid blocking gallery operations.
- The orchestration service does NOT call providers directly — it dispatches jobs. This keeps web requests fast and makes the flow resumable after failures.

**Tests:**
- initiateTraining creates AiGeneration record + dispatches job
- initiateTraining rejects when ai_enabled is false
- initiateTraining rejects when image count below provider minimum
- initiateGeneration validates model is trained
- initiateGeneration respects generation quota
- TrainModelJob calls provider and stores external ID
- PollTrainingStatusJob re-dispatches when still training
- PollTrainingStatusJob calls handleTrainingComplete when done
- PollTrainingStatusJob marks failed after max duration
- GeneratePortraitsJob calls provider and stores external ID
- PollGenerationStatusJob calls handleGenerationComplete on success
- handleGenerationComplete stores each portrait via ImageKitStorage
- handleGenerationComplete creates AiGeneratedPortrait records with correct ImageKit fields
- AiCostService aggregates correctly from database
- AiModelTrained event dispatched on success
- AiGenerationCompleted event dispatched on success
- Activity ledger records for all state transitions

---

### Story 8.4 — AI Generation Filament UI (5 pts)
**The photographer's interface.** Build the Filament admin pages where photographers manage AI generation: enable it on a gallery, see training status, trigger generations, view/download results. Post-processing (bg removal, upscale, retouch) is visual via ImageKit URL transforms — no extra API calls.

**Deliverables:**

- **`AiGenerationRelationManager`** on EditGallery — shows AI generation status for the gallery
  - When no AiGeneration exists: "Enable AI Generation" action button
  - When training: progress indicator with status badge (pending/training), estimated time
  - When trained: generation form (prompt field, num_images selector, cost estimate, "Generate" button)
  - When generation requests exist: table of requests with status, portrait count, cost, created date
  - Row action on completed requests: "View Portraits" → opens gallery modal with thumbnail grid

- **"Enable AI" toggle section** on GalleryResource form:
  - Toggle: `ai_enabled` — shows/hides the AI section
  - When enabled and gallery has images: "Start Training" action with cost confirmation modal
  - Training uses ALL images in the gallery (photographer curates the gallery first)
  - Cost confirmation: "Training costs ${cost}. This will train an AI model on {count} images."

- **AI Portraits viewer** — modal or page showing generated portraits:
  - Thumbnail grid of AiGeneratedPortrait records (uses `imagekit_thumbnail_url`)
  - Click to open full-size in lightbox (uses `imagekit_url`)
  - Download button per portrait (updates downloaded_by_subject)
  - Show request metadata: prompt used, cost, generation date
  - **Post-processing toggles** — apply ImageKit URL transforms live:
    - "Remove Background" → appends `tr:e-bgremove` to URL
    - "Enhance" → appends `tr:e-retouch` to URL
    - "Upscale" → appends `tr:e-upscale` to URL
    - These are display-time transforms — toggling them changes the URL, not the stored file
    - Show a note: "These enhancements are applied on-the-fly via your ImageKit plan"

- **Provider status indicator** — small badge in the AI section showing:
  - Provider name (e.g., "Astria")
  - Connection status (API key configured vs. missing)
  - Available quota (remaining generations)

- **Cost summary** — running total for this gallery:
  - Training cost
  - Generation costs (per request + total)
  - Studio-wide spend (via AiCostService)

**Existing infrastructure:**
- `GalleryShareRelationManager` and `GalleryActivityRelationManager` are pattern templates
- Gallery model already has ai_enabled, ai_training_status fields and constants
- AiGeneration model has remaining_generations computed attribute
- AiGeneration model has total_cost computed attribute
- Filament v4 namespace conventions documented in `Filament-Namespace-Issue.md`

**Technical notes:**
- The AI section should be visually distinct — use a card/section with an icon to separate it from gallery metadata
- Training status should poll via Filament's built-in `poll('5s')` on the relation manager for real-time updates during training
- Generation quota shown as "X of 5 remaining" with progress bar
- Don't show AI section at all if no provider is configured (check registry.has(default))
- Post-processing toggles are client-side URL manipulation via ImageKit's transform syntax — no server calls, no cost tracking needed (ImageKit bills by extension unit usage automatically)
- The portrait viewer URL pattern: `{imagekit_url}` for raw, `{imagekit_url}?tr=e-bgremove` for bg removed, etc. ImageKit handles this via its URL-based transform API.

**Tests:**
- AI section hidden when no provider configured
- Enable AI toggle shows training section
- Start Training action creates AiGeneration + dispatches job
- Training status badge updates correctly
- Generation form visible only when model is trained
- Generation form respects quota (disabled when 0 remaining)
- Cost estimate displays correctly
- Portrait viewer shows thumbnails
- Download action updates downloaded_by_subject
- Post-processing toggles modify image URLs correctly

---

### Story 8.5 — Schema Migration & Provider Registration (3 pts)
**Wire everything together.** Add missing columns to existing tables, register providers and storage in the service provider, and handle the config/migration/dependency housekeeping.

**Deliverables:**

- **Migration** — `2026_04_xx_add_provider_fields_to_ai_tables.php`
  - `ai_generations`: add `provider_key` string (default 'astria'), `external_model_id` string nullable, `provider_metadata` json nullable
  - `ai_generation_requests`: add `provider_key` string (default 'astria'), `external_request_id` string nullable, `provider_metadata` json nullable
  - `ai_generated_portraits`: add `storage_driver` string (default 'imagekit'), `original_provider_url` string 1000 nullable (the ephemeral URL from provider, before ImageKit stored it)

- **Model updates:**
  - `AiGeneration` — add provider_key, external_model_id, provider_metadata to $fillable and $casts
  - `AiGenerationRequest` — add provider_key, external_request_id, provider_metadata to $fillable and $casts
  - `AiGeneratedPortrait` — add storage_driver, original_provider_url to $fillable

- **Composer dependency** — add `imagekit/imagekit` to prophoto-ai's composer.json

- **`AIServiceProvider` boot expansion:**
  - Load config from `config/ai.php`
  - Register `AiProviderRegistry` as singleton
  - Register `ImageKitStorage` as singleton (bound to `AiStorageContract`)
  - Register Astria provider in the registry (when config enabled)
  - Register `AiOrchestrationService` as singleton
  - Register `AiCostService` as singleton
  - Load views (for any future Filament component views)

- **Config file** — `prophoto-ai/config/ai.php`:
  - Full providers config section (as specified in 8.1)
  - Storage config section with ImageKit credentials
  - Queue config: `queue_name` (default 'ai'), `max_training_poll_hours` (24), `max_generation_poll_hours` (2)

**Existing infrastructure:**
- AIServiceProvider exists (minimal — only loads migrations)
- 3 migrations already create the core tables
- GalleryServiceProvider shows the pattern for config loading + singleton registration

**Technical notes:**
- We're adding columns alongside existing ones, NOT renaming. `fine_tune_id` stays (it's Astria-specific and already populated for any test data), `external_model_id` is the provider-agnostic equivalent. The Astria provider maps between them.
- `provider_metadata` json column stores provider-specific data that doesn't fit the contract schema — Astria stores tune metadata, Fal would store model identifiers, etc.
- `original_provider_url` preserves the transient URL for debugging/auditing even after ImageKit has the permanent copy.
- Default values on provider_key ensure existing records (if any from testing) don't break.
- `ImageKitStorage` is bound to `AiStorageContract` interface so future storage drivers (direct S3, Cloudflare R2, etc.) can be swapped without touching orchestration code.

**Tests:**
- Migration adds columns without breaking existing data
- Model $fillable and $casts updated
- AIServiceProvider registers registry singleton
- AIServiceProvider registers ImageKitStorage bound to AiStorageContract
- AIServiceProvider registers Astria provider when config enabled
- AIServiceProvider skips Astria registration when config disabled
- Config loads with correct defaults
- AiProviderRegistry resolves Astria after boot
- ImageKitStorage resolves from container after boot

---

## Implementation Order

```
8.1 Contracts & Registry  →  8.2 Astria + ImageKit  →  8.5 Schema + Wiring  →  8.3 Orchestration + Jobs  →  8.4 Filament UI
     (5 pts)                    (5 pts)                  (3 pts)                   (5 pts)                     (5 pts)
```

**Total: 23 points**

- **8.1 first** — contracts and registry are the foundation everything depends on
- **8.2 second** — implement both external integrations (Astria + ImageKit) against those contracts while the interface design is fresh
- **8.5 third** — wire up the service provider, migrations, config, and composer deps so 8.3 has infrastructure to work with
- **8.3 fourth** — orchestration + jobs need the providers (8.2), the registry (8.1), and the database columns (8.5)
- **8.4 last** — UI needs everything else working to be testable

---

## Architecture Decisions

1. **Generation vs. delivery separation is mandatory** — Astria generates images. ImageKit stores and serves them with URL-based transforms. Background removal, upscaling, retouching, and format conversion happen at the ImageKit URL layer, never at generation time. This makes generation requests simpler, post-processing reversible, and transforms applicable to any stored image.

2. **Contracts in `prophoto-contracts`, implementation in `prophoto-ai`** — same boundary as intelligence. Other packages can type-hint against `AiProviderContract` and `AiStorageContract` without depending on Astria or ImageKit.

3. **Two separate contracts: generation and storage** — `AiProviderContract` for creative compute, `AiStorageContract` for persistence + delivery. A provider might be capable of both (ImageKit has some AI features), but ProPhoto always splits these roles. Astria implements generation; ImageKit implements storage.

4. **Two-phase workflow baked into the generation contract** — train then generate. Providers that don't need training (Fal, Magnific, Claid) implement submitTraining as a no-op. Simpler than multiple contract types.

5. **Polling over webhooks** — simpler to implement, debug, and test for v1. Webhook support can be added later as an optimization without changing the contract.

6. **Dedicated `ai` queue** — AI jobs are slow (minutes to hours) and shouldn't block gallery operations.

7. **`external_model_id` alongside `fine_tune_id`** — additive migration, no renames. The Astria provider maps between them.

8. **Money in cents** — avoids floating-point arithmetic in the service layer. Database columns stay as decimal(8,2).

9. **ImageKit upload-by-URL** — ImageKit SDK accepts a URL as the file parameter. We pass Astria's transient URL directly to ImageKit, which fetches and stores it. No intermediate download to our server needed.

10. **Post-processing at the URL layer** — bg removal (`tr:e-bgremove`), retouch (`tr:e-retouch`), upscale (`tr:e-upscale`), and shadows (`tr:e-shadow`) are ImageKit URL transforms applied at display time. They don't modify the stored file. They're toggled in the UI by manipulating the URL. ImageKit bills these as extension units on their plan — we don't track them per-operation.

11. **No new Filament resource** — AI generation lives as a relation manager on EditGallery, not a separate top-level resource.

12. **Gallery images = training set** — the photographer curates the gallery first, then trains. No separate "select images for training" step.

13. **ProviderRole enum for future routing** — not used for routing in Sprint 8 (only one provider), but the data model supports "give me the identity generation provider" vs. "give me the realtime preview provider" from day one.

14. **This lives in `prophoto-ai`, not `prophoto-intelligence`** — intelligence derives metadata automatically from assets. AI generation is photographer-initiated, costs money, and has a completely different lifecycle. Different package, different concern.

---

## Provider Strategy Roadmap

Sprint 8 implements Astria + ImageKit. Future sprints add providers without changing the contract:

| Sprint | Provider | Role | What It Adds |
|--------|----------|------|-------------|
| **8** | Astria | Identity generation | Fine-tune + generate portraits |
| **8** | ImageKit | Storage + delivery | Upload, CDN, URL transforms (bg remove, retouch, upscale, shadow) |
| Future | Fal.ai | Realtime generation | Instant previews, UI-driven sliders, fast iteration |
| Future | Magnific | Enhancement | Hero exports, print-quality "make this insanely detailed" |
| Future | Claid.ai | Commercial background | E-commerce imagery, catalog consistency, clean product shots |

Each future provider: implement `AiProviderContract`, add config section, register in `AIServiceProvider`. No contract changes needed.

---

## Files Expected

### New Files
| File | Package | Story |
|------|---------|-------|
| `src/Contracts/AI/AiProviderContract.php` | prophoto-contracts | 8.1 |
| `src/Contracts/AI/AiStorageContract.php` | prophoto-contracts | 8.1 |
| `src/DTOs/AI/AiProviderCapabilities.php` | prophoto-contracts | 8.1 |
| `src/DTOs/AI/AiProviderDescriptor.php` | prophoto-contracts | 8.1 |
| `src/DTOs/AI/TrainingRequest.php` | prophoto-contracts | 8.1 |
| `src/DTOs/AI/TrainingResponse.php` | prophoto-contracts | 8.1 |
| `src/DTOs/AI/TrainingStatusResponse.php` | prophoto-contracts | 8.1 |
| `src/DTOs/AI/GenerationRequest.php` | prophoto-contracts | 8.1 |
| `src/DTOs/AI/GenerationResponse.php` | prophoto-contracts | 8.1 |
| `src/DTOs/AI/GenerationStatusResponse.php` | prophoto-contracts | 8.1 |
| `src/DTOs/AI/StorageResult.php` | prophoto-contracts | 8.1 |
| `src/DTOs/AI/Money.php` | prophoto-contracts | 8.1 |
| `src/Enums/AI/ProviderRole.php` | prophoto-contracts | 8.1 |
| `src/Enums/AI/TrainingStatus.php` | prophoto-contracts | 8.1 |
| `src/Enums/AI/GenerationStatus.php` | prophoto-contracts | 8.1 |
| `src/Registry/AiProviderRegistry.php` | prophoto-ai | 8.1 |
| `src/Providers/Astria/AstriaProvider.php` | prophoto-ai | 8.2 |
| `src/Providers/Astria/AstriaApiClient.php` | prophoto-ai | 8.2 |
| `src/Providers/Astria/AstriaConfig.php` | prophoto-ai | 8.2 |
| `src/Storage/ImageKitStorage.php` | prophoto-ai | 8.2 |
| `src/Storage/ImageKitConfig.php` | prophoto-ai | 8.2 |
| `src/Services/AiOrchestrationService.php` | prophoto-ai | 8.3 |
| `src/Services/AiCostService.php` | prophoto-ai | 8.3 |
| `src/Jobs/TrainModelJob.php` | prophoto-ai | 8.3 |
| `src/Jobs/PollTrainingStatusJob.php` | prophoto-ai | 8.3 |
| `src/Jobs/GeneratePortraitsJob.php` | prophoto-ai | 8.3 |
| `src/Jobs/PollGenerationStatusJob.php` | prophoto-ai | 8.3 |
| `src/Events/AiModelTrained.php` | prophoto-ai | 8.3 |
| `src/Events/AiGenerationCompleted.php` | prophoto-ai | 8.3 |
| `src/Filament/RelationManagers/AiGenerationRelationManager.php` | prophoto-ai | 8.4 |
| `database/migrations/2026_04_xx_add_provider_fields_to_ai_tables.php` | prophoto-ai | 8.5 |
| `config/ai.php` | prophoto-ai | 8.5 |

### Modified Files
| File | Package | Story | Change |
|------|---------|-------|--------|
| `src/Models/AiGeneration.php` | prophoto-ai | 8.5 | Add provider_key, external_model_id, provider_metadata to $fillable/$casts |
| `src/Models/AiGenerationRequest.php` | prophoto-ai | 8.5 | Add provider_key, external_request_id, provider_metadata to $fillable/$casts |
| `src/Models/AiGeneratedPortrait.php` | prophoto-ai | 8.5 | Add storage_driver, original_provider_url to $fillable |
| `src/AIServiceProvider.php` | prophoto-ai | 8.5 | Register config, singletons (registry, storage, orchestration, cost), Astria provider, views |
| `composer.json` | prophoto-ai | 8.5 | Add imagekit/imagekit dependency |
| `src/Filament/Resources/GalleryResource.php` | prophoto-gallery | 8.4 | Add AI section to form (enable toggle, training trigger) |
| `src/Filament/Resources/GalleryResource/Pages/EditGallery.php` | prophoto-gallery | 8.4 | Register AiGenerationRelationManager |

---

## What This Sprint Does NOT Include

- Video generation providers (architecture supports it via capabilities, but no implementation yet)
- Webhook receivers for real-time status updates (polling is v1)
- Client-facing AI portal (where subjects view/download their generated portraits)
- Bulk training across multiple galleries (one gallery = one model)
- Provider billing integration (costs tracked locally, not synced with provider billing APIs)
- Admin dashboard for studio-wide AI spend analytics (AiCostService provides the data, dashboard is future)
- Auto-training triggers (photographer must manually initiate)
- Fal.ai, Magnific, or Claid.ai provider implementations (architecture supports them, Sprint 8 only does Astria)
- ImageKit extension unit cost tracking (those are billed directly by ImageKit on your plan)
- DigitalOcean Spaces direct integration (ImageKit sits in front of Spaces — we talk to ImageKit, ImageKit talks to Spaces)

---

## Risk & Open Questions

1. **ImageKit SDK in PHP** — The `imagekit/imagekit` PHP SDK requires PHP 5.6+ with JSON and cURL extensions. Should be fine, but verify it works with our PHP 8.2+ and Laravel 12 setup before building on it.

2. ~~**Astria API docs needed**~~ — ✅ RESOLVED. Full API details confirmed: Bearer auth, POST /tunes, POST /prompts, GET endpoints, timestamp-based status, webhook payloads. See Story 8.2 for complete API mapping.

3. **Training image source** — Astria needs publicly accessible image URLs (8-20 images, 1:1 aspect ratio, under 3MB). Our assets may be stored behind signed URLs or in private storage. Need to verify the current asset derivative URL pattern works, or generate temporary signed URLs for training submission.

4. **Queue infrastructure** — A dedicated `ai` queue needs a worker running. For Dave's solo deployment: `php artisan queue:work --queue=ai,default`.

5. **ImageKit AI transform pricing** — Background removal, retouch, and upscale consume ImageKit extension units. Dave should check his ImageKit plan to understand per-transform costs vs. Astria's built-in options ($0.01/image bg removal, $0.0125/image super-res). Whichever is cheaper per your volume should be the default.

6. **Model expiry** — Astria auto-deletes trained models after 30 days. Extended storage costs $0.50/model/month. The UI should warn photographers when a model is approaching expiry, and we need to decide whether to auto-extend or let them expire. For now: track `model_expires_at`, show warning, no auto-extend.

7. **Consent & privacy** — Astria requires explicit informed consent before uploading biometric likenesses. The UI needs a consent checkbox or liability acceptance step before training. The `liability_accepted_at` column already exists on `AiGenerationRequest` — may need equivalent on `AiGeneration` for the training consent.

8. **Webhook infrastructure** — Astria prefers webhooks over polling. Our v1 uses polling, but we should ensure the app has a publicly accessible HTTPS URL for future webhook support. For local dev, this means ngrok or similar tunneling.

---

*Last updated: 2026-04-15 — Sprint 8 planning v3, adds confirmed Astria API details, generation/delivery separation, 5-provider vision*
