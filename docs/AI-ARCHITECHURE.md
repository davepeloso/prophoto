# ARCHITECTURE-AI-IMAGE-SERVICES-PIPELINE.md

> **Status**: Proposed
> **Owner package**: `prophoto-intelligence`
> **Related packages**: `prophoto-assets`, `prophoto-contracts`, `prophoto-gallery`
> **Last updated**: 2026-04-15

---

## Purpose

This document defines how ProPhoto integrates external AI image-generation and image-editing services without coupling the system to any single vendor.

The goal is to support a **multi-provider pipeline** where:

- canonical asset storage remains under ProPhoto ownership
- generation providers are treated as compute engines, not source-of-truth stores
- downstream delivery and deterministic transforms are separated from creative generation
- provider-specific APIs are normalized behind contracts and DTOs
- package ownership, event flow, and persistence boundaries remain consistent with ProPhoto rules

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

Meaning:
	•	ingest decides
	•	assets attach truth
	•	intelligence derives

This image-services layer lives inside the derived outputs part of that flow.

⸻

Problem Statement

Different external services are good at different jobs:
	•	Astria is strong for identity-sensitive fine-tuning and prompt-driven generation. Its API centers on tune and prompt resources.
	•	ImageKit is strong for delivery, CDN-backed URL transforms, and downstream deterministic image operations. It also supports DigitalOcean Spaces as external storage.
	•	Other providers such as Fal, Magnific, or Claid may be better suited for specific real-time generation, upscale, or commercial background workflows.

If ProPhoto binds business logic directly to one provider, the result will be brittle, expensive to change, and architecturally wrong.

⸻

Architecture Decision

ProPhoto will implement a provider-agnostic image services orchestration layer in prophoto-intelligence.

This layer will:
	1.	accept internal generation/editing requests as ProPhoto DTOs
	2.	route work to a provider adapter based on job type and policy
	3.	persist resulting files into ProPhoto-owned storage
	4.	register resulting derived artifacts through ProPhoto-owned write paths
	5.	emit ProPhoto-owned events describing completed work
	6.	expose final assets for delivery through downstream delivery tooling such as ImageKit

Core principle

External providers are compute, not truth.

ProPhoto must never treat an external provider URL, provider-hosted asset, or provider-side state as the canonical result.

Always:

provider output
  → fetch or receive result
  → persist into ProPhoto-owned storage
  → attach/register through ProPhoto-owned package boundary
  → serve downstream through delivery layer

Never:

provider output URL
  → directly treated as canonical production asset


⸻

High-Level Pipeline

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


⸻

What Each Layer Owns

1. prophoto-assets

Owns:
	•	canonical asset identity
	•	storage of originals and ProPhoto-owned derivatives
	•	normalized metadata and raw metadata truth
	•	session/asset context projection
	•	asset readiness events

Does not own:
	•	provider routing
	•	prompt composition
	•	external provider request orchestration

2. prophoto-intelligence

Owns:
	•	orchestration of derived image jobs
	•	provider routing
	•	provider adapters
	•	normalized request/response DTOs
	•	run records, statuses, costs, and provenance
	•	intelligence events
	•	validation that outputs match declared job type

Does not own:
	•	direct mutation of booking data
	•	direct mutation of assets tables owned elsewhere
	•	direct web UI
	•	direct delivery/CDN concerns

3. Delivery layer such as ImageKit

Owns:
	•	CDN-backed delivery
	•	URL-based downstream transforms
	•	format conversion
	•	resize/crop variants
	•	cheap deterministic post-processing
	•	edge delivery

Does not own:
	•	canonical truth
	•	intelligence orchestration
	•	business workflow state

⸻

Provider Roles

Astria

Use for:
	•	identity-sensitive fine-tuning
	•	tune lifecycle management
	•	prompt-driven generation
	•	generation workflows where subject consistency matters

Astria’s documented API is centered on POST /tunes, POST /tunes/:id/prompts, and prompt/tune retrieval resources.

ImageKit

Use for:
	•	delivery of ProPhoto-owned final assets
	•	URL-based resizing/cropping/format conversion
	•	cheap deterministic downstream transforms
	•	Spaces-backed external storage integration

ImageKit documents DigitalOcean Spaces integration and URL-based AI transformations. Its pricing docs also distinguish extension-unit consumption for transforms such as background removal, retouch, and upscale.

Fal / Magnific / Claid / future providers

Use only through provider adapters.

No provider-specific assumptions may leak above the adapter boundary.

⸻

Architectural Separation: Generation vs Delivery

This separation is mandatory.

Generation/editing providers

Examples:
	•	Astria
	•	Fal
	•	Magnific
	•	Claid
	•	future specialty providers

These are used when ProPhoto needs:
	•	new image synthesis
	•	identity-aware generation
	•	commercial background generation
	•	enhancement that depends on model behavior
	•	provider-side creative transforms

Delivery/transform providers

Examples:
	•	ImageKit

These are used when ProPhoto needs:
	•	fast delivery
	•	signed URLs
	•	format conversion
	•	deterministic resizing/cropping
	•	repeatable low-cost post-processing on already-owned images

Rule

A provider may be both capable of generation and hosting outputs, but ProPhoto must still split those concerns internally.

⸻

Ownership and Boundaries

Package owner

Owner package: prophoto-intelligence

This is the only correct owner because the problem is a derived-output orchestration problem, not a storage problem and not a gallery-view problem.

⸻

Input boundary

Inputs may come from:
	•	AssetReadyV1
	•	AssetSessionContextAttached
	•	explicit admin-triggered derived-image job requests
	•	internal orchestration/planner decisions

Inputs must be normalized into ProPhoto DTOs before any provider adapter executes.

Inputs may include:
	•	AssetId
	•	SessionContextSnapshot
	•	generation/edit request DTO
	•	provider routing hint
	•	policy/config snapshot
	•	output family requirements

Hard rule

prophoto-intelligence must not query booking directly. It must use SessionContextSnapshot, consistent with the existing system rules.

⸻

Persistence boundary

prophoto-intelligence may own:
	•	intelligence run tables
	•	provider execution records
	•	cost ledger tables
	•	request/response audit tables
	•	provider artifact provenance tables

prophoto-assets owns:
	•	canonical assets
	•	derivatives owned by the asset spine
	•	metadata truth
	•	storage registration

Rule

No cross-package writes.

If an intelligence run results in a new ProPhoto-owned artifact, the write path must go through the owning package boundary, not by directly inserting rows into another package’s tables.

⸻

Event boundary

prophoto-intelligence may consume:
	•	AssetReadyV1
	•	AssetSessionContextAttached
	•	other existing intelligence trigger events

prophoto-intelligence may emit versioned immutable events such as:
	•	DerivedImageJobPlanned
	•	DerivedImageJobStarted
	•	DerivedImageJobCompleted
	•	DerivedImageJobFailed
	•	DerivedImageArtifactRegistered
	•	DerivedImageCostRecorded

Events must:
	•	be immutable
	•	be versioned where appropriate
	•	carry IDs and DTOs, not Eloquent models
	•	live in prophoto-contracts

⸻

Proposed Internal Components

These are logical components, not necessarily one class each.

1. Provider registry

Resolves provider adapters by capability and identity.

Responsibilities:
	•	discover registered adapters
	•	expose stable provider identity
	•	answer capability checks
	•	keep orchestration code provider-agnostic

2. Routing policy

Chooses the right provider for a job type.

Possible routing dimensions:
	•	identity-sensitive generation
	•	real-time preview generation
	•	upscale-only enhancement
	•	commercial background generation
	•	downstream delivery transform only

3. Provider adapter

Wraps one external provider.

Responsibilities:
	•	build provider request from internal DTO
	•	authenticate against provider API
	•	submit job
	•	poll or receive callback
	•	normalize result into internal DTO
	•	map provider errors into internal error family

4. Artifact registrar

Handles the transition from provider output to ProPhoto-owned artifact.

Responsibilities:
	•	fetch result payload or file
	•	validate expected output family
	•	persist into ProPhoto-owned storage
	•	attach/register through owning package boundary
	•	emit registration-complete event

5. Cost/provenance recorder

Tracks:
	•	provider name
	•	provider model/version
	•	request type
	•	run ID
	•	cost estimate and final cost
	•	source asset IDs
	•	resulting artifact IDs
	•	timestamps and failure metadata

⸻

Provider Capability Model

Provider adapters should advertise capabilities, but the first implementation should stay narrow and practical.

Examples:
	•	identity_fine_tune
	•	identity_generation
	•	real_time_generation
	•	commercial_background_generation
	•	creative_upscale
	•	delivery_transform

These capability declarations belong in the intelligence layer, not in UI code and not in prophoto-assets.

⸻

Recommended First-Cut Routing Policy

This is a practical default, not a permanent law.

Job type	Preferred provider role
subject-consistent portrait generation	Astria-style fine-tune/generation provider
instant preview generation	low-latency generation provider
premium final upscale	specialty upscale provider
product/commercial background generation	specialty commercial-background provider
final delivery resize/format/crop	ImageKit

This table is policy, not contract. Providers may be swapped without changing higher-level application code.

⸻

Contract Direction

New or extended contracts should live in prophoto-contracts.

Likely contract families:
	•	provider registry contract
	•	generation provider contract
	•	upscale provider contract
	•	background-generation provider contract
	•	normalized request/result DTOs
	•	provenance/cost DTOs
	•	versioned derived-image events

Rule

Check contracts before inventing. If an existing DTO or event already fits, reuse it.

⸻

Operational Flow Examples

Example A — Identity-sensitive portrait generation

AssetReadyV1
  → intelligence planner decides eligible job
  → provider routing selects Astria adapter
  → Astria tune/prompt flow executes
  → result normalized to internal DTO
  → artifact persisted into ProPhoto-owned storage
  → artifact attached through owning package boundary
  → DerivedImageJobCompleted emitted

Astria documents tune creation and prompt creation as its core API workflow.

Example B — Final upscale for approved image

approved asset/image selected
  → intelligence job requested
  → routing selects upscale-capable adapter
  → provider returns enhanced output
  → result persisted into ProPhoto-owned storage
  → final asset delivered downstream via ImageKit

Example C — Product background generation

existing product asset
  → background-generation job requested
  → routing selects commercial-background adapter
  → provider output normalized + stored
  → downstream delivery handled separately


⸻

ImageKit Position in ProPhoto

ImageKit should be treated as a delivery and downstream transform service, not the central intelligence engine.

Use ImageKit for:
	•	serving ProPhoto-owned outputs
	•	deterministic URL-based transforms
	•	Spaces integration
	•	CDN optimization
	•	format conversion
	•	low-cost repeatable post-processing

ImageKit explicitly supports DigitalOcean Spaces integration, which matches the existing DigitalOcean stack direction.

ImageKit also documents URL-based AI transform pricing via extension units, including e-bgremove, e-retouch, and e-upscale.

Important limitation

ImageKit docs do not provide a precise technical definition of what its retouch transform does beyond improving image quality, so ProPhoto should not build product promises around exact retouch semantics until empirically validated.

⸻

External Provider Trust Model

Trusted for compute

Providers may be trusted to:
	•	perform generation or enhancement
	•	return result metadata
	•	expose job status

Not trusted for canonical truth

Providers must not be treated as:
	•	the canonical source of resulting assets
	•	the long-term authoritative record of provenance
	•	the only place where the final file exists

⸻

Security and Compliance Considerations
	•	provider API keys must be stored in environment/config, never inline
	•	provider callbacks must be signed or otherwise validated where supported
	•	provider URLs must be treated as transient
	•	stored provenance must record provider name and model/version for auditability
	•	sensitive identity or customer imagery must be persisted only into ProPhoto-owned storage after generation
	•	deletion/retention policy must remain under ProPhoto control, not provider defaults

Astria documents model lifecycle details such as expiration windows for stored models, which is another reason not to treat provider-side storage as canonical.

⸻

Testing Requirements

Before implementation, the slice must define tests for:
	1.	provider routing chooses expected adapter for a job type
	2.	provider adapter normalizes responses into internal DTOs
	3.	artifact registration never bypasses owning package boundaries
	4.	intelligence never directly writes asset-owned tables
	5.	failure paths capture provider error + mark run correctly
	6.	provider-hosted URLs are not treated as final canonical asset truth
	7.	cost/provenance records are written for successful and failed runs as designed

⸻

Explicitly Out of Scope

This document does not define:
	•	UI for prompt editing
	•	customer-facing prompt builders
	•	live preview UX
	•	provider-specific prompt taxonomies
	•	pricing/billing product rules for customer charge-through
	•	automatic provider benchmarking
	•	capability negotiation beyond first-cut routing
	•	provider-specific storage as canonical truth
	•	cross-package direct writes
	•	direct booking queries from intelligence

⸻

Anti-Patterns

The following are explicitly forbidden:

1. Direct provider lock-in in application code

Bad:
	•	controller or service directly calling Astria SDK/client outside adapter boundary

2. Provider URLs treated as final assets

Bad:
	•	storing only provider result URL and serving it as production truth

3. Cross-package table writes

Bad:
	•	intelligence inserting rows directly into asset-owned tables

4. Delivery concerns embedded into orchestration

Bad:
	•	orchestration code building ImageKit delivery URLs as core business logic

5. Booking queries from intelligence

Bad:
	•	intelligence loading booking/session models directly instead of using SessionContextSnapshot

⸻

Recommended Next Documents

This doc should be followed by:
	1.	ARCHITECTURE-AI-PROVIDER-CONTRACTS.md
	2.	ARCHITECTURE-AI-RUN-AND-PROVENANCE-DATA-MODEL.md
	3.	ARCHITECTURE-AI-PROVIDER-ROUTING-POLICY.md
	4.	ARCHITECTURE-AI-IMAGEKIT-DELIVERY-INTEGRATION.md
	5.	ARCHITECTURE-AI-ASTRIA-ADAPTER.md

⸻

Bottom Line

ProPhoto should not build around one AI vendor.

It should build:
	•	one canonical storage and metadata spine
	•	one provider-agnostic intelligence orchestration layer
	•	many swappable provider adapters
	•	one downstream delivery layer for finalized ProPhoto-owned outputs

That keeps the system aligned with ProPhoto’s rules:
	•	owning packages mutate their own data
	•	events stay immutable and versioned
	•	assets remain canonical truth
	•	intelligence derives rather than owns media truth
	•	delivery remains separate from creative generation

If you want, next I’ll draft the companion doc for `ARCHITECTURE-AI-PROVIDER-CONTRACTS.md` in the same style.