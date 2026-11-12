<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Testimonial;
use App\Notifications\NewTestimonialNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

/**
 * Tags, full-text search and the new-testimonial notification.
 */
class TestimonialSearchTest extends TestCase
{
    // ── Full-text search ──

    public function test_search_matches_the_content(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'onboarding',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials.data', 1)
                ->where('testimonials.data.0.author_name', TestFixturesSeeder::APPROVED_FEATURED)
            );
    }

    public function test_search_matches_the_author(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'Bob',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials.data', 1)
                ->where('testimonials.data.0.author_name', TestFixturesSeeder::APPROVED_PLAIN)
            );
    }

    public function test_search_matches_the_company(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'Northwind',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials.data', 1)
                ->where('testimonials.data.0.author_name', TestFixturesSeeder::APPROVED_PLAIN)
            );
    }

    public function test_search_is_case_insensitive(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'ONBOARDING',
            ]))
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 1));
    }

    public function test_search_matches_a_prefix(): void
    {
        // "embed" should find "embeds" — this is what the `:*` prefix buys,
        // and it stands in for the stemming `simple` does not do.
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'embed',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials.data', 1)
                ->where('testimonials.data.0.author_name', TestFixturesSeeder::APPROVED_PLAIN)
            );
    }

    public function test_search_requires_every_term(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'onboarding Northwind',
            ]))
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 0));
    }

    public function test_search_returns_nothing_for_an_absent_term(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'zzzznotfound',
            ]))
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 0));
    }

    public function test_punctuation_only_search_does_not_error(): void
    {
        // to_tsquery('') is a syntax error in PostgreSQL, so the scope has to
        // skip the clause rather than pass an empty query. Acme Inc. holds four
        // testimonials: approved, approved, pending and rejected.
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => '!!! --- ???',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 4));
    }

    public function test_search_does_not_leak_across_spaces(): void
    {
        // The foreign space has an approved testimonial too.
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'another account',
            ]))
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 0));
    }

    public function test_search_combines_with_a_status_filter(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'search' => 'onboarding',
                'status' => 'pending',
            ]))
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 0));
    }

    public function test_to_tsquery_builds_anded_prefix_terms(): void
    {
        $this->assertSame('hello:* & world:*', Testimonial::toTsquery('Hello, world!'));
        $this->assertNull(Testimonial::toTsquery('   '));
        $this->assertNull(Testimonial::toTsquery('!!!'));
        // Apostrophes and punctuation are separators, not term characters.
        $this->assertSame('don:* & t:*', Testimonial::toTsquery("don't"));
    }

    public function test_to_tsquery_handles_cyrillic(): void
    {
        $this->assertSame('отзыв:*', Testimonial::toTsquery('отзыв'));
    }

    // ── Tags ──

    public function test_the_inbox_exposes_tags(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', TestFixturesSeeder::SPACE_SLUG))
            ->assertInertia(fn (Assert $page) => $page
                ->has('availableTags', 2)
                ->where('availableTags.0.tag', TestFixturesSeeder::TAG_SUPPORT)
                ->where('availableTags.0.count', 2)
            );
    }

    public function test_the_owner_can_add_a_tag(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->actingAs($this->fixtureOwner())
            ->patch(route('testimonials.tags', $testimonial->id), [
                'tags' => ['Onboarding', '  Support  '],
            ])
            ->assertRedirect();

        // Tags are normalised: trimmed and lowercased.
        $this->assertSame(['onboarding', 'support'], $testimonial->fresh()->tags);
    }

    public function test_the_owner_can_clear_every_tag(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::APPROVED_FEATURED);

        $this->assertNotEmpty($testimonial->tags);

        $this->actingAs($this->fixtureOwner())
            ->patch(route('testimonials.tags', $testimonial->id), ['tags' => []])
            ->assertRedirect();

        $this->assertSame([], $testimonial->fresh()->tags);
    }

    public function test_a_stranger_cannot_tag(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->actingAs($this->fixtureStranger())
            ->patch(route('testimonials.tags', $testimonial->id), ['tags' => ['nope']])
            ->assertForbidden();

        // The fixture starts with an empty tag list, not null.
        $this->assertEmpty($testimonial->fresh()->tags);
    }

    public function test_an_admin_can_tag_any_testimonial(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->actingAs($this->fixtureAdmin())
            ->patch(route('testimonials.tags', $testimonial->id), ['tags' => ['vip']])
            ->assertRedirect();

        $this->assertSame(['vip'], $testimonial->fresh()->tags);
    }

    public function test_too_many_tags_are_rejected(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->actingAs($this->fixtureOwner())
            ->patch(route('testimonials.tags', $testimonial->id), [
                'tags' => array_map(fn ($i) => "tag{$i}", range(1, Testimonial::MAX_TAGS + 1)),
            ])
            ->assertSessionHasErrors('tags');
    }

    public function test_an_overlong_tag_is_rejected(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->actingAs($this->fixtureOwner())
            ->patch(route('testimonials.tags', $testimonial->id), [
                'tags' => [str_repeat('a', Testimonial::MAX_TAG_LENGTH + 1)],
            ])
            ->assertSessionHasErrors('tags.0');
    }

    public function test_duplicate_tags_are_rejected(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $this->actingAs($this->fixtureOwner())
            ->patch(route('testimonials.tags', $testimonial->id), [
                'tags' => ['dup', 'dup'],
            ])
            ->assertSessionHasErrors('tags.0');
    }

    public function test_normalise_tags_deduplicates_case_variants(): void
    {
        $this->assertSame(
            ['vip', 'support'],
            Testimonial::normaliseTags(['VIP', 'vip', ' support ', '']),
        );
    }

    public function test_a_blank_tag_does_not_reject_the_whole_list(): void
    {
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        // A tag input emits "" when cleared, and ConvertEmptyStringsToNull
        // turns that into null — which would fail the `string` rule and lose
        // the tags the user did enter.
        $this->actingAs($this->fixtureOwner())
            ->patch(route('testimonials.tags', $testimonial->id), [
                'tags' => ['Onboarding', '  ENTERPRISE  ', 'onboarding', ''],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(['onboarding', 'enterprise'], $testimonial->fresh()->tags);
    }

    public function test_the_inbox_filters_by_tag(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'tag' => TestFixturesSeeder::TAG_ENTERPRISE,
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials.data', 1)
                ->where('testimonials.data.0.author_name', TestFixturesSeeder::APPROVED_FEATURED)
            );
    }

    public function test_a_tag_filter_can_return_several(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'tag' => TestFixturesSeeder::TAG_SUPPORT,
            ]))
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 2));
    }

    public function test_an_unknown_tag_returns_nothing(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'tag' => 'does-not-exist',
            ]))
            ->assertInertia(fn (Assert $page) => $page->has('testimonials.data', 0));
    }

    public function test_tag_and_search_combine(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.testimonials.index', [
                'space' => TestFixturesSeeder::SPACE_SLUG,
                'tag' => TestFixturesSeeder::TAG_SUPPORT,
                'search' => 'onboarding',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('testimonials.data', 1)
                ->where('testimonials.data.0.author_name', TestFixturesSeeder::APPROVED_FEATURED)
            );
    }

    // ── Notification ──

    public function test_submitting_a_testimonial_notifies_the_owner(): void
    {
        Notification::fake();

        $space = $this->fixtureSpace();

        $this->post(route('collect.store', $space->slug), [
            'author_name' => 'Dana Scully',
            'author_email' => 'dana@example.test',
            'rating' => 5,
            'content' => 'A genuinely useful tool.',
        ])->assertRedirect();

        Notification::assertSentTo(
            $space->user,
            NewTestimonialNotification::class,
            fn (NewTestimonialNotification $notification) => $notification->testimonial->author_name === 'Dana Scully',
        );
    }

    public function test_the_notification_is_queued(): void
    {
        // A public submission must never wait on an SMTP handshake.
        $this->assertInstanceOf(
            ShouldQueue::class,
            new NewTestimonialNotification($this->fixtureTestimonial(TestFixturesSeeder::PENDING)),
        );
    }

    public function test_the_notification_names_the_space_and_author(): void
    {
        $space = $this->fixtureSpace();
        $testimonial = $this->fixtureTestimonial(TestFixturesSeeder::PENDING);

        $mail = (new NewTestimonialNotification($testimonial))->toMail($space->user);

        $this->assertStringContainsString($space->name, $mail->subject);
        $this->assertStringContainsString(
            $testimonial->author_name,
            implode(' ', array_map(fn ($line) => (string) $line, $mail->introLines)),
        );
    }

    public function test_a_honeypot_hit_does_not_notify(): void
    {
        Notification::fake();

        $space = $this->fixtureSpace();

        $this->post(route('collect.store', $space->slug), [
            'author_name' => 'Spam Bot',
            'author_email' => 'bot@example.test',
            'rating' => 5,
            'content' => 'Buy cheap things now, visit my site for a great offer today.',
            'website' => 'http://spam.example',
        ]);

        Notification::assertNothingSent();
    }

    public function test_an_inactive_space_does_not_notify(): void
    {
        Notification::fake();

        $space = $this->fixtureInactiveSpace();

        $this->post(route('collect.store', $space->slug), [
            'author_name' => 'Nobody',
            'author_email' => 'nobody@example.test',
            'rating' => 5,
            'content' => 'Trying to submit to a closed space.',
        ]);

        Notification::assertNothingSent();
    }
}
