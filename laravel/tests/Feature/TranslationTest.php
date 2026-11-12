<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionEnum;
use App\Models\Language;
use App\Models\Translation;
use Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\TestFixturesSeeder;
use Tests\TestCase;

/**
 * Locales, database-backed translations and the catalogue CRUD.
 */
class TranslationTest extends TestCase
{
    // ── Languages ──

    public function test_english_is_seeded_as_the_default(): void
    {
        $this->assertSame('en', Language::defaultCode());
    }

    public function test_both_locales_are_seeded_in_order(): void
    {
        $this->assertSame(['en', 'ru'], Language::codes());
    }

    public function test_unsupported_locales_are_rejected(): void
    {
        $this->assertTrue(Language::isSupported('ru'));
        $this->assertFalse(Language::isSupported('de'));
        $this->assertFalse(Language::isSupported(null));
    }

    public function test_an_inactive_language_is_not_offered(): void
    {
        Language::where('code', 'ru')->update(['is_active' => false]);

        $this->assertSame(['en'], Language::codes());
        $this->assertSame(['en'], array_column(Language::options(), 'code'));
    }

    // ── Database-backed translation ──

    public function test_a_file_value_is_used_when_no_row_exists(): void
    {
        // Nothing has been imported into the database for this key.
        $this->assertSame('Dashboard', __('app.nav.dashboard'));
    }

    public function test_a_database_row_overrides_the_file(): void
    {
        Translation::create([
            'locale' => 'en',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Control room',
        ]);

        $this->assertSame('Control room', __('app.nav.dashboard'));
    }

    public function test_the_override_is_per_locale(): void
    {
        Translation::create([
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Моя панель',
        ]);

        App::setLocale('ru');
        $this->assertSame('Моя панель', __('app.nav.dashboard'));

        App::setLocale('en');
        $this->assertSame('Dashboard', __('app.nav.dashboard'));
    }

    public function test_a_nested_key_from_the_database_resolves(): void
    {
        // The row is flat (`common.save`); the translator looks it up as a
        // nested array, so the loader has to expand it.
        Translation::create([
            'locale' => 'en',
            'group' => 'app',
            'key' => 'common.save',
            'value' => 'Persist',
        ]);

        $this->assertSame('Persist', __('app.common.save'));
    }

    public function test_a_row_without_a_value_falls_back_to_the_file(): void
    {
        Translation::create([
            'locale' => 'en',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => null,
        ]);

        $this->assertSame('Dashboard', __('app.nav.dashboard'));
    }

    public function test_updating_a_row_takes_effect_without_clearing_anything(): void
    {
        $translation = Translation::create([
            'locale' => 'en',
            'group' => 'app',
            'key' => 'nav.spaces',
            'value' => 'First',
        ]);

        $this->assertSame('First', __('app.nav.spaces'));

        // The loader memoises per request; a write has to invalidate it.
        $translation->update(['value' => 'Second']);

        $this->assertSame('Second', __('app.nav.spaces'));
    }

    public function test_deleting_a_row_restores_the_file_value(): void
    {
        $translation = Translation::create([
            'locale' => 'en',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Temporary',
        ]);

        $this->assertSame('Temporary', __('app.nav.dashboard'));

        $translation->delete();

        $this->assertSame('Dashboard', __('app.nav.dashboard'));
    }

    public function test_vendor_namespaces_are_not_read_from_the_database(): void
    {
        Translation::create([
            'locale' => 'en',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Overridden',
        ]);

        // A namespaced lookup must still come from the package's own files.
        $this->assertNotSame('Overridden', __('app::nav.dashboard'));
    }

    public function test_the_frontend_map_resolves_through_the_fallback(): void
    {
        Translation::create([
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Панель управления',
        ]);

        App::setLocale('ru');
        $map = Translation::forFrontend('app');

        // Overridden from the database.
        $this->assertSame('Панель управления', $map['nav.dashboard']);
        // Not overridden, so the Russian file supplies it.
        $this->assertSame('Пространства', $map['nav.spaces']);
    }

    // ── Middleware and switching ──

    public function test_the_default_locale_is_used_without_any_signal(): void
    {
        $this->get(route('home'));

        $this->assertSame('en', app()->getLocale());
    }

    public function test_the_accept_language_header_is_honoured(): void
    {
        $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9')->get(route('home'));

        $this->assertSame('ru', app()->getLocale());
    }

    public function test_a_signed_in_users_saved_locale_wins_over_the_header(): void
    {
        $owner = $this->fixtureOwner();
        $owner->forceFill(['locale' => 'ru'])->save();

        $this->actingAs($owner)
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get(route('dashboard'));

        $this->assertSame('ru', app()->getLocale());
    }

    public function test_the_switcher_stores_the_choice_in_the_session(): void
    {
        $this->get(route('locale.switch', 'ru'))->assertRedirect();

        $this->assertSame('ru', session('locale'));

        $this->get(route('home'));
        $this->assertSame('ru', app()->getLocale());
    }

    public function test_the_switcher_persists_the_choice_to_the_profile(): void
    {
        $owner = $this->fixtureOwner();

        $this->actingAs($owner)->get(route('locale.switch', 'ru'))->assertRedirect();

        $this->assertSame('ru', $owner->fresh()->locale);
    }

    public function test_the_switcher_rejects_an_unknown_locale(): void
    {
        $this->get(route('locale.switch', 'de'))->assertNotFound();
    }

    public function test_guests_can_switch_language_on_the_public_page(): void
    {
        $space = $this->fixtureSpace();

        $this->get(route('locale.switch', 'ru'))->assertRedirect();
        $this->get(route('collect.show', $space->slug))->assertOk();
    }

    // ── Shared Inertia props ──

    public function test_the_frontend_receives_the_locale_and_translations(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'en')
                ->has('locales', 2)
                ->where('locales.1.code', 'ru')
                // The map is flat with dotted keys, so it cannot be reached
                // with Inertia's own dot notation.
                ->where('translations', fn ($map) => ($map['nav.dashboard'] ?? null) === 'Dashboard')
            );
    }

    public function test_the_frontend_receives_the_switched_locale(): void
    {
        $this->get(route('locale.switch', 'ru'));
        $space = $this->fixtureSpace();

        $this->get(route('collect.show', $space->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ru')
                ->where('translations', fn ($map) => ($map['nav.dashboard'] ?? null) === 'Панель')
            );
    }

    public function test_the_manage_translations_flag_is_shared(): void
    {
        $this->actingAs($this->fixtureTranslator())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.manageTranslations', true)
            );

        $this->actingAs($this->fixtureOwner())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.can.manageTranslations', false)
            );
    }

    // ── Catalogue CRUD ──

    public function test_no_page_shadows_the_shared_translation_map(): void
    {
        // A page prop named `translations` replaces the shared map, and every
        // t() call on that page then renders the raw key. That is exactly how
        // the catalogue shipped with untranslated buttons: its prop was the
        // paginator, so `t('translations.import')` returned the key itself.
        $admin = $this->fixtureAdmin();
        $space = $this->fixtureSpace();

        $urls = [
            route('dashboard'),
            route('spaces.index'),
            route('spaces.show', $space->slug),
            route('spaces.testimonials.index', $space->slug),
            route('spaces.widgets.index', $space->slug),
            route('spaces.branding.edit', $space->slug),
            route('spaces.analytics', $space->slug),
            route('profile.edit'),
            route('translations.index'),
            route('translations.create'),
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertInertia(function (Assert $page) use ($url): void {
                    $page->where('translations', function ($map) use ($url) {
                        // Inertia's test helper hands this back as a Collection
                        // even though the response serialises it as an object.
                        $values = $map instanceof Collection
                            ? $map->all()
                            : (array) $map;

                        // The shadowed case was the paginator: current_page,
                        // data, links — no interface keys at all.
                        $this->assertIsString(
                            $values['nav.dashboard'] ?? null,
                            "the shared translation map is shadowed on {$url}",
                        );

                        return true;
                    });
                });
        }
    }

    public function test_the_catalogue_passes_its_rows_under_a_non_colliding_prop(): void
    {
        $this->actingAs($this->fixtureTranslator())
            ->get(route('translations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data')
                // The shared map is intact alongside it.
                ->where('translations', fn ($map) => is_string($map['nav.dashboard'] ?? null))
            );
    }

    public function test_guests_are_redirected_from_the_catalogue(): void
    {
        $this->get(route('translations.index'))->assertRedirect(route('login'));
    }

    public function test_an_admin_can_open_the_catalogue(): void
    {
        $this->actingAs($this->fixtureAdmin())
            ->get(route('translations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Translations/Index')
                ->has('languages', 2)
                ->has('stats', 2)
            );
    }

    public function test_a_translator_can_open_the_catalogue(): void
    {
        $this->actingAs($this->fixtureTranslator())
            ->get(route('translations.index'))
            ->assertOk();
    }

    public function test_a_member_cannot_open_the_catalogue(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->get(route('translations.index'))
            ->assertForbidden();
    }

    public function test_a_translator_has_no_access_to_customer_data(): void
    {
        $translator = $this->fixtureTranslator();
        $space = $this->fixtureSpace();

        // The point of the role: it can translate, and nothing else.
        $this->assertTrue($translator->can('translations.manage'));
        $this->assertFalse($translator->can('spaces.view.any'));
        $this->assertFalse($translator->can('testimonials.moderate.any'));
        $this->assertFalse($translator->can('users.manage'));

        $this->actingAs($translator)
            ->get(route('spaces.testimonials.index', $space->slug))
            ->assertForbidden();
    }

    public function test_the_catalogue_creates_a_translation(): void
    {
        $this->actingAs($this->fixtureTranslator())
            ->post(route('translations.store'), [
                'locale' => 'ru',
                'group' => 'app',
                'key' => 'nav.dashboard',
                'value' => 'Панель',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('translations', [
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Панель',
        ]);
    }

    public function test_a_duplicate_key_is_rejected(): void
    {
        $existing = Translation::create([
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Панель',
        ]);

        $this->actingAs($this->fixtureTranslator())
            ->post(route('translations.store'), [
                'locale' => 'ru',
                'group' => 'app',
                'key' => 'nav.dashboard',
                'value' => 'Другое',
            ])
            ->assertSessionHasErrors('key');

        // The same key in another locale is fine.
        $this->actingAs($this->fixtureTranslator())
            ->post(route('translations.store'), [
                'locale' => 'en',
                'group' => 'app',
                'key' => 'nav.dashboard',
                'value' => 'Dashboard',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Панель', $existing->fresh()->value);
    }

    public function test_an_unknown_locale_is_rejected(): void
    {
        $this->actingAs($this->fixtureTranslator())
            ->post(route('translations.store'), [
                'locale' => 'de',
                'group' => 'app',
                'key' => 'nav.dashboard',
                'value' => 'Übersicht',
            ])
            ->assertSessionHasErrors('locale');
    }

    public function test_a_malformed_key_is_rejected(): void
    {
        $this->actingAs($this->fixtureTranslator())
            ->post(route('translations.store'), [
                'locale' => 'en',
                'group' => 'app',
                'key' => 'nav dashboard!!',
                'value' => 'Nope',
            ])
            ->assertSessionHasErrors('key');
    }

    public function test_a_translator_can_update_a_translation(): void
    {
        $translation = Translation::create([
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Старое',
        ]);

        $this->actingAs($this->fixtureTranslator())
            ->put(route('translations.update', $translation), [
                'locale' => 'ru',
                'group' => 'app',
                'key' => 'nav.dashboard',
                'value' => 'Новое',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Новое', $translation->fresh()->value);
    }

    public function test_an_edit_only_writes_the_wording(): void
    {
        $translation = Translation::create([
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Панель',
        ]);

        // A stale or hand-rolled payload that tries to move the entry.
        $this->actingAs($this->fixtureTranslator())
            ->put(route('translations.update', $translation), [
                'locale' => 'en',
                'group' => 'somewhere',
                'key' => 'another.key',
                'value' => 'Обновлено',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $translation->fresh();

        // The identity is fixed at creation, so nothing about it moved.
        $this->assertSame('ru', $fresh->locale);
        $this->assertSame('app', $fresh->group);
        $this->assertSame('nav.dashboard', $fresh->key);
        $this->assertSame('Обновлено', $fresh->value);
    }

    public function test_editing_onto_an_occupied_key_is_not_a_validation_error(): void
    {
        // Both locales already hold this key, which is the normal state after
        // an import — the exact situation that used to block every save.
        Translation::create(['locale' => 'ru', 'group' => 'app', 'key' => 'nav.dashboard', 'value' => 'Панель']);
        $english = Translation::create(['locale' => 'en', 'group' => 'app', 'key' => 'nav.dashboard', 'value' => 'Dashboard']);

        $this->actingAs($this->fixtureTranslator())
            ->put(route('translations.update', $english), [
                'locale' => 'ru',
                'group' => 'app',
                'key' => 'nav.dashboard',
                'value' => 'Wording still applies',
            ])
            ->assertSessionDoesntHaveErrors();

        // Uniqueness is a creation-time concern only, so an edit cannot trip
        // it. The wording is written; the identity is left where it was.
        $fresh = $english->fresh();

        $this->assertSame('en', $fresh->locale);
        $this->assertSame('nav.dashboard', $fresh->key);
        $this->assertSame('Wording still applies', $fresh->value);

        // And the row it would have collided with is untouched.
        $this->assertSame('Панель', Translation::query()
            ->where('locale', 'ru')->where('group', 'app')->where('key', 'nav.dashboard')
            ->value('value'));
    }

    public function test_the_edit_page_shows_the_identity_but_not_as_inputs(): void
    {
        $translation = Translation::create([
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Панель',
        ]);

        $this->actingAs($this->fixtureTranslator())
            ->get(route('translations.edit', $translation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('translation.locale', 'ru')
                ->where('translation.key', 'nav.dashboard')
                ->where('translation.value', 'Панель')
                // No group list: the group is not editable here.
                ->missing('groups')
            );
    }

    public function test_a_translator_can_delete_a_translation(): void
    {
        $translation = Translation::create([
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Панель',
        ]);

        $this->actingAs($this->fixtureTranslator())
            ->delete(route('translations.destroy', $translation))
            ->assertRedirect(route('translations.index'));

        $this->assertDatabaseMissing('translations', ['id' => $translation->id]);
    }

    public function test_a_member_cannot_create_a_translation(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->post(route('translations.store'), [
                'locale' => 'ru',
                'group' => 'app',
                'key' => 'nav.dashboard',
                'value' => 'Взлом',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('translations', 0);
    }

    public function test_the_catalogue_filters_by_locale_and_group(): void
    {
        Translation::create(['locale' => 'ru', 'group' => 'app', 'key' => 'nav.dashboard', 'value' => 'Панель']);
        Translation::create(['locale' => 'ru', 'group' => 'auth', 'key' => 'failed', 'value' => 'Неверные данные']);
        Translation::create(['locale' => 'en', 'group' => 'app', 'key' => 'nav.dashboard', 'value' => 'Dashboard']);

        $this->actingAs($this->fixtureTranslator())
            ->get(route('translations.index', ['locale' => 'ru', 'group' => 'app']))
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1));
    }

    public function test_the_catalogue_can_show_only_untranslated_keys(): void
    {
        Translation::create(['locale' => 'ru', 'group' => 'app', 'key' => 'nav.dashboard', 'value' => 'Панель']);
        Translation::create(['locale' => 'ru', 'group' => 'app', 'key' => 'nav.spaces', 'value' => null]);

        $this->actingAs($this->fixtureTranslator())
            ->get(route('translations.index', ['locale' => 'ru', 'missing' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.key', 'nav.spaces')
            );
    }

    public function test_the_catalogue_searches_keys_and_values(): void
    {
        Translation::create(['locale' => 'ru', 'group' => 'app', 'key' => 'nav.dashboard', 'value' => 'Панель']);
        Translation::create(['locale' => 'ru', 'group' => 'app', 'key' => 'nav.spaces', 'value' => 'Комнаты']);

        $this->actingAs($this->fixtureTranslator())
            ->get(route('translations.index', ['locale' => 'ru', 'search' => 'Комнаты']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.key', 'nav.spaces')
            );
    }

    public function test_the_edit_page_shows_the_other_languages(): void
    {
        $translation = Translation::create([
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Панель',
        ]);

        Translation::create([
            'locale' => 'en',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Dashboard',
        ]);

        $this->actingAs($this->fixtureTranslator())
            ->get(route('translations.edit', $translation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('siblings.en', 'Dashboard')
            );
    }

    // ── Import ──

    public function test_the_import_command_populates_the_catalogue(): void
    {
        $this->assertDatabaseCount('translations', 0);

        $this->artisan('translations:import')->assertSuccessful();

        // The app file alone has well over a hundred keys.
        $this->assertGreaterThan(50, Translation::query()->count());
        $this->assertDatabaseHas('translations', [
            'locale' => 'en',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Dashboard',
        ]);
        $this->assertDatabaseHas('translations', [
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Панель',
        ]);
    }

    public function test_the_import_does_not_overwrite_existing_work(): void
    {
        Translation::create([
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Моя панель',
        ]);

        $this->artisan('translations:import')->assertSuccessful();

        // A translator's wording must survive a deploy.
        $this->assertSame('Моя панель', Translation::query()
            ->where('locale', 'ru')->where('group', 'app')->where('key', 'nav.dashboard')
            ->value('value'));
    }

    public function test_the_import_can_force_an_overwrite(): void
    {
        Translation::create([
            'locale' => 'ru',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Моя панель',
        ]);

        $this->artisan('translations:import', ['--force' => true])->assertSuccessful();

        $this->assertSame('Панель', Translation::query()
            ->where('locale', 'ru')->where('group', 'app')->where('key', 'nav.dashboard')
            ->value('value'));
    }

    public function test_the_import_can_be_limited_to_one_group(): void
    {
        $this->artisan('translations:import', ['--group' => ['auth']])->assertSuccessful();

        $this->assertDatabaseHas('translations', ['group' => 'auth']);
        $this->assertDatabaseMissing('translations', ['group' => 'app']);
    }

    public function test_the_catalogue_import_button_runs_the_command(): void
    {
        $this->actingAs($this->fixtureTranslator())
            ->post(route('translations.import'))
            ->assertRedirect();

        $this->assertGreaterThan(50, Translation::query()->count());
    }

    public function test_a_member_cannot_run_the_import(): void
    {
        $this->actingAs($this->fixtureOwner())
            ->post(route('translations.import'))
            ->assertForbidden();

        $this->assertDatabaseCount('translations', 0);
    }

    // ── Files ──

    public function test_both_language_files_define_the_same_keys(): void
    {
        $english = array_keys(Arr::dot(require lang_path('en/app.php')));
        $russian = array_keys(Arr::dot(require lang_path('ru/app.php')));

        sort($english);
        sort($russian);

        // A key present in one file and not the other is a gap the interface
        // would show as a raw key.
        $this->assertSame($english, $russian);
    }

    public function test_the_russian_file_has_no_untranslated_leftovers(): void
    {
        $english = Arr::dot(require lang_path('en/app.php'));
        $russian = Arr::dot(require lang_path('ru/app.php'));

        $identical = collect($russian)
            ->filter(fn ($value, $key) => ($english[$key] ?? null) === $value)
            ->keys()
            ->all();

        // Values that are legitimately the same in both languages: "Email" is
        // "Email" in Russian, and the stat strip is numbers.
        $allowed = [
            'common.no',
            'auth.email',
            'collect.your_email',
            'welcome.stat_3_value',
            'welcome.stat_4_value',
        ];

        $this->assertSame(
            [],
            array_values(array_diff($identical, $allowed)),
            'These keys still hold the English text: '.implode(', ', array_diff($identical, $allowed)),
        );
    }

    public function test_language_files_are_readable(): void
    {
        foreach (Language::codes() as $code) {
            $this->assertTrue(
                File::isDirectory(lang_path($code)),
                "Missing language directory for [{$code}].",
            );
        }
    }

    // ── Cache ──

    public function test_writing_a_translation_bumps_the_cache_version(): void
    {
        $before = Translation::version();

        Translation::create([
            'locale' => 'en',
            'group' => 'app',
            'key' => 'nav.dashboard',
            'value' => 'Changed',
        ]);

        $this->assertGreaterThan($before, Translation::version());
    }

    public function test_the_language_list_is_cached_but_invalidated(): void
    {
        Cache::flush();

        $this->assertSame(['en', 'ru'], Language::codes());

        // An instance update fires the model event that drops the cache. A
        // mass update would not, which is why the cache also carries a TTL.
        Language::where('code', 'ru')->firstOrFail()->update(['is_active' => false]);

        $this->assertSame(['en'], Language::codes());
    }

    public function test_the_cached_language_payload_is_a_plain_array(): void
    {
        Cache::flush();

        Language::codes();

        $cached = Cache::get(Language::CACHE_KEY);

        // Deliberately arrays, not models. The Redis store in this stack
        // returns `__PHP_Incomplete_Class` for a cached object, which took the
        // whole application down with a TypeError on the first request.
        $this->assertIsArray($cached);
        $this->assertIsArray($cached[0]);
        $this->assertSame('en', $cached[0]['code']);
    }

    public function test_the_cached_payload_survives_a_serialisation_round_trip(): void
    {
        Cache::flush();

        Language::codes();

        // What the Redis store does to whatever it is handed.
        $revived = unserialize(serialize(Cache::get(Language::CACHE_KEY)));

        $this->assertSame('en', $revived[0]['code']);
        $this->assertSame('ru', $revived[1]['code']);
    }

    public function test_the_translator_role_exists_and_is_seeded(): void
    {
        $translator = $this->fixtureTranslator();

        $this->assertTrue($translator->hasRole('translator'));

        // The permission arrives through the role, so it is not "direct".
        $this->assertSame(
            ['translations.manage'],
            $translator->getPermissionsViaRoles()->pluck('name')->all(),
        );
        $this->assertTrue($translator->can('translations.manage'));
    }

    public function test_the_admin_holds_every_permission(): void
    {
        $admin = $this->fixtureAdmin();

        $this->assertTrue($admin->hasAllPermissions(
            PermissionEnum::values(),
        ));
    }

    public function test_the_fixture_emails_are_documented(): void
    {
        $this->assertSame('translator@feedbacker.test', TestFixturesSeeder::TRANSLATOR_EMAIL);
    }
}
