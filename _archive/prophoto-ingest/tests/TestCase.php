<?php

namespace ProPhoto\Ingest\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestbench;
use ProPhoto\Ingest\IngestServiceProvider;

abstract class TestCase extends BaseTestbench
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Load test helpers
        require_once __DIR__ . '/Support/helpers.php';
    }

    /**
     * Register package service providers
     */
    protected function getPackageProviders($app): array
    {
        return [
            IngestServiceProvider::class,
        ];
    }

    /**
     * Define environment setup
     */
    protected function defineEnvironment($app): void
    {
        // Load package config
        $app['config']->set('ingest', require __DIR__ . '/../config/ingest.php');
        $app['config']->set('ingest-tests', require __DIR__ . '/../config/ingest-tests.php');

        // Configure test database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Configure test disks
        $app['config']->set('filesystems.disks.test-temp', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/ingest-temp'),
        ]);

        $app['config']->set('filesystems.disks.test-final', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/images'),
        ]);

        // Use test disks for ingest
        $app['config']->set('ingest.storage.temp_disk', 'test-temp');
        $app['config']->set('ingest.storage.final_disk', 'test-final');

        // Configure test queue
        $app['config']->set('queue.default', 'sync');
    }

    /**
     * Load package migrations
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Clean up test files after each test
     */
    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    protected function cleanupTestFiles(): void
    {
        $paths = [
            storage_path('framework/testing/ingest-temp'),
            storage_path('framework/testing/images'),
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            }
        }
    }

    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
