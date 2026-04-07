# ProPhoto Package Audit

**Date:** 2026-04-05
**Scope:** All 20 packages in the prophoto monorepo
**Baseline:** prophoto-ingest (gold-standard scaffold), prophoto-contracts (shared kernel)

---

## 1. Executive Summary

The monorepo contains 20 packages. Five are actively developed with clear domain boundaries (access, assets, booking, contracts, ingest). Two more have real implementations that serve useful roles (gallery, intelligence). The remaining 13 range from minimal stubs to completely empty scaffolds.

The most significant structural issue is **domain overlap between prophoto-access and the empty scaffolds prophoto-permissions and prophoto-tenancy**. Access already owns Studio/Organization models, contextual RBAC via Spatie, and 58+ permissions across 4 user roles. The scaffolds claim responsibility for exactly the same concerns but contain zero code. They should be archived immediately to prevent future confusion about where new permission or tenancy logic belongs.

**prophoto-ai** and **prophoto-debug** both deserve attention but for different reasons. AI is a small, focused feature package (portrait generation) that is correctly separated from intelligence. Debug is a useful dev tool but depends on legacy ingest events that no longer exist in the rebuilt ingest package.

**Recommended immediate actions:** Archive 10 packages, rewrite 2 READMEs, plan 1 rebuild.

---

## 2. Package-by-Package Assessment

| Package | Status | Recommendation | Why | Immediate Action |
|---|---|---|---|---|
| **prophoto-access** | Active, mature | **Keep** | Owns RBAC, tenancy (Studio/Org), Filament admin, 6 migrations, 58+ permissions. Core infrastructure. | Rewrite README to declare ownership of permissions and tenancy concerns explicitly. |
| **prophoto-ai** | Active, minimal | **Keep** | 3 models (AiGeneration, AiGenerationRequest, AiGeneratedPortrait), clean boundary. Portrait generation is correctly isolated from intelligence. | None. Healthy as-is. |
| **prophoto-assets** | Active, core | **Keep** | Core domain package for asset management. Required by ingest. | None. |
| **prophoto-audit** | Empty scaffold | **Archive** | composer.json + README only. No src/, no migrations, no implementation plan. | Move to `archived/` directory. |
| **prophoto-booking** | Active, core | **Keep** | Core domain package for session/booking management. Required by ingest. Owns BOOKING-DATA-MODEL.md. | None. |
| **prophoto-contracts** | Active, shared kernel | **Keep** | DTOs, enums, events, interfaces shared across packages. Well-maintained with enum coverage tests. | None. |
| **prophoto-debug** | Active, dev tool | **Needs rebuild** | Ingest pipeline tracer with Filament pages and artisan commands. Useful capability but depends on legacy ingest events that no longer exist after the ingest rebuild. | Do not archive. Plan rebuild to wire into new ingest event contracts. |
| **prophoto-downloads** | Empty scaffold | **Archive** | composer.json + README only. | Move to `archived/` directory. |
| **prophoto-gallery** | Active, implemented | **Keep** | Real models, policies, controllers, Filament resources. Functional domain package. | None. |
| **prophoto-ingest** | Active, gold standard | **Keep** | Freshly rebuilt with proper scaffold, matching pipeline, comprehensive tests. Reference architecture for all other packages. | None. |
| **prophoto-intelligence** | Active, core | **Keep** | AI/ML orchestration layer (run management, session context reliability). Distinct from prophoto-ai (which is feature-level portrait generation). | None. |
| **prophoto-interactions** | Minimal | **Keep (review)** | Has 1 model (ImageInteraction) and 1 migration. Small but real. May belong inside gallery or assets long-term. | Review whether ImageInteraction should merge into gallery. Low priority. |
| **prophoto-invoicing** | Minimal | **Keep (review)** | Has some implementation. Will grow when payments are needed. | Review scope before next feature sprint. |
| **prophoto-notifications** | Minimal | **Keep (review)** | Has 1 model (Message) and 1 migration. Real but very thin. | Review whether this should absorb into a broader communications package or stay standalone. |
| **prophoto-payments** | Empty scaffold | **Archive** | No implementation. When payment features are needed, start fresh with proper scaffold (ingest-style). | Move to `archived/` directory. |
| **prophoto-permissions** | Empty scaffold | **Archive** | **Zero code. All declared responsibilities already live in prophoto-access** (Spatie RBAC, PermissionContext, 58+ permissions). Keeping this scaffold actively misleads about where permission logic belongs. | Move to `archived/` directory. Add note to access README. |
| **prophoto-security** | Empty scaffold | **Archive** | No implementation. Vague scope. | Move to `archived/` directory. |
| **prophoto-settings** | Empty scaffold | **Archive** | No implementation. | Move to `archived/` directory. |
| **prophoto-storage** | Empty scaffold | **Archive** | No implementation. Storage concerns are typically handled by Laravel's filesystem or by the assets package. | Move to `archived/` directory. |
| **prophoto-tenancy** | Empty scaffold | **Archive** | **Zero code. Tenancy is already implemented in prophoto-access** (Studio model, Organization model, OrganizationDocument, tenant-scoped middleware). Same overlap problem as prophoto-permissions. | Move to `archived/` directory. Add note to access README. |

---

## 3. Archive Now (10 packages)

These packages should be moved to `archived/` immediately. They contain no meaningful code and their existence creates confusion about domain ownership.

1. **prophoto-audit** — empty scaffold
2. **prophoto-downloads** — empty scaffold
3. **prophoto-payments** — empty scaffold
4. **prophoto-permissions** — empty scaffold, duplicates prophoto-access
5. **prophoto-security** — empty scaffold
6. **prophoto-settings** — empty scaffold
7. **prophoto-storage** — empty scaffold, overlaps assets/Laravel filesystem
8. **prophoto-tenancy** — empty scaffold, duplicates prophoto-access

**Total files affected:** Minimal. Each is just composer.json + README (+ possibly a bare ServiceProvider).

**Git strategy:** `git mv prophoto-{name} archived/prophoto-{name}` for each. Single commit.

---

## 4. README Rewrites (2 packages)

### prophoto-access
The README should explicitly declare that this package owns:
- **Permissions:** All RBAC logic, Spatie integration, PermissionContext, 58+ permissions, 4 user roles
- **Tenancy:** Studio model, Organization model, OrganizationDocument, tenant-scoped resolution
- **Admin:** Filament admin resources for user/role/permission management

This prevents future developers from creating new permissions or tenancy packages.

### prophoto-debug
The README should note:
- Current state: wired to legacy ingest events (no longer emitted)
- Required: rebuild to consume new ingest event contracts from prophoto-contracts
- Status: **not functional until rebuilt**, but architecture is sound

---

## 5. Architectural Redesign Needed (1 package)

### prophoto-debug → Rebuild

**Problem:** The debug package's event listeners reference legacy ingest event classes that no longer exist after the ingest rebuild. The Filament pages and artisan commands may still render but won't capture any pipeline trace data.

**Redesign approach:**
1. Update event listeners to consume the new event contracts defined in prophoto-contracts
2. Verify Filament admin pages render correctly with new event payloads
3. Add integration test proving end-to-end trace capture through the new ingest pipeline
4. Consider renaming to `prophoto-devtools` if scope expands beyond ingest tracing

**Priority:** Medium. Not blocking any current work, but will be needed once the ingest pipeline is in production and you need to debug matching decisions.

---

## 6. Recommended Package Roadmap

### Phase 1 — Cleanup (now)
- Archive 10 empty scaffolds
- Rewrite prophoto-access and prophoto-debug READMEs
- Run `scripts/cleanup.sh` from Mac terminal to complete pending repo cleanup

### Phase 2 — Consolidation (next sprint)
- Review prophoto-interactions: does ImageInteraction belong in gallery or assets?
- Review prophoto-notifications: standalone or merge into a communications concern?
- Review prophoto-invoicing: define scope boundary before payments features arrive

### Phase 3 — Debug Rebuild (when ingest pipeline ships)
- Rewire prophoto-debug to new ingest event contracts
- Add integration tests for pipeline tracing
- Ship as dev-only dependency

### Phase 4 — New Packages (as needed)
- When payment features are needed, scaffold `prophoto-payments` fresh using ingest-style architecture (not the archived empty stub)
- Same principle for any other new domain: scaffold from the gold-standard template, don't resurrect archived stubs

---

## Package Health Summary

```
Active & Healthy (7):     access, ai, assets, booking, contracts, ingest, intelligence
Active & Functional (1):  gallery
Minimal but Real (3):     interactions, invoicing, notifications
Needs Rebuild (1):        debug
Archive Now (8):          audit, downloads, payments, permissions, security, settings, storage, tenancy
```

**Post-cleanup package count:** 12 active (down from 20)
