# ProPhoto Sandbox Postman Workflow

## Purpose

This collection provides a self-loading workflow for running smoke tests against the ProPhoto sandbox API. After each reseed, replace the collection file and run — no manual variable pasting required.

It works with Laravel seed output that generates `postman-collection.json` with fresh runtime values baked directly into the `Load Sandbox Context` request body.

---

## Three Files, Three Responsibilities

| File | Responsibility | Commit? |
| :--- | :--- | :--- |
| `sandbox.json` | **Runtime reference**: human-readable seed output with fresh per-seed values | No |
| `postman-requests.json` | **Source of truth**: flat request definitions used for PR review and LLM authoring | Yes |
| `postman-collection.json` | **Import artifact**: compiled Postman collection with live values baked in | No |
        
---

## Workflow After Each Reseed

```
php artisan db:seed --class=SandboxSeeder
```

Then in Postman:

1. **Replace collection** — Collections → hover `ProPhoto API` → `···` → Replace → select `postman-collection.json`
2. **Run collection** — Runner → select `ProPhoto API` → Run

That's it. The collection is self-loading.

### What happens automatically

`00 — Setup → Load Sandbox Context` is always the first request. Its pre-request script reads the fresh values baked into its own request body and writes them all into the active Postman environment before any other request fires. By the time `01 — Smoke Tests` starts, every `{{VARIABLE}}` placeholder is already resolved.

---

## Collection Structure

### 00 — Setup
- **Load Sandbox Context** — Reads baked-in seed values, sets all env vars. Always runs first.

### 01 — Smoke Tests
Read-only, idempotent. Safe to run at any time and any number of times.

- **Check Session Progress** — Verify seeded session exists with correct shape and counts.
- **Confirm Session (idempotent)** — Verify idempotent confirm returns 200 with `already_processed: true`.
- **Check Preview Status** — Verify preview/thumbnail shape for seeded session.

### 02 — Ingest: Session Lifecycle
Full session lifecycle including state-changing requests.

- **Match Calendar (create session)** — Creates a new `initiated` session, saves ID to `SESSION_ID`.
- **Get Session Progress** — Poll progress for the current session.
- **Confirm Session (seeded)** — Confirms the seeded session (restores `SESSION_ID` from `SANDBOX_SESSION_ID` via pre-request script).
- **Confirm Session — 422 for unknown session** — Negative test, expects 422.
- **Get Preview Status** — Preview status for current session.
- **Unlink Calendar** — Remove calendar association.
- **Get Progress — 404 for unknown session** — Negative test, expects 404.

### 03 — Ingest: File Operations
- **Register Files**, **Apply Tag to File**, **Remove Tag from File**, **Batch Update Files**

### 04 — RBAC: Auth & Permissions
- **Studio user**, **Client user**, **No auth (401)**, **Invalid token (401)**

---

## Variables

All variables are set automatically by `Load Sandbox Context`. Never hardcode these in requests.

| Variable | Source | Description |
| :--- | :--- | :--- |
| `PROPHOTO_API_BASE_URL` | Seeder | Base URL for all API requests |
| `SESSION_ID` | Seeder | Current working session ID (may be overwritten by lifecycle requests) |
| `SANDBOX_SESSION_ID` | Seeder | Stable copy of seeded session ID — never overwritten |
| `GALLERY_ID` | Seeder | Seeded gallery ID |
| `STUDIO_ID` | Seeder | Seeded studio ID |
| `PROPHOTO_BEARER_TOKEN` | Seeder | Default bearer token (studio user) |
| `STUDIO_BEARER_TOKEN` | Seeder | Studio user token |
| `CLIENT_BEARER_TOKEN` | Seeder | Client user token |
| `SUBJECT_BEARER_TOKEN` | Seeder | Subject/guest user token |
| `STUDIO_EMAIL` | Seeder | Studio user email |
| `CLIENT_EMAIL` | Seeder | Client user email |
| `SUBJECT_EMAIL` | Seeder | Subject user email |

### Why two session ID variables?

Some requests in folder 02 create new sessions and overwrite `SESSION_ID` (e.g. `Match Calendar`). `SANDBOX_SESSION_ID` is a stable anchor that always holds the original seeded session — the one that's in `uploading` status and confirmable. Requests that need the seeded session restore `SESSION_ID` from `SANDBOX_SESSION_ID` via a pre-request script.

---

## Collection Request Rules

- **Environment driven** — use `{{VARIABLE}}` for all URLs, IDs, tokens. No hardcoding.
- **No manual values** — every value comes from the seeder via `Load Sandbox Context`.
- **Clear assertions** — every request has test scripts with explicit pass/fail checks.
- **Contract accuracy** — tests assert against actual response field names, not guesses.
- **Idempotent smoke tests** — folder 01 must be safe to run repeatedly without side effects.

---

## Adding New Requests

1. Add an entry to `requestDefinitions()` in `sandbox-seeder.php`
2. Use `{{VARIABLE}}` placeholders — no hardcoded values
3. Put it in the right folder (`00 — Setup`, `01 — Smoke Tests`, etc.)
4. Add pre-request and test scripts
5. Reseed: `php artisan db:seed --class=SandboxSeeder`
6. Replace the collection in Postman

Changes to `postman-requests.json` are committed to source control. `postman-collection.json` and `sandbox.json` are not.

---

## Authoring Guidance

- Prefer descriptive request names.
- Make required variables obvious in descriptions.
- Write tests that tolerate valid idempotent behavior (e.g. confirm on an already-confirmed session should pass, not fail).
- Never assume session state — use `SANDBOX_SESSION_ID` when you need the seeded session specifically.
- Treat Postman as a consumer of generated artifacts, not the primary editing surface.
