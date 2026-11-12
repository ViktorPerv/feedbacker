<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Widget;
use Tests\Fixtures\TestFixturesSeeder;

/**
 * Типизированный доступ к записям, созданным TestFixturesSeeder.
 *
 * Результат каждого поиска кешируется на время теста, поэтому повторное чтение
 * одной фикстуры выполняет только один запрос.
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

    /** Содержит только разрешение `translations.manage`. */
    protected function fixtureTranslator(): User
    {
        return $this->fixtureUser(TestFixturesSeeder::TRANSLATOR_EMAIL);
    }

    protected function fixtureUser(string $email): User
    {
        return $this->fixtureCache["user:{$email}"] ??= User::where('email', $email)->firstOrFail();
    }

    /** Активное пространство владельца с отзывами во всех статусах. */
    protected function fixtureSpace(): Space
    {
        return $this->fixtureSpaceBySlug(TestFixturesSeeder::SPACE_SLUG);
    }

    /** Пространство владельца, закрытое для новых отзывов. */
    protected function fixtureInactiveSpace(): Space
    {
        return $this->fixtureSpaceBySlug(TestFixturesSeeder::INACTIVE_SPACE_SLUG);
    }

    /** Пространство другого пользователя для проверок разграничения доступа. */
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

    /** Плиточный виджет активного пространства владельца. */
    protected function fixtureWidget(): Widget
    {
        return $this->fixtureWidgetByToken(TestFixturesSeeder::MASONRY_WIDGET_TOKEN);
    }

    /** Виджет-бейдж активного пространства владельца. */
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
