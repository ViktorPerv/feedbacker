<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WidgetLayoutEnum;
use App\Models\Widget;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

/**
 * The widget builder.
 *
 * The fixture gives acme-inc two widgets: a masonry wall and a badge, both
 * owned by the owner.
 */
class WidgetTest extends TestCase
{
    public function test_guests_are_redirected_from_the_builder(): void
    {
        $this->get(route('spaces.widgets.index', TestFixturesSeeder::SPACE_SLUG))
            ->assertRedirect(route('login'));
    }

    public function test_the_owner_sees_their_widgets(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.widgets.index', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spaces/Widgets/Index')
                ->where('space.slug', TestFixturesSeeder::SPACE_SLUG)
                ->has('widgets', 2)
                ->where('can.create', true)
            );
    }

    public function test_a_stranger_cannot_see_someone_elses_widgets(): void
    {
        $this->actingAs($this->fixtureStranger())
            ->get(route('spaces.widgets.index', TestFixturesSeeder::SPACE_SLUG))
            ->assertForbidden();
    }

    public function test_an_admin_can_see_any_spaces_widgets(): void
    {
        $this->actingAs($this->fixtureAdmin())
            ->get(route('spaces.widgets.index', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk();
    }

    public function test_the_create_page_offers_every_layout(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.widgets.create', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spaces/Widgets/Create')
                ->has('layouts', count(WidgetLayoutEnum::cases()))
            );
    }

    public function test_the_owner_can_create_a_widget(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.widgets.store', $space->slug), [
                'name' => 'Pricing badge',
                'layout' => WidgetLayoutEnum::Badge->value,
                'is_active' => true,
                'config' => [
                    'theme' => 'dark',
                    'columns' => 3,
                    'limit' => 5,
                    'accent' => '#112233',
                    'heading' => null,
                    'ratings' => true,
                    'footer' => false,
                    'featured_only' => false,
                    'min_rating' => 4,
                ],
            ])
            ->assertRedirect();

        $widget = Widget::where('name', 'Pricing badge')->first();

        $this->assertNotNull($widget);
        $this->assertSame($space->id, $widget->space_id);
        $this->assertSame(WidgetLayoutEnum::Badge, $widget->layout);
        $this->assertTrue($widget->is_active);
        $this->assertSame('dark', $widget->config['theme']);
        $this->assertSame(4, $widget->config['min_rating']);
        $this->assertFalse($widget->config['footer']);
    }

    public function test_a_created_widget_gets_a_token(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.widgets.store', $space->slug), [
                'name' => 'Token check',
                'layout' => WidgetLayoutEnum::Masonry->value,
                'is_active' => true,
                'config' => WidgetLayoutEnum::Masonry->defaultConfig(),
            ]);

        $widget = Widget::where('name', 'Token check')->first();

        $this->assertNotNull($widget);
        $this->assertSame(24, strlen($widget->token));
        $this->assertSame($widget->token, $widget->getRouteKey());
    }

    public function test_the_embed_url_points_at_the_widget_service(): void
    {
        $widget = $this->fixtureWidget();

        // The Go service answers /w/…, not the application. Using url() here
        // would hand the customer a link to a route Laravel does not have.
        $this->assertSame(
            rtrim((string) config('widget.public_url'), '/')
                .'/w/'.TestFixturesSeeder::SPACE_SLUG.'/'.$widget->token.'.js',
            $widget->embedUrl(),
        );
    }

    public function test_the_landing_page_exposes_the_demo_widget(): void
    {
        // The homepage embeds a real widget so a visitor sees the product
        // working. Without demo content there is simply no section.
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('demoWidget', null));
    }

    public function test_the_snippet_carries_the_token_and_the_url(): void
    {
        $widget = $this->fixtureWidget();
        $snippet = $widget->embedSnippet();

        $this->assertStringContainsString(
            'data-feedbacker="'.$widget->token.'"',
            $snippet,
        );
        $this->assertStringContainsString($widget->embedUrl(), $snippet);
    }

    public function test_the_config_falls_back_to_layout_defaults(): void
    {
        $widget = $this->fixtureWidget();

        // The fixture stores only a few keys; the rest come from the layout.
        $config = $widget->configWithDefaults();

        $this->assertSame('light', $config['theme']);
        $this->assertSame(2, $config['columns']);
        $this->assertArrayHasKey('featured_only', $config);
        $this->assertArrayHasKey('ratings', $config);
    }

    public function test_the_owner_can_update_a_widget(): void
    {
        $space = $this->fixtureSpace();
        $widget = $this->fixtureWidget();

        $this->actingAs($this->fixtureOwner())
            ->put(route('spaces.widgets.update', [$space->slug, $widget->token]), [
                'name' => 'Renamed wall',
                'layout' => WidgetLayoutEnum::Carousel->value,
                'is_active' => false,
                'config' => [
                    'theme' => 'dark',
                    'columns' => 1,
                    'limit' => 6,
                    'accent' => null,
                    'heading' => 'Loved by teams',
                    'ratings' => true,
                    'footer' => true,
                    'featured_only' => true,
                    'min_rating' => 5,
                ],
            ])
            ->assertRedirect(route('spaces.widgets.edit', [$space->slug, $widget->token]));

        $widget->refresh();

        $this->assertSame('Renamed wall', $widget->name);
        $this->assertSame(WidgetLayoutEnum::Carousel, $widget->layout);
        $this->assertFalse($widget->is_active);
        $this->assertSame('Loved by teams', $widget->config['heading']);
        $this->assertTrue($widget->config['featured_only']);
    }

    public function test_the_token_survives_an_update(): void
    {
        $space = $this->fixtureSpace();
        $widget = $this->fixtureWidget();
        $token = $widget->token;

        $this->actingAs($this->fixtureOwner())
            ->put(route('spaces.widgets.update', [$space->slug, $token]), [
                'name' => 'Still embedded',
                'layout' => WidgetLayoutEnum::Masonry->value,
                'is_active' => true,
                'config' => WidgetLayoutEnum::Masonry->defaultConfig(),
            ]);

        // Changing the token would break every page already embedding it.
        $this->assertSame($token, $widget->refresh()->token);
    }

    public function test_the_owner_can_delete_a_widget(): void
    {
        $space = $this->fixtureSpace();
        $widget = $this->fixtureWidget();

        $this->actingAs($this->fixtureOwner())
            ->delete(route('spaces.widgets.destroy', [$space->slug, $widget->token]))
            ->assertRedirect(route('spaces.widgets.index', $space->slug));

        $this->assertNull(Widget::find($widget->id));
    }

    public function test_a_stranger_cannot_modify_a_widget(): void
    {
        $space = $this->fixtureSpace();
        $widget = $this->fixtureWidget();
        $stranger = $this->fixtureStranger();

        $this->actingAs($stranger)
            ->get(route('spaces.widgets.edit', [$space->slug, $widget->token]))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->put(route('spaces.widgets.update', [$space->slug, $widget->token]), [
                'name' => 'Hijacked',
                'layout' => WidgetLayoutEnum::Masonry->value,
                'is_active' => true,
                'config' => WidgetLayoutEnum::Masonry->defaultConfig(),
            ])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->delete(route('spaces.widgets.destroy', [$space->slug, $widget->token]))
            ->assertForbidden();

        $this->assertSame('Homepage wall', $widget->refresh()->name);
    }

    public function test_an_admin_can_modify_any_widget(): void
    {
        $space = $this->fixtureSpace();
        $widget = $this->fixtureWidget();

        $this->actingAs($this->fixtureAdmin())
            ->put(route('spaces.widgets.update', [$space->slug, $widget->token]), [
                'name' => 'Renamed by admin',
                'layout' => WidgetLayoutEnum::Masonry->value,
                'is_active' => true,
                'config' => WidgetLayoutEnum::Masonry->defaultConfig(),
            ])
            ->assertRedirect();

        $this->assertSame('Renamed by admin', $widget->refresh()->name);
    }

    public function test_an_unknown_layout_is_rejected(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.widgets.store', $space->slug), [
                'name' => 'Bad layout',
                'layout' => 'fullpage',
                'is_active' => true,
                'config' => WidgetLayoutEnum::Masonry->defaultConfig(),
            ])
            ->assertSessionHasErrors('layout');
    }

    public function test_an_invalid_accent_is_rejected(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.widgets.store', $space->slug), [
                'name' => 'Bad colour',
                'layout' => WidgetLayoutEnum::Masonry->value,
                'is_active' => true,
                'config' => array_merge(WidgetLayoutEnum::Masonry->defaultConfig(), [
                    'accent' => 'red;background:url(x)',
                ]),
            ])
            ->assertSessionHasErrors('config.accent');
    }

    public function test_an_out_of_range_limit_is_rejected(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.widgets.store', $space->slug), [
                'name' => 'Too many',
                'layout' => WidgetLayoutEnum::Masonry->value,
                'is_active' => true,
                'config' => array_merge(WidgetLayoutEnum::Masonry->defaultConfig(), [
                    'limit' => 5000,
                ]),
            ])
            ->assertSessionHasErrors('config.limit');
    }

    public function test_a_name_is_required(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.widgets.store', $space->slug), [
                'layout' => WidgetLayoutEnum::Masonry->value,
                'is_active' => true,
                'config' => WidgetLayoutEnum::Masonry->defaultConfig(),
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_tokens_are_unique_across_widgets(): void
    {
        $space = $this->fixtureSpace();

        $tokens = collect(range(1, 5))->map(function () use ($space) {
            $widget = $space->widgets()->create([
                'name' => 'Bulk '.Str::random(4),
                'layout' => WidgetLayoutEnum::Masonry,
                'config' => WidgetLayoutEnum::Masonry->defaultConfig(),
                'is_active' => true,
            ]);

            return $widget->token;
        });

        $this->assertCount(5, $tokens->unique());
    }

    public function test_deleting_a_space_deletes_its_widgets(): void
    {
        $space = $this->fixtureSpace();
        $ids = $space->widgets()->pluck('id');

        $this->assertGreaterThan(0, $ids->count());

        $space->delete();

        $this->assertSame(0, Widget::whereIn('id', $ids)->count());
    }

    public function test_the_space_page_reports_the_widget_count(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.show', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('widgetsCount', 2)
            );
    }
}
