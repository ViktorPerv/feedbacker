<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TestimonialClick;
use App\Models\WidgetView;
use App\Support\Analytics;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

/**
 * Aggregation over the analytics the Go widget service writes.
 *
 * The fixture gives acme-inc 8 views and 4 clicks across three days; initech
 * has none, which is what makes the scoping assertions meaningful.
 */
class AnalyticsTest extends TestCase
{
    public function test_the_fixture_produced_the_expected_rows(): void
    {
        $this->assertSame(
            TestFixturesSeeder::VIEWS_TOTAL,
            WidgetView::where('space_id', $this->fixtureSpace()->id)->count(),
        );

        $this->assertSame(
            TestFixturesSeeder::CLICKS_TOTAL,
            TestimonialClick::where('space_id', $this->fixtureSpace()->id)->count(),
        );
    }

    public function test_summary_counts_views_clicks_and_rate(): void
    {
        $summary = Analytics::summary(collect([$this->fixtureSpace()->id]));

        $this->assertSame(TestFixturesSeeder::VIEWS_TOTAL, $summary['views']);
        $this->assertSame(TestFixturesSeeder::CLICKS_TOTAL, $summary['clicks']);
        // 4 clicks on 8 views.
        $this->assertSame(50.0, $summary['click_rate']);
        $this->assertSame(Analytics::DEFAULT_WINDOW_DAYS, $summary['days']);
    }

    public function test_summary_is_scoped_to_the_given_spaces(): void
    {
        // initech belongs to the stranger and has no analytics at all.
        $summary = Analytics::summary(collect([$this->fixtureForeignSpace()->id]));

        $this->assertSame(0, $summary['views']);
        $this->assertSame(0, $summary['clicks']);
        $this->assertSame(0.0, $summary['click_rate']);
    }

    public function test_summary_handles_an_empty_scope(): void
    {
        $summary = Analytics::summary(collect());

        $this->assertSame(0, $summary['views']);
        $this->assertSame(0.0, $summary['click_rate']);
    }

    public function test_click_rate_is_zero_rather_than_dividing_by_zero(): void
    {
        // A space with clicks but no recorded views must not blow up.
        $space = $this->fixtureForeignSpace();
        $testimonial = $space->testimonials()->first();

        TestimonialClick::factory()->for($space)->for($testimonial, 'testimonial')->create();

        $summary = Analytics::summary(collect([$space->id]));

        $this->assertSame(0, $summary['views']);
        $this->assertSame(1, $summary['clicks']);
        $this->assertSame(0.0, $summary['click_rate']);
    }

    public function test_the_window_excludes_older_activity(): void
    {
        $space = $this->fixtureSpace();

        // Everything the fixture wrote is inside three days.
        $this->assertSame(
            TestFixturesSeeder::VIEWS_TOTAL,
            Analytics::summary(collect([$space->id]), 7)['views'],
        );

        WidgetView::factory()->for($space)->daysAgo(40)->count(5)->create();

        $this->assertSame(
            TestFixturesSeeder::VIEWS_TOTAL,
            Analytics::summary(collect([$space->id]), 7)['views'],
        );

        $this->assertSame(
            TestFixturesSeeder::VIEWS_TOTAL + 5,
            Analytics::summary(collect([$space->id]), 90)['views'],
        );
    }

    public function test_daily_series_has_one_point_per_day(): void
    {
        $series = Analytics::dailySeries(collect([$this->fixtureSpace()->id]), 7);

        $this->assertCount(7, $series);

        // Oldest first, and every entry carries both metrics.
        foreach ($series as $point) {
            $this->assertArrayHasKey('date', $point);
            $this->assertArrayHasKey('views', $point);
            $this->assertArrayHasKey('clicks', $point);
        }

        $this->assertSame(
            TestFixturesSeeder::VIEWS_TOTAL,
            array_sum(array_column($series, 'views')),
        );

        $this->assertSame(
            TestFixturesSeeder::CLICKS_TOTAL,
            array_sum(array_column($series, 'clicks')),
        );
    }

    public function test_daily_series_includes_empty_days(): void
    {
        $series = Analytics::dailySeries(collect([$this->fixtureSpace()->id]), 7);

        // Two days ago had no activity in the fixture.
        $twoDaysAgo = now()->subDays(2)->toDateString();
        $entry = collect($series)->firstWhere('date', $twoDaysAgo);

        $this->assertNotNull($entry);
        $this->assertSame(0, $entry['views']);
        $this->assertSame(0, $entry['clicks']);
    }

    public function test_daily_series_is_empty_safe(): void
    {
        $series = Analytics::dailySeries(collect(), 7);

        $this->assertCount(7, $series);
        $this->assertSame(0, array_sum(array_column($series, 'views')));
    }

    public function test_top_testimonials_ranks_by_clicks(): void
    {
        $top = Analytics::topTestimonials(collect([$this->fixtureSpace()->id]));

        $this->assertCount(2, $top);

        $this->assertSame(TestFixturesSeeder::APPROVED_FEATURED, $top[0]['author_name']);
        $this->assertSame(TestFixturesSeeder::CLICKS_ON_FEATURED, $top[0]['clicks']);

        $this->assertSame(TestFixturesSeeder::APPROVED_PLAIN, $top[1]['author_name']);
        $this->assertSame(TestFixturesSeeder::CLICKS_ON_PLAIN, $top[1]['clicks']);
    }

    public function test_top_testimonials_respects_the_limit(): void
    {
        $top = Analytics::topTestimonials(collect([$this->fixtureSpace()->id]), limit: 1);

        $this->assertCount(1, $top);
        $this->assertSame(TestFixturesSeeder::APPROVED_FEATURED, $top[0]['author_name']);
    }

    public function test_top_testimonials_truncates_long_content(): void
    {
        $space = $this->fixtureSpace();
        $testimonial = $space->testimonials()->first();
        $testimonial->update(['content' => str_repeat('long ', 100)]);

        TestimonialClick::factory()->for($space)->for($testimonial, 'testimonial')->create();

        $top = Analytics::topTestimonials(collect([$space->id]), limit: 1);

        $this->assertLessThanOrEqual(125, mb_strlen($top[0]['content']));
    }

    public function test_per_space_returns_a_row_for_every_space(): void
    {
        $ids = collect([$this->fixtureSpace()->id, $this->fixtureForeignSpace()->id]);
        $perSpace = Analytics::perSpace($ids);

        $this->assertCount(2, $perSpace);

        $this->assertSame(
            TestFixturesSeeder::VIEWS_TOTAL,
            $perSpace[$this->fixtureSpace()->id]['views'],
        );
        $this->assertSame(0, $perSpace[$this->fixtureForeignSpace()->id]['views']);
    }

    public function test_deleting_a_space_removes_its_analytics(): void
    {
        $spaceId = $this->fixtureSpace()->id;

        $this->fixtureSpace()->delete();

        $this->assertSame(0, WidgetView::where('space_id', $spaceId)->count());
        $this->assertSame(0, TestimonialClick::where('space_id', $spaceId)->count());
    }

    public function test_deleting_a_testimonial_unlinks_views_and_cascades_clicks(): void
    {
        $space = $this->fixtureSpace();
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::APPROVED_FEATURED);

        $view = WidgetView::factory()
            ->for($space)
            ->for($testimonial, 'testimonial')
            ->create();

        $this->assertGreaterThan(
            0,
            TestimonialClick::where('testimonial_id', $testimonial->id)->count(),
        );

        $testimonial->delete();

        // The impression survives, with its reference cleared (nullOnDelete)…
        $this->assertNotNull($view->fresh());
        $this->assertNull($view->fresh()->testimonial_id);

        // …while clicks cascade away with the testimonial.
        $this->assertSame(
            0,
            TestimonialClick::where('testimonial_id', $testimonial->id)->count(),
        );
    }
}
