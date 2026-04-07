# ProPhoto AI

## Purpose

AI portrait generation package. Manages the lifecycle of training custom AI models from subject photos and generating AI portraits on demand. This is a feature-level package for a specific client-facing capability — it is entirely separate from prophoto-intelligence, which handles backend AI orchestration (tagging, embeddings, scene detection) as part of the core ingest loop.

## Responsibilities

- AiGeneration model (training runs linked to galleries)
- AiGenerationRequest model (individual generation requests from subjects)
- AiGeneratedPortrait model (generated portrait outputs)
- Training job lifecycle: image selection → validation → provider submission → completion
- Generation request lifecycle: prompt → queue → result → storage
- Quota tracking per subject/gallery/studio
- Cost attribution per training and generation

## Non-Responsibilities

- Does NOT own intelligence orchestration — tagging, embeddings, and scene detection are prophoto-intelligence
- Does NOT own asset truth — generated portraits reference galleries, not raw assets
- Does NOT own gallery models — depends on prophoto-gallery for Gallery
- Does NOT participate in the ingest → assets → intelligence event loop
- Does NOT mutate ingest, asset, or booking state
- Does NOT own permissions — uses permission constants from prophoto-access

## Integration Points

- **Events listened to:** None currently
- **Events emitted:** None currently (future: AiTrainingCompleted, AiGenerationCompleted)
- **Contracts depended on:** `prophoto/contracts` (shared DTOs/enums)
- **Model relationships:** AiGeneration→Gallery (belongs to), Gallery→AiGeneration (has many, defined in prophoto-gallery)

## Data Ownership

| Table | Model | Purpose |
|---|---|---|
| `ai_generations` | AiGeneration | Training runs linked to galleries |
| `ai_generation_requests` | AiGenerationRequest | Individual generation requests |
| `ai_generated_portraits` | AiGeneratedPortrait | Generated portrait outputs |

## Notes

- ServiceProvider is declared in composer.json (`ProPhoto\AI\AIServiceProvider`) but the file does not yet exist — needs implementation
- This package has a bidirectional model relationship with prophoto-gallery (Gallery↔AiGeneration) — this is intentional but should not expand further
- Completely distinct from prophoto-intelligence: AI here means client-facing portrait generation; intelligence means backend asset analysis
- Composer currently requires `prophoto/galleries` (plural) — this is a bug. The correct package name is `prophoto/gallery`. The `composer.json` dependency must be corrected before this package can be installed.
