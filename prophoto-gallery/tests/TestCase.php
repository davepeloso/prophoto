<?php

namespace ProPhoto\Gallery\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ProPhoto\Assets\AssetServiceProvider;
use ProPhoto\Interactions\InteractionsServiceProvider;

/**
 * Base TestCase for prophoto-gallery package tests.
 *
 * Sets up:
 *  - In-memory SQLite database
 *  - prophoto-assets service provider (for Asset + AssetDerivative models/migrations)
 *  - Minimal gallery provider (loads migrations only — skips Ingest event listener
 *    which requires prophoto-ingest, not installed in the test sandbox)
 *  - All package migrations run before each test
 */
abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            AssetServiceProvider::class,
            InteractionsServiceProvider::class,
            GalleryTestServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Deterministic app key for tests — required by the web middleware
        // group (EncryptCookies, StartSession) used on gallery viewer routes.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Disable the legacy asset_spine read_switch in tests —
        // we use Eloquent relations directly
        $app['config']->set('prophoto-gallery.asset_spine.read_switch', false);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }
}
