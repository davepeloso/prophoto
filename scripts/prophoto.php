#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * ProPhoto Master CLI
 *
 * Usage:
 *   ./scripts/prophoto                 # Interactive menu
 *   ./scripts/prophoto bootstrap       # Create/configure sandbox (idempotent)
 *   ./scripts/prophoto access:bootstrap # Setup Access + Filament admin wiring
 *   ./scripts/prophoto access:user     # Create/update sandbox admin user + role
 *   ./scripts/prophoto sync            # Fast refresh for day-to-day work
 *   ./scripts/prophoto watch           # Auto-sync on file changes
 *   ./scripts/prophoto sandbox:fresh   # Recreate sandbox from scratch
 *   ./scripts/prophoto sandbox:reset   # Reinstall deps in existing sandbox
 *   ./scripts/prophoto doctor          # Environment diagnostics
 *   ./scripts/prophoto test            # Run package + sandbox tests
 *   ./scripts/prophoto rebuild         # Full package rebuild + sandbox sync
 *   ./scripts/prophoto --help
 *   ./scripts/prophoto --dry-run sync
 *   ./scripts/prophoto sandbox:fresh --yes
 *   ./scripts/prophoto watch --interval=2
 */

class ProPhotoWorkspace
{
    private string $baseDir;
    private string $sandboxDir;
    private bool $dryRun = false;
    private bool $assumeYes = false;
    private int $watchIntervalSeconds = 2;
    private string $accessUserName = 'Sandbox Admin';
    private string $accessUserEmail = 'admin@sandbox.test';
    private string $accessUserPassword = 'Password123!';
    private string $accessUserRole = 'studio_user';

    /**
     * Keep this list intentionally small and stable to avoid dependency mismatch
     * during initial bootstrap.
     *
     * @var string[]
     */
    private array $bootstrapPackages = [
        'prophoto/contracts',
        'prophoto/access',
        'prophoto/assets',
        'prophoto/gallery',
        'prophoto/ingest',
        'prophoto/debug',
    ];

    public function __construct()
    {
        $this->baseDir = dirname(__DIR__);
        $this->sandboxDir = $this->baseDir . '/sandbox';
    }

    public function run(array $argv): int
    {
        try {
            $positionals = [];

            foreach (array_slice($argv, 1) as $arg) {
                if ($arg === '--dry-run') {
                    $this->dryRun = true;
                    continue;
                }

                if ($arg === '--yes' || $arg === '-y') {
                    $this->assumeYes = true;
                    continue;
                }

                if ($arg === '--help' || $arg === '-h') {
                    $action = '--help';
                    continue;
                }

                if (str_starts_with($arg, '--interval=')) {
                    $raw = (string) substr($arg, strlen('--interval='));
                    $value = (int) $raw;
                    if ($value < 1 || $value > 60) {
                        throw new RuntimeException('--interval must be between 1 and 60 seconds.');
                    }
                    $this->watchIntervalSeconds = $value;
                    continue;
                }

                if (str_starts_with($arg, '--name=')) {
                    $this->accessUserName = trim((string) substr($arg, strlen('--name=')));
                    continue;
                }

                if (str_starts_with($arg, '--email=')) {
                    $this->accessUserEmail = trim((string) substr($arg, strlen('--email=')));
                    continue;
                }

                if (str_starts_with($arg, '--password=')) {
                    $this->accessUserPassword = (string) substr($arg, strlen('--password='));
                    continue;
                }

                if (str_starts_with($arg, '--role=')) {
                    $this->accessUserRole = trim((string) substr($arg, strlen('--role=')));
                    continue;
                }

                if (!str_starts_with($arg, '-')) {
                    $positionals[] = $arg;
                    continue;
                }

                if (str_starts_with($arg, '-')) {
                    throw new RuntimeException("Unknown option: {$arg}");
                }
            }

            $action = $positionals[0] ?? null;
            if ($action === 'access' && (($positionals[1] ?? null) === 'bootstrap')) {
                $action = 'access:bootstrap';
            }
            if ($action === 'access' && (($positionals[1] ?? null) === 'user')) {
                $action = 'access:user';
            }

            if ($this->dryRun) {
                $this->warning('Running in DRY RUN mode - no changes will be made.');
            }

            if ($action === null) {
                return $this->showMenu();
            }

            return match ($action) {
                'bootstrap' => $this->bootstrap(),
                'access:bootstrap', 'access-bootstrap' => $this->accessBootstrap(),
                'access:user', 'access-user' => $this->accessUser(),
                'sync' => $this->sync(),
                'watch' => $this->watch(),
                'doctor' => $this->doctor(),
                'sandbox:fresh' => $this->sandboxFresh(),
                'sandbox:reset' => $this->sandboxReset(),
                'test' => $this->runTests(),
                'refresh' => $this->sync(),
                'rebuild' => $this->rebuild(),
                '--help', '-h', 'help' => $this->showHelp(),
                default => $this->unknownCommand($action),
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }

    private function showMenu(): int
    {
        if (!$this->isInteractive()) {
            return $this->showHelp();
        }

        $this->info('ProPhoto Workspace Manager');
        echo "\n";
        echo "1) bootstrap      Create/configure sandbox\n";
        echo "2) access:bootstrap Setup Access + Filament wiring\n";
        echo "3) access:user    Create/update sandbox admin user\n";
        echo "4) sync           Fast refresh without rebuild\n";
        echo "5) watch          Auto-sync on file changes\n";
        echo "6) sandbox:fresh  Recreate sandbox (destructive)\n";
        echo "7) sandbox:reset  Reinstall deps in sandbox\n";
        echo "8) test           Run tests\n";
        echo "9) doctor         Diagnostics\n";
        echo "10) rebuild       Full rebuild\n";
        echo "11) exit\n\n";

        echo 'Select an option [1-11]: ';
        $input = trim((string) fgets(STDIN));

        return match ($input) {
            '1' => $this->bootstrap(),
            '2' => $this->accessBootstrap(),
            '3' => $this->accessUser(),
            '4' => $this->sync(),
            '5' => $this->watch(),
            '6' => $this->sandboxFresh(),
            '7' => $this->sandboxReset(),
            '8' => $this->runTests(),
            '9' => $this->doctor(),
            '10' => $this->rebuild(),
            default => 0,
        };
    }

    private function showHelp(): int
    {
        $this->info('ProPhoto Master CLI');
        echo "\n";
        echo "Usage:\n";
        echo "  ./scripts/prophoto [command] [--dry-run]\n\n";
        echo "Commands:\n";
        echo "  bootstrap       Create/configure sandbox (idempotent)\n";
        echo "  access:bootstrap Setup Access + Filament admin wiring\n";
        echo "  access:user     Create/update sandbox admin user + role\n";
        echo "  sync            Fast day-to-day refresh\n";
        echo "  watch           Auto-sync when files change\n";
        echo "  sandbox:fresh   Recreate sandbox (destructive)\n";
        echo "  sandbox:reset   Reinstall deps in existing sandbox\n";
        echo "  doctor          Environment diagnostics\n";
        echo "  test            Run package + sandbox tests\n";
        echo "  rebuild         Rebuild package assets + sandbox sync\n";
        echo "  refresh         Alias for sync\n";
        echo "  --help, -h      Show help\n\n";
        echo "Options:\n";
        echo "  --dry-run       Show what would run without making changes\n\n";
        echo "  --yes, -y       Skip confirmation prompts (for sandbox:fresh)\n";
        echo "  --interval=N    Watch poll interval in seconds (1-60, default: 2)\n\n";
        echo "  --name=VALUE    Admin name for access:user (default: Sandbox Admin)\n";
        echo "  --email=VALUE   Admin email for access:user (default: admin@sandbox.test)\n";
        echo "  --password=VAL  Admin password for access:user (default: Password123!)\n";
        echo "  --role=VALUE    Role for access:user (default: studio_user)\n\n";
        return 0;
    }

    private function unknownCommand(string $action): int
    {
        $this->error("Unknown command: {$action}");
        echo "Run './scripts/prophoto --help' for usage information.\n";
        return 1;
    }

    // =========================================================================
    // BOOTSTRAP / SYNC
    // =========================================================================

    private function bootstrap(): int
    {
        $this->info('Bootstrapping sandbox (idempotent)...');

        $steps = [
            'Create sandbox app (if missing)' => fn() => $this->ensureSandboxApp(),
            'Configure path repositories' => fn() => $this->addPathRepositories(),
            'Require core local packages' => fn() => $this->requireBootstrapPackages(),
            'Install composer dependencies' => fn() => $this->runInSandbox('composer install --no-interaction --no-progress --no-scripts'),
            'Configure .env defaults' => fn() => $this->setupEnv(),
            'Ensure sqlite database file' => fn() => $this->ensureSqliteDatabase(),
            'Generate app key (if missing)' => fn() => $this->ensureAppKey(),
            'Install dev dashboard scaffold' => fn() => $this->ensureDevDashboardScaffold(),
            'Run migrations' => fn() => $this->runInSandbox('php artisan migrate --force'),
            'Install npm dependencies' => fn() => $this->installNpmDependencies(),
            'Build sandbox assets' => fn() => $this->buildSandboxAssets(true),
            'Publish package assets' => fn() => $this->publishAllAssets(),
        ];

        return $this->runSteps($steps);
    }

    private function accessBootstrap(): int
    {
        $this->ensureSandboxExists();
        $this->info('Bootstrapping Access + Filament...');

        $steps = [
            'Configure path repositories' => fn() => $this->addPathRepositories(),
            'Require core local packages' => fn() => $this->requireBootstrapPackages(),
            'Ensure Filament package' => fn() => $this->ensureComposerRequirement('filament/filament', '^4.0'),
            'Ensure Spatie Permission package' => fn() => $this->ensureComposerRequirement('spatie/laravel-permission', '^6.0'),
            'Install composer dependencies' => fn() => $this->runInSandbox('composer install --no-interaction --no-progress --no-scripts'),
            'Install dev dashboard scaffold' => fn() => $this->ensureDevDashboardScaffold(),
            'Install Filament panel scaffolding' => fn() => $this->ensureFilamentPanelProvider(),
            'Publish Spatie Permission migrations' => fn() => $this->publishSpatiePermissionMigrations(),
            'Register Access plugin in Filament panel' => fn() => $this->ensureAccessPluginRegistered(),
            'Apply ProPhoto admin panel theme' => fn() => $this->ensureAdminPanelThemeOverrides(),
            'Add Access traits to User model' => fn() => $this->ensureUserModelAccessTraits(),
            'Run migrations' => fn() => $this->runInSandbox('php artisan migrate --force'),
            'Seed roles and permissions' => fn() => $this->runInSandbox('php artisan db:seed --class=\'ProPhoto\\Access\\Database\\Seeders\\RolesAndPermissionsSeeder\' --force 2>&1'),
            'Clear Laravel caches' => fn() => $this->runInSandbox('php artisan optimize:clear'),
        ];

        return $this->runSteps($steps);
    }

    private function accessUser(): int
    {
        $this->ensureSandboxExists();
        $this->validateAccessUserInputs();

        $this->info("Ensuring Access admin user ({$this->accessUserEmail})...");

        $steps = [
            'Configure path repositories' => fn() => $this->addPathRepositories(),
            'Require core local packages' => fn() => $this->requireBootstrapPackages(),
            'Ensure Filament package' => fn() => $this->ensureComposerRequirement('filament/filament', '^4.0'),
            'Ensure Spatie Permission package' => fn() => $this->ensureComposerRequirement('spatie/laravel-permission', '^6.0'),
            'Install composer dependencies' => fn() => $this->runInSandbox('composer install --no-interaction --no-progress --no-scripts'),
            'Install Filament panel scaffolding' => fn() => $this->ensureFilamentPanelProvider(),
            'Publish Spatie Permission migrations' => fn() => $this->publishSpatiePermissionMigrations(),
            'Register Access plugin in Filament panel' => fn() => $this->ensureAccessPluginRegistered(),
            'Apply ProPhoto admin panel theme' => fn() => $this->ensureAdminPanelThemeOverrides(),
            'Add Access traits to User model' => fn() => $this->ensureUserModelAccessTraits(),
            'Run migrations' => fn() => $this->runInSandbox('php artisan migrate --force'),
            'Seed roles and permissions' => fn() => $this->runInSandbox('php artisan db:seed --class=\'ProPhoto\\Access\\Database\\Seeders\\RolesAndPermissionsSeeder\' --force 2>&1'),
            'Create or update Filament admin user' => fn() => $this->createOrUpdateAccessUser(),
            "Assign '{$this->accessUserRole}' role" => fn() => $this->assignAccessUserRole(),
            'Clear Laravel caches' => fn() => $this->runInSandbox('php artisan optimize:clear'),
        ];

        $status = $this->runSteps($steps);
        if ($status === 0) {
            $this->info("Admin user ready at /admin/login: {$this->accessUserEmail}");
        }

        return $status;
    }

    private function sync(): int
    {
        $this->ensureSandboxExists();
        $this->info('Syncing sandbox (fast path)...');

        $steps = [
            'Configure path repositories' => fn() => $this->addPathRepositories(),
            'Require core local packages' => fn() => $this->requireBootstrapPackages(),
            'Composer install (incremental)' => fn() => $this->runInSandbox('composer install --no-interaction --no-progress --no-scripts'),
            'Composer autoload refresh' => fn() => $this->runInSandbox('composer dump-autoload -o'),
            'Install dev dashboard scaffold' => fn() => $this->ensureDevDashboardScaffold(),
            'Run migrations' => fn() => $this->runInSandbox('php artisan migrate --force'),
            'Clear Laravel caches' => fn() => $this->runInSandbox('php artisan optimize:clear'),
            'Publish package assets' => fn() => $this->publishAllAssets(),
            'Install npm dependencies (if missing)' => fn() => $this->installNpmDependencies(),
            'Build sandbox assets (if missing)' => fn() => $this->buildSandboxAssets(false),
        ];

        return $this->runSteps($steps);
    }

    private function watch(): int
    {
        $this->ensureSandboxExists();

        if ($this->dryRun) {
            $this->warning('Dry run watch executes one sync pass and exits.');
            return $this->sync();
        }

        $this->info("Watching for changes every {$this->watchIntervalSeconds}s. Press Ctrl+C to stop.");
        $lastFingerprint = $this->buildWatchFingerprint();
        $lastRunAt = 0;

        while (true) {
            sleep($this->watchIntervalSeconds);
            clearstatcache();
            $currentFingerprint = $this->buildWatchFingerprint();

            if ($currentFingerprint === $lastFingerprint) {
                continue;
            }

            $now = time();
            if (($now - $lastRunAt) < $this->watchIntervalSeconds) {
                $lastFingerprint = $currentFingerprint;
                continue;
            }

            $this->warning('Change detected. Running sync...');
            $status = $this->sync();
            if ($status === 0) {
                $this->info('Watch sync complete.');
            } else {
                $this->error('Watch sync failed. Continuing to watch for new changes.');
            }

            $lastFingerprint = $this->buildWatchFingerprint();
            $lastRunAt = $now;
        }
    }

    // =========================================================================
    // SANDBOX LIFECYCLE
    // =========================================================================

    private function sandboxFresh(): int
    {
        $this->warning('This will delete and recreate sandbox/.');

        if (!$this->dryRun && !$this->assumeYes && !$this->confirm('Continue with sandbox:fresh?')) {
            $this->info('Cancelled.');
            return 0;
        }

        $steps = [
            'Delete sandbox directory' => fn() => $this->deleteSandbox(),
        ];

        $status = $this->runSteps($steps);
        if ($status !== 0) {
            return $status;
        }

        return $this->bootstrap();
    }

    private function sandboxReset(): int
    {
        $this->ensureSandboxExists();
        $this->info('Resetting sandbox dependencies...');

        $steps = [
            'Remove vendor directory' => fn() => $this->deleteDirectory($this->sandboxDir . '/vendor'),
            'Remove node_modules directory' => fn() => $this->deleteDirectory($this->sandboxDir . '/node_modules'),
            'Install composer dependencies' => fn() => $this->runInSandbox('composer install --no-interaction --no-progress --no-scripts'),
            'Install npm dependencies' => fn() => $this->runInSandbox('npm install'),
            'Run migrations' => fn() => $this->runInSandbox('php artisan migrate --force'),
            'Clear Laravel caches' => fn() => $this->runInSandbox('php artisan optimize:clear'),
            'Build sandbox assets' => fn() => $this->buildSandboxAssets(true),
            'Publish package assets' => fn() => $this->publishAllAssets(),
        ];

        return $this->runSteps($steps);
    }

    // =========================================================================
    // DIAGNOSTICS / TEST / REBUILD
    // =========================================================================

    private function doctor(): int
    {
        $this->info('Running diagnostics...');

        $checks = [
            'PHP Version' => fn() => $this->checkPhpVersion(),
            'Composer Version' => fn() => $this->checkComposerVersion(),
            'Node Version' => fn() => $this->checkNodeVersion(),
            'ExifTool' => fn() => $this->checkExifTool(),
            'Sandbox Exists' => fn() => $this->checkSandboxExists(),
            'Path Repositories' => fn() => $this->checkPathRepositories(),
            'Symlinks Active' => fn() => $this->checkSymlinks(),
        ];

        $rows = [];
        foreach ($checks as $name => $check) {
            $result = $check();
            $rows[] = [
                'Check' => $name,
                'Status' => $result['pass'] ? 'PASS' : 'FAIL',
                'Details' => $result['message'],
            ];
        }

        $this->printTable(['Check', 'Status', 'Details'], $rows);

        $failCount = count(array_filter($rows, fn(array $row) => $row['Status'] === 'FAIL'));
        if ($failCount === 0) {
            $this->info('All checks passed.');
            return 0;
        }

        $this->warning("{$failCount} check(s) failed.");
        return 1;
    }

    private function runTests(): int
    {
        $this->info('Running tests...');

        $steps = [];
        foreach ($this->discoverPackageDirectories() as $packageDir) {
            if (file_exists($packageDir . '/composer.json')) {
                $packageName = basename($packageDir);
                $steps["Test {$packageName}"] = fn() => $this->exec("cd " . escapeshellarg($packageDir) . " && composer test 2>&1 || echo 'No tests configured'");
            }
        }

        if (is_dir($this->sandboxDir) && file_exists($this->sandboxDir . '/artisan')) {
            $steps['Test sandbox'] = fn() => $this->runInSandbox("php artisan test 2>&1 || echo 'No tests configured'");
        } else {
            $this->warning('Sandbox not found; skipping sandbox test suite.');
        }

        return $this->runSteps($steps);
    }

    private function rebuild(): int
    {
        $this->ensureSandboxExists();
        $this->info('Running full rebuild...');

        $steps = [];
        foreach ($this->discoverPackageDirectories() as $packageDir) {
            if (file_exists($packageDir . '/package.json')) {
                $packageName = basename($packageDir);
                $steps["Build {$packageName} assets"] = fn() => $this->exec(
                    "cd " . escapeshellarg($packageDir) . " && npm install && npm run build 2>&1 || echo 'No build script'"
                );
            }
        }

        $steps['Sync sandbox'] = fn() => $this->syncInternalForRebuild();

        return $this->runSteps($steps);
    }

    private function syncInternalForRebuild(): string
    {
        $this->addPathRepositories();
        $this->requireBootstrapPackages();
        $this->runInSandbox('composer install --no-interaction --no-progress --no-scripts');
        $this->runInSandbox('composer dump-autoload -o');
        $this->runInSandbox('php artisan migrate --force');
        $this->runInSandbox('php artisan optimize:clear');
        $this->runInSandbox('npm install');
        $this->buildSandboxAssets(true);
        $this->publishAllAssets();
        return 'Sandbox synced';
    }

    // =========================================================================
    // SANDBOX HELPERS
    // =========================================================================

    private function ensureSandboxApp(): string
    {
        if (is_dir($this->sandboxDir) && file_exists($this->sandboxDir . '/artisan')) {
            return 'sandbox/ already exists';
        }

        $command = sprintf(
            'cd %s && composer create-project laravel/laravel sandbox --prefer-dist --no-interaction',
            escapeshellarg($this->baseDir)
        );
        $this->exec($command);

        if (!file_exists($this->sandboxDir . '/artisan')) {
            throw new RuntimeException('Sandbox creation failed: artisan file not found.');
        }

        return 'sandbox/ created';
    }

    private function ensureSandboxExists(): void
    {
        if (!is_dir($this->sandboxDir) || !file_exists($this->sandboxDir . '/artisan')) {
            throw new RuntimeException("Sandbox not found. Run './scripts/prophoto bootstrap' first.");
        }
    }

    private function deleteSandbox(): string
    {
        if (!is_dir($this->sandboxDir)) {
            return 'sandbox/ already absent';
        }

        $this->deleteDirectory($this->sandboxDir);
        return 'sandbox/ deleted';
    }

    private function addPathRepositories(): string
    {
        $this->ensureSandboxExists();

        $composerJson = $this->sandboxDir . '/composer.json';
        if (!file_exists($composerJson)) {
            throw new RuntimeException('sandbox/composer.json not found.');
        }

        $content = json_decode((string) file_get_contents($composerJson), true);
        if (!is_array($content)) {
            throw new RuntimeException('Unable to parse sandbox/composer.json.');
        }

        $repositories = $content['repositories'] ?? [];
        if (!is_array($repositories)) {
            $repositories = [];
        }

        $updated = false;
        $found = false;

        foreach ($repositories as &$repo) {
            if (!is_array($repo)) {
                continue;
            }

            if (($repo['type'] ?? null) === 'path' && ($repo['url'] ?? null) === '../prophoto-*') {
                $found = true;
                if (($repo['options']['symlink'] ?? null) !== true) {
                    $repo['options']['symlink'] = true;
                    $updated = true;
                }
            }
        }
        unset($repo);

        if (!$found) {
            $repositories[] = [
                'type' => 'path',
                'url' => '../prophoto-*',
                'options' => ['symlink' => true],
            ];
            $updated = true;
        }

        $content['repositories'] = $repositories;

        if ($updated && !$this->dryRun) {
            $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Failed to encode sandbox/composer.json.');
            }
            file_put_contents($composerJson, $json . PHP_EOL);
        }

        return $updated ? 'Path repository configured' : 'Path repository already configured';
    }

    private function requireBootstrapPackages(): string
    {
        $this->ensureSandboxExists();

        $composerJson = $this->sandboxDir . '/composer.json';
        $content = json_decode((string) file_get_contents($composerJson), true);
        if (!is_array($content)) {
            throw new RuntimeException('Unable to parse sandbox/composer.json.');
        }

        $required = $content['require'] ?? [];
        if (!is_array($required)) {
            $required = [];
        }

        $missing = [];
        foreach ($this->bootstrapPackages as $package) {
            if (!array_key_exists($package, $required)) {
                $missing[] = $package;
            }
        }

        if ($missing === []) {
            return 'Core local packages already required';
        }

        $withVersions = array_map(fn(string $package) => $package . ':@dev', $missing);
        $command = 'composer require ' . implode(' ', $withVersions) . ' --no-interaction --no-progress --no-scripts';
        $this->runInSandbox($command);

        return 'Required: ' . implode(', ', $missing);
    }

    private function ensureComposerRequirement(string $package, string $version): string
    {
        $this->ensureSandboxExists();

        $composerJson = $this->sandboxDir . '/composer.json';
        $content = json_decode((string) file_get_contents($composerJson), true);
        if (!is_array($content)) {
            throw new RuntimeException('Unable to parse sandbox/composer.json.');
        }

        $required = $content['require'] ?? [];
        if (!is_array($required)) {
            $required = [];
        }

        if (array_key_exists($package, $required)) {
            return "{$package} already required";
        }

        $this->runInSandbox("composer require {$package}:{$version} --no-interaction --no-progress");
        return "Required {$package}:{$version}";
    }

    private function ensureFilamentPanelProvider(): string
    {
        $provider = $this->sandboxDir . '/app/Providers/Filament/AdminPanelProvider.php';
        if (file_exists($provider)) {
            return 'Filament panel provider already present';
        }

        if ($this->dryRun) {
            return '[DRY RUN] Filament panel provider would be installed';
        }

        $this->runInSandbox('php artisan filament:install --panels --no-interaction');

        if (!file_exists($provider)) {
            throw new RuntimeException('Filament panel provider not found after install.');
        }

        return 'Filament panel provider installed';
    }

    private function publishSpatiePermissionMigrations(): string
    {
        $pattern = $this->sandboxDir . '/database/migrations/*_create_permission_tables.php';
        $existing = glob($pattern) ?: [];
        if ($existing !== []) {
            return 'Spatie Permission migrations already published';
        }

        $this->runInSandbox('php artisan vendor:publish --provider=\'Spatie\\Permission\\PermissionServiceProvider\' --tag=\'permission-migrations\' --force 2>&1');

        $published = glob($pattern) ?: [];
        if ($published === []) {
            throw new RuntimeException('Spatie Permission migration publish failed. Expected *_create_permission_tables.php in sandbox/database/migrations.');
        }

        return 'Spatie Permission migrations published';
    }

    private function ensureAccessPluginRegistered(): string
    {
        $provider = $this->sandboxDir . '/app/Providers/Filament/AdminPanelProvider.php';
        if (!file_exists($provider)) {
            if ($this->dryRun) {
                return '[DRY RUN] Access plugin would be registered in AdminPanelProvider';
            }
            throw new RuntimeException('AdminPanelProvider.php not found.');
        }

        $content = (string) file_get_contents($provider);
        $original = $content;

        $content = $this->addUseImport($content, 'ProPhoto\\Access\\Filament\\AccessPlugin');

        if (!str_contains($content, 'AccessPlugin::make()')) {
            if (str_contains($content, '->plugins([')) {
                $count = 0;
                $content = (string) preg_replace(
                    '/->plugins\(\[\s*/',
                    "->plugins([\n                AccessPlugin::make(),\n",
                    $content,
                    1,
                    $count
                );

                if ($count === 0) {
                    throw new RuntimeException('Unable to update existing ->plugins() block.');
                }
            } else {
                $count = 0;
                $content = (string) preg_replace_callback(
                    '/return \$panel(.*?);/s',
                    function (array $matches): string {
                        $chain = rtrim((string) $matches[1]);
                        return "return \$panel{$chain}\n            ->plugins([\n                AccessPlugin::make(),\n            ]);";
                    },
                    $content,
                    1,
                    $count
                );

                if ($count === 0) {
                    throw new RuntimeException('Unable to add ->plugins() to AdminPanelProvider.');
                }
            }
        }

        if ($content !== $original && !$this->dryRun) {
            file_put_contents($provider, $content);
        }

        return $content === $original ? 'Access plugin already registered' : 'Access plugin registered';
    }

    private function ensureAdminPanelThemeOverrides(): string
    {
        $provider = $this->sandboxDir . '/app/Providers/Filament/AdminPanelProvider.php';
        if (!file_exists($provider)) {
            if ($this->dryRun) {
                return '[DRY RUN] AdminPanelProvider theme overrides would be applied';
            }

            throw new RuntimeException('AdminPanelProvider.php not found.');
        }

        $content = (string) file_get_contents($provider);
        $original = $content;

        $content = $this->addUseImport($content, 'Filament\\View\\PanelsRenderHook');

        if (!str_contains($content, 'PanelsRenderHook::STYLES_AFTER')) {
            $renderHookSnippet = <<<'PHP'
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => $this->adminOverridesCss(),
            )
PHP;

            $count = 0;
            $content = (string) preg_replace(
                '/\n(\s*->discoverResources\()/',
                "\n{$renderHookSnippet}\n$1",
                $content,
                1,
                $count
            );

            if ($count === 0) {
                $content = (string) preg_replace(
                    '/\n(\s*->pages\(\[)/',
                    "\n{$renderHookSnippet}\n$1",
                    $content,
                    1,
                    $count
                );
            }

            if ($count === 0) {
                throw new RuntimeException('Unable to add renderHook() to AdminPanelProvider.');
            }
        }

        if (!str_contains($content, 'private function adminOverridesCss(): string')) {
            $methodSnippet = <<<'PHP'
    private function adminOverridesCss(): string
    {
        return <<<'HTML'
<style>
    :root,
    :root.dark {
        --pp-bg: #060b14;
        --pp-surface-1: #0f1726;
        --pp-surface-2: #182338;
        --pp-surface-3: #111c2d;
        --pp-border: #2a3953;
        --pp-border-strong: #3b4f70;
        --pp-text: #e7eefb;
        --pp-muted: #95a6c3;
        --pp-accent: #00c48c;
    }

    .fi-body {
        --sidebar-width: 14rem;
        background: linear-gradient(180deg, #060b14 0%, #08111f 100%) !important;
    }

    .fi-topbar,
    .fi-body-has-topbar .fi-sidebar-header {
        background: #0b111d !important;
        border-bottom: 1px solid #1f2b40 !important;
        box-shadow: none !important;
    }

    .fi-main-sidebar {
        background: #060b14 !important;
        border-inline-end: 1px solid #1f2b40;
    }

    .fi-sidebar-nav {
        padding: 1rem 0.6rem !important;
        gap: 1rem !important;
    }

    .fi-sidebar-nav-groups {
        margin-inline: 0 !important;
        gap: 1rem !important;
    }

    .fi-sidebar-group-btn,
    .fi-sidebar-item-btn,
    .fi-sidebar-database-notifications-btn {
        border-radius: 10px !important;
    }

    .fi-sidebar-item-btn,
    .fi-sidebar-database-notifications-btn {
        padding: 0.55rem 0.65rem !important;
    }

    .fi-sidebar-group-btn {
        padding: 0.35rem 0.45rem !important;
    }

    .fi-sidebar-group-label,
    .fi-sidebar-item-label {
        letter-spacing: 0.01em;
    }

    .fi-sidebar-group-label {
        font-size: 0.95rem !important;
        color: #98a9c6 !important;
    }

    .fi-sidebar-item-label {
        font-size: 1rem !important;
        color: #c8d4e8 !important;
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: #121b2a !important;
        box-shadow: inset 0 0 0 1px #2e4260;
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label,
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon {
        color: #ffc300 !important;
    }

    .fi-ta-ctn,
    .fi-section:not(.fi-section-not-contained):not(.fi-aside),
    .fi-section.fi-aside > .fi-section-content-ctn,
    .fi-tabs:not(.fi-contained),
    .fi-dropdown-panel,
    .fi-modal-window {
        background: var(--pp-surface-1) !important;
        border: 1px solid var(--pp-border) !important;
        box-shadow: none !important;
    }

    .fi-ta-header,
    .fi-ta-header-toolbar,
    .fi-ta-filters-above-content-ctn,
    .fi-ta-reorder-indicator,
    .fi-ta-selection-indicator,
    .fi-ta-filter-indicators,
    .fi-pagination,
    .fi-section.fi-secondary,
    .fi-tabs.fi-contained {
        background: var(--pp-surface-3) !important;
        border-color: var(--pp-border) !important;
    }

    .fi-ta-table > thead > tr,
    .fi-ta-table > tfoot,
    .fi-ta-table-stacked-header-cell {
        background: var(--pp-surface-2) !important;
    }

    .fi-ta-table,
    .fi-ta-table > tbody,
    .fi-ta-table > tbody > tr,
    .fi-ta-table > thead,
    .fi-ta-table > tfoot {
        border-color: var(--pp-border) !important;
    }

    .fi-ta-table > tbody > tr {
        transition: background-color 120ms ease;
    }

    .fi-ta-table > tbody > tr:hover {
        background: #162234 !important;
    }

    .fi-ta-header-cell,
    .fi-ta-header-group-cell {
        color: #a9bad4 !important;
        font-size: 0.74rem !important;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .fi-ta-cell .fi-ta-cell-content,
    .fi-section-header-heading,
    .fi-sc-section .fi-sc-section-label,
    .fi-sc-tabs-tab.fi-active .fi-tabs-item-label,
    .fi-tabs-item.fi-active .fi-tabs-item-label,
    .fi-modal-heading {
        color: var(--pp-text) !important;
    }

    .fi-ta-header-description,
    .fi-section-header-description,
    .fi-tabs-item .fi-tabs-item-label,
    .fi-modal-description,
    .fi-fo-field-label,
    .fi-fo-field-helper-text {
        color: var(--pp-muted) !important;
    }

    .fi-input-wrp {
        background: #0f1a2b !important;
        border: 1px solid var(--pp-border-strong) !important;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03) !important;
    }

    .fi-input,
    .fi-select-input,
    textarea.fi-input,
    input.fi-input {
        color: var(--pp-text) !important;
    }

    .fi-input::placeholder,
    textarea.fi-input::placeholder,
    input.fi-input::placeholder {
        color: #7f92b0 !important;
    }

    .fi-badge {
        border-color: #41557a !important;
    }

    .fi-btn.fi-color-primary {
        box-shadow: none !important;
    }
</style>
HTML;
    }
PHP;

            $trimmed = rtrim($content);
            $count = 0;
            $content = (string) preg_replace(
                '/\n}\s*$/',
                "\n\n{$methodSnippet}\n}\n",
                $trimmed,
                1,
                $count
            );

            if ($count === 0) {
                throw new RuntimeException('Unable to append adminOverridesCss() to AdminPanelProvider.');
            }
        }

        if ($content !== $original && !$this->dryRun) {
            file_put_contents($provider, $content);
        }

        return $content === $original ? 'AdminPanelProvider theme already configured' : 'AdminPanelProvider theme configured';
    }

    private function ensureUserModelAccessTraits(): string
    {
        $userPath = $this->sandboxDir . '/app/Models/User.php';
        if (!file_exists($userPath)) {
            if ($this->dryRun) {
                return '[DRY RUN] User model traits would be updated';
            }
            throw new RuntimeException('sandbox/app/Models/User.php not found.');
        }

        $content = (string) file_get_contents($userPath);
        $original = $content;

        $content = $this->addUseImport($content, 'Spatie\\Permission\\Traits\\HasRoles');
        $content = $this->addUseImport($content, 'ProPhoto\\Access\\Traits\\HasContextualPermissions');

        $classPos = strpos($content, 'class User extends Authenticatable');
        if ($classPos === false) {
            throw new RuntimeException('Unable to locate User class declaration.');
        }

        $bracePos = strpos($content, '{', $classPos);
        if ($bracePos === false) {
            throw new RuntimeException('Unable to locate User class body.');
        }

        $classBody = substr($content, $bracePos + 1);
        $updatedTraits = false;

        if (preg_match('/^\s*use\s+([^;]+);/m', $classBody, $match, PREG_OFFSET_CAPTURE) === 1) {
            $fullMatch = $match[0][0];
            $relativeOffset = $match[0][1];
            $traitList = $match[1][0];
            $globalOffset = $bracePos + 1 + $relativeOffset;

            $traits = array_map('trim', explode(',', $traitList));
            $missing = [];

            foreach (['HasRoles', 'HasContextualPermissions'] as $trait) {
                if (!in_array($trait, $traits, true)) {
                    $missing[] = $trait;
                }
            }

            if ($missing !== []) {
                $newTraits = implode(', ', array_merge($traits, $missing));
                $newLine = preg_replace('/use\s+[^;]+;/', 'use ' . $newTraits . ';', $fullMatch, 1);
                $content = substr($content, 0, $globalOffset) . $newLine . substr($content, $globalOffset + strlen($fullMatch));
                $updatedTraits = true;
            }
        } else {
            $insertion = "\n    use HasFactory, Notifiable, HasRoles, HasContextualPermissions;\n";
            $content = substr($content, 0, $bracePos + 1) . $insertion . substr($content, $bracePos + 1);
            $updatedTraits = true;
        }

        if (($content !== $original || $updatedTraits) && !$this->dryRun) {
            file_put_contents($userPath, $content);
        }

        return $content === $original ? 'User model already has Access traits' : 'User model traits updated';
    }

    private function validateAccessUserInputs(): void
    {
        if ($this->accessUserName === '') {
            throw new RuntimeException('--name cannot be empty.');
        }

        if (!filter_var($this->accessUserEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('--email must be a valid email address.');
        }

        if (strlen($this->accessUserPassword) < 8) {
            throw new RuntimeException('--password must be at least 8 characters.');
        }

        if ($this->accessUserRole === '') {
            throw new RuntimeException('--role cannot be empty.');
        }
    }

    private function createOrUpdateAccessUser(): string
    {
        $existingUserId = $this->findUserIdByEmail($this->accessUserEmail);
        if ($existingUserId === null) {
            $this->runInSandbox(
                'php artisan make:filament-user'
                . ' --name=' . escapeshellarg($this->accessUserName)
                . ' --email=' . escapeshellarg($this->accessUserEmail)
                . ' --password=' . escapeshellarg($this->accessUserPassword)
                . ' --no-interaction 2>&1'
            );

            $createdId = $this->findUserIdByEmail($this->accessUserEmail);
            if ($createdId === null) {
                throw new RuntimeException("Unable to verify created user: {$this->accessUserEmail}");
            }

            return "Created {$this->accessUserEmail} (ID {$createdId})";
        }

        $code = sprintf(
            '$user = App\\Models\\User::find(%d);'
            . '$user->name = %s;'
            . '$user->password = Illuminate\\Support\\Facades\\Hash::make(%s);'
            . '$user->save();'
            . 'echo $user->id;',
            $existingUserId,
            var_export($this->accessUserName, true),
            var_export($this->accessUserPassword, true)
        );

        $this->runInSandbox('php artisan tinker --execute=' . escapeshellarg($code) . ' 2>&1', true);

        return "Updated {$this->accessUserEmail} (ID {$existingUserId})";
    }

    private function assignAccessUserRole(): string
    {
        $userId = $this->findUserIdByEmail($this->accessUserEmail);
        if ($userId === null) {
            throw new RuntimeException("Cannot assign role. User not found: {$this->accessUserEmail}");
        }

        $this->runInSandbox(
            'php artisan permission:assign-role'
            . ' ' . escapeshellarg($this->accessUserRole)
            . ' ' . $userId
            . ' --no-interaction 2>&1'
        );

        return "Assigned '{$this->accessUserRole}' to {$this->accessUserEmail} (ID {$userId})";
    }

    private function findUserIdByEmail(string $email): ?int
    {
        $code = sprintf("echo App\\Models\\User::where('email', %s)->value('id');", var_export($email, true));
        $output = trim($this->runInSandbox('php artisan tinker --execute=' . escapeshellarg($code) . ' 2>&1', true));

        if ($output === '' || strcasecmp($output, 'null') === 0) {
            return null;
        }

        if (preg_match('/\b(\d+)\b/', $output, $matches) !== 1) {
            throw new RuntimeException("Unable to resolve user id for {$email}. Output: {$output}");
        }

        return (int) $matches[1];
    }

    private function addUseImport(string $content, string $fqcn): string
    {
        $useLine = 'use ' . $fqcn . ';';
        if (str_contains($content, $useLine)) {
            return $content;
        }

        if (preg_match_all('/^use\s+[^;]+;/m', $content, $matches, PREG_OFFSET_CAPTURE) && !empty($matches[0])) {
            $last = end($matches[0]);
            $insertPos = $last[1] + strlen($last[0]);
            return substr($content, 0, $insertPos) . "\n" . $useLine . substr($content, $insertPos);
        }

        if (preg_match('/^namespace\s+[^;]+;\n/m', $content, $match, PREG_OFFSET_CAPTURE) === 1) {
            $insertPos = $match[0][1] + strlen($match[0][0]);
            return substr($content, 0, $insertPos) . "\n" . $useLine . substr($content, $insertPos);
        }

        throw new RuntimeException('Unable to insert use statement into file.');
    }

    private function setupEnv(): string
    {
        $this->ensureSandboxExists();

        $envExample = $this->sandboxDir . '/.env.example';
        $env = $this->sandboxDir . '/.env';

        if (!file_exists($env)) {
            if (!file_exists($envExample)) {
                throw new RuntimeException('sandbox/.env.example not found.');
            }
            if (!$this->dryRun) {
                copy($envExample, $env);
            }
        }

        $updates = [
            'APP_NAME' => '"ProPhoto Sandbox"',
            'APP_ENV' => 'local',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://localhost',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->sandboxDir . '/database/database.sqlite',
            'QUEUE_CONNECTION' => 'database',
            'CACHE_STORE' => 'file',
            'SESSION_DRIVER' => 'file',
            'FILESYSTEM_DISK' => 'local',
        ];

        if (!$this->dryRun) {
            $content = (string) file_get_contents($env);
            foreach ($updates as $key => $value) {
                $content = $this->setEnvValue($content, $key, $value);
            }
            file_put_contents($env, $content);
        }

        return '.env configured';
    }

    private function ensureDevDashboardScaffold(): string
    {
        $this->ensureSandboxExists();

        $routePath = $this->sandboxDir . '/routes/web.php';
        $viewDirectory = $this->sandboxDir . '/resources/views';
        $viewPath = $viewDirectory . '/dev-dashboard.blade.php';

        $routeTemplate = <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $workspaceRoot = realpath(base_path('..')) ?: dirname(base_path());
    $componentDirs = glob($workspaceRoot . '/prophoto-*', GLOB_ONLYDIR) ?: [];
    sort($componentDirs);

    $composerPath = base_path('composer.json');
    $composer = [];
    if (file_exists($composerPath)) {
        $decoded = json_decode((string) file_get_contents($composerPath), true);
        if (is_array($decoded)) {
            $composer = $decoded;
        }
    }

    $required = is_array($composer['require'] ?? null) ? $composer['require'] : [];
    $routeHints = [
        'access' => '/admin',
        'debug' => '/admin',
        'ingest' => '/' . ltrim((string) config('ingest.route_prefix', 'ingest'), '/'),
        'gallery' => '/api/galleries',
    ];

    $components = [];
    foreach ($componentDirs as $dir) {
        $directoryName = basename($dir);
        $short = str_starts_with($directoryName, 'prophoto-')
            ? substr($directoryName, strlen('prophoto-'))
            : $directoryName;

        $packageName = 'prophoto/' . $short;
        $vendorPath = base_path('vendor/prophoto/' . $short);
        $requiredInSandbox = array_key_exists($packageName, $required);
        $vendorExists = is_dir($vendorPath) || is_link($vendorPath);

        $components[] = [
            'short' => $short,
            'name' => ucwords(str_replace(['-', '_'], ' ', $short)),
            'package' => $packageName,
            'required' => $requiredInSandbox,
            'installed' => $requiredInSandbox && $vendorExists,
            'symlinked' => is_link($vendorPath),
            'version' => $required[$packageName] ?? null,
            'url' => $routeHints[$short] ?? null,
        ];
    }

    $installedCount = count(array_filter($components, fn(array $component): bool => $component['installed']));
    $requiredCount = count(array_filter($components, fn(array $component): bool => $component['required']));

    return view('dev-dashboard', [
        'components' => $components,
        'installedCount' => $installedCount,
        'requiredCount' => $requiredCount,
        'totalCount' => count($components),
        'appName' => (string) config('app.name', 'ProPhoto Sandbox'),
    ]);
});
PHP;

        $viewTemplate = <<<'BLADE'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} | Dev Dashboard</title>
    <style>
        :root {
            --bg: #f3f4f8;
            --panel: #ffffff;
            --text: #16181d;
            --muted: #5a6270;
            --line: #d8dde8;
            --ok-bg: #e8f6ee;
            --ok-text: #166534;
            --missing-bg: #fef2f2;
            --missing-text: #991b1b;
            --accent: #1f4cbf;
            --shadow: 0 6px 20px rgba(19, 33, 68, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(170deg, #eef2ff 0%, var(--bg) 45%, #f7fafc 100%);
            color: var(--text);
        }

        .wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }

        .hero {
            background: radial-gradient(circle at 10% 0%, #e7ecff 0%, #ffffff 40%, #ffffff 100%);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 20px;
        }

        .hero h1 {
            margin: 0 0 8px;
            font-size: 28px;
            line-height: 1.2;
        }

        .meta {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 18px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin: 0 0 18px;
        }

        .stat {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--panel);
            padding: 12px;
        }

        .stat .label {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stat .value {
            font-size: 24px;
            font-weight: 700;
        }

        .links {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .links a {
            display: inline-block;
            text-decoration: none;
            border: 1px solid var(--line);
            background: var(--panel);
            color: var(--accent);
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border-bottom: 1px solid var(--line);
            padding: 11px 12px;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: #f8f9fd;
            color: #2f3a4f;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        code {
            background: #f3f5fb;
            border: 1px solid #e4e9f4;
            border-radius: 6px;
            padding: 2px 6px;
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge.ok {
            background: var(--ok-bg);
            color: var(--ok-text);
        }

        .badge.missing {
            background: var(--missing-bg);
            color: var(--missing-text);
        }

        .muted {
            color: var(--muted);
        }

        .commands {
            margin-top: 18px;
            background: #0f172a;
            color: #dbeafe;
            border-radius: 12px;
            padding: 14px;
            overflow: auto;
            font-size: 13px;
            line-height: 1.5;
        }

        .commands code {
            border: 0;
            background: transparent;
            color: inherit;
            padding: 0;
        }
    </style>
</head>
<body>
@php
    $quickLinks = [];
    foreach ($components as $component) {
        if (!empty($component['url'])) {
            $quickLinks[$component['url']] = $component['name'];
        }
    }
    $quickLinks['/up'] = 'Laravel Health';
@endphp
<main class="wrap">
    <section class="hero">
        <h1>{{ $appName }} Dev Dashboard</h1>
        <div class="meta">Home: <code>{{ url('/') }}</code></div>

        <div class="stats">
            <div class="stat">
                <div class="label">Installed Components</div>
                <div class="value">{{ $installedCount }}</div>
            </div>
            <div class="stat">
                <div class="label">Required In Sandbox</div>
                <div class="value">{{ $requiredCount }}</div>
            </div>
            <div class="stat">
                <div class="label">Local Components Found</div>
                <div class="value">{{ $totalCount }}</div>
            </div>
        </div>

        <ul class="links">
            @foreach($quickLinks as $path => $label)
                <li><a href="{{ $path }}">{{ $label }}</a></li>
            @endforeach
        </ul>
    </section>

    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>Component</th>
                    <th>Package</th>
                    <th>Status</th>
                    <th>Required</th>
                    <th>Symlinked</th>
                    <th>Navigate</th>
                </tr>
            </thead>
            <tbody>
                @foreach($components as $component)
                    <tr>
                        <td>{{ $component['name'] }}</td>
                        <td><code>{{ $component['package'] }}</code></td>
                        <td>
                            <span class="badge {{ $component['installed'] ? 'ok' : 'missing' }}">
                                {{ $component['installed'] ? 'Installed' : 'Not installed' }}
                            </span>
                        </td>
                        <td>{{ $component['required'] ? 'Yes' : 'No' }}</td>
                        <td>{{ $component['symlinked'] ? 'Yes' : 'No' }}</td>
                        <td>
                            @if(!empty($component['url']))
                                <a href="{{ $component['url'] }}">Open</a>
                            @else
                                <span class="muted">No route hint</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <div class="commands"><code>./scripts/prophoto sync
./scripts/prophoto access:bootstrap
cd sandbox && php artisan clear:all full --npm</code></div>
</main>
</body>
</html>
BLADE;

        if ($this->dryRun) {
            return '[DRY RUN] Dev dashboard scaffold would be installed';
        }

        if (!is_dir($viewDirectory)) {
            mkdir($viewDirectory, 0777, true);
        }

        $updated = [];

        $currentRoute = file_exists($routePath) ? (string) file_get_contents($routePath) : '';
        if ($currentRoute !== $routeTemplate . PHP_EOL) {
            file_put_contents($routePath, $routeTemplate . PHP_EOL);
            $updated[] = 'routes/web.php';
        }

        $currentView = file_exists($viewPath) ? (string) file_get_contents($viewPath) : '';
        if ($currentView !== $viewTemplate . PHP_EOL) {
            file_put_contents($viewPath, $viewTemplate . PHP_EOL);
            $updated[] = 'resources/views/dev-dashboard.blade.php';
        }

        if ($updated === []) {
            return 'Dev dashboard scaffold already up to date';
        }

        return 'Updated ' . implode(', ', $updated);
    }

    private function ensureSqliteDatabase(): string
    {
        $dbPath = $this->sandboxDir . '/database/database.sqlite';
        if (!$this->dryRun) {
            if (!is_dir(dirname($dbPath))) {
                mkdir(dirname($dbPath), 0777, true);
            }
            if (!file_exists($dbPath)) {
                touch($dbPath);
            }
        }

        return 'SQLite file ready';
    }

    private function ensureAppKey(): string
    {
        $env = $this->sandboxDir . '/.env';
        if (!file_exists($env)) {
            throw new RuntimeException('sandbox/.env not found.');
        }

        $content = (string) file_get_contents($env);
        $hasKey = preg_match('/^APP_KEY=.+$/m', $content) === 1;
        if ($hasKey) {
            return 'APP_KEY already set';
        }

        $this->runInSandbox('php artisan key:generate --force');
        return 'APP_KEY generated';
    }

    private function installNpmDependencies(): string
    {
        $this->ensureSandboxExists();

        if (is_dir($this->sandboxDir . '/node_modules')) {
            return 'node_modules already installed';
        }

        $this->runInSandbox('npm install');
        return 'npm dependencies installed';
    }

    private function buildSandboxAssets(bool $force): string
    {
        $this->ensureSandboxExists();
        $manifest = $this->sandboxDir . '/public/build/manifest.json';

        if (!$force && file_exists($manifest)) {
            return 'Sandbox assets already built';
        }

        $this->runInSandbox('npm run build');
        return 'Sandbox assets built';
    }

    // =========================================================================
    // DIAGNOSTIC CHECKS
    // =========================================================================

    private function checkPhpVersion(): array
    {
        $version = PHP_VERSION;
        $pass = version_compare($version, '8.2.0', '>=');
        $binary = PHP_BINARY;
        return ['pass' => $pass, 'message' => $version . ' (' . $binary . ')' . ($pass ? '' : ' (need 8.2+)')];
    }

    private function checkComposerVersion(): array
    {
        $output = trim($this->exec('composer --version 2>&1', true));
        $pass = str_contains($output, 'Composer');
        preg_match('/(\d+\.\d+\.\d+)/', $output, $matches);
        return ['pass' => $pass, 'message' => $matches[1] ?? 'Not found'];
    }

    private function checkNodeVersion(): array
    {
        $output = trim($this->exec('node --version 2>&1', true));
        $pass = str_starts_with($output, 'v');
        return ['pass' => $pass, 'message' => $output !== '' ? $output : 'Not found'];
    }

    private function checkExifTool(): array
    {
        $output = trim($this->exec('exiftool -ver 2>&1', true));
        $pass = is_numeric($output);
        return ['pass' => $pass, 'message' => $pass ? 'v' . $output : 'Not found'];
    }

    private function checkSandboxExists(): array
    {
        $exists = is_dir($this->sandboxDir) && file_exists($this->sandboxDir . '/artisan');
        return ['pass' => $exists, 'message' => $exists ? 'Found' : 'Not found'];
    }

    private function checkPathRepositories(): array
    {
        $composerJson = $this->sandboxDir . '/composer.json';
        if (!file_exists($composerJson)) {
            return ['pass' => false, 'message' => 'sandbox/composer.json not found'];
        }

        $content = json_decode((string) file_get_contents($composerJson), true);
        if (!is_array($content)) {
            return ['pass' => false, 'message' => 'Invalid composer.json'];
        }

        $found = false;
        foreach (($content['repositories'] ?? []) as $repo) {
            if (($repo['type'] ?? null) === 'path' && ($repo['url'] ?? null) === '../prophoto-*') {
                $found = (($repo['options']['symlink'] ?? false) === true);
            }
        }

        return [
            'pass' => $found,
            'message' => $found ? 'Configured with symlink=true' : 'Missing ../prophoto-* path repository',
        ];
    }

    private function checkSymlinks(): array
    {
        $vendorDir = $this->sandboxDir . '/vendor/prophoto';
        if (!is_dir($vendorDir)) {
            return ['pass' => false, 'message' => 'No prophoto packages installed'];
        }

        $symlinks = [];
        foreach (scandir($vendorDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $vendorDir . '/' . $entry;
            if (is_link($path)) {
                $symlinks[] = $entry;
            }
        }

        return [
            'pass' => count($symlinks) > 0,
            'message' => $symlinks === [] ? 'No symlinks found' : implode(', ', $symlinks),
        ];
    }

    // =========================================================================
    // UTILITY
    // =========================================================================

    private function buildWatchFingerprint(): string
    {
        $roots = array_merge(
            $this->discoverPackageDirectories(),
            [$this->baseDir . '/scripts']
        );

        $entries = [];
        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $entries = array_merge($entries, $this->collectWatchEntries($root));
        }

        sort($entries);
        return hash('sha256', implode("\n", $entries));
    }

    /**
     * @return string[]
     */
    private function collectWatchEntries(string $root): array
    {
        $entries = [];
        $iterator = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $iterator,
            function (SplFileInfo $item): bool {
                if ($item->isDir()) {
                    return !$this->isIgnoredWatchDirectory($item->getFilename());
                }
                return true;
            }
        );

        $files = new RecursiveIteratorIterator($filter);
        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if (!$this->isWatchableFile($path)) {
                continue;
            }

            $entries[] = $path . '|' . $file->getMTime() . '|' . $file->getSize();
        }

        return $entries;
    }

    private function isIgnoredWatchDirectory(string $directoryName): bool
    {
        $ignored = [
            '.git',
            '.idea',
            '.vscode',
            'vendor',
            'node_modules',
            'dist',
            'build',
            'coverage',
            'tmp',
            'output',
            'public',
        ];

        return in_array($directoryName, $ignored, true);
    }

    private function isWatchableFile(string $path): bool
    {
        $basename = basename($path);

        if (str_ends_with($basename, '.blade.php')) {
            return true;
        }

        if (in_array($basename, ['composer.json', 'package.json', 'package-lock.json', 'yarn.lock', 'pnpm-lock.yaml', '.env', '.env.example'], true)) {
            return true;
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $watchExtensions = [
            'php',
            'js',
            'jsx',
            'ts',
            'tsx',
            'css',
            'scss',
            'sass',
            'vue',
            'json',
            'md',
            'yaml',
            'yml',
            'xml',
            'sh',
        ];

        return in_array($extension, $watchExtensions, true);
    }

    private function publishAllAssets(): string
    {
        $this->ensureSandboxExists();

        $tags = [
            'ingest-assets',
            'ingest-config',
            'debug-config',
            'debug-views',
            'prophoto-gallery-config',
            'prophoto-gallery-views',
            'prophoto-gallery-migrations',
        ];

        foreach ($tags as $tag) {
            $this->runInSandbox("php artisan vendor:publish --tag={$tag} --force 2>&1 || true");
        }

        return 'Assets published';
    }

    /**
     * @param array<string, callable> $steps
     */
    private function runSteps(array $steps): int
    {
        $results = [];
        $startTime = microtime(true);

        foreach ($steps as $name => $step) {
            $stepStart = microtime(true);
            $success = true;
            $output = '';

            try {
                if ($this->dryRun) {
                    $output = "[DRY RUN] {$name}";
                } else {
                    $output = (string) $step();
                }
            } catch (Throwable $e) {
                $success = false;
                $output = $e->getMessage();
            }

            $durationMs = (int) round((microtime(true) - $stepStart) * 1000);
            $results[] = [
                'Step' => $name,
                'Status' => $success ? 'OK' : 'FAIL',
                'Time' => $durationMs . 'ms',
            ];

            if (!$success) {
                $this->error("Step failed: {$name}");
                if ($output !== '') {
                    echo $output . "\n";
                }
                break;
            }
        }

        $this->printTable(['Step', 'Status', 'Time'], $results);

        $failCount = count(array_filter($results, fn(array $row) => $row['Status'] === 'FAIL'));
        $totalMs = (int) round((microtime(true) - $startTime) * 1000);

        if ($failCount === 0) {
            $this->info("All steps completed in {$totalMs}ms.");
            return 0;
        }

        $this->error("{$failCount} step(s) failed.");
        return 1;
    }

    private function runInSandbox(string $command, bool $capture = false): string
    {
        $this->ensureSandboxExists();
        return $this->exec('cd ' . escapeshellarg($this->sandboxDir) . ' && ' . $command, $capture);
    }

    private function exec(string $command, bool $capture = false): string
    {
        if ($this->dryRun) {
            return '[DRY RUN] ' . $command;
        }

        if ($capture) {
            return (string) (shell_exec($command) ?? '');
        }

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException("Command failed (exit {$exitCode}): {$command}\n" . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function setEnvValue(string $content, string $key, string $value): string
    {
        $line = $key . '=' . $value;
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $content) === 1) {
            return (string) preg_replace($pattern, $line, $content, 1);
        }

        $trimmed = rtrim($content, "\n");
        return $trimmed . "\n" . $line . "\n";
    }

    private function deleteDirectory(string $dir): string
    {
        if (!is_dir($dir)) {
            return basename($dir) . ' already absent';
        }

        if ($this->dryRun) {
            return '[DRY RUN] delete ' . $dir;
        }

        $iterator = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);

        return basename($dir) . ' removed';
    }

    /**
     * @return string[]
     */
    private function discoverPackageDirectories(): array
    {
        $dirs = glob($this->baseDir . '/prophoto-*', GLOB_ONLYDIR) ?: [];
        sort($dirs);
        return $dirs;
    }

    private function isInteractive(): bool
    {
        if (function_exists('stream_isatty')) {
            return stream_isatty(STDIN);
        }

        return true;
    }

    private function confirm(string $question): bool
    {
        if ($this->assumeYes) {
            return true;
        }

        if (!$this->isInteractive()) {
            return false;
        }

        echo $question . ' [y/N]: ';
        $input = strtolower(trim((string) fgets(STDIN)));
        return in_array($input, ['y', 'yes'], true);
    }

    private function info(string $message): void
    {
        echo '[INFO] ' . $message . "\n";
    }

    private function warning(string $message): void
    {
        echo '[WARN] ' . $message . "\n";
    }

    private function error(string $message): void
    {
        echo '[ERROR] ' . $message . "\n";
    }

    /**
     * @param string[] $headers
     * @param array<int, array<string, string>> $rows
     */
    private function printTable(array $headers, array $rows): void
    {
        if ($rows === []) {
            echo "(no rows)\n";
            return;
        }

        $widths = [];
        foreach ($headers as $header) {
            $widths[$header] = strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($headers as $header) {
                $value = $row[$header] ?? '';
                $widths[$header] = max($widths[$header], strlen($value));
            }
        }

        $separator = '+';
        foreach ($headers as $header) {
            $separator .= str_repeat('-', $widths[$header] + 2) . '+';
        }

        echo $separator . "\n";
        echo '|';
        foreach ($headers as $header) {
            echo ' ' . str_pad($header, $widths[$header]) . ' |';
        }
        echo "\n";
        echo $separator . "\n";

        foreach ($rows as $row) {
            echo '|';
            foreach ($headers as $header) {
                echo ' ' . str_pad($row[$header] ?? '', $widths[$header]) . ' |';
            }
            echo "\n";
        }

        echo $separator . "\n";
    }
}

$workspace = new ProPhotoWorkspace();
exit($workspace->run($argv));
