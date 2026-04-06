<?php

namespace ProPhoto\Debug\Services;

use ProPhoto\Debug\Models\ConfigSnapshot;

class ConfigRecorder
{
    /**
     * Create a new configuration snapshot.
     */
    public function snapshot(string $name, ?string $description = null): ConfigSnapshot
    {
        return ConfigSnapshot::create([
            'name' => $name,
            'description' => $description,
            'config_data' => $this->captureIngestConfig(),
            'queue_config' => $this->captureQueueConfig(),
            'supervisor_config' => $this->captureSupervisorConfig(),
            'environment' => $this->captureEnvironment(),
        ]);
    }

    /**
     * Capture the ingest package configuration.
     */
    public function captureIngestConfig(): array
    {
        return [
            'storage' => config('ingest.storage', []),
            'schema' => config('ingest.schema', []),
            'exiftool' => [
                'binary' => config('ingest.exiftool.binary'),
                'timeout' => config('ingest.exiftool.timeout'),
                'speed_mode' => config('ingest.exiftool.speed_mode'),
                'preview_tags' => config('ingest.exiftool.preview_tags'),
                'max_preview_size' => config('ingest.exiftool.max_preview_size'),
                'fallback_to_php' => config('ingest.exiftool.fallback_to_php'),
            ],
            'exif' => [
                'thumbnail' => config('ingest.exif.thumbnail', []),
                'preview' => config('ingest.exif.preview', []),
                'final' => config('ingest.exif.final', []),
            ],
            'cleanup' => config('ingest.cleanup', []),
        ];
    }

    /**
     * Capture the queue configuration.
     */
    public function captureQueueConfig(): array
    {
        $queueConfig = config('queue', []);

        return [
            'default' => $queueConfig['default'] ?? null,
            'connections' => collect($queueConfig['connections'] ?? [])
                ->map(function ($connection, $name) {
                    return [
                        'driver' => $connection['driver'] ?? null,
                        'queue' => $connection['queue'] ?? null,
                        'retry_after' => $connection['retry_after'] ?? null,
                        'after_commit' => $connection['after_commit'] ?? null,
                    ];
                })
                ->toArray(),
            'batching' => $queueConfig['batching'] ?? null,
            'failed' => [
                'driver' => $queueConfig['failed']['driver'] ?? null,
                'database' => $queueConfig['failed']['database'] ?? null,
                'table' => $queueConfig['failed']['table'] ?? null,
            ],
        ];
    }

    /**
     * Capture supervisor configuration from config files.
     */
    public function captureSupervisorConfig(): ?array
    {
        $configs = [];
        $paths = config('debug.supervisor_paths', [
            '/etc/supervisor/conf.d/',
            '/etc/supervisord.d/',
        ]);

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $files = glob($path.'*.conf');
                foreach ($files as $file) {
                    $parsed = $this->parseSupervisorConfig($file);
                    if (! empty($parsed)) {
                        $configs = array_merge($configs, $parsed);
                    }
                }
            }
        }

        // Also try to capture Horizon configuration if available
        $horizonConfig = $this->captureHorizonConfig();
        if ($horizonConfig) {
            $configs['horizon'] = $horizonConfig;
        }

        return ! empty($configs) ? $configs : null;
    }

    /**
     * Parse a supervisor config file.
     */
    protected function parseSupervisorConfig(string $file): array
    {
        if (! file_exists($file) || ! is_readable($file)) {
            return [];
        }

        $content = file_get_contents($file);
        $programs = [];
        $currentProgram = null;
        $currentConfig = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || str_starts_with($line, ';') || str_starts_with($line, '#')) {
                continue;
            }

            // Check for program section
            if (preg_match('/^\[program:(.+)\]$/', $line, $matches)) {
                if ($currentProgram !== null) {
                    $programs[$currentProgram] = $currentConfig;
                }
                $currentProgram = $matches[1];
                $currentConfig = [];
                continue;
            }

            // Parse key=value pairs
            if ($currentProgram !== null && str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Only capture relevant settings
                if (in_array($key, [
                    'command',
                    'process_name',
                    'numprocs',
                    'autostart',
                    'autorestart',
                    'startsecs',
                    'stopwaitsecs',
                    'stdout_logfile',
                    'stderr_logfile',
                ])) {
                    // Convert numeric values
                    if (is_numeric($value)) {
                        $value = (int) $value;
                    } elseif ($value === 'true') {
                        $value = true;
                    } elseif ($value === 'false') {
                        $value = false;
                    }

                    $currentConfig[$key] = $value;
                }
            }
        }

        // Don't forget the last program
        if ($currentProgram !== null) {
            $programs[$currentProgram] = $currentConfig;
        }

        return $programs;
    }

    /**
     * Capture Laravel Horizon configuration if available.
     */
    protected function captureHorizonConfig(): ?array
    {
        if (! config()->has('horizon')) {
            return null;
        }

        return [
            'driver' => config('horizon.driver'),
            'prefix' => config('horizon.prefix'),
            'use' => config('horizon.use'),
            'environments' => collect(config('horizon.environments', []))
                ->map(function ($supervisors) {
                    return collect($supervisors)->map(function ($supervisor) {
                        return [
                            'connection' => $supervisor['connection'] ?? null,
                            'queue' => $supervisor['queue'] ?? null,
                            'balance' => $supervisor['balance'] ?? null,
                            'processes' => $supervisor['processes'] ?? null,
                            'tries' => $supervisor['tries'] ?? null,
                            'timeout' => $supervisor['timeout'] ?? null,
                            'maxProcesses' => $supervisor['maxProcesses'] ?? null,
                            'minProcesses' => $supervisor['minProcesses'] ?? null,
                        ];
                    })->toArray();
                })
                ->toArray(),
        ];
    }

    /**
     * Capture relevant environment variables.
     */
    public function captureEnvironment(): array
    {
        $envVars = config('debug.capture_environment', []);
        $captured = [];

        foreach ($envVars as $var) {
            $value = env($var);
            if ($value !== null) {
                // Sanitize sensitive values (just in case)
                if ($this->isSensitiveVar($var)) {
                    $captured[$var] = '[REDACTED]';
                } else {
                    $captured[$var] = $value;
                }
            }
        }

        // Add some system info
        $captured['_php_version'] = PHP_VERSION;
        $captured['_laravel_version'] = app()->version();
        $captured['_app_env'] = app()->environment();

        return $captured;
    }

    /**
     * Check if a variable name looks sensitive.
     */
    protected function isSensitiveVar(string $var): bool
    {
        $sensitivePatterns = [
            'PASSWORD',
            'SECRET',
            'KEY',
            'TOKEN',
            'CREDENTIAL',
            'AUTH',
        ];

        $var = strtoupper($var);

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($var, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all snapshots.
     */
    public function getSnapshots(): \Illuminate\Database\Eloquent\Collection
    {
        return ConfigSnapshot::orderByDesc('created_at')->get();
    }

    /**
     * Get a specific snapshot by ID.
     */
    public function getSnapshot(int $id): ?ConfigSnapshot
    {
        return ConfigSnapshot::find($id);
    }

    /**
     * Compare two snapshots.
     */
    public function compareSnapshots(int $id1, int $id2): ?array
    {
        $snapshot1 = $this->getSnapshot($id1);
        $snapshot2 = $this->getSnapshot($id2);

        if (! $snapshot1 || ! $snapshot2) {
            return null;
        }

        return $snapshot1->diff($snapshot2);
    }

    /**
     * Delete a snapshot by ID.
     */
    public function deleteSnapshot(int $id): bool
    {
        return ConfigSnapshot::destroy($id) > 0;
    }
}
