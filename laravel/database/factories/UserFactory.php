<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Текущий пароль, используемый фабрикой.
     */
    protected static ?string $password;

    /**
     * Определяет состояние модели по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Помечает адрес электронной почты модели как неподтверждённый.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Назначает созданному пользователю роль администратора.
     *
     * Требует предварительного запуска RolePermissionSeeder.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([RoleEnum::Admin->value]);
        });
    }

    /**
     * Назначает созданному пользователю базовую роль.
     *
     * Требует предварительного запуска RolePermissionSeeder.
     */
    public function member(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([RoleEnum::User->value]);
        });
    }

    /**
     * Создаёт пользователя без какой-либо роли.
     */
    public function roleless(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([]);
        });
    }
}
