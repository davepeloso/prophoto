# prophoto-ingest

ProPhoto ingest domain package. Owns the pipeline for receiving raw assets, running session-association matching, and emitting events consumed by downstream packages.

## Integrations

| Package | Role |
|---------|------|
| `prophoto-contracts` | Shared interfaces for assets, sessions, and matching decisions |
| `prophoto-assets` | Asset spine — ingest writes asset records here |
| `prophoto-booking` | Session/booking data — ingest reads session windows for matching |
| `prophoto-intelligence` | Derived intelligence layer — ingest provides session context |

## Architecture docs

Context loading rules for this package:

- **Booking / ingest / session matching**: `RULES.md` + `SYSTEM.md` + docs 3–7 in `ARCHITECTURE-INDEX.md`
- **Intelligence integration**: `RULES.md` + `SYSTEM.md` + docs 8–13 in `ARCHITECTURE-INDEX.md`

Key docs:
- `docs/architecture/INGEST-SESSION-ASSOCIATION-DATA-MODEL.md`
- `docs/architecture/INGEST-SESSION-ASSOCIATION-IMPLEMENTATION-CHECKLIST.md`
- `docs/architecture/SESSION-MATCHING-STRATEGY.md`

## Structure

```
src/
├── IngestServiceProvider.php
├── Contracts/          # Ingest-owned interfaces
├── Repositories/       # Data access layer
├── Services/           # Core domain services
├── Support/            # Helpers, DTOs, value objects
└── Events/             # Domain events

config/
└── ingest.php          # Session association + matching config

database/
└── migrations/         # Ingest-owned migrations

tests/
├── Feature/
├── Unit/
└── TestCase.php
```

## Config

Published via:
```bash
php artisan vendor:publish --tag=prophoto-ingest-config
php artisan vendor:publish --tag=prophoto-ingest-migrations
```
