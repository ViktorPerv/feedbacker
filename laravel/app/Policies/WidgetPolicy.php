<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Widget;

/**
 * Widgets are owned through their space, so every ability delegates to
 * SpacePolicy. Keeping the rule in one place means "who may edit this space"
 * has exactly one answer.
 */
class WidgetPolicy
{
    public function view(User $user, Widget $widget): bool
    {
        return $user->can('view', $widget->space);
    }

    public function update(User $user, Widget $widget): bool
    {
        return $user->can('update', $widget->space);
    }

    public function delete(User $user, Widget $widget): bool
    {
        return $user->can('delete', $widget->space);
    }
}
