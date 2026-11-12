<?php

declare(strict_types=1);

namespace App\Enums;

enum TestimonialStatusEnum: string
{
    /** Submitted through the public collection page, awaiting moderation. */
    case Pending = 'pending';

    /** Visible on the public wall. */
    case Approved = 'approved';

    /** Hidden from the public wall. */
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Whether testimonials in this state may be shown publicly.
     */
    public function isPublic(): bool
    {
        return $this === self::Approved;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
