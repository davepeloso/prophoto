<?php

namespace ProPhoto\Ingest\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * ExiftoolDoctor - Diagnostic command for ExifTool configuration
 *
 * Helps diagnose "exiftool: not found" errors by checking:
 * - PHP environment (SAPI, user, config values)
 * - PATH configuration (current and with prefix)
 * - ExifTool binary availability and version
 */
class ExiftoolDoctor extends Command
{
    protected $signature = 'exiftool:doctor';

    protected $description = 'Diagnose ExifTool configuration and availability';

    public function handle(): int
    {
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                  ExifTool Configuration Doctor                 ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // 1. PHP Environment
        $this->section('PHP Environment');
        $this->line('  SAPI:           ' . php_sapi_name());
        $this->line('  User:           ' . get_current_user() . ' (UID: ' . getmyuid() . ')');
        $this->line('  PHP Version:    ' . PHP_VERSION);
        $this->newLine();

        // 2. Configuration
        $this->section('ExifTool Configuration');
        $bin = config('exiftool.bin', 'exiftool');
        $pathPrefix = config('exiftool.path_prefix');

        $this->line('  Binary (EXIFTOOL_BIN):        ' . $bin);
        $this->line('  Path Prefix (PATH_PREFIX):    ' . ($pathPrefix ?: '(not set)'));
        $this->newLine();

        // 3. PATH Information
        $this->section('PATH Environment');
        $currentPath = getenv('PATH') ?: '';
        $this->line('  Current PATH:');
        $this->line('    ' . $this->truncatePath($currentPath, 100));
        $this->newLine();

        if (!empty($pathPrefix)) {
            $effectivePath = rtrim($pathPrefix, ':') . ($currentPath ? (':' . $currentPath) : '');
            $this->line('  Effective PATH (with prefix):');
            $this->line('    ' . $this->truncatePath($effectivePath, 100));
            $this->newLine();
        }

        // 4. Binary Check
        $this->section('Binary Check');

        // Try which command first (to see what's in PATH)
        if (file_exists('/usr/bin/which')) {
            $this->line('  Running: which exiftool');
            $whichProcess = new Process(['/usr/bin/which', 'exiftool']);
            $whichProcess->run();

            if ($whichProcess->isSuccessful()) {
                $location = trim($whichProcess->getOutput());
                $this->line('    ✓ Found in PATH: ' . $location);
            } else {
                $this->line('    ✗ Not found in PATH');
            }
            $this->newLine();
        }

        // Check if configured binary path exists (if absolute)
        if (str_starts_with($bin, '/')) {
            $exists = file_exists($bin);
            $readable = $exists && is_readable($bin);

            $this->line('  Checking absolute path: ' . $bin);
            $this->line('    Exists:     ' . ($exists ? '✓ Yes' : '✗ No'));
            if ($exists) {
                $this->line('    Readable:   ' . ($readable ? '✓ Yes' : '✗ No'));
                $this->line('    Executable: ' . (is_executable($bin) ? '✓ Yes' : '✗ No'));
            }
            $this->newLine();
        }

        // 5. ExifTool Execution Test
        $this->section('ExifTool Execution Test');
        $this->line('  Running: ' . $bin . ' -ver');
        $this->newLine();

        // Build environment with PATH prefix if configured
        $env = null;
        if (!empty($pathPrefix)) {
            $augmentedPath = rtrim($pathPrefix, ':') . ($currentPath ? (':' . $currentPath) : '');
            $env = ['PATH' => $augmentedPath];
        }

        // Run exiftool -ver
        $command = [$bin, '-ver'];
        $process = new Process($command, null, $env);
        $process->setTimeout(10);

        try {
            $process->run();

            $exitCode = $process->getExitCode();
            $stdout = trim($process->getOutput());
            $stderr = trim($process->getErrorOutput());

            $this->line('  Exit Code:  ' . $exitCode);

            if ($exitCode === 0) {
                $this->line('  STDOUT:     ' . ($stdout ?: '(empty)'));
                $this->line('  Version:    ' . $stdout);
                $this->newLine();
                $this->info('✓ ExifTool is working correctly!');
                return self::SUCCESS;
            } elseif ($exitCode === 127) {
                $this->line('  STDERR:     ' . ($stderr ?: '(empty)'));
                $this->newLine();
                $this->error('✗ ExifTool binary not found (exit code 127)');
                $this->newLine();
                $this->showRecommendations();
                return self::FAILURE;
            } else {
                $this->line('  STDOUT:     ' . ($stdout ?: '(empty)'));
                $this->line('  STDERR:     ' . ($stderr ?: '(empty)'));
                $this->newLine();
                $this->error('✗ ExifTool execution failed (exit code ' . $exitCode . ')');
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('✗ Process execution failed: ' . $e->getMessage());
            $this->newLine();
            $this->showRecommendations();
            return self::FAILURE;
        }
    }

    /**
     * Display section header
     */
    protected function section(string $title): void
    {
        $this->line('┌─ ' . $title . ' ' . str_repeat('─', max(0, 60 - strlen($title))));
    }

    /**
     * Truncate long PATH strings for display
     */
    protected function truncatePath(string $path, int $length): string
    {
        if (strlen($path) <= $length) {
            return $path;
        }

        return substr($path, 0, $length) . '... (truncated)';
    }

    /**
     * Show recommendations for fixing common issues
     */
    protected function showRecommendations(): void
    {
        $this->info('Recommendations:');
        $this->newLine();
        $this->line('1. Set absolute path in .env:');
        $this->line('   EXIFTOOL_BIN=/usr/local/bin/exiftool');
        $this->newLine();
        $this->line('2. Or set PATH prefix in .env:');
        $this->line('   EXIFTOOL_PATH_PREFIX=/usr/local/bin');
        $this->newLine();
        $this->line('3. After updating .env, clear config cache:');
        $this->line('   php artisan config:clear');
        $this->newLine();
        $this->line('4. Restart PHP-FPM/Horizon if running:');
        $this->line('   php artisan horizon:terminate');
        $this->newLine();
        $this->line('5. Find your exiftool installation:');
        $this->line('   which exiftool');
        $this->line('   locate exiftool');
    }
}
