# ProPhoto Intelligence

## Purpose

Owns derived intelligence: planning which AI generators to run against an asset, orchestrating their execution, and persisting the results (labels, embeddings, run records). This package is the terminal consumer of the core event loop — it receives fully contextualized assets and produces derived intelligence outputs. It is entirely separate from prophoto-ai (which handles client-facing portrait generation).

## Core Loop Role

Intelligence is **position 3** in the core event loop. It derives outputs from canonical asset truth.

```
  prophoto-ingest  ──(SessionAssociationResolved)──►  prophoto-assets
  prophoto-assets  ──(AssetSessionContextAttached)──►  ► prophoto-intelligence
  prophoto-assets  ──(AssetReadyV1)──────────────────►  ► prophoto-intelligence
```

When `AssetSessionContextAttached` arrives, the `HandleAssetSessionContextAttached` listener triggers intelligence planning — determining which generators to run based on the asset's session context and media kind. When `AssetReadyV1` arrives, the entry orchestrator evaluates whether to start an intelligence run.

If this package is removed, no AI tagging, embedding generation, or scene detection occurs. Assets exist but are never analyzed.

## Responsibilities

- IntelligenceEntryOrchestrator: decides whether to start a run when an asset event arrives
- IntelligenceOrchestrator: executes a planned run (tagging generators)
- IntelligenceEmbeddingOrchestrator: executes embedding generation runs
- IntelligencePlanner: decides which generators to run for a given asset based on session context, media kind, and generator capabilities
- IntelligenceGeneratorRegistry: registry of available generator implementations
- IntelligenceExecutionService: runs a single generator and captures results
- IntelligencePersistenceService: persists generator outputs (labels, embeddings, run status)
- IntelligenceRunRepository: run record persistence and query
- HandleAssetSessionContextAttached listener: triggers planning when session context is attached
- Generators: DemoTaggingGenerator, DemoEmbeddingGenerator, EventSceneTaggingGenerator
- Planning internals: PlannedIntelligenceRun, GeneratorDescriptor, PlannerDecisionReason
- IntelligenceServiceProvider: registers all orchestrators, planner, registry, generators, persistence services, and event listeners

## Non-Responsibilities

- MUST NOT query booking tables directly — session context arrives via `SessionContextSnapshot` DTO, never by querying booking models. This is a hard rule from SYSTEM.md.
- MUST NOT mutate asset records — assets package owns canonical asset truth
- MUST NOT mutate ingest decisions — ingest package owns assignment truth
- MUST NOT own client-facing AI features — portrait generation lives in prophoto-ai
- MUST NOT define its own events for the core loop — it consumes events, it does not feed back into ingest or assets

## Integration Points

- **Events listened to:** `AssetSessionContextAttached` (from prophoto-assets), `AssetReadyV1` (from prophoto-contracts, dispatched by assets)
- **Events emitted:** `AssetIntelligenceGenerated`, `AssetIntelligenceRunStarted`, `AssetEmbeddingUpdated` (all defined in prophoto-contracts — these are informational, not part of the core loop)
- **Contracts depended on:** `prophoto/contracts` (DTOs, enums, event classes, AssetIntelligenceGeneratorContract), `prophoto/assets` (AssetSessionContextAttached event, asset references)
- **Consumed by:** Nothing in the current system — intelligence is the terminal node. Future consumers may listen to intelligence events for search indexing or recommendations.

## Data Ownership

| Table | Purpose |
|---|---|
| `intelligence_runs` | Run records: which generators ran against which asset, status, timing, scope |
| `asset_labels` | Labels produced by tagging generators (scene tags, object detection, etc.) |
| `asset_embeddings` | Vector embeddings produced by embedding generators (for similarity search) |

## Notes

- Intelligence receives session context exclusively via the `SessionContextSnapshot` DTO. It never instantiates booking models or runs booking queries. This isolation is enforced by SYSTEM.md.
- The planner uses `GeneratorDescriptor` to match generator capabilities against asset context — not all generators run for all assets.
- `IntelligenceEntryOrchestrator` is gated by config (`intelligence.entry_orchestrator_enabled`) and can be disabled without affecting the rest of the loop.
- This package has 13 test files including vertical slice tests, planner matrix tests, and unit tests for generators, orchestrators, and listeners.
- No README existed before this rewrite.
- ServiceProvider: `ProPhoto\Intelligence\IntelligenceServiceProvider` (auto-discovered)
