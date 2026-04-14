<?php

namespace ProPhoto\Access\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ProPhoto\Access\AccessServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base TestCase for prophoto-access package tests.
 *
 * Sets up:
 *  - In-memory SQLite database
 *  - Spatie laravel-permission
 *  - ProPhoto Access service provider
 *  - Migrations
 */
abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            AccessServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('permission.models.permission', \Spatie\Permission\Models\Permission::class);
        $app['config']->set('permission.models.role', \Spatie\Permission\Models\Role::class);
        $app['config']->set('permission.cache.expiration_time', \DateInterval::createFromDateString('24 hours'));
        $app['config']->set('permission.column_names.role_pivot_key', 'role_id');
        $app['config']->set('permission.column_names.permission_pivot_key', 'permission_id');
        $app['config']->set('permission.column_names.model_morph_key', 'model_id');
        $app['config']->set('permission.column_names.team_foreign_key', 'team_id');
        $app['config']->set('permission.register_permission_check_method', true);
        $app['config']->set('permission.teams', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('vendor:publish', [
            '--provider' => 'Spatie\Permission\PermissionServiceProvider',
            '--force'    => true,
        ]);
        $this->artisan('migrate');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
