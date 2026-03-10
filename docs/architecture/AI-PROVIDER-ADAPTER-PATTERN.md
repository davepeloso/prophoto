# AI Provider Adapter Pattern
Date: March 10, 2026
Status: Architecture design (headless, no code)

## 1) Purpose and Architectural Boundary
Define how provider integrations are isolated behind capability-based adapters in `prophoto-intelligence`.

Boundary:
- Asset Spine remains canonical and unchanged.
- Orchestrator coordinates runs, not provider APIs.
- Generators translate domain context/results.
- Provider adapters handle vendor-specific API details.

This document does not modify existing Asset Spine or intelligence contracts.

## 2) Orchestrator vs Generator vs Provider Adapter
`Orchestrator`:
- plans runs
- creates/transitions run lifecycle
- invokes generators
- coordinates persistence/events

`Generator`:
- receives `IntelligenceRunContext` + canonical metadata
- prepares capability input
- calls adapter
- maps adapter response to domain DTOs (`LabelResult`, `EmbeddingResult`, `GeneratorResult`)

`Provider Adapter`:
- authenticates and calls external/local model provider
- serializes provider requests
- parses provider responses
- maps provider errors to adapter-level failures

## 3) Why Capability Contracts Over Vendor Contracts
Prefer:
- `EmbeddingProviderContract`
- `ImageTaggingProviderContract`

Over:
- `OpenAIProviderContract`
- `ReplicateProviderContract`

Reason:
- domain logic depends on capabilities (embedding, tagging), not vendor names
- providers can be swapped without touching orchestrator/generator logic
- prevents vendor lock-in from leaking into domain layer

## 4) Proposed v1 Provider Contracts
In `prophoto-intelligence` (implementation-facing contracts):
- `EmbeddingProviderContract`
  - `generateEmbedding(EmbeddingRequest $request): EmbeddingResponse`
- `ImageTaggingProviderContract`
  - `generateTags(ImageTaggingRequest $request): ImageTaggingResponse`

These are internal to intelligence package boundaries unless other packages need them later.

## 5) Request/Response DTO Pattern
Use adapter DTOs to isolate provider schemas:
- `EmbeddingRequest` / `EmbeddingResponse`
- `ImageTaggingRequest` / `ImageTaggingResponse`

Rules:
- adapter DTOs are provider-bridge types, not persistence/domain models
- generators map adapter DTOs into intelligence result DTOs
- orchestrator does not parse provider payloads

## 6) Provider Metadata Handling
Provider metadata (request IDs, token usage, latency, safety flags) should not pollute canonical result DTO fields.

Use:
- adapter response metadata fields
- `GeneratorResult->meta` for run-scoped diagnostics

## 7) Error Handling Boundaries
Adapter:
- captures provider-specific failures (timeouts, HTTP/provider errors, malformed payloads)

Generator:
- translates adapter failures into generator execution failure context

Orchestrator:
- classifies retryable vs non-retryable
- updates run status (`failed`/`cancelled`)
- avoids completion events on failed runs

## 8) Testing Strategy
Generator tests:
- mock provider adapter
- assert request construction + response-to-DTO mapping

Adapter tests:
- request serialization
- response parsing
- provider error mapping

Orchestrator tests:
- run lifecycle transitions
- retry/idempotency behavior
- persistence/event coordination with mocked generators

## 9) Minimal v1 Provider Setup
Minimal v1 stack:
- contracts:
  - `EmbeddingProviderContract`
  - `ImageTaggingProviderContract`
- DTOs:
  - `EmbeddingRequest`, `EmbeddingResponse`
  - `ImageTaggingRequest`, `ImageTaggingResponse`
- generators:
  - `AssetEmbeddingGenerator`
  - `AssetTaggingGenerator`
- providers:
  - one real provider adapter per capability
  - one fake/test adapter per capability

Optional DI bindings by capability:
- `EmbeddingProviderContract -> <provider implementation>`
- `ImageTaggingProviderContract -> <provider implementation>`

## 10) Guardrails / What Not To Do
Do not:
- inject provider SDKs directly into orchestrator
- let repositories call provider APIs
- leak provider payload shapes across package boundaries
- build a single monolithic provider service for all capabilities
- encode vendor names throughout domain orchestration logic
- modify Asset Spine contracts for provider concerns

Keep provider concerns isolated, generator mapping explicit, and orchestration deterministic.
