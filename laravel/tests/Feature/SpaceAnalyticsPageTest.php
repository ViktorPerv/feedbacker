<?php

declare(strict_types=1);

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

class SpaceAnalyticsPageTest extends TestCase
{
    public function test_guests_are_redirected(): void
    {
        $this->get(route('spaces.analytics', TestFixturesSeeder::SPACE_SLUG))
            ->assertRedirect(route('login'));
    }

    public function test_the_owner_sees_the_analytics_page(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.analytics', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spaces/Analytics')
                ->where('space.slug', TestFixturesSeeder::SPACE_SLUG)
                ->where('summary.views', TestFixturesSeeder::VIEWS_TOTAL)
                ->where('summary.clicks', TestFixturesSeeder::CLICKS_TOTAL)
                // JSON turns 50.0 into 50, so compare numerically rather than
                // strictly on the PHP type.
                ->where('summary.click_rate', fn ($value) => (float) $value === 50.0)
                ->has('series', 30)
                ->has('topTestimonials', 2)
                ->where('window', 30)
            );
    }

    public function test_a_stranger_is_forbidden(): void
    {
        $this->actingAs($this->fixtureStranger())
            ->get(route('spaces.analytics', TestFixturesSeeder::SPACE_SLUG))
            ->assertForbidden();
    }

    public function test_an_admin_can_see_any_spaces_analytics(): void
    {
        $this->actingAs($this->fixtureAdmin())
            ->get(route('spaces.analytics', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk();
    }

    public function test_the_window_can_be_switched(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.analytics', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'days' => 7,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('window', 7)
                ->has('series', 7)
            );
    }

    public function test_an_unsupported_window_falls_back_to_the_default(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.analytics', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'days' => 12345,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('window', 30)
                ->has('series', 30)
            );
    }

    public function test_the_page_exposes_a_ready_to_paste_embed_snippet(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.analytics', TestFixturesSeeder::SPACE_SLUG))
            ->assertInertia(fn (Assert $page) => $page
                ->where('space.embed_url', url('/w/'.TestFixturesSeeder::SPACE_SLUG.'.js'))
                ->where(
                    'space.embed_snippet',
                    fn (string $snippet) => str_contains($snippet, 'data-feedbacker="'.TestFixturesSeeder::SPACE_SLUG.'"')
                        && str_contains($snippet, '/w/'.TestFixturesSeeder::SPACE_SLUG.'.js')
                )
            );
    }

    public function test_a_space_without_activity_reports_zeroes(): void
    {
        $this->actingAs($this->fixtureStranger())
            ->get(route('spaces.analytics', TestFixturesSeeder::FOREIGN_SPACE_SLUG))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.views', 0)
                ->where('summary.clicks', 0)
                ->where('summary.click_rate', fn ($value) => (float) $value === 0.0)
                ->has('topTestimonials', 0)
            );
    }

    public function test_the_space_page_carries_a_summary_and_the_snippet(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.show', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spaces/Show')
                ->where('analytics.views', TestFixturesSeeder::VIEWS_TOTAL)
                ->where('analytics.clicks', TestFixturesSeeder::CLICKS_TOTAL)
                ->has('space.embed_snippet')
            );
    }

    public function test_the_dashboard_carries_analytics_for_an_admin(): void
    {
        $this->actingAs($this->fixtureAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.views', TestFixturesSeeder::VIEWS_TOTAL)
                ->where('analytics.clicks', TestFixturesSeeder::CLICKS_TOTAL)
            );
    }

    public function test_the_dashboard_analytics_are_scoped_for_a_member(): void
    {
        // The owner holds acme-inc (which has activity) and globex (which does
        // not), so the totals match acme-inc alone.
        $this->actingAs($this->fixtureOwner())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.views', TestFixturesSeeder::VIEWS_TOTAL)
                ->where('analytics.clicks', TestFixturesSeeder::CLICKS_TOTAL)
            );

        // The stranger owns only initech, which has no analytics.
        $this->actingAs($this->fixtureStranger())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.views', 0)
                ->where('analytics.clicks', 0)
            );
    }
}
