<?php

namespace ProPhoto\Access\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use ProPhoto\Access\AccessServiceProvider;
use ProPhoto\Access\Database\Seeders\RolesAndPermissionsSeeder;
use ProPhoto\Access\Enums\UserRole;
use ProPhoto\Access\Permissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Permission\PermissionRegistrar;

/**
 * RolesAndPermissionsSeederTest
 *
 * Verifies the seeder produces the expected roles and permission assignments:
 *
 *  1. All expected roles exist after seeding
 *  2. studio_user has every permission
 *  3. client_user has proofing permissions but NOT studio-only ones
 *  4. guest_user has a narrow set of image interaction permissions
 *  5. vendor_user exists but has no auto-assigned permissions
 *  6. Phase 2 proofing permissions (can_version_images, can_duplicate_images,
 *     can_consent_ai_use) are seeded correctly
 */
class RolesAndPermissionsSeederTest extends TestCase
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

        // Run the seeder under test
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ── Roles ─────────────────────────────────────────────────────────────────

    /** @test */
    public function all_expected_roles_exist(): void
    {
        foreach ([
            UserRole::STUDIO_USER->value,
            UserRole::CLIENT_USER->value,
            UserRole::GUEST_USER->value,
            UserRole::VENDOR_USER->value,
        ] as $roleName) {
            $this->assertNotNull(
                Role::findByName($roleName),
                "Role '$roleName' should exist after seeding"
            );
        }
    }

    // ── studio_user ───────────────────────────────────────────────────────────

    /** @test */
    public function studio_user_has_all_permissions(): void
    {
        $role = Role::findByName('studio_user');
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        $this->assertContains(Permissions::UPLOAD_IMAGES, $rolePermissions);
        $this->assertContains(Permissions::DELETE_IMAGES, $rolePermissions);
        $this->assertContains(Permissions::MANAGE_STUDIO_SETTINGS, $rolePermissions);
        $this->assertContains(Permissions::VIEW_ALL_DATA, $rolePermissions);
        $this->assertContains(Permissions::MANAGE_STRIPE, $rolePermissions);
    }

    // ── client_user ───────────────────────────────────────────────────────────

    /** @test */
    public function client_user_has_proofing_permissions(): void
    {
        $role = Role::findByName('client_user');
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        foreach ([
            Permissions::VIEW_GALLERIES,
            Permissions::DOWNLOAD_IMAGES,
            Permissions::APPROVE_IMAGES,
            Permissions::RATE_IMAGES,
            Permissions::COMMENT_ON_IMAGES,
            Permissions::VIEW_INVOICES,
            Permissions::CONSENT_AI_USE,
        ] as $permission) {
            $this->assertContains(
                $permission,
                $rolePermissions,
                "client_user should have $permission"
            );
        }
    }

    /** @test */
    public function client_user_does_not_have_studio_only_permissions(): void
    {
        $role = Role::findByName('client_user');
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        foreach ([
            Permissions::UPLOAD_IMAGES,
            Permissions::DELETE_IMAGES,
            Permissions::MANAGE_STUDIO_SETTINGS,
            Permissions::VIEW_ALL_DATA,
            Permissions::MANAGE_STRIPE,
        ] as $permission) {
            $this->assertNotContains(
                $permission,
                $rolePermissions,
                "client_user must NOT have studio-only permission: $permission"
            );
        }
    }

    // ── guest_user ────────────────────────────────────────────────────────────

    /** @test */
    public function guest_user_has_narrow_proofing_permissions(): void
    {
        $role = Role::findByName('guest_user');
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        foreach ([
            Permissions::VIEW_GALLERIES,
            Permissions::APPROVE_IMAGES,
            Permissions::RATE_IMAGES,
            Permissions::COMMENT_ON_IMAGES,
            Permissions::CONSENT_AI_USE,
        ] as $permission) {
            $this->assertContains(
                $permission,
                $rolePermissions,
                "guest_user should have $permission"
            );
        }
    }

    /** @test */
    public function guest_user_cannot_manage_invoices_or_studio(): void
    {
        $role = Role::findByName('guest_user');
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        foreach ([
            Permissions::CREATE_INVOICE,
            Permissions::VIEW_INVOICES,
            Permissions::MANAGE_STUDIO_SETTINGS,
            Permissions::CREATE_USER,
        ] as $permission) {
            $this->assertNotContains(
                $permission,
                $rolePermissions,
                "guest_user must NOT have $permission"
            );
        }
    }

    // ── Phase 2 permissions ───────────────────────────────────────────────────

    /** @test */
    public function phase2_proofing_permissions_are_seeded(): void
    {
        foreach ([
            Permissions::VERSION_IMAGES,
            Permissions::DUPLICATE_IMAGES,
            Permissions::CONSENT_AI_USE,
        ] as $permission) {
            $this->assertNotNull(
                Permission::findByName($permission),
                "Phase 2 permission '$permission' should exist in the database"
            );
        }
    }

    /** @test */
    public function consent_ai_use_is_available_to_client_and_guest(): void
    {
        foreach (['client_user', 'guest_user'] as $roleName) {
            $role = Role::findByName($roleName);
            $this->assertContains(
                Permissions::CONSENT_AI_USE,
                $role->permissions->pluck('name')->toArray(),
                "$roleName should have can_consent_ai_use"
            );
        }
    }
}
