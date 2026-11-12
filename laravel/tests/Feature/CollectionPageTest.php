<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TestimonialStatusEnum;
use App\Models\Testimonial;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

class CollectionPageTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'author_name' => 'Jane Doe',
            'author_email' => 'jane@example.com',
            'author_title' => 'Head of Growth',
            'author_company' => 'Acme Inc.',
            'rating' => 5,
            'content' => 'They helped us double activation in a quarter.',
        ], $overrides);
    }

    public function test_the_collection_page_is_publicly_accessible(): void
    {
        $this->get(route('collect.show', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Collect/Show')
                ->where('space.slug', TestFixturesSeeder::SPACE_SLUG)
                ->where('space.is_active', true)
                ->has('branding')
                ->has('stats')
            );
    }

    public function test_it_exposes_the_fixture_branding(): void
    {
        $this->get(route('collect.show', TestFixturesSeeder::SPACE_SLUG))
            ->assertInertia(fn (Assert $page) => $page
                ->where('branding.primary_color', '#4F46E5')
                ->where('branding.button_text', 'Leave a testimonial')
                ->where('branding.thank_you_message', 'Thank you for your feedback!')
            );
    }

    public function test_an_unknown_slug_is_not_found(): void
    {
        $this->get(route('collect.show', 'does-not-exist'))->assertNotFound();
    }

    public function test_a_visitor_can_submit_a_testimonial(): void
    {
        $space = $this->fixtureSpace();
        $before = $space->testimonials()->count();

        $this->post(route('collect.store', $space->slug), $this->payload())
            ->assertRedirect(route('collect.show', $space->slug))
            ->assertSessionHas('success');

        $this->assertSame($before + 1, $space->testimonials()->count());

        $testimonial = Testimonial::where('author_name', 'Jane Doe')->firstOrFail();

        $this->assertSame(5, $testimonial->rating);
        $this->assertSame(TestimonialStatusEnum::Pending, $testimonial->status);
        $this->assertSame('direct', $testimonial->source);
        $this->assertNull($testimonial->approved_at);
    }

    public function test_submissions_start_pending_and_are_not_public(): void
    {
        $space = $this->fixtureSpace();
        $approvedBefore = $space->approvedTestimonials()->count();

        $this->post(route('collect.store', $space->slug), $this->payload());

        $this->assertSame($approvedBefore, $space->approvedTestimonials()->count());
        $this->assertSame(2, $space->testimonials()->pending()->count());
    }

    public function test_required_fields_are_validated(): void
    {
        $this->post(route('collect.store', TestFixturesSeeder::SPACE_SLUG), [])
            ->assertSessionHasErrors(['author_name', 'rating', 'content']);
    }

    public function test_the_rating_must_be_between_one_and_five(): void
    {
        $this->post(
            route('collect.store', TestFixturesSeeder::SPACE_SLUG),
            $this->payload(['rating' => 9]),
        )->assertSessionHasErrors('rating');
    }

    public function test_the_content_has_a_minimum_length(): void
    {
        $this->post(
            route('collect.store', TestFixturesSeeder::SPACE_SLUG),
            $this->payload(['content' => 'ok']),
        )->assertSessionHasErrors('content');
    }

    public function test_a_visitor_cannot_set_the_status_or_featured_flag(): void
    {
        $space = $this->fixtureSpace();

        $this->post(route('collect.store', $space->slug), $this->payload([
            'status' => TestimonialStatusEnum::Approved->value,
            'is_featured' => true,
            'space_id' => $this->fixtureForeignSpace()->id,
        ]));

        $testimonial = Testimonial::where('author_name', 'Jane Doe')->firstOrFail();

        $this->assertSame($space->id, $testimonial->space_id);
        $this->assertSame(TestimonialStatusEnum::Pending, $testimonial->status);
        $this->assertFalse($testimonial->is_featured);
    }

    public function test_a_closed_space_rejects_submissions(): void
    {
        $space = $this->fixtureInactiveSpace();
        $before = $space->testimonials()->count();

        $this->post(route('collect.store', $space->slug), $this->payload())
            ->assertSessionHas('error');

        $this->assertSame($before, $space->testimonials()->count());
    }

    public function test_the_honeypot_silently_drops_spam(): void
    {
        $space = $this->fixtureSpace();
        $before = $space->testimonials()->count();

        $this->post(route('collect.store', $space->slug), $this->payload([
            'website' => 'http://spam.example.com',
        ]))->assertRedirect(route('collect.show', $space->slug));

        $this->assertSame($before, $space->testimonials()->count());
    }

    public function test_the_page_reports_public_stats(): void
    {
        // The fixture gives acme-inc two approved testimonials: 5 and 3.
        $this->get(route('collect.show', TestFixturesSeeder::SPACE_SLUG))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.approved_count', 2)
                // JSON turns 4.0 into 4, so compare numerically rather than
                // strictly on the PHP type.
                ->where('stats.average_rating', fn ($value) => (float) $value === 4.0)
            );
    }

    public function test_rejected_testimonials_are_excluded_from_the_stats(): void
    {
        $space = $this->fixtureSpace();

        $this->assertSame(2, $space->approvedTestimonials()->count());
        $this->assertSame(0, $space->approvedTestimonials()
            ->where('author_name', TestFixturesSeeder::REJECTED)
            ->count());
    }
}
