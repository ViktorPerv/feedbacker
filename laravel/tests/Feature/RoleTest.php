<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

class RoleTest extends TestCase
{
    public function test_the_seeder_creates_every_role_and_permission(): void
    {
        foreach (RoleEnum::values() as $role) {
            $this->assertTrue(
                Role::where('name', $role)->exists(),
                "Role [{$role}] is missing."
            );
        }

        foreach (PermissionEnum::values() as $permission) {
            $this->assertTrue(
                Permission::where('name', $permission)->exists(),
                "Permission [{$permission}] is missing."
            );
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(count(RoleEnum::values()), Role::count());
        $this->assertSame(count(PermissionEnum::values()), Permission::count());
    }

    public function test_the_admin_role_holds_every_permission(): void
    {
        $admin = Role::findByName(RoleEnum::Admin->value);

        $this->assertEqualsCanonicalizing(
            PermissionEnum::values(),
            $admin->permissions->pluck('name')->all(),
        );
    }

    public function test_the_user_role_holds_no_oversight_permission(): void
    {
        $user = Role::findByName(RoleEnum::User->value);

        foreach (PermissionEnum::oversight() as $permission) {
            $this->assertFalse(
                $user->hasPermissionTo($permission),
                "Role [user] must not hold [{$permission}]."
            );
        }
    }

    public function test_the_fixture_accounts_carry_the_expected_roles(): void
    {
        $this->assertTrue($this->fixtureAdmin()->hasRole(RoleEnum::Admin->value));
        $this->assertFalse($this->fixtureAdmin()->hasRole(RoleEnum::User->value));

        $this->assertTrue($this->fixtureOwner()->hasRole(RoleEnum::User->value));
        $this->assertFalse($this->fixtureOwner()->hasRole(RoleEnum::Admin->value));

        $this->assertTrue($this->fixtureStranger()->hasRole(RoleEnum::User->value));
    }

    public function test_a_new_account_receives_the_default_role(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->hasRole(RoleEnum::User->value));
        $this->assertFalse($user->hasRole(RoleEnum::Admin->value));
    }

    public function test_registration_assigns_the_default_role(): void
    {
        $this->post(route('register'), [
            'name' => 'Fresh User',
            'email' => 'fresh@feedbacker.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'fresh@feedbacker.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(RoleEnum::User->value));
    }

    public function test_an_existing_role_is_not_overwritten_on_creation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->hasRole(RoleEnum::Admin->value));
        $this->assertFalse($admin->hasRole(RoleEnum::User->value));
        $this->assertSame(1, $admin->roles()->count());
    }

    public function test_the_admin_seeder_assigns_the_admin_role(): void
    {
        config([
            'feedbacker.admin.name' => 'Root',
            'feedbacker.admin.email' => 'root@feedbacker.test',
            'feedbacker.admin.password' => 'sup3r-secret',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'root@feedbacker.test')->first();

        $this->assertNotNull($admin);
        $this->assertSame('Root', $admin->name);
        $this->assertTrue($admin->hasRole(RoleEnum::Admin->value));
        $this->assertTrue(password_verify('sup3r-secret', $admin->password));
    }

    public function test_the_admin_seeder_is_idempotent(): void
    {
        config([
            'feedbacker.admin.email' => 'root@feedbacker.test',
            'feedbacker.admin.password' => 'sup3r-secret',
        ]);

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', 'root@feedbacker.test')->count());
    }

    public function test_the_user_model_no_longer_exposes_an_is_admin_flag(): void
    {
        $user = $this->fixtureAdmin();

        $this->assertFalse(
            array_key_exists('is_admin', $user->getAttributes()),
            'The is_admin column should be gone from the users table.'
        );
        $this->assertFalse(method_exists($user, 'isAdmin'));
    }

    public function test_the_shared_auth_payload_hides_roles_and_permissions(): void
    {
        $this->actingAs($this->fixtureAdmin())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->missing('auth.user.roles')
                ->missing('auth.user.permissions')
            );
    }

    public function test_the_fixture_password_is_the_documented_one(): void
    {
        $this->assertTrue(
            password_verify(
                TestFixturesSeeder::PASSWORD,
                $this->fixtureOwner()->password,
            )
        );
    }
}
