<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

/**
 * Authorisation for individual testimonials.
 *
 * Moderation is delegated to SpacePolicy so the "who may moderate this space"
 * rule lives in exactly one place.
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
