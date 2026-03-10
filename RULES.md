# ProPhoto Rules (Authoritative)

This file defines hard architecture constraints for the repo.

If any document conflicts with this file, `RULES.md` wins.

## 1) Package Dependency Law

- `prophoto-contracts` depends on nothing in the domain layer.
- Compile-time direction is one-way: `contracts -> core (planned) -> access -> domains`.
- Domain packages may depend only on domains that represent earlier stages in the asset lifecycle.
- Example lifecycle: `capture -> ingest -> assets -> galleries -> delivery`.
- Circular dependencies are forbidden.

## 2) Database Ownership

- Each table has exactly one owning package.
- The owning package owns that table's migrations and model lifecycle.
- Cross-package migrations are forbidden.
- Foreign keys to upstream-owned tables are allowed.
- Cross-package Eloquent relationships are allowed only downstream -> upstream.

## 3) Integration Style

- Cross-package integration should use contracts and events, not direct concrete class coupling.
- Shared events must live in `prophoto-contracts`.
- Shared service interfaces and cross-package DTOs must live in `prophoto-contracts`.
- Prefer passing IDs/DTOs at boundaries; avoid importing peer/downstream models.

## 4) UI Boundary

- Foundational packages are headless by default.
- `prophoto-contracts` and foundational data/service packages must not ship UI frameworks or admin panels.
- UI belongs in app entrypoints (for example sandbox/app) or explicitly designated UI packages listed in `SYSTEM.md`.
- New foundational packages (for example `prophoto-assets`) must stay UI-free.

## 5) Metadata Spine Rule

For media assets, metadata is first-class and must persist end-to-end:

- Persist raw extracted metadata as immutable source truth.
- Persist normalized metadata as a schema-versioned, queryable projection.
- Never destroy raw metadata because normalization changes.
- Derivatives must reference the canonical asset identity.
- Metadata records must carry provenance (extractor source, tool version, timestamps).

## 6) Storage Ownership

Each physical media object must have a single canonical asset identity.

- Original files must be owned by the package responsible for asset identity (for example `prophoto-assets`).
- Other packages must reference assets by ID and must not store independent copies of the same original media.
- Derivatives must reference the canonical asset identity.
- Storage path conventions must be resolved through contracts, not hardcoded across packages.

## 7) Domain Events

Domain events represent historical facts and must be immutable.

- Events must not be modified after introduction.
- If an event structure changes, introduce a new versioned event.
- Events must carry stable identifiers (for example asset IDs), not full models.
- Events must live in `prophoto-contracts`.

## PR Compliance Checklist

- [ ] Dependency direction correct.
- [ ] Table ownership respected.
- [ ] Integration uses contracts/events.
- [ ] UI placement correct.
- [ ] Metadata spine preserved.
