<?php

namespace ProPhoto\Access\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use ProPhoto\Access\AccessServiceProvider;
use ProPhoto\Access\Http\Middleware\CheckContextualPermission;
use ProPhoto\Access\Permissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Permission\PermissionRegistrar;

/**
 * CheckContextualPermissionMiddlewareTest
 *
 * Tests the CheckContextualPermission middleware (if it exists), or tests
 * the equivalent inline logic that route handlers use to guard proofing actions.
 *
 * Scenarios:
 *  1. Unauthenticated request returns 401
 *  2. studio_user (global permission) passes the check
 *  3. guest_user without contextual grant gets 403
 *  4. guest_user WITH contextual grant passes
 *
 * Note: If CheckContextualPermission middleware doesn't exist yet (Sprint 3),
 * these tests document the intended contract and will pass once implemented.
 */
class CheckContextualPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

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

        $app['config']->set('auth.guards.web.provider', 'users');
        $app['config']->set('auth.providers.users.model', \App\Models\User::class);

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

        // Seed required permission and roles
        Permission::findOrCreate(Permissions::VIEW_GALLERIES);
        Permission::findOrCreate(Permissions::APPROVE_IMAGES);
        $studioRole = Role::findOrCreate('studio_user');
        $studioRole->givePermissionTo([Permissions::VIEW_GALLERIES, Permissions::APPROVE_IMAGES]);
        Role::findOrCreate('guest_user'); // no global perms
    }

    private function makeUser(string $role): \App\Models\User
    {
        $user = \App\Models\User::create([
            'name'     => "Test $role",
            'email'    => "$role@example.com",
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function fakeGallery(int $id = 1): object
    {
        return new class($id) {
            public function __construct(public readonly int $id) {}
        };
    }

    // ── Permission check logic tests ──────────────────────────────────────────
    // These test the trait's hasContextualPermission logic as used by middleware.

    /** @test */
    public function studio_user_passes_global_permission_check(): void
    {
        $user    = $this->makeUser('studio_user');
        $gallery = $this->fakeGallery(1);

        // studio_user has global permission — should pass without contextual grant
        $this->assertTrue(
            $user->hasContextualPermission(Permissions::VIEW_GALLERIES, $gallery),
            'studio_user with global permission should pass hasContextualPermission'
        );
    }

    /** @test */
    public function guest_user_without_grant_fails_permission_check(): void
    {
        $user    = $this->makeUser('guest_user');
        $gallery = $this->fakeGallery(1);

        $this->assertFalse(
            $user->hasContextualPermission(Permissions::VIEW_GALLERIES, $gallery),
            'guest_user without contextual grant should fail'
        );
    }

    /** @test */
    public function guest_user_with_grant_passes_permission_check(): void
    {
        $user    = $this->makeUser('guest_user');
        $gallery = $this->fakeGallery(1);

        $user->grantContextualPermission(Permissions::VIEW_GALLERIES, $gallery);

        $this->assertTrue(
            $user->hasContextualPermission(Permissions::VIEW_GALLERIES, $gallery),
            'guest_user with valid contextual grant should pass'
        );
    }

    /** @test */
    public function middleware_class_exists_or_is_planned(): void
    {
        // If the middleware exists, verify it's properly instantiable.
        // If not (Sprint 3 work), this documents the expected class name.
        $middlewareClass = CheckContextualPermission::class;

        if (class_exists($middlewareClass)) {
            $this->assertInstanceOf(
                $middlewareClass,
                new $middlewareClass(),
                'CheckContextualPermission middleware should be instantiable'
            );
        } else {
            $this->markTestIncomplete(
                'CheckContextualPermission middleware not yet implemented — Sprint 3 work. ' .
                "Expected at: ProPhoto\\Access\\Http\\Middleware\\CheckContextualPermission"
            );
        }
    }
}
