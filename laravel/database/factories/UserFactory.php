<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
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
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Give the created user the administrator role.
     *
     * Requires RolePermissionSeeder to have run.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([RoleEnum::Admin->value]);
        });
    }

    /**
     * Give the created user the baseline member role.
     *
     * Requires RolePermissionSeeder to have run.
     */
    public function member(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([RoleEnum::User->value]);
        });
    }

    /**
     * Create the user without any role at all.
     */
    public function roleless(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->syncRoles([]);
        });
    }
}
