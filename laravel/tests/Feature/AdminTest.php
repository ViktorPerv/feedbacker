<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Space;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use JsonException;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

/**
 * Управление чужими учётными записями с ролью `admin`.
 *
 * В фикстурах у администратора нет собственных данных: acme-inc и globex
 * принадлежат владельцу, а initech — другому пользователю.
 */
class AdminTest extends TestCase
{
    /**
     * Извлекает запись из свойств Inertia по slug, чтобы проверки не зависели
     * от порядка, выбранного контроллером.
     */
    private function spaceProps(TestResponse $response, string $slug): array
    {
        $spaces = $response->viewData('page')['props']['spaces'];

        foreach ($spaces as $space) {
            if ($space['slug'] === $slug) {
                return $space;
            }
        }

        $this->fail("Space [{$slug}] is missing from the index props.");
    }

    public function test_an_admin_sees_every_space_in_the_index(): void
    {
        $response = $this->actingAs($this->fixtureAdmin())
            ->get(route('spaces.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spaces/Index')
                ->where('canViewAll', true)
                ->has('spaces', 3)
            );

        $this->assertEqualsCanonicalizing(
            [
                TestFixturesSeeder::SPACE_SLUG,
                TestFixturesSeeder::INACTIVE_SPACE_SLUG,
                TestFixturesSeeder::FOREIGN_SPACE_SLUG,
            ],
            collect($response->viewData('page')['props']['spaces'])->pluck('slug')->all(),
        );
    }

    public function test_a_member_only_sees_their_own_spaces(): void
    {
        $this->actingAs($this->fixtureStranger())
            ->get(route('spaces.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewAll', false)
                ->has('spaces', 1)
                ->where('spaces.0.slug', TestFixturesSeeder::FOREIGN_SPACE_SLUG)
            );
    }

    public function test_an_admin_can_view_another_users_space(): void
    {
        $this->actingAs($this->fixtureAdmin())
            ->get(route('spaces.show', TestFixturesSeeder::SPACE_SLUG))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spaces/Show')
                ->where('can.update', true)
                ->where('can.delete', true)
            );
    }

    public function test_an_admin_can_update_another_users_space(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureAdmin())
            ->put(route('spaces.update', $space->slug), [
                'name' => 'Renamed by admin',
                'slug' => $space->slug,
            ])
            ->assertRedirect(route('spaces.show', $space->slug));

        $this->assertSame('Renamed by admin', $space->refresh()->name);
    }

    public function test_an_admin_can_delete_another_users_space(): void
    {
        $space = $this->fixtureSpace();

        $this->actingAs($this->fixtureAdmin())
            ->delete(route('spaces.destroy', $space->slug))
            ->assertRedirect(route('spaces.index'));

        $this->assertNull(Space::find($space->id));
    }

    public function test_a_member_cannot_update_another_users_space(): void
    {
        $this->actingAs($this->fixtureStranger())
            ->put(route('spaces.update', TestFixturesSeeder::SPACE_SLUG), [
                'name' => 'Nope',
                'slug' => TestFixturesSeeder::SPACE_SLUG,
            ])
            ->assertForbidden();
    }

    public function test_a_member_cannot_delete_another_users_space(): void
    {
        $this->actingAs($this->fixtureStranger())
            ->delete(route('spaces.destroy', TestFixturesSeeder::SPACE_SLUG))
            ->assertForbidden();

        $this->assertTrue(
            Space::where('slug', TestFixturesSeeder::SPACE_SLUG)->exists()
        );
    }

    public function test_the_index_reports_per_space_abilities_and_owner(): void
    {
        $response = $this->actingAs($this->fixtureAdmin())
            ->get(route('spaces.index'))
            ->assertOk();

        $foreign = $this->spaceProps($response, TestFixturesSeeder::FOREIGN_SPACE_SLUG);
        $this->assertTrue($foreign['can']['update']);
        $this->assertTrue($foreign['can']['delete']);
        $this->assertTrue($foreign['can']['moderate']);
        $this->assertSame($this->fixtureStranger()->name, $foreign['owner_name']);

        // The admin owns nothing, so every row shows an owner and none is own.
        foreach ($response->viewData('page')['props']['spaces'] as $space) {
            $this->assertNotNull($space['owner_name']);
        }
    }

    public function test_the_index_hides_the_owner_when_it_is_you(): void
    {
        $response = $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.index'))
            ->assertOk();

        $own = $this->spaceProps($response, TestFixturesSeeder::SPACE_SLUG);

        $this->assertNull($own['owner_name']);
        $this->assertTrue($own['can']['update']);
        $this->assertTrue($own['can']['moderate']);
    }

    public function test_a_member_cannot_see_a_foreign_space_at_all(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.index'))
            ->assertInertia(fn (Assert $page) => $page->has('spaces', 2));

        $this->actingAs($this->fixtureOwner())
            ->get(route('spaces.show', TestFixturesSeeder::FOREIGN_SPACE_SLUG))
            ->assertForbidden();
    }

    /**
     * @throws JsonException
     */
    public function test_an_admin_can_manage_their_own_space(): void
    {
        // The fixtures deliberately leave the admin with no space, so create
        // one here to cover the owner path for an admin account.
        $space = Space::factory()->for($this->fixtureAdmin())->create([
            'name' => 'Admin Space',
            'slug' => 'admin-space',
        ]);

        $this->actingAs($this->fixtureAdmin())
            ->get(route('spaces.show', $space->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', true)
                ->where('can.delete', true)
            );

        $this->actingAs($this->fixtureAdmin())
            ->put(route('spaces.update', $space->slug), [
                'name' => 'Renamed',
                'slug' => $space->slug,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_a_roleless_account_can_still_work_with_its_own_space(): void
    {
        // Ownership alone authorises own-resource operations, so an account
        // whose role was revoked does not lose access to its own data.
        $user = User::factory()->roleless()->create();
        $space = Space::factory()->for($user)->create(['slug' => 'orphan']);

        $this->assertSame(0, $user->roles()->count());

        $this->actingAs($user)
            ->get(route('spaces.show', 'orphan'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('spaces.index'))
            ->assertInertia(fn (Assert $page) => $page->has('spaces', 1));
    }

    public function test_the_dashboard_counts_every_space_for_an_admin(): void
    {
        $this->actingAs($this->fixtureAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('canViewAll', true)
                ->where('spacesCount', 3)
                // Alice, Bob and Frank are approved; Carol, Dave and Erin are not.
                ->where('testimonialsCount', 6)
                ->where('pendingCount', 2)
            );
    }

    public function test_the_dashboard_counts_only_own_data_for_a_member(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewAll', false)
                ->where('spacesCount', 2)
                // acme-inc holds four, globex one.
                ->where('testimonialsCount', 5)
                ->where('pendingCount', 2)
            );
    }
}
