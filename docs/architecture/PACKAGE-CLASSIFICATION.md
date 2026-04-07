# ProPhoto Package Classification Report

**Date:** 2026-04-06
**Method:** Evidence-based classification against core event loop
**Hard rule:** A package is ACTIVE_CORE only if removing it breaks the ingest → assets → intelligence event loop or removes canonical truth required by it.

---

## Core Loop Definition

```
prophoto-ingest  ──(SessionAssociationResolved)──►  prophoto-assets
prophoto-assets  ──(AssetSessionContextAttached)──►  prophoto-intelligence
prophoto-assets  ──(AssetReadyV1)──────────────────►  prophoto-intelligence
```

Supporting: prophoto-contracts (shared kernel), prophoto-booking (session truth consumed by ingest)

---

## 1. Classification Table

| Package | Status | Evidence | Recommended Action |
|---|---|---|---|
| **prophoto-contracts** | ACTIVE_CORE | Required by 9+ active packages. 136 PHP files import its namespace. Defines all DTOs, enums, events, and interfaces for the core loop. 17 enums, 23 DTOs, cross-domain event contracts. 8 tests. | Keep untouched. |
| **prophoto-ingest** | ACTIVE_CORE | Owns session matching pipeline (candidate generation → scoring → decision → assignment). Emits `SessionAssociationResolved` consumed by assets. Requires contracts, assets, booking. 2 migrations, 8 tests, 55 internal PHP files. | Keep untouched. |
| **prophoto-assets** | ACTIVE_CORE | Owns canonical media truth. Listens to `SessionAssociationResolved`, emits `AssetSessionContextAttached` and `AssetReadyV1`. Required by ingest, intelligence, gallery. 5 models, 5 migrations, 9 tests. Registers 7 contracts in service container. | Keep untouched. |
| **prophoto-intelligence** | ACTIVE_CORE | Owns derived intelligence orchestration. Listens to `AssetSessionContextAttached` and `AssetReadyV1`. 3 migrations, 13 tests. Complex orchestration with planner, registry, generators. | Keep untouched. |
| **prophoto-booking** | ACTIVE_CORE | Owns session/operational truth (Session, BookingRequest models). Required by ingest for session matching. 2 models, 2 migrations. Referenced by gallery (Gallery→Session relationship). | Keep untouched. **Risk:** ServiceProvider declared in composer.json but file does not exist. No tests. Needs attention but not archival. |
| **prophoto-access** | ACTIVE_SECONDARY | Owns Studio, Organization, PermissionContext, OrganizationDocument models. 6 migrations. Full Spatie RBAC integration with 58+ permissions, 4 user roles, Filament admin. **Externally used by:** booking (Session/BookingRequest reference Studio/Org), gallery (14 files import Permissions/UserRole/Studio/Org), invoicing (Invoice references Studio/Org), notifications (Message references Studio). | Keep in place. Document role as the owner of identity, tenancy, and RBAC. |
| **prophoto-gallery** | ACTIVE_SECONDARY | 9 models, 14 migrations, full Filament admin, routes, controllers, policies. Requires contracts, access, assets. **Externally used by:** ai (AiGeneration→Gallery), interactions (ImageInteraction→Image), notifications (Message→Gallery/Image), booking (Session→Gallery). | Keep in place. |
| **prophoto-ai** | ACTIVE_SECONDARY | 3 models, 3 migrations. Cross-references gallery (AiGeneration→Gallery, Gallery→AiGeneration). ServiceProvider declared but not implemented. | Keep in place. Note: ServiceProvider stub needs implementation if this package is to be loaded. |
| **prophoto-interactions** | ACTIVE_SECONDARY | 1 model (ImageInteraction), 1 migration. Bidirectional relationship: ImageInteraction→Image and Image→ImageInteraction. ServiceProvider declared but not implemented. | Keep in place. Review whether it should merge into gallery long-term. |
| **prophoto-invoicing** | ACTIVE_SECONDARY | 3 models (Invoice, InvoiceItem, CustomFee), 3 migrations, 1 policy. References Access (Studio/Org). ServiceProvider declared but not implemented. Composer requires `prophoto/tenancy` (dead reference — no code actually uses ProPhoto\Tenancy). | Keep in place. **Action:** Remove `prophoto/tenancy` from composer.json — it's a dead dependency. |
| **prophoto-notifications** | ACTIVE_SECONDARY | 1 model (Message), 1 migration. References Access (Studio) and Gallery (Gallery/Image). ServiceProvider declared but not implemented. Composer requires `prophoto/tenancy` (dead reference). | Keep in place. **Action:** Remove `prophoto/tenancy` from composer.json. |
| **prophoto-audit** | UNUSED | Zero external references. Not required in any composer.json. No PHP namespace imports outside itself. Empty scaffold (composer.json + README only). | ARCHIVE CANDIDATE. Already copied to `_archived/` and spec preserved in `docs/architecture/future/audit.md`. |
| **prophoto-downloads** | UNUSED | Zero external references. Not required in any composer.json. No PHP namespace imports outside itself. Empty scaffold. | ARCHIVE CANDIDATE. Already copied to `_archived/` and spec preserved in `docs/architecture/future/downloads.md`. |
| **prophoto-payments** | UNUSED | Zero external references. Not required in any composer.json. No PHP namespace imports outside itself. Empty scaffold. | ARCHIVE CANDIDATE. Already copied to `_archived/` and spec preserved in `docs/architecture/future/payments.md`. |
| **prophoto-permissions** | UNUSED | Zero external references. Not required in any composer.json. No PHP namespace imports. Empty scaffold. All declared responsibilities already implemented by prophoto-access. | ARCHIVE CANDIDATE. Already in `_archived/`. |
| **prophoto-security** | UNUSED | Zero external references. Not required in any composer.json. No PHP namespace imports. Empty scaffold. | ARCHIVE CANDIDATE. Already in `_archived/`. |
| **prophoto-settings** | UNUSED | Zero external references. Not required in any composer.json. No PHP namespace imports. Empty scaffold. | ARCHIVE CANDIDATE. Already in `_archived/`. |
| **prophoto-storage** | UNUSED | Zero external references. Not required in any composer.json. No PHP namespace imports. Empty scaffold. | ARCHIVE CANDIDATE. Already in `_archived/`. |
| **prophoto-tenancy** | UNUSED | Listed in composer.json of 3 packages (booking, invoicing, notifications) BUT zero PHP files import `ProPhoto\Tenancy\` anywhere in the repo. The composer dependency is declared but never consumed in code. Empty scaffold. Studio/Organization models already live in prophoto-access. | ARCHIVE CANDIDATE. Already in `_archived/`. **Action required:** Remove dead `prophoto/tenancy` dependency from booking, invoicing, and notifications composer.json files. |
| **prophoto-debug** | UNKNOWN | Has real implementation (32 files — models, listeners, Filament pages, artisan commands, config, migrations). Referenced in `scripts/prophoto.php` as a bootstrap package (line 51). BUT: depends on legacy ingest events that no longer exist after ingest rebuild. Not required by any other package's composer.json. No PHP namespace imports outside itself and the script. | NEEDS REVIEW. Do not move. Already copied to `_archived/` but original should stay until rebuild decision is made. **Risk:** Removing it will break `scripts/prophoto.php`. |

---

## 2. Archive Candidates (confirmed UNUSED)

These packages have zero external references in code and can be safely removed from the active root:

1. `prophoto-audit`
2. `prophoto-downloads`
3. `prophoto-payments`
4. `prophoto-permissions`
5. `prophoto-security`
6. `prophoto-settings`
7. `prophoto-storage`
8. `prophoto-tenancy`

All 8 have already been copied to `_archived/` and their READMEs preserved as feature specs in `docs/architecture/future/`.

**To finalize the archive**, run from your Mac terminal:
```bash
cd ~/Sites/prophoto
for pkg in audit downloads payments permissions security settings storage tenancy; do
  rm -rf prophoto-$pkg
done
git add -A
git commit -m "Remove 8 unused scaffold packages from root (archived in _archived/)"
```

---

## 3. Packages Flagged UNKNOWN

### prophoto-debug

**Why UNKNOWN instead of UNUSED:**
- It has 32 real files with working code (models, listeners, Filament pages, artisan commands)
- It is referenced in `scripts/prophoto.php` line 51 as a bootstrap package
- Removing it from the root will break that script

**Why not ACTIVE:**
- Its event listeners consume legacy ingest events that no longer exist
- No other package requires it in composer.json
- No PHP files outside the package import its namespace (only the bootstrap script references it by package name)

**Recommended path:** Keep at root until you decide whether to rebuild or archive. If archived, also update `scripts/prophoto.php` to remove it from `$bootstrapPackages`.

---

## 4. Risks

### Dead `prophoto/tenancy` Dependency (HIGH)
Three active packages declare `"prophoto/tenancy": "@dev"` in their composer.json:
- `prophoto-booking/composer.json`
- `prophoto-invoicing/composer.json`
- `prophoto-notifications/composer.json`

**No code actually uses `ProPhoto\Tenancy\` anywhere.** This is a dead dependency that will cause `composer install` to fail if `prophoto-tenancy` is removed from the root without cleaning these references first. **Must remove before archiving prophoto-tenancy from root.**

### Missing ServiceProviders (MEDIUM)
Four active secondary packages declare ServiceProviders in composer.json that do not exist as files:
- `prophoto-ai` → `ProPhoto\AI\AIServiceProvider` (missing)
- `prophoto-interactions` → `ProPhoto\Interactions\InteractionsServiceProvider` (missing)
- `prophoto-invoicing` → `ProPhoto\Invoicing\InvoicingServiceProvider` (missing)
- `prophoto-notifications` → `ProPhoto\Notifications\NotificationsServiceProvider` (missing)

These won't cause crashes (Laravel gracefully handles missing auto-discovered providers in dev), but they should be either implemented or removed from composer.json.

### Missing BookingServiceProvider (MEDIUM)
`prophoto-booking` is ACTIVE_CORE but its ServiceProvider is declared in composer.json and does not exist. This is more concerning than the secondary packages because booking is part of the core loop — ingest depends on it.

### prophoto-debug in Bootstrap Script (LOW)
`scripts/prophoto.php` lists `prophoto/debug` as a bootstrap package. If debug is removed from root, this script breaks. The script itself may or may not be in active use.

### Bidirectional Gallery ↔ AI Coupling (LOW)
`prophoto-gallery` imports from `prophoto-ai` and vice versa. This creates a circular dependency that will need untangling if either package's scope changes. Not urgent but worth noting.

---

## Summary

```
ACTIVE_CORE (5):        contracts, ingest, assets, intelligence, booking
ACTIVE_SECONDARY (6):   access, gallery, ai, interactions, invoicing, notifications
UNUSED (8):             audit, downloads, payments, permissions, security, settings, storage, tenancy
UNKNOWN (1):            debug
```

**Post-cleanup active count:** 11 packages (+ debug pending review)
