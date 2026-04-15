<?php

namespace ProPhoto\Notifications\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Orchestra\Testbench\TestCase as BaseTestCase;
use ProPhoto\Notifications\NotificationsServiceProvider;

/**
 * Base TestCase for prophoto-notifications package tests.
 *
 * Uses in-memory SQLite. Loads only what the notification
 * package needs — no gallery/asset providers required since
 * the listener receives a plain event object (IDs, not models).
 */
abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            NotificationsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('app.url', 'http://prophoto-app.test');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Point auth model to our stub user
        $app['config']->set('auth.providers.users.model', TestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        // Create the test users table (not owned by this package)
        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->unsignedBigInteger('studio_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        // Create a minimal studios table (referenced by messages FK)
        $this->app['db']->connection()->getSchemaBuilder()->create('studios', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        // Create minimal galleries table (referenced by messages FK)
        $this->app['db']->connection()->getSchemaBuilder()->create('galleries', function ($table) {
            $table->id();
            $table->unsignedBigInteger('studio_id');
            $table->string('subject_name')->nullable();
            $table->timestamps();
        });

        // Create minimal images table (referenced by messages FK)
        $this->app['db']->connection()->getSchemaBuilder()->create('images', function ($table) {
            $table->id();
            $table->unsignedBigInteger('gallery_id');
            $table->timestamps();
        });
    }

    /**
     * Create a test studio and return its ID.
     */
    protected function makeStudio(string $name = 'Test Studio'): int
    {
        return (int) $this->app['db']->connection()->table('studios')->insertGetId([
            'name'       => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Create a test user and return the model.
     */
    protected function makeUser(int $studioId, array $overrides = []): TestUser
    {
        $id = $this->app['db']->connection()->table('users')->insertGetId(array_merge([
            'studio_id'  => $studioId,
            'name'       => 'Test User',
            'email'      => 'photographer@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return TestUser::find($id);
    }
}

/**
 * Minimal user model for tests.
 */
class TestUser extends Authenticatable
{
    protected $table = 'users';
    protected $guarded = [];
}
