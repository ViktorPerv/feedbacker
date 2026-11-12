<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Space;
use App\Models\WidgetView;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WidgetView>
 */
class WidgetViewFactory extends Factory
{
    protected $model = WidgetView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'testimonial_id' => null,
            'referrer' => fake()->optional()->url(),
            'user_agent' => 'Mozilla/5.0 (fixture)',
            'ip_address' => fake()->ipv4(),
            'viewed_at' => now(),
        ];
    }

    /**
     * Place the impression at a specific moment.
     */
    public function at(DateTimeInterface $moment): static
    {
        return $this->state(fn (array $attributes) => [
            'viewed_at' => $moment,
        ]);
    }

    public function daysAgo(int $days): static
    {
        return $this->at(now()->subDays($days));
    }
}
