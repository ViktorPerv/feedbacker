<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Space;
use App\Models\SpaceBranding;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

class SpaceTest extends TestCase
{
    public function test_guests_are_redirected_from_the_spaces_index(): void
    {
        $this->get(route('spaces.index'))->assertRedirect(route('login'));
    }

    public function test_it_lists_only_the_spaces_owned_by_the_current_user(): void
    {
        // The owner holds acme-inc and globex; initech belongs to the stranger.
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spaces/Index')
                ->has('spaces', 2)
                ->where('spaces', fn ($spaces) => collect($spaces)
                    ->pluck('slug')
                    ->sort()
                    ->values()
                    ->all() === [
                        TestFixturesSeeder::SPACE_SLUG,
                        TestFixturesSeeder::INACTIVE_SPACE_SLUG,
                    ])
            );
    }

    public function test_it_creates_a_space_with_default_branding(): void
    {
        $owner = $this->fixtureOwner();

        $this->actingAs($owner)
            ->post(route('spaces.store'), [
                'name' => 'Brand New Co.',
                'slug' => 'brand-new-co',
                'custom_domain' => null,
                'is_active' => true,
            ])
            ->assertRedirect();

        $space = Space::where('slug', 'brand-new-co')->first();

        $this->assertNotNull($space);
        $this->assertSame($owner->id, $space->user_id);
        $this->assertNotNull($space->branding);
        $this->assertSame('#4F46E5', $space->branding->primary_color);
        $this->assertSame('#FFFFFF', $space->branding->background_color);
    }

    public function test_it_slugifies_the_slug_before_saving(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.store'), [
                'name' => 'My Great Company',
                'slug' => 'My Great Company!!',
            ])
            ->assertRedirect();

        $this->assertTrue(Space::where('slug', 'my-great-company')->exists());
    }

    public function test_it_rejects_a_slug_that_is_already_taken(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.store'), [
                'name' => 'Impostor',
                'slug' => TestFixturesSeeder::SPACE_SLUG,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_it_rejects_a_malformed_slug(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->post(route('spaces.store'), [
                'name' => 'Weird',
                'slug' => '___',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_it_shows_a_space_to_its_owner_using_the_slug(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.show', $space->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spaces/Show')
                ->where('space.slug', $space->slug)
                ->where('space.id', $space->id)
                ->has('space.branding')
            );
    }

    public function test_it_forbids_viewing_another_users_space(): void
    {
        $this->actingAs($this->fixtureStranger())
            ->get(route('spaces.show', TestFixturesSeeder::SPACE_SLUG))
            ->assertForbidden();
    }

    public function test_it_updates_a_space(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->put(route('spaces.update', $space->slug), [
                'name' => 'New name',
                'slug' => 'new-slug',
                'custom_domain' => 'reviews.example.com',
                'is_active' => false,
            ])
            ->assertRedirect(route('spaces.show', 'new-slug'));

        $space->refresh();

        $this->assertSame('New name', $space->name);
        $this->assertSame('new-slug', $space->slug);
        $this->assertSame('reviews.example.com', $space->custom_domain);
        $this->assertFalse($space->is_active);
    }

    public function test_it_allows_keeping_the_same_slug_when_updating(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->put(route('spaces.update', $space->slug), [
                'name' => 'Renamed',
                'slug' => $space->slug,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_it_forbids_updating_another_users_space(): void
    {
        $this->actingAs($this->fixtureStranger())
            ->put(route('spaces.update', TestFixturesSeeder::SPACE_SLUG), [
                'name' => 'Hacked',
                'slug' => TestFixturesSeeder::SPACE_SLUG,
            ])
            ->assertForbidden();
    }

    public function test_it_deletes_a_space_owned_by_the_user(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->delete(route('spaces.destroy', $space->slug))
            ->assertRedirect(route('spaces.index'));

        $this->assertNull(Space::find($space->id));
    }

    public function test_branding_is_deleted_with_the_space(): void
    {
        $space = $this->fixtureSpace();
        $brandingId = $space->branding->id;

        $space->delete();

        $this->assertNull(SpaceBranding::find($brandingId));
    }

    public function test_it_forbids_deleting_another_users_space(): void
    {
        $this->actingAs($this->fixtureStranger())
            ->delete(route('spaces.destroy', TestFixturesSeeder::SPACE_SLUG))
            ->assertForbidden();

        $this->assertTrue(
            Space::where('slug', TestFixturesSeeder::SPACE_SLUG)->exists()
        );
    }
}
