# Intelligence Run Data Model
Date: March 10, 2026
Status: Frozen pre-migration data model (no code/migrations yet)

## Purpose
Freeze the concrete database ownership model for Derived Intelligence before migration implementation.

Core rules:
- `prophoto-intelligence` owns all intelligence tables.
- All derived records reference canonical `asset_id` from `prophoto-assets`.
- Runs are immutable historical records after terminal state.
- Latest/current intelligence is computed at query time (no mutable "current" row).
- No canonical asset tables are modified by this model.

## Ownership and Boundary
- Owning package: `prophoto-intelligence`
- Canonical upstream reference: `prophoto-assets.assets.id` (or canonical PK type equivalent)
- Cross-package change rule: no migrations in this phase modify `prophoto-assets` tables

---

## 1) `intelligence_runs`
### Purpose
Track every orchestrated generator execution as an auditable, versioned run record.

### Columns
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | big integer / ULID PK | Run identifier |
| `asset_id` | yes | FK (matches `assets.id` type) | Canonical asset link |
| `generator_type` | yes | string | Generator family |
| `generator_version` | yes | string | Generator code/pipeline revision |
| `model_name` | yes | string | Model identity |
| `model_version` | yes | string | Model revision |
| `configuration_hash` | yes | string | Hash of effective generator config |
| `run_scope` | yes | enum/string | `single_asset|batch|reindex|migration` |
| `run_status` | yes | enum/string | `pending|running|completed|failed|cancelled` |
| `started_at` | nullable | timestamp | Set when execution begins |
| `completed_at` | nullable | timestamp | Set on successful completion |
| `failed_at` | nullable | timestamp | Set on terminal failure |
| `failure_code` | nullable | string | Machine-oriented failure code |
| `failure_message` | nullable | text | Operator/debug message |
| `cancelled_at` | nullable | timestamp | Set on cancellation |
| `cancellation_reason` | nullable | text | Cancellation context |
| `retry_count` | yes | integer (default 0) | Retry tracking |
| `created_at` | yes | timestamp | Run creation time |
| `updated_at` | yes | timestamp | State transitions only |
| `trigger_source` | optional future | enum/string | `asset_ready|manual_reprocess|scheduled_batch|migration` |

### Foreign Keys
- `asset_id -> assets.id` (canonical reference)

### Uniqueness Constraints
- Primary key on `id`
- Active-run concurrency constraint:
  - Only one active run per
    (`asset_id`, `generator_type`, `generator_version`, `model_name`, `model_version`)
    where status is active (`pending`, `running`)
  - Enforce via partial unique index where supported; otherwise atomic check + lock at run creation

### Indexes (example strategy)
- `idx_runs_asset_created` on (`asset_id`, `created_at`)
- `idx_runs_status` on (`run_status`)
- `idx_runs_asset_model_status_completed` on (`asset_id`, `generator_type`, `generator_version`, `model_name`, `model_version`, `run_status`, `completed_at`)
- `idx_runs_asset_config_status` on (`asset_id`, `configuration_hash`, `run_status`)

### Mutability Rules
- Mutable only for lifecycle transitions (`pending -> running -> completed|failed|cancelled`) and failure/retry metadata.
- Terminal runs (`completed`, `failed`, `cancelled`) are immutable historical records.
- Upgrades/reprocesses create new runs; prior runs are never overwritten.

### `configuration_hash` Usage
- Represents effective generator configuration.
- Derived from:
  - `generator_type`
  - `generator_version`
  - `model_name`
  - `model_version`
  - effective generator parameters
- Skip logic may short-circuit execution when a completed run already exists for same `asset_id` + `configuration_hash`.

---

## 2) `asset_labels`
### Purpose
Store AI-generated tags/labels produced by a specific run.

### Columns
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | big integer / ULID PK | Label row identifier |
| `asset_id` | yes | FK (matches `assets.id` type) | Canonical asset link |
| `run_id` | yes | FK to `intelligence_runs.id` | Producing run |
| `label` | yes | string | Label value |
| `confidence` | nullable | decimal/float | Confidence score |
| `created_at` | yes | timestamp | Write time |

### Foreign Keys
- `asset_id -> assets.id`
- `run_id -> intelligence_runs.id`

### Uniqueness Constraints
- Primary key on `id`
- Run-level idempotency: unique (`run_id`, `label`) in v1

### Indexes (example strategy)
- `idx_labels_asset` on (`asset_id`)
- `idx_labels_run` on (`run_id`)
- `idx_labels_asset_label` on (`asset_id`, `label`)
- optional future: `idx_labels_generator_label` on (`generator_type`, `label`) for generator-scoped label search

### Mutability Rules
- Label rows are immutable within a run.
- Reprocessing creates labels under a new `run_id`; old labels remain unchanged.
- Latest labels are selected by query-time run selection, not by row mutation.

---

## 3) `asset_embeddings`
### Purpose
Store embedding vectors produced by a specific run for semantic retrieval/search.

### Columns
| Column | Required | Type (conceptual) | Notes |
|---|---|---|---|
| `id` | yes | big integer / ULID PK | Embedding row identifier |
| `asset_id` | yes | FK (matches `assets.id` type) | Canonical asset link |
| `run_id` | yes | FK to `intelligence_runs.id` | Producing run |
| `embedding_vector` | yes | vector/json/blob | Storage-engine dependent |
| `vector_dimensions` | yes | integer | Embedding dimension size |
| `model_family` | optional future | string | Family grouping (for example `openai/text-embedding-3`, `clip`) |
| `created_at` | yes | timestamp | Write time |

### Foreign Keys
- `asset_id -> assets.id`
- `run_id -> intelligence_runs.id`

### Uniqueness Constraints
- Primary key on `id`
- One embedding per asset per run: unique (`asset_id`, `run_id`) in v1

### Indexes (example strategy)
- `idx_embeddings_asset` on (`asset_id`)
- `idx_embeddings_run` on (`run_id`)
- Optional engine-specific vector index on `embedding_vector` (deferred to implementation engine choice)

### Mutability Rules
- Embedding rows are immutable once written.
- Reprocessing/model changes create new embedding rows under new runs.
- Historical embeddings remain queryable.

---

## Latest/Current Read Rule
There is no mutable "current intelligence" table in v1.

Repositories compute latest at read time:
- latest successful run for asset (optionally scoped by generator/model)
- latest labels from latest successful label-producing run
- latest embedding from latest successful embedding-producing run

This preserves history and avoids destructive overwrites.

## Guardrails
- Do not modify canonical `assets` or canonical metadata tables in intelligence migrations.
- Do not backfill by mutating old runs; create new runs/results.
- Do not let generator internals bypass run-scoped persistence constraints.
