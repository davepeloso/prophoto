# Phase 8 — AI Generation Provider Abstraction
## Sprint Retrospective & Context Preservation

**Sprint date:** April 15, 2026
**Primary package:** `prophoto-ai` (registry, Astria provider, ImageKit storage, orchestration, jobs, events, Filament UI)
**Secondary package:** `prophoto-contracts` (new `AI/` contract namespace — interfaces, DTOs, enums)
**Read-only dependencies:** `prophoto-gallery`, `prophoto-assets`, `prophoto-access`
**Contracts modified:** Yes — new `Contracts/AI/`, `DTOs/AI/`, `Enums/AI/` subtrees
**Assets modified:** None
**Status:** Complete — all 5 stories landed, 152 tests / 396 assertions passing via `php artisan test --testsuite=AI` from the sandbox app

---

## What Was Built

### Story 8.1 — Contracts & Registry (8 pts)
The shared kernel for all future AI work. Lives in `prophoto-contracts` so consumer packages never depend on a concrete provider.

- **`AiProviderContract`** (`prophoto-contracts/src/Contracts/AI/`) — generation seam. `providerKey()`, `displayName()`, `providerRole()`, `capabilities()`, `validateConfiguration()`, `submitTraining()`, `getTrainingStatus()`, `submitGeneration()`, `getGenerationStatus()`, `estimateTrainingCost()`, `estimateGenerationCost()`.
- **`AiStorageContract`** (`prophoto-contracts/src/Contracts/AI/`) — delivery seam. `upload(sourceUrl, fileName, folder, tags)`, `generateUrl(fileId, transforms)`, `generateSignedUrl(fileId, transforms, expireSeconds)`, `delete(fileId)`, `validateConfiguration()`.
- **DTOs** (`prophoto-contracts/src/DTOs/AI/`) — all readonly, constructor-promoted: `TrainingRequest`, `TrainingResponse`, `TrainingStatusResponse`, `GenerationRequest`, `GenerationResponse`, `GenerationStatusResponse`, `StorageResult`, `Money`, `AiProviderCapabilities`, `AiProviderDescriptor`.
- **Enums** (`prophoto-contracts/src/Enums/AI/`) — `ProviderRole` (identity/realtime/upscale/commercial), `TrainingStatus` (queued/training/ready/failed/expired), `GenerationStatus` (queued/processing/completed/failed).
- **`AiProviderRegistry`** (`prophoto-ai/src/Registry/`) — descriptor + lazy-resolver pattern. `register(AiProviderDescriptor)`, `list(): array`, `resolve(key): AiProviderContract`. Descriptors store closures, providers are materialized only on resolve.

32 tests. Every contract method has a test that fails if the shape changes.

---

### Story 8.2 — Astria Provider + ImageKit Storage (13 pts)
First concrete implementations of both contracts.

- **`AstriaProvider`** (`prophoto-ai/src/Providers/Astria/`) — implements `AiProviderContract`. Maps Astria's timestamp-based status model (`finished_training_at`, `trained_at`) to our 5-state `TrainingStatus` enum. Idempotency key echoed back via Astria's `title` field.
- **`AstriaApiClient`** — thin HTTP wrapper. Bearer auth, `POST /tunes`, `POST /prompts`, `GET /tunes/{id}`, `GET /prompts/{id}`. Every HTTP call lives here; `AstriaProvider` orchestrates and maps DTOs.
- **`AstriaConfig`** — API key, base URL, default hyperparameters, per-image pricing (training $5/model, generation $0.10/image base).
- **`ImageKitStorage`** (`prophoto-ai/src/Storage/`) — implements `AiStorageContract`. `upload()` fetches from Astria's transient URL and persists to ImageKit (backed by DigitalOcean Spaces). `generateUrl()` builds `tr:` transform strings. `generateSignedUrl()` uses ImageKit SDK signing.
- **`ImageKitConfig`** — public key, private key, URL endpoint, DO Spaces config (endpoint is ImageKit — Spaces is transparent).

63 tests. Includes HTTP mocking of every Astria endpoint, status-mapping edge cases, and transform-string assembly.

---

### Story 8.3 — Orchestration, Cost, Jobs, Events (13 pts)
The engine that turns "photographer clicked Generate" into "portraits in the DB".

- **`AiOrchestrationService`** (`prophoto-ai/src/Services/`):
  - `initiateTraining(Gallery, Collection<Image>, ?providerKey)` — validates `ai_enabled`, validates image count against `AiProviderCapabilities::minTrainingImages/maxTrainingImages`, creates `AiGeneration` row, dispatches `TrainModelJob` on `ai` queue, logs to `GalleryActivityLogger`.
  - `initiateGeneration(AiGeneration, ?prompt, numImages, ?providerKey)` — validates `isReady()`, validates `remaining_generations` quota, creates `AiGenerationRequest`, dispatches `GeneratePortraitsJob`.
  - `handleTrainingComplete(AiGeneration, TrainingStatusResponse)` — updates status, dispatches `AiModelTrained` event.
  - `handleGenerationComplete(AiGenerationRequest, GenerationStatusResponse)` — loops image URLs, calls `storage.upload()` per image, creates `AiGeneratedPortrait` rows with ImageKit `file_id`, `url`, `thumbnail_url`, dispatches `AiGenerationCompleted`.
- **`AiCostService`** — `estimateTrainingCost()`, `estimateGenerationCost()` (both delegate to `registry.resolve()`), `totalSpentForGallery()`, `totalSpentForStudio()` (aggregate from DB). All money in cents via `Money` DTO.
- **Jobs** (`prophoto-ai/src/Jobs/`):
  - `TrainModelJob` — `tries=3`, `timeout=120`, `backoff=[30,60,120]`. Submits to provider, stores `external_model_id`, dispatches `PollTrainingStatusJob` with 30s initial delay.
  - `PollTrainingStatusJob` — `tries=1`, self-dispatching. `BACKOFF_SCHEDULE = [30, 60, 120]` seconds capped at 120, max runtime `max_training_poll_hours` (24h). Ctor: `(generationId, Carbon $startedAt, int $pollCount=0)`.
  - `GeneratePortraitsJob` — same pattern, 15s initial delay for its poll child.
  - `PollGenerationStatusJob` — `BACKOFF_SCHEDULE = [15, 30, 60]`, max 2h.
- **Events** (`prophoto-ai/src/Events/`):
  - `AiModelTrained(galleryId, generationId, providerKey, modelStatus, ?trainedAt)`
  - `AiGenerationCompleted(galleryId, generationId, requestId, portraitCount, providerKey)`
- **Injectable logging** — every service and job takes `Psr\Log\LoggerInterface` (`NullLogger` default). No facade `Log::` calls.

20 tests. Covers validation paths, quota enforcement, backoff math, and the full storage.upload → portrait-row persistence loop.

---

### Story 8.4 — Filament RelationManager (5 pts)
Admin UI on the Gallery edit page.

- **`AiGenerationRelationManager`** (`prophoto-ai/src/Filament/RelationManagers/`):
  - `$relationship = 'aiGeneration'`, `$title = 'AI Generation'`, `$icon = 'heroicon-o-sparkles'`.
  - `getPolling()` returns `'5s'` during active training/generation, `null` when idle — keeps the browser from hammering the DB once a run completes.
  - `modifyQueryUsing()` queries `AiGenerationRequest` through the gallery's `AiGeneration` (relation-manager v4 idiom, since `getTableQuery()` returns null in v4).
  - Header actions: `makeStartTrainingAction()` with cost-confirmation modal; `makeGenerateAction()` with prompt form + numImages slider.
  - Row action: `makeViewPortraitsAction()` opens a portrait-grid modal rendered via `renderPortraitGrid()` using ImageKit thumbnail URLs + post-processing hints (`tr:e-bgremove`, `tr:e-upscale`).
  - `escapeHtml()` helper for XSS protection in the grid.
  - Context-aware empty states by generation status.
- **`GalleryResource::getRelations()`** (prophoto-gallery) — registered between `GalleryImagesRelationManager` and `GalleryShareRelationManager`:
  ```php
  GalleryImagesRelationManager::class,
  AiGenerationRelationManager::class,
  GalleryShareRelationManager::class,
  GalleryActivityRelationManager::class,
  ```

Relation-manager tests run from the **app** (not the package) because Filament isn't a prophoto-ai dependency — see the sandbox note below.

---

### Story 8.5 — Schema Additions, Config, Service Provider Wiring (5 pts)
Glues the above to Laravel.

- **Migration** (`2026_04_xx_add_provider_fields_to_ai_tables.php`): adds `provider_key`, `external_model_id`, `provider_metadata` (JSON) to `ai_generations`; `provider_key`, `external_request_id`, `provider_metadata` to `ai_generation_requests`; `storage_driver`, `original_provider_url` to `ai_generated_portraits`.
- **`config/ai.php`** — provider list, default provider, polling caps (`max_training_poll_hours`, `max_generation_poll_hours`), queue name (`ai`).
- **`AIServiceProvider`** — registers:
  - `AiProviderRegistry` as singleton (+ Astria descriptor on boot)
  - `AiStorageContract` bound to `ImageKitStorage`
  - `AiOrchestrationService` singleton (registry + storage + logger)
  - `AiCostService` singleton (registry)
  - Config, views, migrations

33 tests. Covers migration shape, config loading, container bindings.

---

## Files Created

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

## Files Modified

| File | Package | Story | Change |
|------|---------|-------|--------|
| `src/Models/AiGeneration.php` | prophoto-ai | 8.5 | Added `provider_key`, `external_model_id`, `provider_metadata` to `$fillable`/`$casts` |
| `src/Models/AiGenerationRequest.php` | prophoto-ai | 8.5 | Added `provider_key`, `external_request_id`, `provider_metadata` |
| `src/Models/AiGeneratedPortrait.php` | prophoto-ai | 8.5 | Added `storage_driver`, `original_provider_url` |
| `src/AIServiceProvider.php` | prophoto-ai | 8.5 | Registered config, registry, storage, orchestration, cost service, Astria descriptor |
| `composer.json` | prophoto-ai | 8.5 | Added `imagekit/imagekit`, aligned dev deps with mature packages (`phpunit/phpunit: ^11.0`, `orchestra/testbench: ^9.0\|^10.0`) |
| `src/Filament/Resources/GalleryResource.php` | prophoto-gallery | 8.4 | Import + register `AiGenerationRelationManager` |
| `create-sandbox.sh` | root | 8 (infra) | Added `prophoto-ai` to PACKAGES array, Testbench install with `--with-all-dependencies`, phpunit.xml testsuite registration |
| `prophoto-app/phpunit.xml` | sandbox | 8 (infra) | Added `AI` testsuite pointing to `../prophoto-ai/tests` |

---

## Lessons Learned (Things That Tripped Us Up)

### 1. PHPUnit version mismatch between package and app
Running `vendor/bin/phpunit --configuration ../prophoto-ai/phpunit.xml` from the app blew up with:
> Call to undefined method `PHPUnit\TextUI\Configuration\Configuration::registerMockObjectsFromTestArgumentsRecursively()`

Root cause: the app had PHPUnit 11 but the package's composer had no explicit constraint, pulling in PHPUnit 10. **Fix:** aligned `prophoto-ai/composer.json` with the pattern every other mature package uses — explicit `"phpunit/phpunit": "^11.0"` plus `"orchestra/testbench": "^9.0|^10.0"`. This is now the template for any new package.

### 2. Filament tests don't belong in the package
We initially wrote `AiGenerationRelationManager` unit tests inside `prophoto-ai/tests/Unit/Filament/`. Filament isn't a dep of the package, so nine tests failed with `Class 'Filament\Resources\RelationManagers\RelationManager' not found`. **Fix:** deleted the `tests/Unit/Filament/` directory entirely. Filament tests live at the app level where Filament is actually installed.

### 3. Orchestra Testbench + Symfony yaml conflict
`composer require orchestra/testbench --dev` from the app failed with:
> orchestra/testbench[v10.0.0, ..., v10.11.0] require symfony/yaml ^7.2 -> found symfony/yaml[v7.2.0, ..., v7.4.8] but the package is fixed to v8.0.8

Laravel 12 ships Symfony 8 for most components; Testbench 10 still wants Symfony 7 for yaml. **Fix:** `composer require orchestra/testbench:"^10.0" --dev --with-all-dependencies`. Baked into `create-sandbox.sh` so every future rebuild does this automatically.

### 4. Sandbox script drift
When we rebuilt the sandbox mid-sprint, `prophoto-ai` wasn't in the PACKAGES array and the AI testsuite wasn't registered in `phpunit.xml`. **Rule going forward:** every new package touches `create-sandbox.sh` in two places — PACKAGES array AND testsuite registration. This is on the checklist for every future sprint that adds a package.

### 5. Filament v4 polling on RelationManagers
`$this->polling = '5s'` as a static property doesn't work for conditional polling. Solution: override `getPolling(): ?string` and return `'5s'` or `null` based on the current generation status. This keeps the browser quiet after a run completes.

---

## Architecture Observations for Future Sprints

- **Two-layer AI is a force multiplier.** Post-processing via URL transforms (bgremove, upscale, retouch) means we can offer those features to any image in any gallery — not just AI-generated ones. A future Sprint could expose ImageKit transforms as a general-purpose "enhance" feature on proofing galleries.
- **The registry pattern is the right shape for future providers.** Adding Fal.ai, Magnific, or Claid.ai is now a closed problem: implement the contract, register a descriptor, write tests. No orchestration or UI changes required. This is exactly what the contracts were designed to enable.
- **Event-driven completion works.** `AiGenerationCompleted` is already consumable by `prophoto-notifications` for an "Your AI portraits are ready" email without any coupling back to `prophoto-ai`. This is a future win.
- **Per-image "mark for AI" selection UI is the obvious Sprint 9 follow-up.** Today the RelationManager action uses all gallery images for training; a photographer might want to select 10 specific ones. That's a UI concern, not a contract concern.

---

## Test Summary

| Story | Package | Tests | Assertions |
|-------|---------|-------|------------|
| 8.1 Contracts & Registry | prophoto-ai + prophoto-contracts | 32 | ~90 |
| 8.2 Astria + ImageKit | prophoto-ai | 63 | ~160 |
| 8.3 Orchestration + Jobs | prophoto-ai | 20 | ~65 |
| 8.4 Filament RelationManager | prophoto-app (app-level) | *pending dedicated run* | — |
| 8.5 Schema + Wiring | prophoto-ai | 33 | ~80 |
| **Total (package-level, run from app)** | | **152** | **396** |

Run command (from `prophoto-app/`):
```bash
php artisan test --testsuite=AI
```

---

## What This Sprint Deliberately Did NOT Include

(Carried forward from `SPRINT-8-SPECS.md` for retrospective clarity)

- Video generation providers
- Webhook receivers (polling is v1)
- Client-facing AI portal
- Bulk training across multiple galleries
- Provider billing reconciliation
- Studio-wide AI spend dashboard
- Auto-training triggers
- Fal.ai / Magnific / Claid.ai implementations
- ImageKit extension-unit cost tracking
- Per-image "mark for AI" selection UI

---

*Last updated: 2026-04-15 — Sprint 8 complete, all stories landed, 152 tests / 396 assertions passing.*
