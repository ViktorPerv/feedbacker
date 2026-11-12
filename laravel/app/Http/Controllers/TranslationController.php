<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PermissionEnum;
use App\Http\Requests\TranslationRequest;
use App\Models\Language;
use App\Models\Translation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Translation catalogue.
 *
 * Reachable by `admin` and `translator`; the translator role exists so the
 * catalogue can be maintained without granting access to customer data.
 */
class TranslationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizePermission(PermissionEnum::TranslationsManage);

        $locale = $request->string('locale')->toString() ?: Language::defaultCode();
        $group = $request->string('group')->toString();
        $search = trim($request->string('search')->toString());
        $onlyMissing = $request->boolean('missing');

        $translations = Translation::query()
            ->forLocale($locale)
            ->when($group !== '', fn ($query) => $query->inGroup($group))
            ->when($search !== '', fn ($query) => $query->matching($search))
            // "Missing" is a row with no value — the key exists because it was
            // imported, but nobody has written the translation yet.
            ->when($onlyMissing, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereNull('value')
                ->orWhere('value', '')))
            ->orderBy('group')
            ->orderBy('key')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Translations/Index', [
            // Deliberately NOT called `translations`: a page prop with that
            // name replaces the shared translation map, and every t() call on
            // this page then renders the raw key instead of the wording.
            'entries' => $translations->through(fn (Translation $translation) => [
                'id' => $translation->id,
                'locale' => $translation->locale,
                'group' => $translation->group,
                'key' => $translation->key,
                'value' => $translation->value,
                'updated_at' => $translation->updated_at?->toIso8601String(),
            ]),
            'filters' => [
                'locale' => $locale,
                'group' => $group,
                'search' => $search,
                'missing' => $onlyMissing,
            ],
            'languages' => Language::options(),
            'groups' => Translation::groups(),
            'stats' => $this->stats(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission(PermissionEnum::TranslationsManage);

        return Inertia::render('Translations/Create', [
            'languages' => Language::options(),
            'groups' => Translation::groups(),
            'defaults' => [
                'locale' => $request->string('locale')->toString() ?: Language::defaultCode(),
                'group' => $request->string('group')->toString() ?: 'app',
                'key' => '',
                'value' => '',
            ],
        ]);
    }

    public function store(TranslationRequest $request): RedirectResponse
    {
        $this->authorizePermission(PermissionEnum::TranslationsManage);

        $translation = Translation::create($request->translationData());

        return redirect()
            ->route('translations.edit', $translation)
            ->with('success', 'Translation created.');
    }

    public function edit(Translation $translation): Response
    {
        $this->authorizePermission(PermissionEnum::TranslationsManage);

        return Inertia::render('Translations/Edit', [
            'translation' => [
                'id' => $translation->id,
                'locale' => $translation->locale,
                'group' => $translation->group,
                'key' => $translation->key,
                'value' => $translation->value,
                'updated_at' => $translation->updated_at?->toIso8601String(),
            ],
            'languages' => Language::options(),
            // What the same key says in the other languages, so a translator
            // can work from the original wording.
            'siblings' => Translation::query()
                ->where('group', $translation->group)
                ->where('key', $translation->key)
                ->where('locale', '!=', $translation->locale)
                ->pluck('value', 'locale'),
        ]);
    }

    public function update(TranslationRequest $request, Translation $translation): RedirectResponse
    {
        $this->authorizePermission(PermissionEnum::TranslationsManage);

        // Only the wording is written. The (locale, group, key) triple is the
        // row's identity and is fixed at creation: letting an edit move it
        // would mean landing on a slot another row already holds, which is
        // exactly the collision the unique index forbids.
        $translation->update([
            'value' => $request->validated('value'),
        ]);

        return back()->with('success', 'Translation saved.');
    }

    public function destroy(Translation $translation): RedirectResponse
    {
        $this->authorizePermission(PermissionEnum::TranslationsManage);

        $translation->delete();

        return redirect()
            ->route('translations.index')
            ->with('success', 'Translation deleted. The language file value applies again.');
    }

    /**
     * Pull the language files into the database.
     *
     * Additive: existing rows are left alone, so this cannot undo a
     * translator's work.
     */
    public function import(): RedirectResponse
    {
        $this->authorizePermission(PermissionEnum::TranslationsManage);

        Artisan::call('translations:import');

        return back()->with('success', trim(Artisan::output()));
    }

    /**
     * How complete each language is, for the index header.
     *
     * @return array<int, array<string, mixed>>
     */
    private function stats(): array
    {
        $total = Translation::query()
            ->where('locale', Language::defaultCode())
            ->count();

        return collect(Language::options())
            ->map(function (array $language) use ($total) {
                $translated = Translation::query()
                    ->forLocale($language['code'])
                    ->whereNotNull('value')
                    ->where('value', '!=', '')
                    ->count();

                return [
                    'code' => $language['code'],
                    'native_name' => $language['native_name'],
                    'translated' => $translated,
                    'total' => $total,
                    'percent' => $total > 0 ? (int) round($translated / $total * 100) : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function authorizePermission(PermissionEnum $permission): void
    {
        abort_unless(
            request()->user()?->can($permission->value),
            403,
            'You do not have permission to manage translations.',
        );
    }
}
