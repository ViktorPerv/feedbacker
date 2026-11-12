<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Enums\RoleEnum;
use App\Enums\WidgetLayoutEnum;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\TestimonialClick;
use App\Models\User;
use App\Models\WidgetView;
use Database\Seeders\LanguageSeeder;
use Illuminate\Database\Seeder;

/**
 * Эталонный набор данных для всех тестов.
 *
 * Тесты находят записи по объявленным здесь стабильным идентификаторам вместо
 * создания собственных данных. Это сохраняет читаемость проверок и позволяет
 * менять структуру данных только в одном файле.
 *
 * Наполнитель выполняется внутри транзакции каждого теста, поэтому созданные
 * данные не сохраняются после его завершения.
 */
class TestFixturesSeeder extends Seeder
{
    // ── Accounts ──
    public const ADMIN_EMAIL = 'admin@feedbacker.test';

    public const OWNER_EMAIL = 'owner@feedbacker.test';

    public const STRANGER_EMAIL = 'stranger@feedbacker.test';

    /** Содержит только `translations.manage` без доступа к данным клиентов. */
    public const TRANSLATOR_EMAIL = 'translator@feedbacker.test';

    public const PASSWORD = 'password';

    // ── Spaces ──
    /** Активное пространство владельца с отзывами во всех статусах. */
    public const SPACE_SLUG = 'acme-inc';

    /** Пространство владельца, закрытое для новых отзывов. */
    public const INACTIVE_SPACE_SLUG = 'globex';

    /** Пространство другого пользователя для проверок разграничения доступа. */
    public const FOREIGN_SPACE_SLUG = 'initech';

    // ── Testimonials (keyed by author name) ──
    public const APPROVED_FEATURED = 'Alice Approved';

    public const APPROVED_PLAIN = 'Bob Approved';

    public const PENDING = 'Carol Pending';

    public const REJECTED = 'Dave Rejected';

    public const INACTIVE_SPACE_PENDING = 'Erin Pending';

    public const FOREIGN_APPROVED = 'Frank Approved';

    // ── Analytics on acme-inc ──
    // Deliberately round numbers so assertions stay readable: 8 views and
    // 4 clicks is a 50% click rate, spread over three days.
    public const VIEWS_TOTAL = 8;

    public const CLICKS_TOTAL = 4;

    public const CLICKS_ON_FEATURED = 3;

    public const CLICKS_ON_PLAIN = 1;

    // ── Widgets on acme-inc ──
    public const MASONRY_WIDGET_TOKEN = 'fixtureMasonryWidget0001';

    public const BADGE_WIDGET_TOKEN = 'fixtureBadgeWidget000002';

    /** Отзыв со ссылкой на видео. */
    public const VIDEO_TESTIMONIAL = self::APPROVED_PLAIN;

    public const VIDEO_URL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    // ── Tags ──
    public const TAG_ENTERPRISE = 'enterprise';

    public const TAG_SUPPORT = 'support';

    public function run(): void
    {
        // Reference data the migration seeds in production. The test database
        // is cloned schema-only, so it has to be re-applied here or the locale
        // middleware would find no supported language.
        (new LanguageSeeder)->run();

        $admin = $this->createUser(self::ADMIN_EMAIL, 'Admin User', [RoleEnum::Admin->value]);
        $owner = $this->createUser(self::OWNER_EMAIL, 'Space Owner', [RoleEnum::User->value]);
        $stranger = $this->createUser(self::STRANGER_EMAIL, 'Stranger User', [RoleEnum::User->value]);
        $translator = $this->createUser(self::TRANSLATOR_EMAIL, 'Translator User', [RoleEnum::Translator->value]);

        $space = $this->createSpace($owner, self::SPACE_SLUG, 'Acme Inc.', true, [
            'primary_color' => '#4F46E5',
            'button_text' => 'Leave a testimonial',
            'thank_you_message' => 'Thank you for your feedback!',
        ]);

        $inactive = $this->createSpace($owner, self::INACTIVE_SPACE_SLUG, 'Globex', false);
        $foreign = $this->createSpace($stranger, self::FOREIGN_SPACE_SLUG, 'Initech', true);

        // Timestamps are staggered on purpose. The schema stores timestamps
        // with second precision, so records created in the same second tie and
        // `ORDER BY created_at DESC` becomes non-deterministic. Spacing them a
        // day apart keeps ordering assertions meaningful.
        //
        // Acme Inc. — one testimonial in each state so status filters, counts
        // and moderation flows all have something to work with.
        Testimonial::factory()->for($space)->approved()->featured()->create([
            'author_name' => self::APPROVED_FEATURED,
            'author_email' => 'alice@globex.io',
            'author_title' => 'CTO',
            'author_company' => 'Globex',
            'rating' => 5,
            'content' => 'Cut our onboarding time in half. The team actually uses it.',
            'tags' => [self::TAG_ENTERPRISE, self::TAG_SUPPORT],
            'created_at' => now()->subDays(4),
        ]);

        Testimonial::factory()->for($space)->approved()->create([
            'author_name' => self::APPROVED_PLAIN,
            'author_email' => 'bob@northwind.test',
            'author_title' => 'Founder',
            'author_company' => 'Northwind',
            'rating' => 3,
            'content' => 'Straightforward to set up and the embeds look good.',
            'video_url' => self::VIDEO_URL,
            'tags' => [self::TAG_SUPPORT],
            'created_at' => now()->subDays(3),
        ]);

        Testimonial::factory()->for($space)->pending()->create([
            'author_name' => self::PENDING,
            'author_email' => 'carol@initech.test',
            'author_title' => 'Head of Support',
            'author_company' => 'Initech',
            'rating' => 4,
            'content' => 'Our customers finally have somewhere to leave praise.',
            'created_at' => now()->subDays(2),
        ]);

        Testimonial::factory()->for($space)->rejected()->create([
            'author_name' => self::REJECTED,
            'author_email' => 'dave@example.test',
            'rating' => 1,
            'content' => 'Not a good fit for us, unfortunately.',
            'created_at' => now()->subDay(),
        ]);

        Testimonial::factory()->for($inactive)->pending()->create([
            'author_name' => self::INACTIVE_SPACE_PENDING,
            'rating' => 5,
            'content' => 'Submitted while the space was still open.',
            'created_at' => now()->subDays(2),
        ]);

        Testimonial::factory()->for($foreign)->approved()->create([
            'author_name' => self::FOREIGN_APPROVED,
            'rating' => 4,
            'content' => 'Belongs to another account entirely.',
            'created_at' => now()->subDays(2),
        ]);

        // Keep the reference so the variable is not flagged as unused; the
        // admin account is fetched by email in tests.
        unset($admin);

        $this->seedAnalytics($space);
        $this->seedWidgets($space);
    }

    /**
     * Два сохранённых виджета acme-inc: плиточная стена и компактный бейдж.
     */
    private function seedWidgets(Space $space): void
    {
        $space->widgets()->create([
            'name' => 'Homepage wall',
            'layout' => WidgetLayoutEnum::Masonry,
            'token' => self::MASONRY_WIDGET_TOKEN,
            'config' => array_merge(WidgetLayoutEnum::Masonry->defaultConfig(), [
                'theme' => 'light',
                'columns' => 2,
                'limit' => 9,
            ]),
            'is_active' => true,
        ]);

        $space->widgets()->create([
            'name' => 'Footer badge',
            'layout' => WidgetLayoutEnum::Badge,
            'token' => self::BADGE_WIDGET_TOKEN,
            'config' => array_merge(WidgetLayoutEnum::Badge->defaultConfig(), [
                'theme' => 'dark',
                'min_rating' => 4,
            ]),
            'is_active' => true,
        ]);
    }

    /**
     * Показы виджетов и клики по карточкам для acme-inc.
     *
     * Восемь показов и четыре клика распределены по трём дням, чтобы сводка,
     * дневной график и список популярных отзывов имели стабильные значения.
     */
    private function seedAnalytics(Space $space): void
    {
        $featured = Testimonial::where('author_name', self::APPROVED_FEATURED)->firstOrFail();
        $plain = Testimonial::where('author_name', self::APPROVED_PLAIN)->firstOrFail();

        // 4 views today, 3 yesterday, 1 three days ago.
        foreach ([0 => 4, 1 => 3, 3 => 1] as $daysAgo => $count) {
            WidgetView::factory()
                ->for($space)
                ->daysAgo($daysAgo)
                ->count($count)
                ->create();
        }

        // 2 clicks today and 1 yesterday, all on the featured testimonial.
        foreach ([0 => 2, 1 => 1] as $daysAgo => $count) {
            TestimonialClick::factory()
                ->for($space)
                ->for($featured, 'testimonial')
                ->daysAgo($daysAgo)
                ->count($count)
                ->create();
        }

        TestimonialClick::factory()
            ->for($space)
            ->for($plain, 'testimonial')
            ->daysAgo(1)
            ->create();
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function createUser(string $email, string $name, array $roles): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
            'email_verified_at' => now(),
        ]);

        // Override whatever default the model event assigned.
        $user->syncRoles($roles);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $branding
     */
    private function createSpace(
        User $owner,
        string $slug,
        string $name,
        bool $isActive,
        array $branding = [],
    ): Space {
        $space = $owner->spaces()->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => $isActive,
        ]);

        if ($branding !== []) {
            $space->branding()->update($branding);
        }

        return $space->refresh();
    }
}
