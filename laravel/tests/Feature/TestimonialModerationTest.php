<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TestimonialStatusEnum;
use App\Models\Testimonial;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

/**
 * Очередь модерации на основе набора фикстур.
 *
 * В acme-inc четыре отзыва: Alice (одобрен и избран), Bob (одобрен),
 * Carol (ожидает модерации) и Dave (отклонён).
 */
class TestimonialModerationTest extends TestCase
{
    public function test_guests_are_redirected_from_the_inbox(): void
    {
        $this->get(route('spaces.testimonials.index', TestFixturesSeeder::SPACE_SLUG))
            ->assertRedirect(route('login'));
    }

    public function test_the_owner_can_open_the_inbox(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Testimonials/Index')
                ->where('space.slug', TestFixturesSeeder::SPACE_SLUG)
                ->has('testimonials.data', 4)
                ->where('counts.all', 4)
                ->where('counts.pending', 1)
                ->where('counts.approved', 2)
                ->where('counts.rejected', 1)
            );
    }

    public function test_a_stranger_cannot_open_someone_elses_inbox(): void
    {
        $this->actingAs($this->fixtureStranger())
            ->get(route('spaces.testimonials.index', TestFixturesSeeder::SPACE_SLUG))
            ->assertForbidden();
    }

    public function test_an_admin_can_open_any_inbox(): void
    {
        $this->actingAs($this->fixtureAdmin())
            ->get(route('spaces.testimonials.index', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk();
    }

    public function test_the_owner_can_approve_a_pending_testimonial(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);
        $this->assertSame(TestimonialStatusEnum::Pending, $testimonial->status);

        $this->actingAs($this->fixtureOwner())
            ->patch(route('testimonials.approve', $testimonial->id))
            ->assertRedirect();

        $testimonial->refresh();

        $this->assertSame(TestimonialStatusEnum::Approved, $testimonial->status);
        $this->assertNotNull($testimonial->approved_at);
    }

    public function test_rejecting_clears_the_featured_flag(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::APPROVED_FEATURED);
        $this->assertTrue($testimonial->is_featured);

        $this->actingAs($this->fixtureOwner())
            ->patch(route('testimonials.reject', $testimonial->id))
            ->assertRedirect();

        $testimonial->refresh();

        $this->assertSame(TestimonialStatusEnum::Rejected, $testimonial->status);
        $this->assertFalse($testimonial->is_featured);
    }

    public function test_the_owner_can_toggle_the_featured_flag(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::APPROVED_PLAIN);
        $this->assertFalse($testimonial->is_featured);

        $owner = $this->fixtureOwner();

        $this->actingAs($owner)->patch(route('testimonials.feature', $testimonial->id));
        $this->assertTrue($testimonial->refresh()->is_featured);

        $this->actingAs($owner)->patch(route('testimonials.feature', $testimonial->id));
        $this->assertFalse($testimonial->refresh()->is_featured);
    }

    public function test_the_owner_can_delete_a_testimonial(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::REJECTED);
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->delete(route('testimonials.destroy', $testimonial->id))
            ->assertRedirect(route('spaces.testimonials.index', $space->slug));

        $this->assertNull(Testimonial::find($testimonial->id));
        $this->assertSame(3, $space->testimonials()->count());
    }

    public function test_a_stranger_cannot_moderate(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);
        $stranger = $this->fixtureStranger();

        $this->actingAs($stranger)
            ->patch(route('testimonials.approve', $testimonial->id))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->patch(route('testimonials.reject', $testimonial->id))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->patch(route('testimonials.feature', $testimonial->id))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->delete(route('testimonials.destroy', $testimonial->id))
            ->assertForbidden();

        $this->assertSame(TestimonialStatusEnum::Pending, $testimonial->refresh()->status);
    }

    public function test_an_admin_can_moderate_any_space(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->actingAs($this->fixtureAdmin())
            ->patch(route('testimonials.approve', $testimonial->id))
            ->assertRedirect();

        $this->assertSame(TestimonialStatusEnum::Approved, $testimonial->refresh()->status);
    }

    public function test_the_inbox_filters_by_status(): void
    {
        $owner = $this->fixtureOwner();
        $route = route('spaces.testimonials.index', [
            'space' => TestFixturesSeeder::SPACE_SLUG,
            'status' => TestimonialStatusEnum::Approved->value,
        ]);

        $this->actingAs($owner)
            ->get($route)
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials.data', 2)
                ->where('filters.status', TestimonialStatusEnum::Approved->value)
                // Counts stay global so the tabs keep their badges.
                ->where('counts.all', 4)
                ->where('counts.pending', 1)
                ->where('counts.rejected', 1)
            );
    }

    public function test_the_inbox_searches_author_and_content(): void
    {
        $owner = $this->fixtureOwner();

        $this->actingAs($owner)
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'Alice',
            ]))
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 1));

        $this->actingAs($owner)
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'embeds',
            ]))
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 1));

        $this->actingAs($owner)
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'Northwind',
            ]))
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 1));
    }

    public function test_the_inbox_filters_by_rating(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'rating' => 5,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials.data', 1)
                ->where('testimonials.data.0.author_name', TestFixturesSeeder::APPROVED_FEATURED)
            );
    }

    public function test_an_unknown_status_filter_is_ignored(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'status' => 'bogus',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 4));
    }

    public function test_the_inbox_is_scoped_to_one_space(): void
    {
        // initech holds exactly one testimonial, owned by the stranger.
        $this->actingAs($this->fixtureStranger())
            ->get(route('spaces.testimonials.index', TestFixturesSeeder::FOREIGN_SPACE_SLUG))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials.data', 1)
                ->where('testimonials.data.0.author_name', TestFixturesSeeder::FOREIGN_APPROVED)
            );
    }

    public function test_the_default_ordering_puts_newest_first(): void
    {
        $response = $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk();

        $authors = collect($response->viewData('page')['props']['testimonials']['data'])
            ->pluck('author_name')
            ->all();

        $this->assertSame(TestFixturesSeeder::REJECTED, $authors[0]);
        $this->assertSame(TestFixturesSeeder::APPROVED_FEATURED, $authors[3]);
    }

    public function test_deleting_a_space_deletes_its_testimonials(): void
    {
        $space = $this->fixtureSpace();
        $ids = $space->testimonials()->pluck('id');

        $this->assertSame(4, $ids->count());

        $space->delete();

        $this->assertSame(0, Testimonial::whereIn('id', $ids)->count());
    }
}
