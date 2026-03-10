
Your job is to formalize, automate, and document this workspace so that:

 • humans and AI agents can understand the system quickly
 • sandbox resets are deterministic and one-command
 • Composer path repositories are the single source of truth
 • packages remain independently releasable
 • integration testing is reliable and repeatable

# Make one canonical “workspace” repo

 • Treat the current root (where sandbox/ and prophoto-* live) as the workspace.
Add 3 top-level docs that give instant context:

* SYSTEM.md — what each package is, and how they connect
* DEV.md — how to bootstrap, reset sandbox, run tests
* DEPENDENCIES.md — shared stuff (ExifTool, queues, storage conventions, etc.)

## Normalize package hygiene (reduce noise, speed agents)

Stop committing/installing vendor/ and node_modules/ inside packages

 • In each prophoto-* package repo:
 • Ensure .gitignore excludes vendor/ and node_modules/
 • Remove committed vendor/ / node_modules/ if present
 • Keep vendor/ + node_modules/ only where they belong (sandbox and/or tooling), not in every package.

 Rule:
 • Only the sandbox app needs a vendor/ and node_modules/ for day-to-day dev.
 • Packages should be “clean source repos” + tests.

So:
 • add vendor/ and node_modules/ to every package .gitignore
 • delete them from package repos (and probably from this workspace copy too)
 • install dependencies through the sandbox (and for package tests via scripts)


## Keep sandbox wired via Composer path repositories + symlinks

 • Preserve the existing sandbox behavior: repositories: [ {type:path, url: ../prophoto-*, options: {symlink:true}} ]
 • Ensure the required package constraints stay on dev-main / @dev as you already do.  ￼

## **repositories (with symlinks)**

This is the money move. In sandbox/composer.json:

```
"repositories": [
  {
    "type": "path",
    "url": "../prophoto-*",
    "options": { "symlink": true }
  }
]
```

Then in sandbox you require packages normally:

```
cd sandbox
composer require prophoto/ingest:dev-main prophoto/access:dev-main prophoto/galleries:dev-main
```

If you already require them, great — just ensure it’s using the **path repo** and not pulling from Packagist/VCS.

Why this works:

* edits in prophoto-ingest/src instantly reflect in sandbox
* the sandbox is just a consumer app; packages remain real packages

## Create the single entrypoint: ./scripts/prophoto (Laravel Prompts UI)

 • Implement a master CLI (scripts/prophoto.php + optional bash launcher scripts/prophoto) that is the only command you run manually.
 • It provides:
 • interactive menu (Laravel Prompts)
 • named workflows (non-interactive flags)
 • dry-run mode
 • clear PASS/FAIL summary at the end

## Consolidate all operational scripts into the master CLI (no duplicates)

 • Migrate these into prophoto.php as subcommands / workflows and delete the standalone scripts after parity:
 • doctor (env/tooling/path-repo checks)
 • test (package tests + sandbox smoke tests)
 • sandbox:fresh (delete + recreate deterministically)
 • sandbox:reset (keep folder, clear deps/caches, reinstall)
 • refresh (your daily cache clears + assets + installs)
 • If you want to keep any old shell scripts temporarily, mark them “internal” and do not document them—then remove once stable.

## Add “Doctor” checks that remove agent guessing

Inside prophoto doctor, check and report PASS/FAIL:
 • PHP / Composer / Node versions
 • exiftool presence (if ingest depends on it)
 • sandbox composer.json still contains path repos + symlink:true
 • sandbox is resolving local packages (not remote)
 • no vendor/ / node_modules/ inside package repos (optionally offer cleanup)

## Add deterministic sandbox creation/reset flows

Inside prophoto sandbox:fresh:
 • confirm destructive action
 • recreate sandbox in a repeatable way
 • apply .env template
 • ensure path repos present
 • require local packages
 • migrate
 • npm install/build (optional prompt)

Inside prophoto sandbox:reset:
 • wipe vendor/node_modules/caches
 • reinstall and rebuild
 • migrate if needed

## Standardize cross-package sharing (prevent drift) Best practice:

* create prophoto-contracts (interfaces, DTOs, event names, enums)
* optionally prophoto-support (shared helpers)
* make the other packages depend on it

This reduces circular dependency chaos and gives agents one obvious “shared layer.”

 • If you see duplicated shared concepts, create a single shared layer:
 • prophoto-contracts (interfaces/DTOs/events/enums)
 • optionally prophoto-support (helpers)
 • Update packages to depend on it instead of copy/paste or nested “foundation” code.

 Testing strategy: two layers, one command
 • Package-level tests (Testbench) per package
 • Sandbox integration smoke tests (minimal, real cross-package validation)
 • Run all via prophoto test (one command)

 Only one documented way to work
 • In docs/DEV.md, document only:
 • ./scripts/prophoto (interactive)
 • ./scripts/prophoto doctor
 • ./scripts/prophoto sandbox:fresh
 • ./scripts/prophoto sandbox:reset
 • ./scripts/prophoto test
 • ./scripts/prophoto refresh (daily)

Everything else becomes implementation detail.



Below is the rewritten Claude Code agent prompt, tightened and explicit, that forces the agent to:
 1. port every existing script into prophoto.php
 2. verify functional parity
 3. delete the old scripts in the same PR

No ambiguity, no duplicates, no “we’ll clean it up later”.

You can paste this verbatim into Claude Code.

⸻

Claude Code Prompt — ProPhoto Master CLI (Consolidation & De-duplication)

ROLE

You are a senior Laravel + Composer workspace architect.

You are working in a workspace repo that contains:
 • multiple local Laravel Composer packages (prophoto-*)
 • a disposable Laravel sandbox app (sandbox/)
 • a /scripts directory containing several operational shell scripts

Your task is to consolidate all operational scripts into a single master CLI entrypoint that uses Laravel Prompts, and to remove the legacy scripts once parity is confirmed.

⸻

🚫 ABSOLUTE RULE (READ FIRST)

There must be exactly ONE user-facing command when you finish:

./scripts/prophoto

All other operational scripts must be deleted in the same PR once their logic has been ported and verified.

No duplicates.
No parallel tools.
No “deprecated but still present” scripts.

⸻

🎯 OBJECTIVES (IN ORDER)

1) Create the master CLI

Implement:
 • scripts/prophoto.php (PHP CLI using Laravel Prompts)
 • optional launcher scripts/prophoto (bash → php)

This is the only command a human or agent should run.

⸻

2) Inventory existing scripts

Before writing code, scan /scripts and identify all existing operational scripts, including (but not limited to):
 • rebuild.sh
 • doctor.sh
 • test.sh
 • sandbox-reset.sh
 • sandbox-fresh.sh
 • any package install / uninstall helpers

Create an internal checklist mapping:

OLD SCRIPT  →  NEW prophoto ACTION

You must not miss any script.

⸻

3) Port logic into prophoto.php (no regressions)

For each existing script:
 • Extract its logic
 • Implement it as a named action or workflow inside prophoto.php
 • Preserve behavior exactly unless a bug is obvious (document any fix)

Examples:
 • doctor.sh → prophoto doctor
 • sandbox-fresh.sh → prophoto sandbox:fresh
 • sandbox-reset.sh → prophoto sandbox:reset
 • rebuild.sh → prophoto refresh or prophoto rebuild
 • test.sh → prophoto test

All actions must be selectable via:
 • interactive menu (Laravel Prompts)
 • non-interactive CLI flags

⸻

4) Verify parity (MANDATORY)

Before deleting any script, you must prove parity.

For each old script:
 • Run the old script
 • Run the new prophoto action
 • Confirm:
 • same side effects
 • same files touched
 • same commands executed (or documented improvements)

If output differs, explain why in code comments.

⸻

5) Delete legacy scripts (same PR)

Once parity is confirmed:
 • DELETE the old scripts from /scripts
 • Remove any documentation references to them
 • Update docs/DEV.md to reference only ./scripts/prophoto

If a script is not deleted, the task is incomplete.

⸻

🧠 CLI UX REQUIREMENTS (Laravel Prompts)

The master CLI must provide:

Main menu
 • Daily Refresh (fast)
 • Full Rebuild (slow)
 • Sandbox → Fresh
 • Sandbox → Reset
 • Run Tests
 • Doctor / Diagnostics
 • Exit

Prompts must include
 • confirmations before destructive actions
 • progress/status output
 • clear success/failure messages
 • optional --dry-run
 • optional --non-interactive <action>

⸻

🩺 DOCTOR CHECKS (REQUIRED)

Inside prophoto doctor, verify and report PASS/FAIL:
 • PHP version
 • Composer version
 • Node version
 • required system tools (e.g. exiftool)
 • sandbox composer.json contains path repositories + symlink:true
 • sandbox resolves local packages (not remote)
 • no vendor/ or node_modules/ inside prophoto-* packages

Offer to auto-fix where safe.

⸻

🧱 SANDBOX FLOWS (REQUIRED)

prophoto sandbox:fresh
 • confirm destructive action
 • delete sandbox
 • recreate deterministically
 • apply .env template
 • enforce composer path repos
 • require local packages
 • migrate
 • npm install/build (prompted)

prophoto sandbox:reset
 • keep sandbox
 • remove vendor/node_modules/caches
 • reinstall
 • rebuild assets
 • migrate if needed

⸻

🧪 TESTING

Implement:
 • package-level tests (Testbench)
 • sandbox integration smoke tests
 • unified runner: prophoto test

⸻

📄 DOCUMENTATION UPDATE (REQUIRED)

Update or create:
 • docs/DEV.md
 • document only ./scripts/prophoto
 • include example commands
 • explicitly state legacy scripts were removed

⸻

✅ FINAL CHECKLIST (MUST PASS)

Before stopping, verify:
 • scripts/prophoto.php exists and works
 • All old scripts are fully ported
 • Parity verified for each old script
 • Old scripts deleted in same PR
 • No duplicate functionality remains
 • docs/DEV.md references only prophoto
 • A new agent can onboard using one command

⸻

🧠 GUIDING PRINCIPLE

One workspace.
One command.
Zero duplicates.

Contracts Principle

Yep. The “contracts principle” is the thing that keeps a multi-package Laravel system from turning into a spaghetti dependency graph.

What “contracts” means

A contracts layer is a small, stable package that contains only the shared “shape” of the system:
 • Interfaces (what services must do)
 • DTOs / Value Objects (what data looks like crossing package boundaries)
 • Enums / constants (event names, abilities, job names, tag keys, etc.)
 • Event payload classes (if you use events to integrate packages)

It does not contain real business logic, database models, controllers, migrations, or HTTP routes.

Think of it as: “We agree the world looks like this.”

Why it matters in your setup (10 packages)

Without contracts, you end up with:
 • duplicated classes (“UploadStatus”, “GalleryVisibility”, etc.)
 • circular dependencies (ingest needs galleries, galleries needs access, access needs ingest)
 • breaking changes because one package “reaches into” another’s internals
 • agents getting confused because boundaries aren’t explicit

With contracts:
 • packages depend inward on a stable core
 • integration happens via interfaces + typed messages
 • you can swap implementations without rewriting everything

The dependency rule

The key principle:

Domain packages may depend on prophoto-contracts, but prophoto-contracts depends on nothing in your domain packages.

So dependency arrows look like:

prophoto-ingest   ┐
prophoto-galleries├──> prophoto-contracts
prophoto-access   ┘

Never the other way around.

What belongs in prophoto-contracts

Here’s a concrete list that maps to the kind of stuff you’re building (ingest/gallery/access):

1) Interfaces (service boundaries)

Examples:
 • IngestServiceContract (create job, ingest file, extract metadata)
 • GalleryRepositoryContract (create gallery, attach asset, list assets)
 • AccessPolicyContract / PermissionCheckerContract
 • AssetStorageContract (store/retrieve originals/derivatives)
 • MetadataReaderContract (ExifTool vs alternate implementation)

Why: packages stop calling each other’s concrete classes.

2) DTOs / Value Objects (data across boundaries)

Examples:
 • AssetId, GalleryId (typed IDs)
 • IngestRequest (file path, source, user, options)
 • IngestResult (asset id, derivative paths, extracted metadata summary)
 • AssetMetadata (normalized metadata shape)
 • PermissionDecision (allowed/denied + reason)

Why: you stop passing loose arrays everywhere.

3) Enums / constants (shared vocabulary)

Examples:
 • AssetType (RAW/JPEG/HEIC/VIDEO)
 • DerivativeType (thumb/preview/web/original)
 • IngestStatus (queued/processing/complete/failed)
 • Ability (view_gallery, upload_asset, delete_asset)
 • EventNames (AssetIngested, GalleryCreated…)

Why: packages stop inventing strings that drift.

4) Events (integration hooks)

You can either:
 • publish event name constants only, or
 • publish event classes like AssetIngested that carry a DTO payload

Either is fine. If you choose classes, keep them simple and stable.

5) Exceptions (only shared ones)

Examples:
 • AssetNotFound
 • PermissionDenied
 • MetadataReadFailed

Why: consistent error handling across packages.

What must NOT be in contracts

If you put these in contracts, you’ll regret it:
 • Eloquent models
 • migrations
 • controllers/routes
 • service providers with real registrations
 • database table names tied to one package’s schema
 • implementation code (ExifTool invocation, file IO, policies, etc.)

Contracts should be boring.

How packages should use contracts (Laravel-friendly)

Pattern A: Interface + service provider binding

In prophoto-ingest:
 • implement MetadataReaderContract with ExiftoolMetadataReader
 • bind it in the package provider:

$this->app->bind(
  \Prophoto\Contracts\Metadata\MetadataReaderContract::class,
  \Prophoto\Ingest\Metadata\ExiftoolMetadataReader::class
);

In prophoto-galleries:
 • you type-hint the contract, not the class.

Pattern B: Events for loose coupling

prophoto-ingest emits AssetIngested (from contracts), prophoto-galleries listens.
Now galleries doesn’t need to import ingest internals.

Pattern C: “Support” package is separate

If you have shared helpers that are not strictly “shape” (string helpers, filesystem helpers), put them in:
 • prophoto-support

Keep contracts clean.

Versioning strategy (how to avoid breaking everything)

Treat prophoto-contracts as slow-moving:
 • avoid breaking changes
 • when you must break, bump major and migrate packages deliberately
 • keep contracts small to reduce churn

A practical folder layout for contracts

```bash
prophoto-contracts/
  src/
    Contracts/
      Ingest/
        IngestServiceContract.php
        MetadataReaderContract.php
      Gallery/
        GalleryRepositoryContract.php
      Access/
        AccessPolicyContract.php
        PermissionCheckerContract.php
      Asset/
        AssetStorageContract.php
      Metadata/
        AssetMetadata.php
        IngestResult.php
      Events/
        AssetIngested.php
    DTOs/
      AssetId.php
      GalleryId.php
      IngestRequest.php
    Enums/
      AssetType.php
      DerivativeType.php
      IngestStatus.php
      Ability.php
    Exceptions/
      AssetNotFound.php
      PermissionDenied.php
      MetadataReadFailed.php
```

The “contract test” (quick sanity check)

When you’re wondering “does this go in contracts?” ask:
 1. Would two packages need to agree on this exact shape?
 2. Can I imagine swapping the implementation without changing dependents?
 3. Would this stay stable for months?

If yes → contracts.
If it’s “how we do it” not “what it is” → not contracts.

⸻

If you tell me the top 5 cross-package touchpoints you currently have (example: “ingest creates assets that galleries shows; access controls gallery routes”), I’ll propose an exact prophoto-contracts v0.1 interface/DTO/event list tailored to your system.