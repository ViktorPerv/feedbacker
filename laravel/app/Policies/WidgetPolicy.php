<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Widget;

/**
 * Виджеты принадлежат пространству, поэтому все проверки делегируются
 * SpacePolicy. Так правило редактирования пространства остаётся единым.
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
