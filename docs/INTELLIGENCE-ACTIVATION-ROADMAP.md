# Intelligence Activation Roadmap
## Context Preservation for Sprints 8–9+

**Written:** April 15, 2026 — after Sprint 6 completion, before Sprint 7 planning
**Purpose:** Capture the current state of `prophoto-intelligence` and the build order for activating it, so any future agent (or Dave) can pick this up cold without re-auditing the codebase.

---

## TL;DR

The intelligence package infrastructure is **built and tested**. It's not "scaffolded" — it's a working pipeline with an orchestrator, planner, registry, persistence layer, 3 migrations, 16 source files, and 11 tests. What's missing is (a) a real generator that does actual AI work, (b) a config file to publish, and (c) gallery-side UI to surface the outputs. The recommended activation order is below.

---

## What Already Exists (Do NOT Rebuild)

### Core Pipeline (fully implemented)
| Component | File | Status |
|-----------|------|--------|
| Entry Orchestrator | `src/Services/IntelligenceEntryOrchestrator.php` | ✅ Handles `AssetReadyV1`, orchestrates full plan → execute → persist flow |
| Planner | `src/Services/IntelligencePlanner.php` | ✅ Pure function — evaluates generator applicability, skip conditions, existing runs |
| Registry | `src/Services/IntelligenceGeneratorRegistry.php` | ✅ Factory managing all generators + descriptors |
| Execution Service | `src/Services/IntelligenceExecutionService.php` | ✅ Invokes generators, validates context/asset match |
| Persistence Service | `src/Services/IntelligencePersistenceService.php` | ✅ Writes labels + embeddings transactionally |
| Run Repository | `src/Services/IntelligenceRunRepository.php` | ✅ CRUD for intelligence runs with concurrency protection |
| Service Provider | `src/IntelligenceServiceProvider.php` | ✅ All singletons bound, event listeners registered |

### Database (3 migrations, all deployed)
| Table | Purpose |
|-------|---------|
| `intelligence_runs` | Run tracking with status machine (pending → running → completed/failed). Unique constraint on active runs per asset/generator/model combo prevents duplicate work. |
| `asset_labels` | Per-asset, per-run labels with confidence scores (0–1). Unique on (run_id, label). |
| `asset_embeddings` | Per-asset, per-run vector embeddings (JSON). Unique on (asset_id, run_id). |

### Demo Generators (3 registered, used for testing)
| Generator | Type | Outputs | Notes |
|-----------|------|---------|-------|
| `DemoTaggingGenerator` | `demo_tagging` | labels | Hardcoded tags: `demo_tagged` (0.95), `asset_ready` (0.90), optionally `jpeg` (0.85) |
| `DemoEmbeddingGenerator` | `demo_embedding` | embeddings | Generates deterministic test vectors |
| `EventSceneTaggingGenerator` | `event_scene_tagging` | labels | Requires session context, prefers wedding/portrait/engagement |

### Events (defined in prophoto-contracts)
| Event | Dispatched By | Consumed By |
|-------|--------------|-------------|
| `AssetReadyV1` | `AssetCreationService` (prophoto-assets) | `IntelligenceServiceProvider` → entry orchestrator |
| `AssetSessionContextAttached` | `HandleSessionAssociationResolved` (prophoto-assets) | `HandleAssetSessionContextAttached` (prophoto-intelligence) |
| `AssetIntelligenceRunStarted` | Entry orchestrator | Future consumers |
| `AssetIntelligenceGenerated` | Entry orchestrator | Future consumers (gallery enrichment, search indexing) |
| `AssetEmbeddingUpdated` | Entry orchestrator | Future consumers (similarity search) |

### Generator Contract (in prophoto-contracts)
```php
interface AssetIntelligenceGeneratorContract
{
    public function generatorType(): string;
    public function generatorVersion(): string;
    public function generate(IntelligenceRunContext $runContext, array $canonicalMetadata): GeneratorResult;
}
```

### Key DTOs (in prophoto-contracts)
- `SessionContextSnapshot` — carries session state (session type, job type, time window, reliability tier, manual lock state)
- `PlannedIntelligenceRun` — planner output: decision + skip reasoning
- `LabelResult` — label string + confidence (0–1) + generator metadata
- `EmbeddingResult` — vector array + dimensions, validates size matches and all values are finite
- `GeneratorResult` — wrapper containing labels and/or embeddings arrays

---

## The Event Signal Gap

`AssetReadyV1` fires from `AssetCreationService::createFromFile()` with `hasDerivatives: false` because thumbnail generation is async (via `GenerateAssetThumbnail` job). This means:

- **Intelligence CAN run on original file metadata immediately** — EXIF, dimensions, camera model, GPS, etc. are available at `AssetReadyV1` time.
- **Intelligence CANNOT use thumbnails/previews** — derivatives aren't ready yet.
- **No "derivatives complete" event exists** — if a future generator needs a thumbnail (e.g., visual AI analysis), we'd need to add an `AssetDerivativesReady` event dispatched after `GenerateAssetThumbnail` completes.

**For Sprint 8, this is fine.** The first real generators (quality scoring from EXIF, auto-tagging from metadata, session-based scene detection) all work from normalized metadata, not from pixel data. Visual AI (image recognition, style detection) comes later and would require the derivatives signal.

---

## Sprint 8 — Intelligence Activation (Recommended Order)

### 8.1 — Publish Config + Package README (1 pt)
- Create `config/prophoto-intelligence.php` with all keys the service provider reads
- Document enable flags, model defaults, generator routing
- Write package README with setup, extension points, skip reasons
- **Why first:** Every subsequent story needs config to be explicit, not implicit env/defaults

### 8.2 — End-to-End Sandbox Verification (2 pts)
- Wire `prophoto-intelligence` into the sandbox app's service provider (if not already)
- Upload a test image through the ingest flow
- Verify: `AssetReadyV1` fires → planner evaluates → demo generator runs → labels persisted → events dispatched
- Verify: `intelligence_runs` row shows completed status
- **Why second:** Proves the pipeline works in a real app before writing real generators

### 8.3 — Image Quality Scoring Generator (3 pts)
- First **real** generator: scores image quality from EXIF/normalized metadata
- Inputs: resolution, ISO, shutter speed, aperture, focal length, camera model
- Outputs: labels like `high_quality` (confidence based on technical metrics), `low_light`, `high_iso`, `sharp`, `soft`
- Register in `IntelligenceGeneratorRegistry` with descriptor
- Tests: known EXIF → expected labels with expected confidence ranges
- **Why this generator first:** Uses only metadata (no pixel analysis needed), immediately useful for photographers, validates the full pipeline with real data

### 8.4 — Session-Aware Scene Tagging Generator (3 pts)
- Extends `EventSceneTaggingGenerator` pattern with real logic
- Uses `SessionContextSnapshot` (session type, job type) combined with metadata (time of day, location, sequence position)
- Outputs: scene labels like `ceremony`, `reception`, `first_dance`, `portraits`, `getting_ready`
- Requires session context — planner correctly skips if context is missing/unreliable
- **Why this generator:** Demonstrates session-context integration end-to-end, directly useful for auto-organizing wedding galleries

### 8.5 — Filament Intelligence Results Display (3 pts)
- Surface `asset_labels` on the gallery image detail view or asset admin
- Show labels with confidence badges, grouped by generator
- Show run history (status, timing, generator version)
- Read-only — no editing of intelligence outputs from the admin
- **Why last in Sprint 8:** Outputs need to exist before building UI to display them

---

## Sprint 9+ — Intelligence-Powered Gallery Features

These depend on Sprint 8 being complete and producing real data.

### 9.1 — Smart Gallery Curation
- Auto-suggest "best of" selections based on quality scores + scene coverage
- Present as a "Quick Select" button on the gallery edit page
- Photographer approves/rejects suggestions (never auto-publishes)
- Requires: quality scoring generator (8.3) + scene tagging (8.4) producing real labels

### 9.2 — Gallery Engagement Scoring
- Score client engagement across views, approvals, downloads per gallery
- Aggregate into a gallery health metric shown on the dashboard
- Requires: download tracking (Sprint 6 ✅) + view tracking (Sprint 6 ✅) + approval data (Sprint 4 ✅)
- **Note:** This is more analytics than intelligence — could use raw SQL aggregation, not the generator pipeline

### 9.3 — Download Prediction
- Predict which images a client will download based on approval patterns from historical galleries
- Requires: enough historical data to train on (may need seeded sample data for development)
- Could be a generator that runs on `GallerySubmitted` event rather than `AssetReadyV1`

### 9.4 — Smart Notification Timing
- Analyze photographer activity patterns (login times, response times) to optimize notification delivery
- Deferred — requires activity data that doesn't exist yet

### 9.5 — Visual AI Integration (Future)
- Image recognition, style detection, face grouping, duplicate detection
- Requires: `AssetDerivativesReady` event (doesn't exist yet)
- Requires: external AI service integration (OpenAI Vision, Google Cloud Vision, or self-hosted)
- Heaviest lift — save for when the pipeline is proven with metadata-only generators

---

## Concurrency & Safety Notes for Implementors

1. **Active run uniqueness** is enforced at DB level (unique index on asset_id + generator_type + generator_version + model_name + model_version where status is pending/running). Don't add application-level locking — the database handles it.
2. **`markRunning()` is atomic** — competing workers safely race; loser gets a constraint violation and backs off.
3. **`insertOrIgnore`** on labels/embeddings ensures retry safety — a crashed-and-retried run won't create duplicates.
4. **Configuration hash** on runs enables idempotent detection — same config + same asset = skip if already completed.
5. **No automatic retries** — failed runs stay failed. Retry logic is deferred to the queue consumer layer (not the orchestrator).

---

## Anti-Patterns to Avoid

1. **Don't query booking from intelligence** — use `SessionContextSnapshot` DTO. The snapshot carries everything needed.
2. **Don't create generators that call peer generators** — generators are independent; the planner/orchestrator handles ordering.
3. **Don't modify `prophoto-contracts` for intelligence-specific types** — check if the DTO/enum/event already exists. (As of Sprint 6, contracts remain untouched since the intelligence contracts were originally defined.)
4. **Don't add ML-based session matching** — session matching is deterministic (rule-based). Keep it that way.
5. **Don't write to `prophoto-assets` tables from intelligence** — intelligence has its own tables (`intelligence_runs`, `asset_labels`, `asset_embeddings`). The read-only boundary with assets is preserved.
6. **Don't skip the planner** — always go through `IntelligenceEntryOrchestrator`. Direct generator invocation bypasses skip logic, concurrency protection, and event dispatch.

---

## Dependencies Map

```
Sprint 7 (Gallery Polish)          Sprint 8 (Intelligence Activation)
  ├── Bulk download                  ├── 8.1 Config + README
  ├── Gallery expiration             ├── 8.2 Sandbox verification
  ├── Client messaging               ├── 8.3 Quality scoring generator
  └── (no intelligence deps)        ├── 8.4 Scene tagging generator
                                     └── 8.5 Filament results display
                                              │
                                     Sprint 9+ (Intelligence + Gallery)
                                       ├── 9.1 Smart gallery curation (needs 8.3 + 8.4)
                                       ├── 9.2 Engagement scoring (needs Sprint 6 data)
                                       ├── 9.3 Download prediction (needs historical data)
                                       ├── 9.4 Smart notification timing (needs activity data)
                                       └── 9.5 Visual AI (needs AssetDerivativesReady event)
```

---

*This document is a planning aid, not a spec. Each story should get a proper sprint spec before implementation. The architecture docs in `/docs/ARCHITECTURE-*` are authoritative for system rules.*

*Last updated: 2026-04-15 — post Sprint 6, pre Sprint 7*
