<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

/**
 * Авторизация действий с отдельными отзывами.
 *
 * Проверка модерации делегирована SpacePolicy, чтобы правило доступа к
 * модерации пространства находилось в одном месте.
 */
class TestimonialPolicy
{
    public function moderate(User $user, Testimonial $testimonial): bool
    {
        return $user->can('moderateTestimonials', $testimonial->space);
    }

    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $this->moderate($user, $testimonial);
    }
}
