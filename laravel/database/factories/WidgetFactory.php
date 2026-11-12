<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WidgetLayoutEnum;
use App\Models\Space;
use App\Models\Widget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Widget>
 */
class WidgetFactory extends Factory
{
    protected $model = Widget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $layout = fake()->randomElement(WidgetLayoutEnum::cases());

        return [
            'space_id' => Space::factory(),
            'name' => Str::title(fake()->words(2, true)),
            'layout' => $layout,
            'token' => Str::random(24),
            'config' => $layout->defaultConfig(),
            'is_active' => true,
        ];
    }

    public function layout(WidgetLayoutEnum $layout): static
    {
        return $this->state(fn (array $attributes) => [
            'layout' => $layout,
            'config' => $layout->defaultConfig(),
        ]);
    }

    public function masonry(): static
    {
        return $this->layout(WidgetLayoutEnum::Masonry);
    }

    public function carousel(): static
    {
        return $this->layout(WidgetLayoutEnum::Carousel);
    }

    public function single(): static
    {
        return $this->layout(WidgetLayoutEnum::Single);
    }

    public function badge(): static
    {
        return $this->layout(WidgetLayoutEnum::Badge);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Override individual configuration keys.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function config(array $overrides): static
    {
        return $this->state(fn (array $attributes) => [
            'config' => array_merge(
                $attributes['config'] ?? WidgetLayoutEnum::Masonry->defaultConfig(),
                $overrides,
            ),
        ]);
    }
}
