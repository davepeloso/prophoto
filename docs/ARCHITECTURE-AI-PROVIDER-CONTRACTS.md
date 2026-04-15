# Architecture — AI Provider Contracts

> **Scope:** The shared seams that every AI generation provider and every delivery backend must conform to. Lives in `prophoto-contracts` so any package can depend on the shapes without depending on a concrete provider.

**Status:** Landed in Sprint 8 (2026-04-15). All contracts, DTOs, and enums are in `prophoto-contracts/src/{Contracts,DTOs,Enums}/AI/`. First implementations (Astria provider + ImageKit storage) ship in `prophoto-ai`.

---

## Why this exists

ProPhoto's AI surface has two separable concerns that get conflated in most SaaS products:

1. **Generation** — creative compute. Expensive, model-driven, subject to provider drift and model-expiry windows. Examples: Astria (identity/fine-tune), Fal.ai (realtime), Magnific (upscale), Claid.ai (commercial).
2. **Delivery** — deterministic transforms + CDN. Cheap, URL-driven, repeatable. Today: ImageKit on top of DigitalOcean Spaces.

Keeping these separate means a new generation provider can land without touching delivery, and post-processing (bgremove, upscale, retouch) runs at URL-read time on already-stored assets — no regeneration required.

**Core invariant:** providers are compute, not truth. A provider URL is always transient. Every final asset is persisted into ProPhoto-owned storage and served via the delivery layer.

```
provider output → fetch → persist to storage (via AiStorageContract) → serve via URL transforms
```

---

## Package layout

```
prophoto-contracts/
  src/Contracts/AI/
    AiProviderContract.php           ← generation seam
    AiStorageContract.php            ← delivery seam
  src/DTOs/AI/
    AiProviderCapabilities.php       ← what a provider can/can't do
    AiProviderDescriptor.php         ← registry registration record
    TrainingRequest.php / TrainingResponse.php / TrainingStatusResponse.php
    GenerationRequest.php / GenerationResponse.php / GenerationStatusResponse.php
    StorageResult.php                ← return value from AiStorageContract::upload()
    Money.php                        ← integer cents + currency, never float
  src/Enums/AI/
    ProviderRole.php                 ← identity | realtime | upscale | commercial
    TrainingStatus.php               ← queued | training | ready | failed | expired
    GenerationStatus.php             ← queued | processing | completed | failed

prophoto-ai/
  src/Registry/AiProviderRegistry.php
  src/Providers/Astria/...           ← first implementer of AiProviderContract
  src/Storage/ImageKitStorage.php    ← first implementer of AiStorageContract
```

`prophoto-contracts` stays headless — zero domain dependencies, zero concrete clients. It ships shapes. Every concrete implementation lives downstream.

---

## `AiProviderContract` — the generation seam

Every generation provider implements this exact surface. No exceptions, no "this provider also has a special extra method" leakage. If a capability isn't on the contract, callers don't get it.

```php
namespace ProPhoto\Contracts\Contracts\AI;

interface AiProviderContract
{
    public function providerKey(): string;                 // e.g. 'astria'
    public function displayName(): string;                 // for UI
    public function providerRole(): ProviderRole;          // enum
    public function capabilities(): AiProviderCapabilities;// declarative feature flags
    public function validateConfiguration(): bool;         // health-check

    public function submitTraining(TrainingRequest $r): TrainingResponse;
    public function getTrainingStatus(string $externalModelId): TrainingStatusResponse;

    public function submitGeneration(GenerationRequest $r): GenerationResponse;
    public function getGenerationStatus(string $externalRequestId): GenerationStatusResponse;

    public function estimateTrainingCost(int $imageCount): Money;
    public function estimateGenerationCost(int $numImages): Money;
}
```

### Design rules embedded in the contract

- **Providers that don't train** (e.g., a future realtime-only provider) still implement `submitTraining()` — they return a `TrainingResponse` with `externalModelId = 'none'` and `cost = Money::zero()`. This keeps orchestration uniform; the registry does not need `if ($supportsTraining)` branches.
- **Status is polled, not pushed.** Webhooks are deferred; v1 is poll-driven. The contract forces every provider to expose a `getXStatus(string $externalId)` that returns a normalized DTO — provider-specific state strings are mapped to our enums inside the provider, never leaked upward.
- **Cost is always `Money` (integer cents).** No floats, no doubles. Estimate before commit, record actual on completion. This matches how `AiGeneration.fine_tune_cost` is stored (`decimal:2` in DB, cents in code).
- **Capabilities are declarative, not call-to-discover.** `AiProviderCapabilities` returns a frozen struct: `supportsTraining`, `minTrainingImages`, `maxTrainingImages`, `supportedAspectRatios`, `maxOutputsPerRequest`, etc. The orchestration service validates requests against capabilities *before* spending money on the API call.

---

## `AiStorageContract` — the delivery seam

The storage contract is tiny on purpose. The delivery layer is stateless URL generation on top of a blob store.

```php
namespace ProPhoto\Contracts\Contracts\AI;

interface AiStorageContract
{
    public function upload(string $sourceUrl, string $fileName, string $folder, array $tags = []): StorageResult;
    public function generateUrl(string $fileId, array $transforms = []): string;
    public function generateSignedUrl(string $fileId, array $transforms = [], int $expireSeconds = 3600): string;
    public function delete(string $fileId): bool;
    public function validateConfiguration(): bool;
}
```

### Design rules embedded in the contract

- **`upload()` takes a source URL, not bytes.** The delivery implementation is responsible for fetching from the transient provider URL and persisting. This is the exact seam that enforces the "provider URL is never canonical" rule — the orchestration layer cannot "just save the Astria URL" because the only way to turn it into a `StorageResult` is to go through upload.
- **Transforms are provider-specific, not standardized.** We do not try to invent a cross-provider transform vocabulary. ImageKit gets ImageKit params (`['e-bgremove' => '']`); a hypothetical Cloudflare Images backend would get Cloudflare params. The contract specifies the shape (`array`), not the vocabulary.
- **Signed URLs are first-class.** Client-facing galleries, time-limited downloads, and moderation preview links all require expiring URLs. Making this a contract method (not an optional SDK feature) forces every backend to address it.

---

## DTOs

All DTOs are readonly PHP 8 classes. Constructor-promoted public properties, no setters.

| DTO | Purpose |
|-----|---------|
| `TrainingRequest` | Training images, subject metadata, tuning params, idempotency key |
| `TrainingResponse` | `externalModelId`, initial status, estimated cost, created-at |
| `TrainingStatusResponse` | Current `TrainingStatus`, progress %, model-expires-at, raw provider payload |
| `GenerationRequest` | `externalModelId`, prompt, negative prompt, num outputs, aspect ratio, seed |
| `GenerationResponse` | `externalRequestId`, initial status, estimated cost |
| `GenerationStatusResponse` | Current `GenerationStatus`, output URLs (transient), raw provider payload |
| `StorageResult` | `fileId`, persistent URL, thumbnail URL, size, content-type |
| `Money` | `amountCents: int`, `currency: string`. `::zero()`, `::fromCents()`, `->add()` |
| `AiProviderCapabilities` | Feature flags + numeric bounds (min/max images, max outputs, supported ratios) |
| `AiProviderDescriptor` | `providerKey`, `displayName`, `role`, `capabilities`, + lazy resolver closure |

### Why `AiProviderDescriptor` exists separately from the provider

The registry stores descriptors, not providers. A descriptor is cheap — it's metadata plus a closure that builds the real provider when asked. This means:

- Booting the app doesn't instantiate N HTTP clients and N API keys.
- `$registry->list()` is free (returns descriptors only).
- `$registry->resolve('astria')` is the only call that materializes a provider.
- Unknown providers (bad config, missing module) fail closed at resolution time with a clear error, not at boot.

---

## Enums

- **`ProviderRole`** — `identity`, `realtime`, `upscale`, `commercial`. The role tells the UI which provider to surface by default for a given intent; it does not restrict capabilities.
- **`TrainingStatus`** — `queued`, `training`, `ready`, `failed`, `expired`. Each provider maps its own status strings to these five.
- **`GenerationStatus`** — `queued`, `processing`, `completed`, `failed`. Same pattern.

Provider-specific statuses (Astria's timestamp-based model, Fal.ai's event model, Magnific's tier progression) never leak into orchestration or the DB. They're mapped at the provider boundary.

---

## Cross-cutting rules

1. **Contracts own shapes, not behavior.** No default methods, no abstract base classes in the contract namespace. Concrete behavior lives in `prophoto-ai` or whoever implements.
2. **No provider-specific imports in consumer code.** `AiOrchestrationService`, Filament UI, jobs, and events all depend on `AiProviderContract` and DTOs — never on `AstriaProvider` directly.
3. **Money is always cents + currency.** Floats are forbidden for money anywhere in the AI surface.
4. **Idempotency is the caller's responsibility.** `TrainingRequest` and `GenerationRequest` carry an idempotency key; the provider echoes it back. Orchestration uses this to detect and short-circuit duplicate submissions after retries.
5. **`validateConfiguration()` is cheap and non-destructive.** It's allowed to make a single API call (e.g., `GET /account`), but must not submit jobs, spend money, or mutate provider-side state.
6. **Events, not return values, signal completion.** Polling jobs update the database and dispatch `AiModelTrained` / `AiGenerationCompleted`. Consumers never poll the AI tables.
7. **Storage is written through, read direct.** Writes always go through `AiStorageContract::upload()`. Reads (URL generation) are cheap enough to call on every render — the delivery layer is just string-building with signing.

---

## Adding a new provider (the happy path)

1. Create `prophoto-ai/src/Providers/<Vendor>/<Vendor>Provider.php` implementing `AiProviderContract`.
2. Create a thin API client next to it (`<Vendor>ApiClient.php`) — everything HTTP lives in the client, never in the provider class. The provider orchestrates and maps DTOs.
3. Add a config file entry under `config/ai.php` with API key, endpoint, and role.
4. Register a descriptor in `AIServiceProvider::boot()`:
   ```php
   $registry->register(new AiProviderDescriptor(
       providerKey: 'vendor',
       displayName: 'Vendor',
       role: ProviderRole::Identity,
       capabilities: $vendorCapabilities,
       resolver: fn () => $this->app->make(VendorProvider::class),
   ));
   ```
5. Write unit tests against the contract: capability declaration, status mapping, cost estimation, idempotency echo, `validateConfiguration()` behavior.

No changes to orchestration, jobs, events, Filament UI, or the registry are required. That's the whole point of the contracts.

---

## Adding a new storage backend

Rare, but supported. Implement `AiStorageContract`, bind it in the service provider, swap the singleton. The orchestration layer resolves `AiStorageContract` from the container — it has no knowledge of ImageKit specifically.

Gotcha: transforms are backend-specific. If you swap backends, anywhere that passes `['e-bgremove' => '']` needs to translate. Keep transform maps centralized.

---

## What this contracts surface deliberately does NOT include

- **Webhook receivers.** v1 is poll-only. Adding webhooks means a new route + signature verification + event dispatch, not new contract methods.
- **Provider billing sync.** We track local cost per job. Reconciliation with provider billing APIs is a future concern.
- **Cross-provider transform vocabulary.** No attempt to normalize "bgremove" across backends. Backends speak their own transform language.
- **Bulk operations.** One training = one model, one generation = one request. Batching is orchestration-level, not contract-level.
- **Model management UI.** Listing/deleting/extending trained models is a provider-admin concern. The contract surfaces enough to support it (`getTrainingStatus()` returns expiry), but the management UI is not in Sprint 8.

---

*Last updated: 2026-04-15 — Sprint 8 complete. See `SPRINT-8-SPECS.md` for story-level detail and `AI-ARCHITECTURE.md` for the broader narrative.*
