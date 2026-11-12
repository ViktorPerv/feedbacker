<?php

declare(strict_types=1);

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

class SpaceBrandingTest extends TestCase
{
    public function test_it_shows_the_branding_page_to_the_owner(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.branding.edit', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spaces/Branding')
                ->where('space.slug', TestFixturesSeeder::SPACE_SLUG)
                ->has('branding')
                ->where('branding.primary_color', '#4F46E5')
            );
    }

    public function test_it_updates_branding_for_the_space(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureOwner())
            ->put(route('spaces.branding.update', $space->slug), [
                'logo_url' => 'https://example.com/logo.png',
                'primary_color' => '#112233',
                'background_color' => '#FAFAFA',
                'font_family' => 'Roboto',
                'button_text' => 'Share your story',
                'thank_you_message' => 'Thanks a lot!',
            ])
            ->assertRedirect(route('spaces.branding.edit', $space->slug));

        $space->refresh()->load('branding');

        $this->assertSame('https://example.com/logo.png', $space->branding->logo_url);
        $this->assertSame('#112233', $space->branding->primary_color);
        $this->assertSame('#FAFAFA', $space->branding->background_color);
        $this->assertSame('Roboto', $space->branding->font_family);
        $this->assertSame('Share your story', $space->branding->button_text);
        $this->assertSame('Thanks a lot!', $space->branding->thank_you_message);
    }

    public function test_it_rejects_an_invalid_hex_color(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->put(route('spaces.branding.update', TestFixturesSeeder::SPACE_SLUG), [
                'primary_color' => 'not-a-color',
                'background_color' => '#FFFFFF',
                'font_family' => 'Inter',
                'button_text' => 'Go',
                'thank_you_message' => 'Thanks',
            ])
            ->assertSessionHasErrors('primary_color');
    }

    public function test_it_rejects_a_non_http_logo_url(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->put(route('spaces.branding.update', TestFixturesSeeder::SPACE_SLUG), [
                'logo_url' => 'javascript:alert(1)',
                'primary_color' => '#4F46E5',
                'background_color' => '#FFFFFF',
                'font_family' => 'Inter',
                'button_text' => 'Go',
                'thank_you_message' => 'Thanks',
            ])
            ->assertSessionHasErrors('logo_url');
    }

    public function test_it_forbids_editing_branding_of_another_users_space(): void
    {
        $this->actingAs($this->fixtureStranger())
            ->get(route('spaces.branding.edit', TestFixturesSeeder::SPACE_SLUG))
            ->assertForbidden();
    }
}
