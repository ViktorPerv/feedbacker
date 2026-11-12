<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every role and permission from the enums.
 *
 * Driven by `RoleEnum::permissions()` rather than a hand-written matrix, so a
 * permission added to the enum reaches the right roles on the next seed
 * without this file being touched.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = App::make(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // ── Permissions ──
        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        $registrar->forgetCachedPermissions();

        // ── Roles ──
        foreach (RoleEnum::cases() as $role) {
            $model = Role::findOrCreate($role->value, 'web');

            // Resolve to models, not names: syncPermissions() with strings has
            // been observed to miss when the permission cache is cold.
            $models = collect($role->permissions())
                ->map(fn (PermissionEnum $permission) => Permission::findByName($permission->value, 'web'))
                ->all();

            $model->syncPermissions($models);
        }

        $registrar->forgetCachedPermissions();
    }
}
