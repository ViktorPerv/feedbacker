<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TestimonialStatusEnum;
use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'author_name' => fake()->name(),
            'author_email' => fake()->unique()->safeEmail(),
            'author_avatar_url' => null,
            'author_title' => fake()->jobTitle(),
            'author_company' => fake()->company(),
            'rating' => fake()->numberBetween(1, 5),
            'content' => fake()->paragraph(),
            'video_url' => null,
            'status' => TestimonialStatusEnum::Pending,
            'tags' => [],
            'is_featured' => false,
            'source' => 'direct',
            'imported_from_url' => null,
            'approved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestimonialStatusEnum::Approved,
            'approved_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestimonialStatusEnum::Pending,
            'approved_at' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestimonialStatusEnum::Rejected,
            'approved_at' => null,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function rating(int $rating): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $rating,
        ]);
    }
}
