<?php

declare(strict_types=1);

namespace App\Enums;

enum TestimonialStatusEnum: string
{
    /** Отправлен с публичной страницы и ожидает модерации. */
    case Pending = 'pending';

    /** Отображается на публичной стене. */
    case Approved = 'approved';

    /** Скрыт с публичной стены. */
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
     * Определяет, можно ли публично показывать отзыв в этом статусе.
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
