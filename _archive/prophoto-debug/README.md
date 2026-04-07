# prophoto-debug — Retired

Status: **Retired. Not active. Do not install or reference.**

## Why This Package Is Retired

This was a legacy dev/debug toolkit for tracing the ingest pipeline (preview extraction, metadata extraction, thumbnail generation). It recorded per-method decision traces and surfaced them via Filament admin pages and artisan commands.

It was retired because:

- It depended on ingest event classes from the pre-rebuild legacy ingest package
- Those event classes no longer exist after the ingest pipeline was fully rebuilt in prophoto-ingest
- Its event listeners would silently no-op against the new architecture — it would never capture any traces
- It was removed from the active `$bootstrapPackages` list in `scripts/prophoto.php`
- No active package requires it in composer.json

## What Replaced It

The rebuilt prophoto-ingest package has native observability through its structured services, repositories, and comprehensive test suite. If pipeline tracing is needed in the future, it should be rebuilt from scratch against the new ingest event contracts defined in prophoto-contracts (see `docs/architecture/future/debug.md` for the original design intent).

## What Is Preserved Here

The original code is preserved in this directory for historical reference only. Do not use it as a starting point for new development. If you need a devtools package, scaffold from prophoto-ingest (the gold-standard template) and wire to the current event contracts.

## Do Not

- Do NOT add `prophoto/debug` back to `scripts/prophoto.php` `$bootstrapPackages`
- Do NOT require `prophoto/debug` in any active package's composer.json
- Do NOT copy event listener code from this package — the events it listened to no longer exist

---

<!-- Original code preserved below for historical reference -->
