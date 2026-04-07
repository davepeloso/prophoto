<?php

namespace ProPhoto\Debug\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class UninstallCommand extends Command
{
    protected $signature = 'debug:uninstall
                            {--drop-tables : Drop the debug database tables}
                            {--remove-config : Remove published config file}
                            {--remove-views : Remove published view files}
                            {--all : Remove everything (tables, config, views)}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Clean up prophoto-debug package from this application';

    public function handle(): int
    {
        $this->info('ProPhoto Debug Uninstaller');
        $this->line('');

        $dropTables = $this->option('drop-tables') || $this->option('all');
        $removeConfig = $this->option('remove-config') || $this->option('all');
        $removeViews = $this->option('remove-views') || $this->option('all');
        $force = $this->option('force');

        if (! $dropTables && ! $removeConfig && ! $removeViews) {
            $this->warn('No cleanup options specified. Use one or more of:');
            $this->line('  --drop-tables    Drop debug_ingest_traces and debug_config_snapshots tables');
            $this->line('  --remove-config  Remove published config/debug.php file');
            $this->line('  --remove-views   Remove published views from resources/views/vendor/debug');
            $this->line('  --all            Remove everything');
            $this->line('');
            $this->line('Add --force to skip confirmation prompts.');

            return self::SUCCESS;
        }

        $this->line('The following actions will be performed:');
        if ($dropTables) {
            $this->line('  - Drop database tables: debug_ingest_traces, debug_config_snapshots');
        }
        if ($removeConfig) {
            $this->line('  - Remove config file: config/debug.php');
        }
        if ($removeViews) {
            $this->line('  - Remove view directory: resources/views/vendor/debug');
        }
        $this->line('');

        if (! $force && ! $this->confirm('Do you want to proceed?')) {
            $this->info('Uninstall cancelled.');

            return self::SUCCESS;
        }

        // Drop tables
        if ($dropTables) {
            $this->dropTables();
        }

        // Remove config
        if ($removeConfig) {
            $this->removeConfig();
        }

        // Remove views
        if ($removeViews) {
            $this->removeViews();
        }

        $this->line('');
        $this->info('Cleanup complete!');
        $this->line('');
        $this->line('To fully remove the package, run:');
        $this->line('  composer remove prophoto/debug');
        $this->line('');
        $this->line('Then clear caches:');
        $this->line('  php artisan optimize:clear');

        return self::SUCCESS;
    }

    protected function dropTables(): void
    {
        $this->info('Dropping database tables...');

        $tables = ['debug_ingest_traces', 'debug_config_snapshots'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
                $this->line("  Dropped: {$table}");
            } else {
                $this->line("  Skipped: {$table} (does not exist)");
            }
        }
    }

    protected function removeConfig(): void
    {
        $this->info('Removing config file...');

        $configPath = config_path('debug.php');

        if (file_exists($configPath)) {
            unlink($configPath);
            $this->line("  Removed: {$configPath}");
        } else {
            $this->line('  Skipped: config/debug.php (does not exist)');
        }
    }

    protected function removeViews(): void
    {
        $this->info('Removing published views...');

        $viewPath = resource_path('views/vendor/debug');

        if (is_dir($viewPath)) {
            $this->deleteDirectory($viewPath);
            $this->line("  Removed: {$viewPath}");
        } else {
            $this->line('  Skipped: views/vendor/debug (does not exist)');
        }
    }

    protected function deleteDirectory(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}