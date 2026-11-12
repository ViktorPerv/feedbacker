<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * One translation string.
 *
 * `key` uses dot notation for nesting (`nav.dashboard`), matching how Laravel's
 * translator resolves keys, so a row can override a value buried inside a
 * nested array in a language file.
 */
class Translation extends Model
{
    /**
     * Bumped on every write. It is part of the loader's cache key, which is
     * what makes a save visible immediately without flushing anything.
     */
    public const VERSION_KEY = 'translations:version';

    public const CACHE_TTL = 3600;

    protected $fillable = ['locale', 'group', 'key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => self::bumpVersion());
        static::deleted(fn () => self::bumpVersion());
    }

    // ── Cache versioning ──

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function bumpVersion(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    // ── Queries ──

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * Match either the key or the value, so a translator can search for the
     * English wording they are trying to replace.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('key', 'ilike', $like)
                ->orWhere('value', 'ilike', $like);
        });
    }

    /**
     * Every group that has at least one row, for the filter dropdown.
     *
     * @return array<int, string>
     */
    public static function groups(): array
    {
        return static::query()
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->all();
    }

    /**
     * The keys the frontend needs, as a flat list.
     *
     * The union of what is in the database and what is in the canonical `en`
     * file: a key that exists only in the file still has to be shipped, and a
     * key a translator added ahead of the file has to appear too.
     *
     * @return array<int, string>
     */
    public static function frontendKeys(string $group = 'app'): array
    {
        $fromFiles = [];

        $path = lang_path('en/'.$group.'.php');

        if (is_file($path)) {
            $fromFiles = array_keys(Arr::dot(require $path));
        }

        $fromDatabase = static::query()
            ->where('group', $group)
            ->distinct()
            ->pluck('key')
            ->all();

        return array_values(array_unique([...$fromFiles, ...$fromDatabase]));
    }

    /**
     * A flat key => value map for the current locale, resolved through the
     * translator so file fallbacks apply.
     *
     * @return array<string, string>
     */
    public static function forFrontend(string $group = 'app'): array
    {
        $out = [];

        foreach (static::frontendKeys($group) as $key) {
            $out[$key] = __($group.'.'.$key);
        }

        return $out;
    }
}
