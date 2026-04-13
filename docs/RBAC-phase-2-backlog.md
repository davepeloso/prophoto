# RBAC Phase 2 Backlog

**Status:** Deferred — not blocking Phase 2 (Proofing) or Phase 3 (Invoicing)
**Created:** April 12, 2026
**Context:** Items captured during Phase 2 planning that are genuine future needs but would
add scope without adding value to proofing or delivery. Address as a dedicated sprint
once Phase 2 is live and there is real usage data to validate priorities.

---

## 1. System Admin Role (`system_admin`)

**What:** A new `UserRole` case that operates above `studio_user` — crosses studio
boundaries, can impersonate users for debugging, has billing-level access across all
studios in the system.

**Why not now:** There is currently one studio (yours). A cross-studio role has no surface
area to act on yet. Building it now would be speculative.

**When to build:** When the system has more than one studio, or when you need to
debug/support a client account remotely without logging in as them.

**What's already in place:**
- `Permissions::VIEW_ALL_DATA` constant exists — this is the key permission
- `UserRole` enum just needs a `SYSTEM_ADMIN` case added
- Spatie's `Role::create(['name' => 'system_admin'])` + `givePermissionTo(Permission::all())`
  is the entire implementation for basic superuser
- Laravel Filament impersonation via `filament-shield` or `filament-user-impersonation`
  package would handle the support/debug use case

**Stub to add now (no-op, just reserves the slot):**
```php
// In UserRole enum — add but don't wire up yet
case SYSTEM_ADMIN = 'system_admin'; // Cross-studio tech admin — Phase RBAC-2
```

---

## 2. Ingest / Upload Session Permissions

**What:** Finer-grained permissions for the ingest pipeline, separate from the broad
`ACCESS_STAGING` catch-all.

**Why:** Phase 2 will surface session data to photographers in the approval dashboard.
Phase 3 will potentially let org admins view session status. Right now everything is
`studio_user` only by assumption, not by explicit permission.

**Permissions to add:**
```php
// In Permissions.php
public const VIEW_UPLOAD_SESSION   = 'can_view_upload_session';   // See session status/progress
public const MANAGE_UPLOAD_SESSION = 'can_manage_upload_session'; // Confirm/cancel sessions
public const VIEW_INGEST_FILES     = 'can_view_ingest_files';     // See individual file status
```

**Matrix:**
| Permission | studio_user | client_user | guest_user |
|---|---|---|---|
| `can_view_upload_session` | ✅ | ❌ (Phase 3 consideration) | ❌ |
| `can_manage_upload_session` | ✅ | ❌ | ❌ |
| `can_view_ingest_files` | ✅ | ❌ | ❌ |

---

## 3. Asset-Layer Permissions

**What:** Permissions that operate on `Asset` and `AssetDerivative` records directly,
distinct from gallery-level image permissions.

**Why:** The image spine introduced `Asset` (canonical file) vs `Image` (gallery record)
as separate concepts. `can_upload_images` and `can_delete_images` currently operate at
the gallery layer. There is no permission governing who can:
- Delete an asset entirely (vs. removing it from one gallery)
- Reprocess/regenerate derivatives
- Access the raw original file vs. a web-resolution derivative

**Permissions to add:**
```php
public const MANAGE_ASSETS         = 'can_manage_assets';         // Reprocess, regenerate derivatives
public const DELETE_ASSET          = 'can_delete_asset';          // Permanent asset deletion (destructive)
public const ACCESS_ORIGINAL_FILE  = 'can_access_original_file';  // Download raw unprocessed original
```

**Matrix:**
| Permission | studio_user | client_user | guest_user | Notes |
|---|---|---|---|---|
| `can_manage_assets` | ✅ | ❌ | ❌ | Studio only — touches storage |
| `can_delete_asset` | ✅ | ❌ | ❌ | Separate from gallery delete — permanent |
| `can_access_original_file` | ✅ | ❌ | ❌ | Phase 3 gated on payment for clients |

---

## 4. Sub-Roles Within `client_user`: Billing Contact & Marketing Admin

**What:** The `Permissions.php` file already has `IS_BILLING_CONTACT` and
`IS_MARKETING_ADMIN` as special-role constants. These need to be wired into the `UserRole`
enum (or handled as contextual permission flags) so the system can enforce different
permission sets for different contacts within the same organization.

**Why:** A UCLA Health billing contact should see invoices but not gallery images.
A marketing admin should see and approve images but not invoices. Both are `client_user`
role today, meaning they get the full `client_user` permission set.

**Options:**
1. **Sub-roles** — `client_billing` and `client_marketing` as distinct Spatie roles,
   each with their own permission set. Clean but multiplies role count.
2. **Contextual flags** — `IS_BILLING_CONTACT` and `IS_MARKETING_ADMIN` remain as
   Spatie permissions granted contextually (per-organization), and `PermissionService`
   uses them to restrict the base `client_user` set. Fits the existing architecture better.

**Recommendation:** Option 2. The `PermissionService::getEffectivePermissions()` method
already has the org-override pattern. Extend it to check `IS_BILLING_CONTACT` and
restrict gallery permissions to zero when true, and check `IS_MARKETING_ADMIN` and
restrict invoice permissions to zero when true.

---

## 5. Project Hierarchy (Photographer-Side Organizer)

**What:** A `projects` table that lets photographers group galleries into named projects
for their own dashboard organization (e.g., "UCLA Health Spring 2026 Headshots" contains
12 individual galleries).

**Scope:** Photographer-only organizer. No client-facing implications. No RBAC changes
required. Clients and subjects never see "Projects" — they see individual galleries via
their share link as today.

**Implementation (when ready):**
```php
// New migration in prophoto-gallery
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// Add to galleries table
$table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
```

**No changes needed to:**
- `PermissionContext` or `HasContextualPermissions`
- Any client/guest-facing policies
- `GalleryShare` or proofing flow

**RBAC note:** `can_create_gallery` implicitly covers project management since projects
are studio-only. Could add explicit `can_manage_projects` if you want Filament UI to
be independently togglable, but it's not required.

---

## 6. `Library` Level (Future, Evaluate After Phase 3)

**What:** A `libraries` table above `projects` — e.g., "2026 Corporate Clients" library
contains many projects.

**Decision deferred:** Evaluate after Phase 2 and Phase 3 are live. Real usage will
show whether photographers actually need two levels of organization above gallery, or
whether `Project` alone is sufficient.

**If added:** Same pattern as Project — photographer-only, no RBAC changes, just a
`library_id` foreign key on `projects`.

---

## Summary: What to Do in the RBAC Phase 2 Sprint

When this sprint is scheduled, the work is:

1. Stub `SYSTEM_ADMIN` in `UserRole` enum (30 min)
2. Add ingest permissions to `Permissions.php` + `RolesAndPermissionsSeeder` (1 hour)
3. Add asset-layer permissions to `Permissions.php` + `RolesAndPermissionsSeeder` (1 hour)
4. Wire `IS_BILLING_CONTACT` and `IS_MARKETING_ADMIN` into `PermissionService` (2–3 hours)
5. Build `projects` table + `project_id` on `Gallery` + Filament resource (1 day)
6. Update `RolesAndPermissionsSeeder` to include all new permissions (1 hour)
7. Update `PermissionMatrix` Filament page category mappings for new permissions (1 hour)

**Estimated size:** ~2–3 days. Not a full sprint on its own — candidate for pairing
with the "RBAC wiring into sandbox" work that should happen at the start of Phase 2.
