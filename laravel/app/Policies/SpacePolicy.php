<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Space;
use App\Models\User;

/**
 * Authorisation for spaces.
 *
 * Ownership covers the everyday case. The `.any` permissions add cross-account
 * oversight on top, and are held by the `admin` role only.
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
     * Who may read and act on the moderation inbox for this space.
     */
    public function moderateTestimonials(User $user, Space $space): bool
    {
        return $space->user_id === $user->id
            || $user->checkPermissionTo(PermissionEnum::TestimonialsModerateAny->value);
    }
}
