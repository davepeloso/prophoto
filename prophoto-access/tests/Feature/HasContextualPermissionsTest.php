<?php

namespace ProPhoto\Access\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Orchestra\Testbench\TestCase;
use ProPhoto\Access\AccessServiceProvider;
use ProPhoto\Access\Models\PermissionContext;
use ProPhoto\Access\Permissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Permission\PermissionRegistrar;

/**
 * HasContextualPermissionsTest
 *
 * Tests the core RBAC contextual permission system:
 *
 *  1. studio_user has a global permission → hasContextualPermission returns true
 *     (no gallery context needed)
 *  2. guest_user has no global permission but has a contextual grant for gallery A
 *     → hasContextualPermission returns true for gallery A
 *  3. guest_user does NOT have a contextual grant for gallery B
 *     → hasContextualPermission returns false for gallery B
 *  4. Expired contextual grant is treated as no grant
 *  5. grantContextualPermission creates a PermissionContext record
 *  6. revokeContextualPermission removes the record
 *  7. syncContextualPermissions replaces the full set
 */
class HasContextualPermissionsTest extends TestCase
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
        // In-memory SQLite for tests
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

        // Run Spatie migrations
        $this->artisan('vendor:publish', [
            '--provider' => 'Spatie\Permission\PermissionServiceProvider',
            '--force'    => true,
        ]);
        $this->artisan('migrate');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(string $role): \App\Models\User
    {
        $user = \App\Models\User::create([
            'name'     => "Test $role",
            'email'    => "$role@test.com",
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** Minimal fake Gallery context object — no real Gallery table needed */
    private function fakeGallery(int $id = 1): object
    {
        return new class($id) {
            public function __construct(public readonly int $id) {}
        };
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /** @test */
    public function studio_user_has_global_permission_via_role(): void
    {
        Permission::findOrCreate(Permissions::UPLOAD_IMAGES);
        $studioRole = Role::findOrCreate('studio_user');
        $studioRole->givePermissionTo(Permissions::UPLOAD_IMAGES);

        $user = $this->makeUser('studio_user');

        $this->assertTrue(
            $user->hasPermissionTo(Permissions::UPLOAD_IMAGES),
            'studio_user should have can_upload_images via role'
        );
    }

    /** @test */
    public function guest_user_has_contextual_permission_for_correct_gallery(): void
    {
        Permission::findOrCreate(Permissions::VIEW_GALLERIES);
        Role::findOrCreate('guest_user'); // no global perms

        $user    = $this->makeUser('guest_user');
        $gallery = $this->fakeGallery(42);

        // Grant contextually
        $user->grantContextualPermission(Permissions::VIEW_GALLERIES, $gallery);

        $this->assertTrue(
            $user->hasContextualPermission(Permissions::VIEW_GALLERIES, $gallery),
            'guest_user should have contextual can_view_gallery for gallery 42'
        );
    }

    /** @test */
    public function guest_user_has_no_permission_for_different_gallery(): void
    {
        Permission::findOrCreate(Permissions::VIEW_GALLERIES);
        Role::findOrCreate('guest_user');

        $user      = $this->makeUser('guest_user');
        $galleryA  = $this->fakeGallery(42);
        $galleryB  = $this->fakeGallery(99);

        $user->grantContextualPermission(Permissions::VIEW_GALLERIES, $galleryA);

        $this->assertFalse(
            $user->hasContextualPermission(Permissions::VIEW_GALLERIES, $galleryB),
            'guest_user must NOT have can_view_gallery for a different gallery'
        );
    }

    /** @test */
    public function expired_contextual_grant_is_not_valid(): void
    {
        Permission::findOrCreate(Permissions::VIEW_GALLERIES);
        Role::findOrCreate('guest_user');

        $user    = $this->makeUser('guest_user');
        $gallery = $this->fakeGallery(1);

        // Grant with expiry in the past
        $user->grantContextualPermission(
            Permissions::VIEW_GALLERIES,
            $gallery,
            now()->subDay()
        );

        $this->assertFalse(
            $user->hasContextualPermission(Permissions::VIEW_GALLERIES, $gallery),
            'Expired contextual grant should return false'
        );
    }

    /** @test */
    public function grant_creates_permission_context_record(): void
    {
        Permission::findOrCreate(Permissions::APPROVE_IMAGES);
        Role::findOrCreate('guest_user');

        $user    = $this->makeUser('guest_user');
        $gallery = $this->fakeGallery(5);

        $user->grantContextualPermission(Permissions::APPROVE_IMAGES, $gallery);

        $this->assertDatabaseHas('permission_contexts', [
            'user_id'          => $user->id,
            'contextable_id'   => 5,
        ]);
    }

    /** @test */
    public function revoke_removes_permission_context_record(): void
    {
        Permission::findOrCreate(Permissions::APPROVE_IMAGES);
        Role::findOrCreate('guest_user');

        $user    = $this->makeUser('guest_user');
        $gallery = $this->fakeGallery(5);

        $user->grantContextualPermission(Permissions::APPROVE_IMAGES, $gallery);
        $user->revokeContextualPermission(Permissions::APPROVE_IMAGES, $gallery);

        $this->assertFalse(
            $user->hasContextualPermission(Permissions::APPROVE_IMAGES, $gallery),
            'After revoke, permission should be gone'
        );
    }

    /** @test */
    public function sync_replaces_all_contextual_permissions(): void
    {
        Permission::findOrCreate(Permissions::VIEW_GALLERIES);
        Permission::findOrCreate(Permissions::APPROVE_IMAGES);
        Permission::findOrCreate(Permissions::RATE_IMAGES);
        Role::findOrCreate('guest_user');

        $user    = $this->makeUser('guest_user');
        $gallery = $this->fakeGallery(7);

        // Start with two permissions
        $user->grantContextualPermissions(
            [Permissions::VIEW_GALLERIES, Permissions::APPROVE_IMAGES],
            $gallery
        );

        // Sync to just one
        $user->syncContextualPermissions(
            [Permissions::RATE_IMAGES],
            $gallery
        );

        $active = $user->getContextualPermissions($gallery);

        $this->assertContains(Permissions::RATE_IMAGES, $active);
        $this->assertNotContains(Permissions::VIEW_GALLERIES, $active);
        $this->assertNotContains(Permissions::APPROVE_IMAGES, $active);
    }
}
