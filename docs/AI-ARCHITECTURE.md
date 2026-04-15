# AI Image Services Pipeline Architecture

> **Status**: Proposed
> **Owner package**: `prophoto-intelligence`
> **Related packages**: `prophoto-assets`, `prophoto-contracts`, `prophoto-gallery`
> **Last updated**: 2026-04-15

---

## Purpose

This document defines how ProPhoto integrates external AI image-generation and image-editing services without coupling the system to any single vendor.

The goal is to support a **multi-provider pipeline** where:

- Canonical asset storage remains under ProPhoto ownership.
- Generation providers are treated as compute engines, not source-of-truth stores.
- Downstream delivery and deterministic transforms are separated from creative generation.
- Provider-specific APIs are normalized behind contracts and DTOs.
- Package ownership, event flow, and persistence boundaries remain consistent with ProPhoto rules.

This is **not** a blank-slate AI feature. It must obey the existing spine:

```text
prophoto-ingest (decides)
  → emits SessionAssociationResolved

prophoto-assets (attaches canonical truth)
  → consumes SessionAssociationResolved
  → emits AssetSessionContextAttached
  → emits AssetReadyV1

prophoto-intelligence (derives outputs)
  → consumes AssetSessionContextAttached
  → consumes AssetReadyV1
```

Meaning:
- Ingest decides.
- Assets attach truth.
- Intelligence derives.

This image-services layer lives inside the derived outputs part of that flow.

---

## Problem Statement

Different external services are good at different jobs:
- **Astria** is strong for identity-sensitive fine-tuning and prompt-driven generation. Its API centers on tune and prompt resources.
- **ImageKit** is strong for delivery, CDN-backed URL transforms, and downstream deterministic image operations. It also supports DigitalOcean Spaces as external storage.
- **Other providers** such as Fal, Magnific, or Claid may be better suited for specific real-time generation, upscale, or commercial background workflows.

If ProPhoto binds business logic directly to one provider, the result will be brittle, expensive to change, and architecturally wrong.

---

## Architecture Decision

ProPhoto will implement a provider-agnostic image services orchestration layer in `prophoto-intelligence`.

This layer will:
1. Accept internal generation/editing requests as ProPhoto DTOs.
2. Route work to a provider adapter based on job type and policy.
3. Persist resulting files into ProPhoto-owned storage.
4. Register resulting derived artifacts through ProPhoto-owned write paths.
5. Emit ProPhoto-owned events describing completed work.
6. Expose final assets for delivery through downstream delivery tooling such as ImageKit.

### Core Principle

External providers are compute, not truth.

ProPhoto must never treat an external provider URL, provider-hosted asset, or provider-side state as the canonical result.

**Always:**
```text
provider output
  → fetch or receive result
  → persist into ProPhoto-owned storage
  → attach/register through ProPhoto-owned package boundary
  → serve downstream through delivery layer
```

**Never:**
```text
provider output URL
  → directly treated as canonical production asset
```

---

## High-Level Pipeline

```text
Upload / existing asset in ProPhoto
    ↓
prophoto-assets owns canonical originals + metadata
    ↓
prophoto-intelligence decides derived job
    ↓
provider adapter executes against Astria / Fal / Magnific / Claid / other
    ↓
result is normalized into ProPhoto DTO
    ↓
result file is persisted into ProPhoto-owned storage
    ↓
derived artifact is attached through owning package boundary
    ↓
delivery layer serves final asset via ImageKit or equivalent
```

---

## What Each Layer Owns

### 1. `prophoto-assets`

**Owns:**
- Canonical asset identity.
- Storage of originals and ProPhoto-owned derivatives.
- Normalized metadata and raw metadata truth.
- Session/asset context projection.
- Asset readiness events.

**Does not own:**
- Provider routing.
- Prompt composition.
- External provider request orchestration.

### 2. `prophoto-intelligence`

**Owns:**
- Orchestration of derived image jobs.
- Provider routing.
- Provider adapters.
- Normalized request/response DTOs.
- Run records, statuses, costs, and provenance.
- Intelligence events.
- Validation that outputs match declared job type.

**Does not own:**
- Direct mutation of booking data.
- Direct mutation of assets tables owned elsewhere.
- Direct web UI.
- Direct delivery/CDN concerns.

### 3. Delivery Layer (e.g., ImageKit)

**Owns:**
- CDN-backed delivery.
- URL-based downstream transforms.
- Format conversion.
- Resize/crop variants.
- Cheap deterministic post-processing.
- Edge delivery.

**Does not own:**
- Canonical truth.
- Intelligence orchestration.
- Business workflow state.

---

## Provider Roles

### Astria
- **Use for:**
    - Identity-sensitive fine-tuning.
    - Tune lifecycle management.
    - Prompt-driven generation.
    - Generation workflows where subject consistency matters.

Astria’s documented API is centered on `POST /tunes`, `POST /tunes/:id/prompts`, and prompt/tune retrieval resources.

### ImageKit
- **Use for:**
    - Delivery of ProPhoto-owned final assets.
    - URL-based resizing/cropping/format conversion.
    - Cheap deterministic downstream transforms.
    - Spaces-backed external storage integration.

ImageKit documents DigitalOcean Spaces integration and URL-based AI transformations. Its pricing docs also distinguish extension-unit consumption for transforms such as background removal, retouch, and upscale.

### Fal / Magnific / Claid / Future Providers
- Use only through provider adapters.
- No provider-specific assumptions may leak above the adapter boundary.

---

## Architectural Separation: Generation vs Delivery

This separation is mandatory.

### Generation/Editing Providers
- **Examples:** Astria, Fal, Magnific, Claid, future specialty providers.
- **Used when ProPhoto needs:**
    - New image synthesis.
    - Identity-aware generation.
    - Commercial background generation.
    - Enhancement that depends on model behavior.
    - Provider-side creative transforms.

### Delivery/Transform Providers
- **Examples:** ImageKit.
- **Used when ProPhoto needs:**
    - Fast delivery.
    - Signed URLs.
    - Format conversion.
    - Deterministic resizing/cropping.
    - Repeatable low-cost post-processing on already-owned images.

### Rule
A provider may be both capable of generation and hosting outputs, but ProPhoto must still split those concerns internally.

---

## Ownership and Boundaries

### Package Owner
**Owner package:** `prophoto-intelligence`

This is the only correct owner because the problem is a derived-output orchestration problem, not a storage problem and not a gallery-view problem.

### Input Boundary
Inputs may come from:
- `AssetReadyV1`
- `AssetSessionContextAttached`
- Explicit admin-triggered derived-image job requests.
- Internal orchestration/planner decisions.

Inputs must be normalized into ProPhoto DTOs before any provider adapter executes.

Inputs may include:
- `AssetId`
- `SessionContextSnapshot`
- Generation/edit request DTO.
- Provider routing hint.
- Policy/config snapshot.
- Output family requirements.

**Hard rule:** `prophoto-intelligence` must not query booking directly. It must use `SessionContextSnapshot`, consistent with the existing system rules.

### Persistence Boundary
**`prophoto-intelligence` may own:**
- Intelligence run tables.
- Provider execution records.
- Cost ledger tables.
- Request/response audit tables.
- Provider artifact provenance tables.

**`prophoto-assets` owns:**
- Canonical assets.
- Derivatives owned by the asset spine.
- Metadata truth.
- Storage registration.

**Rule:** No cross-package writes. If an intelligence run results in a new ProPhoto-owned artifact, the write path must go through the owning package boundary, not by directly inserting rows into another package’s tables.

### Event Boundary
**`prophoto-intelligence` may consume:**
- `AssetReadyV1`
- `AssetSessionContextAttached`
- Other existing intelligence trigger events.

**`prophoto-intelligence` may emit versioned immutable events such as:**
- `DerivedImageJobPlanned`
- `DerivedImageJobStarted`
- `DerivedImageJobCompleted`
- `DerivedImageJobFailed`
- `DerivedImageArtifactRegistered`
- `DerivedImageCostRecorded`

**Events must:**
- Be immutable.
- Be versioned where appropriate.
- Carry IDs and DTOs, not Eloquent models.
- Live in `prophoto-contracts`.

---

## Proposed Internal Components

These are logical components, not necessarily one class each.

### 1. Provider Registry
Resolves provider adapters by capability and identity.
- **Responsibilities:**
    - Discover registered adapters.
    - Expose stable provider identity.
    - Answer capability checks.
    - Keep orchestration code provider-agnostic.

### 2. Routing Policy
Chooses the right provider for a job type.
- **Possible routing dimensions:**
    - Identity-sensitive generation.
    - Real-time preview generation.
    - Upscale-only enhancement.
    - Commercial background generation.
    - Downstream delivery transform only.

### 3. Provider Adapter
Wraps one external provider.
- **Responsibilities:**
    - Build provider request from internal DTO.
    - Authenticate against provider API.
    - Submit job.
    - Poll or receive callback.
    - Normalize result into internal DTO.
    - Map provider errors into internal error family.

### 4. Artifact Registrar
Handles the transition from provider output to ProPhoto-owned artifact.
- **Responsibilities:**
    - Fetch result payload or file.
    - Validate expected output family.
    - Persist into ProPhoto-owned storage.
    - Attach/register through owning package boundary.
    - Emit registration-complete event.

### 5. Cost/Provenance Recorder
Tracks:
- Provider name, model, and version.
- Request type and run ID.
- Cost estimate and final cost.
- Source asset IDs and resulting artifact IDs.
- Timestamps and failure metadata.

---

## Provider Capability Model

Provider adapters should advertise capabilities, but the first implementation should stay narrow and practical.

**Examples:**
- `identity_fine_tune`
- `identity_generation`
- `real_time_generation`
- `commercial_background_generation`
- `creative_upscale`
- `delivery_transform`

These capability declarations belong in the intelligence layer, not in UI code and not in `prophoto-assets`.

---

## Recommended First-Cut Routing Policy

This is a practical default, not a permanent law.

| Job type | Preferred provider role |
| :--- | :--- |
| subject-consistent portrait generation | Astria-style fine-tune/generation provider |
| instant preview generation | low-latency generation provider |
| premium final upscale | specialty upscale provider |
| product/commercial background generation | specialty commercial-background provider |
| final delivery resize/format/crop | ImageKit |

This table is policy, not contract. Providers may be swapped without changing higher-level application code.

---

## Contract Direction

New or extended contracts should live in `prophoto-contracts`.

**Likely contract families:**
- Provider registry contract.
- Generation provider contract.
- Upscale provider contract.
- Background-generation provider contract.
- Normalized request/result DTOs.
- Provenance/cost DTOs.
- Versioned derived-image events.

**Rule:** Check contracts before inventing. If an existing DTO or event already fits, reuse it.

---

## Operational Flow Examples

### Example A — Identity-sensitive portrait generation
`AssetReadyV1`
  → intelligence planner decides eligible job
  → provider routing selects Astria adapter
  → Astria tune/prompt flow executes
  → result normalized to internal DTO
  → artifact persisted into ProPhoto-owned storage
  → artifact attached through owning package boundary
  → `DerivedImageJobCompleted` emitted

Astria documents tune creation and prompt creation as its core API workflow.

### Example B — Final upscale for approved image
Approved asset/image selected
  → intelligence job requested
  → routing selects upscale-capable adapter
  → provider returns enhanced output
  → result persisted into ProPhoto-owned storage
  → final asset delivered downstream via ImageKit

### Example C — Product background generation
Existing product asset
  → background-generation job requested
  → routing selects commercial-background adapter
  → provider output normalized + stored
  → downstream delivery handled separately

---

## ImageKit Position in ProPhoto

ImageKit should be treated as a delivery and downstream transform service, not the central intelligence engine.

**Use ImageKit for:**
- Serving ProPhoto-owned outputs.
- Deterministic URL-based transforms.
- Spaces integration.
- CDN optimization.
- Format conversion.
- Low-cost repeatable post-processing.

ImageKit explicitly supports DigitalOcean Spaces integration, which matches the existing DigitalOcean stack direction.

ImageKit also documents URL-based AI transform pricing via extension units, including `e-bgremove`, `e-retouch`, and `e-upscale`.

**Important limitation:** ImageKit docs do not provide a precise technical definition of what its retouch transform does beyond improving image quality, so ProPhoto should not build product promises around exact retouch semantics until empirically validated.

---

## External Provider Trust Model

### Trusted for Compute
Providers may be trusted to:
- Perform generation or enhancement.
- Return result metadata.
- Expose job status.

### Not Trusted for Canonical Truth
Providers must not be treated as:
- The canonical source of resulting assets.
- The long-term authoritative record of provenance.
- The only place where the final file exists.

---

## Security and Compliance Considerations
- Provider API keys must be stored in environment/config, never inline.
- Provider callbacks must be signed or otherwise validated where supported.
- Provider URLs must be treated as transient.
- Stored provenance must record provider name and model/version for auditability.
- Sensitive identity or customer imagery must be persisted only into ProPhoto-owned storage after generation.
- Deletion/retention policy must remain under ProPhoto control, not provider defaults.

Astria documents model lifecycle details such as expiration windows for stored models, which is another reason not to treat provider-side storage as canonical.

---

## Testing Requirements

Before implementation, the slice must define tests for:
1. Provider routing chooses expected adapter for a job type.
2. Provider adapter normalizes responses into internal DTOs.
3. Artifact registration never bypasses owning package boundaries.
4. Intelligence never directly writes asset-owned tables.
5. Failure paths capture provider error + mark run correctly.
6. Provider-hosted URLs are not treated as final canonical asset truth.
7. Cost/provenance records are written for successful and failed runs as designed.

---

## Explicitly Out of Scope

This document does not define:
- UI for prompt editing.
- Customer-facing prompt builders.
- Live preview UX.
- Provider-specific prompt taxonomies.
- Pricing/billing product rules for customer charge-through.
- Automatic provider benchmarking.
- Capability negotiation beyond first-cut routing.
- Provider-specific storage as canonical truth.
- Cross-package direct writes.
- Direct booking queries from intelligence.

---

## Anti-Patterns

The following are explicitly forbidden:

### 1. Direct provider lock-in in application code
**Bad:** Controller or service directly calling Astria SDK/client outside adapter boundary.

### 2. Provider URLs treated as final assets
**Bad:** Storing only provider result URL and serving it as production truth.

### 3. Cross-package table writes
**Bad:** Intelligence inserting rows directly into asset-owned tables.

### 4. Delivery concerns embedded into orchestration
**Bad:** Orchestration code building ImageKit delivery URLs as core business logic.

### 5. Booking queries from intelligence
**Bad:** Intelligence loading booking/session models directly instead of using `SessionContextSnapshot`.

---

## Recommended Next Documents

This doc should be followed by:
1. `ARCHITECTURE-AI-PROVIDER-CONTRACTS.md`
2. `ARCHITECTURE-AI-RUN-AND-PROVENANCE-DATA-MODEL.md`
3. `ARCHITECTURE-AI-PROVIDER-ROUTING-POLICY.md`
4. `ARCHITECTURE-AI-IMAGEKIT-DELIVERY-INTEGRATION.md`
5. `ARCHITECTURE-AI-ASTRIA-ADAPTER.md`

---

## Bottom Line

ProPhoto should not build around one AI vendor.

It should build:
- One canonical storage and metadata spine.
- One provider-agnostic intelligence orchestration layer.
- Many swappable provider adapters.
- One downstream delivery layer for finalized ProPhoto-owned outputs.

That keeps the system aligned with ProPhoto’s rules:
- Owning packages mutate their own data.
- Events stay immutable and versioned.
- Assets remain canonical truth.
- Intelligence derives rather than owns media truth.
- Delivery remains separate from creative generation.