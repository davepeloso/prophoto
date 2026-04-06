# Future Packages / Planned Domains

This directory contains feature specifications for packages that are not yet implemented (or require rebuild before use).

These documents originate from archived package READMEs and represent design intent, not working code. Do not treat them as authoritative architecture until they have been reviewed and updated at implementation time.

## Planned Domains

| File | Status | Summary |
|---|---|---|
| [audit.md](audit.md) | Planned | Event-driven audit trail across all packages |
| [downloads.md](downloads.md) | Planned | Async bulk ZIP generation with progress tracking |
| [payments.md](payments.md) | Planned | Stripe checkout, webhooks, invoice reconciliation |
| [permissions.md](permissions.md) | Planned | Contextual per-resource permissions + invitation system |
| [security.md](security.md) | Planned | Magic link tokens, rate limiting, abuse detection |
| [settings.md](settings.md) | Planned | Feature flags + hierarchical studio/org settings |
| [storage.md](storage.md) | Planned | Unified storage abstraction (local, ImageKit, S3) |
| [tenancy.md](tenancy.md) | Planned | Subdomain resolution, multi-studio admin, impersonation |
| [debug.md](debug.md) | Needs rebuild | Ingest pipeline tracer — wired to legacy events, must reconnect to new contracts |

## Notes on Overlap

Before implementing any of these, review against currently active packages:

- **permissions.md** — base RBAC already implemented in `prophoto-access`. This spec covers the contextual/per-resource and invitation layer only.
- **tenancy.md** — Studio/Organization models already implemented in `prophoto-access`. This spec covers subdomain resolution and impersonation.
- **storage.md** — review overlap with `prophoto-assets` before starting.

## How to Use These Specs

1. When you are ready to implement a feature, read the spec here first
2. Update the spec to reflect current architecture before writing any code
3. Scaffold the new package using `prophoto-ingest` as the gold-standard template
4. Do not copy code from the archived packages — they may be stale
