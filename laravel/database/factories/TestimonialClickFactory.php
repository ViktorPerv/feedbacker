<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\TestimonialClick;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestimonialClick>
 */
class TestimonialClickFactory extends Factory
{
    protected $model = TestimonialClick::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'testimonial_id' => Testimonial::factory(),
            'referrer' => fake()->optional()->url(),
            'clicked_at' => now(),
        ];
    }

    /**
     * Устанавливает для клика указанное время.
     */
    public function at(DateTimeInterface $moment): static
    {
        return $this->state(fn (array $attributes) => [
            'clicked_at' => $moment,
        ]);
    }

    public function daysAgo(int $days): static
    {
        return $this->at(now()->subDays($days));
    }
}
