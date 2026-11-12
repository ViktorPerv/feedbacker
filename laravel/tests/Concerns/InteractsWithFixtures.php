<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Widget;
use Tests\Fixtures\TestFixturesSeeder;

/**
 * Typed access to the records created by TestFixturesSeeder.
 *
 * Each lookup is memoised for the duration of a single test, so reading the
 * same fixture repeatedly costs one query.
 */
trait InteractsWithFixtures
{
    /** @var array<string, mixed> */
    private array $fixtureCache = [];

    protected function fixtureAdmin(): User
    {
        return $this->fixtureUser(TestFixturesSeeder::ADMIN_EMAIL);
    }

    protected function fixtureOwner(): User
    {
        return $this->fixtureUser(TestFixturesSeeder::OWNER_EMAIL);
    }

    protected function fixtureStranger(): User
    {
        return $this->fixtureUser(TestFixturesSeeder::STRANGER_EMAIL);
    }

    /** Holds `translations.manage` and nothing else. */
    protected function fixtureTranslator(): User
    {
        return $this->fixtureUser(TestFixturesSeeder::TRANSLATOR_EMAIL);
    }

    protected function fixtureUser(string $email): User
    {
        return $this->fixtureCache["user:{$email}"] ??= User::where('email', $email)->firstOrFail();
    }

    /** The owner's active space, holding testimonials in every state. */
    protected function fixtureSpace(): Space
    {
        return $this->fixtureSpaceBySlug(TestFixturesSeeder::SPACE_SLUG);
    }

    /** The owner's space, closed for submissions. */
    protected function fixtureInactiveSpace(): Space
    {
        return $this->fixtureSpaceBySlug(TestFixturesSeeder::INACTIVE_SPACE_SLUG);
    }

    /** The stranger's space, used for cross-account checks. */
    protected function fixtureForeignSpace(): Space
    {
        return $this->fixtureSpaceBySlug(TestFixturesSeeder::FOREIGN_SPACE_SLUG);
    }

    protected function fixtureSpaceBySlug(string $slug): Space
    {
        return $this->fixtureCache["space:{$slug}"] ??= Space::where('slug', $slug)
            ->firstOrFail();
    }

    protected function fixtureTestimonial(string $authorName): Testimonial
    {
        return $this->fixtureCache["testimonial:{$authorName}"] ??= Testimonial::where(
            'author_name',
            $authorName,
        )->firstOrFail();
    }

    /** The default masonry widget on the owner's active space. */
    protected function fixtureWidget(): Widget
    {
        return $this->fixtureWidgetByToken(TestFixturesSeeder::MASONRY_WIDGET_TOKEN);
    }

    /** The badge widget on the owner's active space. */
    protected function fixtureBadgeWidget(): Widget
    {
        return $this->fixtureWidgetByToken(TestFixturesSeeder::BADGE_WIDGET_TOKEN);
    }

    protected function fixtureWidgetByToken(string $token): Widget
    {
        return $this->fixtureCache["widget:{$token}"] ??= Widget::where('token', $token)
            ->firstOrFail();
    }
}
