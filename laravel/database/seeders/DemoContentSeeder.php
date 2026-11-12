<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TestimonialStatusEnum;
use App\Enums\WidgetLayoutEnum;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Database\Seeder;

/**
 * Realistic content for the landing page demo.
 *
 * The homepage embeds a real widget, so the space behind it needs real
 * approved testimonials — an empty wall demonstrates nothing. Idempotent:
 * re-running updates the same rows rather than duplicating them.
 *
 *   make artisan cmd="db:seed --class=DemoContentSeeder"
 */
class DemoContentSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private const REVIEWS = [
        [
            'author_name' => 'Marta Kowalski',
            'author_email' => 'marta@brightlane.io',
            'author_title' => 'Head of Growth',
            'author_company' => 'Brightlane',
            'rating' => 5,
            'content' => 'We replaced a spreadsheet, a form builder and a screenshot folder with one link. The wall went live the same afternoon and our signup page converts noticeably better.',
            'tags' => ['growth', 'landing page'],
            'is_featured' => true,
        ],
        [
            'author_name' => 'Daniel Osei',
            'author_email' => 'daniel@northpeak.dev',
            'author_title' => 'Founder',
            'author_company' => 'Northpeak',
            'rating' => 5,
            'content' => 'The moderation inbox is the part I did not know I needed. Nothing reaches the site until I approve it, so I stopped worrying about what a customer might paste in.',
            'tags' => ['moderation'],
            'is_featured' => true,
        ],
        [
            'author_name' => 'Sofia Marchetti',
            'author_email' => 'sofia@lumenlabs.co',
            'author_title' => 'Product Marketing',
            'author_company' => 'Lumen Labs',
            'rating' => 4,
            'content' => 'Four layouts out of the box meant we could put a carousel on the pricing page and a badge in the footer without asking anyone for design time.',
            'tags' => ['widgets'],
            'is_featured' => false,
        ],
        [
            'author_name' => 'Tomas Lindqvist',
            'author_email' => 'tomas@vantatech.se',
            'author_title' => 'Engineering Manager',
            'author_company' => 'Vanta Tech',
            'rating' => 5,
            'content' => 'One line of HTML and it was live. The embed is served from its own service, so it never slowed our own pages down — that was the deciding factor for us.',
            'tags' => ['performance', 'engineering'],
            'is_featured' => true,
        ],
        [
            'author_name' => 'Priya Raman',
            'author_email' => 'priya@quillworks.in',
            'author_title' => 'Customer Success Lead',
            'author_company' => 'Quillworks',
            'rating' => 5,
            'content' => 'Asking for a review used to be a five-email thread. Now I send one link and the review is waiting for me, already formatted, with a rating attached.',
            'tags' => ['customer success'],
            'is_featured' => false,
        ],
        [
            'author_name' => 'Henrik Bauer',
            'author_email' => 'henrik@altmark.de',
            'author_title' => 'Managing Director',
            'author_company' => 'Altmark',
            'rating' => 4,
            'content' => 'Straightforward tool that does one job well. I would like a few more layout options eventually, but what is here already covers everything we needed on day one.',
            'tags' => ['feedback'],
            'is_featured' => false,
        ],
    ];

    public function run(): void
    {
        $space = Space::firstOrCreate(
            ['slug' => 'admin-space'],
            [
                'name' => 'Admin Space',
                'user_id' => User::where('email', 'admin@feedbacker.test')->value('id')
                    ?? User::query()->value('id'),
                'is_active' => true,
            ],
        );

        foreach (self::REVIEWS as $index => $review) {
            Testimonial::updateOrCreate(
                [
                    'space_id' => $space->id,
                    'author_name' => $review['author_name'],
                ],
                [
                    ...$review,
                    'status' => TestimonialStatusEnum::Approved,
                    'source' => 'direct',
                    // Staggered so the wall has a visible order.
                    'created_at' => now()->subDays(count(self::REVIEWS) - $index),
                    'approved_at' => now()->subDays(count(self::REVIEWS) - $index),
                ],
            );
        }

        $widget = Widget::updateOrCreate(
            ['token' => 'demoWallOnLanding000001'],
            [
                'space_id' => $space->id,
                'name' => 'Landing page wall',
                'layout' => WidgetLayoutEnum::Masonry,
                'config' => array_merge(WidgetLayoutEnum::Masonry->defaultConfig(), [
                    'theme' => 'light',
                    'columns' => 3,
                    'limit' => 6,
                    'ratings' => true,
                    'footer' => true,
                    'heading' => 'What our customers say',
                ]),
                'is_active' => true,
            ],
        );

        $this->command?->info(sprintf(
            'Demo content ready: %d reviews in "%s", widget "%s".',
            count(self::REVIEWS),
            $space->name,
            $widget->name,
        ));
    }
}
