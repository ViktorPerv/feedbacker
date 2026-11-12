<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Создаёт все роли и разрешения из перечислений.
 *
 * Использует `RoleEnum::permissions()` вместо вручную заданной матрицы, поэтому
 * новое разрешение попадёт в нужные роли при следующем заполнении БД.
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
