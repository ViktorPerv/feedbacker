<?php

declare(strict_types=1);

namespace App\Enums;

enum WidgetLayoutEnum: string
{
    /** Responsive masonry wall — the original. */
    case Masonry = 'masonry';

    /** Horizontal scroll-snap row with arrows. */
    case Carousel = 'carousel';

    /** One testimonial, for a hero or a sidebar. */
    case Single = 'single';

    /** Avatars plus a count and an average — small enough for a pricing table. */
    case Badge = 'badge';

    public function label(): string
    {
        return match ($this) {
            self::Masonry => 'Masonry wall',
            self::Carousel => 'Carousel',
            self::Single => 'Single review',
            self::Badge => 'Rating badge',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Masonry => 'A responsive grid of every approved testimonial. Best for a dedicated section.',
            self::Carousel => 'One row you scroll through. Good for narrow sections and sidebars.',
            self::Single => 'Just the most important review. Ideal for a hero or a landing page.',
            self::Badge => 'Avatars, a star average and a count. Fits a pricing table or a footer.',
        };
    }

    /**
     * Layouts where a column count is meaningful.
     */
    public function supportsColumns(): bool
    {
        return $this === self::Masonry;
    }

    /**
     * Layouts where a per-page limit is meaningful.
     */
    public function supportsLimit(): bool
    {
        return in_array($this, [self::Masonry, self::Carousel], true);
    }

    /**
     * Layouts that render individual testimonial text.
     */
    public function rendersTestimonials(): bool
    {
        return $this !== self::Badge;
    }

    /**
     * The default configuration for this layout.
     *
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return [
            'theme' => 'light',
            'columns' => 3,
            'limit' => match ($this) {
                self::Single => 1,
                self::Badge => 1,
                default => 12,
            },
            'accent' => null,
            'heading' => null,
            'ratings' => true,
            'footer' => true,
            'featured_only' => $this === self::Single,
            'min_rating' => null,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
                'supports_columns' => $case->supportsColumns(),
                'supports_limit' => $case->supportsLimit(),
                'renders_testimonials' => $case->rendersTestimonials(),
                'default_config' => $case->defaultConfig(),
            ],
            self::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
