<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
// roles/permissions are hidden so the shared Inertia `auth.user` payload stays
// a clean account record. Authorisation is resolved server-side and handed to
// the front end as explicit `can` props.
#[Hidden(['password', 'remember_token', 'roles', 'permissions'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    /**
     * Every account gets the baseline `user` role unless it already has one.
     *
     * Guarded so that it is a no-op before RolePermissionSeeder has run (for
     * example in a test that does not seed roles).
     */
    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $user->assignDefaultRole();
        });
    }

    public function assignDefaultRole(): void
    {
        if ($this->roles()->exists()) {
            return;
        }

        if (Role::query()->where('name', RoleEnum::User->value)->doesntExist()) {
            return;
        }

        $this->assignRole(RoleEnum::User->value);
    }
}
