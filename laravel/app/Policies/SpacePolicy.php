<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Space;
use App\Models\User;

/**
 * Авторизация действий с пространствами.
 *
 * Владение покрывает обычные действия. Разрешения `.any` добавляют управление
 * чужими пространствами и принадлежат только роли `admin`.
 */
class SpacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Space $space): bool
    {
        return $space->user_id === $user->id
            || $user->checkPermissionTo(PermissionEnum::SpacesViewAny->value);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Space $space): bool
    {
        return $space->user_id === $user->id
            || $user->checkPermissionTo(PermissionEnum::SpacesUpdateAny->value);
    }

    public function delete(User $user, Space $space): bool
    {
        return $space->user_id === $user->id
            || $user->checkPermissionTo(PermissionEnum::SpacesDeleteAny->value);
    }

    /**
     * Определяет доступ к очереди модерации пространства.
     */
    public function moderateTestimonials(User $user, Space $space): bool
    {
        return $space->user_id === $user->id
            || $user->checkPermissionTo(PermissionEnum::TestimonialsModerateAny->value);
    }
}
