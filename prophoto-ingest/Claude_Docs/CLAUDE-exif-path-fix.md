### Fix “exiftool: not found” in a Laravel/PHP app (modeled after ImageMagick fix)

#### Goal
Diagnose and fix failures where the app executes `exiftool` but the process returns exit code 127 (`exiftool: not found`) due to PATH/environment differences between the shell and PHP runtime (PHP‑FPM/Horizon/queue workers). Make `exiftool` invocation robust, configurable, and testable.

#### Key Constraints and Lessons Learned
- The terminal PATH is not the same as the PHP runtime’s PATH (Herd, PHP‑FPM, Horizon). Do not rely on PATH being correct.
- Avoid using `env()` at runtime; use `config()` backed by a config file so config caching doesn’t break things.
- Provide two knobs in `.env`:
  - `EXIFTOOL_BIN` (absolute binary path, e.g., `/opt/local/bin/exiftool`)
  - `EXIFTOOL_PATH_PREFIX` (directory to prepend to PATH when spawning processes, e.g., `/opt/local/bin`)
- Standardize logs and add a diagnostic Artisan command to confirm the app environment can find/execute the binary.

#### Tasks
1) Configuration
- Create `config/exiftool.php`:
```
<?php
return [
    'bin' => env('EXIFTOOL_BIN', 'exiftool'),
    'path_prefix' => env('EXIFTOOL_PATH_PREFIX'),
];
```
- Ensure the app reads `config('exiftool.bin', 'exiftool')` and `config('exiftool.path_prefix')` (not `env()`) when constructing any `exiftool` processes.

2) Update all usages to be config‑driven and PATH‑augmented
- Search the codebase for invocations: strings like `exiftool`, `Process([ 'exiftool', ... ])`, or shell strings containing `exiftool`.
- Replace with:
```
$bin = config('exiftool.bin', 'exiftool');
$prefix = config('exiftool.path_prefix');
$env = null;
if (!empty($prefix)) {
    $currentPath = getenv('PATH') ?: '';
    $env = [ 'PATH' => rtrim($prefix, ':') . ($currentPath ? (':' . $currentPath) : ''), ];
}
$command = [$bin, /* args… */];
$process = new Process($command, null, $env);
```
- Standardize logging before run:
  - Resolved binary value
  - First ~200 chars of effective PATH when prefixing
  - The exact command line

3) Add an Artisan diagnostic command: `exiftool:doctor`
- Create `app/Console/Commands/ExiftoolDoctor.php` that:
  - Prints PHP SAPI, user, `config('exiftool.bin')`, `config('exiftool.path_prefix')`
  - Shows current PATH and effective PATH with prefix
  - Runs `$bin -ver` (or `$bin -ver` / `$bin -V`, depending on exiftool; `exiftool -ver` prints a version) via `Symfony\Component\Process\Process` using the same env logic; show exit code and STDOUT/STDERR
  - Optionally run `/usr/bin/which exiftool` to display location if PATH finds it

4) Clear caches and restart services (very important)
- Provide ops instructions to the user:
```
php artisan config:clear
php artisan cache:clear
# If Horizon is used
php artisan horizon:terminate
# Restart PHP-FPM/Herd services for the site
```

5) Add `.env` examples
```
EXIFTOOL_BIN=/opt/local/bin/exiftool
EXIFTOOL_PATH_PREFIX=/opt/local/bin
```

6) Verification
- Run `php artisan exiftool:doctor` and confirm:
  - Binary path is correct (file exists if absolute)
  - Effective PATH includes the prefix (if set)
  - Exit code 0 and version printed
- Trigger the app path where `exiftool` is used and check logs for the standardized lines. Ensure success, or capture the complete error output if not.

7) Optional hardening
- Where practical, prefer absolute `EXIFTOOL_BIN` over relying on PATH.
- Cap stored error message length (e.g., 1000 chars) to avoid oversized DB fields.
- Ensure any queue workers (Horizon) inherit the same config by restarting them after changes.

#### Deliverables
- New file: `config/exiftool.php`.
- Updated code at all exiftool call sites to use `config()` and augmented PATH.
- New command: `app/Console/Commands/ExiftoolDoctor.php` registered in `app/Console/Kernel.php`.
- Commit message (example):
  - `chore(exiftool): make exiftool path configurable, unify process env, add exiftool:doctor`
- README/ops note with `.env` vars and restart steps.

#### Acceptance Criteria
- Running `php artisan exiftool:doctor` exits 0 and prints exiftool version.
- App paths invoking `exiftool` succeed without “not found” when `.env` is configured and services restarted.
- Logs show resolved binary, effective PATH (preview), and executed command.
- No direct runtime `env()` reads for exiftool; all go through `config()`.

#### Anti‑goals / pitfalls to avoid
- Don’t rely on shell PATH inside PHP services.
- Don’t forget to restart Horizon/PHP‑FPM after changing `.env`/config.
- Don’t leave mixed patterns (some places using `exiftool` literal, others using config).

Use concise diffs/patches for changes. If you detect other tools (e.g., ImageMagick) using a different pattern, align them to the same approach for consistency.