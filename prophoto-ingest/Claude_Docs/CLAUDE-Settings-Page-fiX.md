
⚠️ **This settings page was partially implemented and contains many inappropriate or unsafe end-user settings. We are cleaning it up, moving some settings to .env, deleting unused settings, and fixing broken ones.**

⚠️ **This area is extremely intertwined with ingestion logic, chart rendering logic, metadata extraction, and runtime config overrides. You must not modify or delete any business logic. Only change the user-facing settings system.**

Your modifications MUST be **strictly scoped** to the settings system files listed below.

---

# 🔒 CRITICAL SAFETY RULES (DO NOT VIOLATE)

These rules override every other instruction:

1. **Do NOT modify or delete any business logic.**
   This includes:

    * ingestion workflows
    * file movement
    * directory/path building
    * metadata extraction
    * thumbnail generation
    * chart JSON computation
    * dashboard chart rendering
    * storage operations
    * service provider logic unrelated to loading DB settings

2. **Only modify the files whose primary purpose is settings UI, validation, persistence, or config defaults.**
   Allowed files include:

    * `resources/js/Pages/Ingest/Settings.tsx`
    * `app/Http/Controllers/IngestSettingsController.php`
    * `app/Services/IngestSettingsService.php`
    * `app/Models/IngestSetting.php`
    * `config/ingest.php`
    * new helper: `app/Services/MetadataKeyService.php` (if needed)

3. **Assume deleted settings may be shallow placeholders.**
   Do NOT remove deeper code that consumes the same config keys.
   Delete ONLY:

    * UI fields
    * validation rules
    * persistence
    * form data
      Keep the rest of the system intact.

4. **When modifying Chart Fields**, ONLY change the UI and the setting itself.
   Do NOT touch chart calculation code.

5. **When moving settings to ENV**, do NOT delete or rewrite the ingestion logic that uses them.
   Only:

    * remove them from the UI
    * update `config/ingest.php` to read them from `env()`
    * delete their validation + DB persistence

6. **If unsure whether code is business logic, do NOT modify it.**
   Err on the side of preserving all runtime behavior.

---

# 🧭 HIGH-LEVEL GOAL (FULL CONTEXT)

This settings page was originally built to expose internal package config, but this was a mistake. End users should NOT control storage disks, route prefixes, middleware, image processing, thumbnails, or preview settings. Many fields are half-implemented or unused.
We are now performing a cleanup:

* Keep only meaningful end-user ingestion settings
* Move developer-level settings to `.env`
* Remove leftover scaffolding
* Fix the parts that matter (Chart Fields, placeholders)
* Do not break the ingestion system

---

# 🗑 DELETE COMPLETELY (UI + validation + persistence ONLY)

Delete these settings entirely from:

* the React form
* the Tabs/Triggers
* the controller validation
* the flattening logic
* settings stored in DB
* the settings service
  Do NOT delete unrelated config or runtime logic.

Delete:

* Queue Status
* Queue Connection
* Storage Link Status
* `public/storage` symlink indicators
* ALL Image Processing settings
* ALL Thumbnail Settings
* ALL Preview Settings
* Association Models
* Disk driver/disk selector UI
* `core.middleware`

---

# 🔧 MOVE TO ENV (REMOVE FROM UI + DB; KEEP RUNTIME BEHAVIOR)

These settings are developer-only and should not appear in the UI.

Remove them from:

* UI
* validation
* flattened persistence
* database

Add or update env-based config overrides in `config/ingest.php`:

* `route_prefix` → `INGEST_ROUTE_PREFIX`
* `storage.temp_disk` → `INGEST_TEMP_DISK`
* `storage.final_disk` → `INGEST_FINAL_DISK`
* `storage.paths.*` → `INGEST_STORAGE_PATH_*` (or nested arrays)

Appearance is NOT user-facing. Remove appearance UI and use env variables:

* `INGEST_THEME`
* `INGEST_RADIUS`

**Do not remove any code that reads these config values at runtime.**

---

# 🟢 KEEP IN DB + KEEP IN UI

These are the **only** settings that should remain user-editable:

* Quick Tags
* File Schema:

    * directory pattern
    * filename pattern
    * sequence_padding
* Available Placeholders (these are generated, not typed)
* Metadata-based placeholder expansion
* Chart Fields (but must be fixed)

---

# 🛠 FIX / MODIFY (SAFE & TARGETED)

## 1. Chart Fields → Replace text input with dropdown

* Create a dropdown populated from metadata JSON keys in the `metadata` column
* Add new DB key: `metadata.default_chart_field`
* Update validation + flattening for this single setting
* Do NOT modify chart generation code

## 2. Placeholder Expansion

* Add a `MetadataKeyService` to scan JSON metadata keys
* Use it to generate dynamic placeholder lists
* Show these placeholders in UI
* Do NOT modify ingestion filename logic, except to read the chosen schema/pattern as usual

## 3. Remove Appearance Tab

* Remove the entire tab + fields
* Move to ENV
* Keep default theme loaders intact

---

# 📁 FILES TO MODIFY (AND ONLY THESE)

You may edit:

* `resources/js/Pages/Ingest/Settings.tsx`
* `app/Http/Controllers/IngestSettingsController.php`
* `app/Services/IngestSettingsService.php`
* `app/Models/IngestSetting.php`
* `config/ingest.php`
* optionally create: `app/Services/MetadataKeyService.php`

DO NOT edit:

* ingest pipeline
* chart JSON builders
* metadata extractors
* file upload handlers
* migration files
* any service provider logic besides reading config defaults

---

# 🎯 FINAL REQUIRED OUTPUT

Produce actual multi-file diffs in patch format, touching ONLY the allowed settings-system files.
Output:

1. UI updates (Settings.tsx)
2. Updated validation + flattening (IngestSettingsController)
3. Updated persistence logic (Service + Model as needed)
4. New ENV-driven config file updates (`config/ingest.php`)
5. Optional new MetadataKeyService
6. Final list of DB-backed settings that remain after cleanup

Follow all safety rules. Preserve all business logic. Perform a surgical refactor of the settings system only.
