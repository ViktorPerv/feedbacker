<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Создаёт или обновляет локального администратора и назначает роль `admin`.
     *
     * Учётные данные берутся из config/feedbacker.php, который читает
     * ADMIN_NAME, ADMIN_EMAIL и ADMIN_PASSWORD из окружения.
     */
    public function run(): void
    {
        $email = config('feedbacker.admin.email');
        $password = config('feedbacker.admin.password');

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('feedbacker.admin.name'),
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles([RoleEnum::Admin->value]);

        $this->command?->info(sprintf(
            'Admin ready: %s (%s, role: %s)',
            $admin->email,
            $admin->wasRecentlyCreated ? 'created' : 'updated',
            $admin->getRoleNames()->implode(', '),
        ));

        if ($password === 'password') {
            $this->command?->warn(
                'The admin password is still the default. Set ADMIN_PASSWORD in laravel/.env before deploying.'
            );
        }
    }
}
