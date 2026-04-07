# ProPhoto Access

## Purpose

Identity, tenancy, and role-based access control for the ProPhoto system. This package owns the Studio and Organization models (the tenancy boundary), user role definitions, contextual permission enforcement, and the Filament admin UI for managing roles and permissions. It does not contain domain logic — it provides the identity and authorization layer that all other packages depend on.

## Responsibilities

- Studio model (top-level tenant) and Organization model (sub-tenant)
- OrganizationDocument model (documents attached to organizations)
- PermissionContext model (resource-scoped permission grants with optional expiry)
- User RBAC columns and the `HasContextualPermissions` trait
- Four user roles: `studio_user`, `client_user`, `guest_user`, `vendor_user`
- 58+ permission constants defined in `Permissions.php`
- `CheckContextualPermission` middleware for route-level enforcement
- Filament plugin: permission matrix page, role resource, permission resource
- `RolesAndPermissionsSeeder` for bootstrapping roles and permissions

## Non-Responsibilities

- Does NOT own domain models (galleries, bookings, invoices, images, AI generations)
- Does NOT define policies for domain models — policies live in their respective packages and reference `Permissions` constants from here
- Does NOT own session or booking truth (that is prophoto-booking)
- Does NOT own asset truth (that is prophoto-assets)
- Does NOT participate in the ingest → assets → intelligence event loop
- Does NOT perform direct queries against other packages' tables

## Integration Points

- **Events listened to:** None
- **Events emitted:** None currently
- **Contracts depended on:** None (this package is a dependency root — others depend on it)
- **Consumed by:** prophoto-gallery, prophoto-booking, prophoto-invoicing, prophoto-notifications, prophoto-ai (all import models, roles, or permissions from this package)

## Data Ownership

| Table | Model | Purpose |
|---|---|---|
| `studios` | Studio | Top-level tenant |
| `organizations` | Organization | Client organizations within a studio |
| `organization_documents` | OrganizationDocument | Documents attached to organizations |
| `organization_user` | (pivot) | User-organization membership |
| `permission_contexts` | PermissionContext | Resource-scoped permission grants |
| `users` (columns only) | — | Adds RBAC columns via migration |

This package also depends on Spatie's `roles`, `permissions`, `model_has_roles`, and `model_has_permissions` tables.

## Notes

- This package consolidates what was originally split across prophoto-permissions and prophoto-tenancy (both now archived as empty scaffolds)
- Policies referencing domain models should live in those domain packages, not here
- All permission constants are string-based and defined centrally in `Permissions.php` — new permissions must be added here
- ServiceProvider: `ProPhoto\Access\AccessServiceProvider` (auto-discovered)
