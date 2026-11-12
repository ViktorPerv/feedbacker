<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A locale the application can be displayed in.
 *
 * Read on every request by the SetLocale middleware, so the list is cached and
 * invalidated on write rather than queried each time.
 */
class Language extends Model
{
    public const CACHE_KEY = 'languages:all';

    public const CACHE_TTL = 300;

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'is_active',
        'is_default',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    // ── Queries ──

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    /**
     * Every language, active or not.
     *
     * Plain arrays are cached, not models. The cache store serialises what it
     * is given, and handing it an Eloquent collection couples the cached
     * payload to the class graph: a client-side serializer setting or a stale
     * entry turns the read into `__PHP_Incomplete_Class` and the whole request
     * fails. Arrays survive any of that.
     *
     * A short TTL rather than forever: a mass update (`Language::where(...)
     * ->update(...)`) bypasses model events and so bypasses the invalidation
     * below, and a locale list that is a few minutes stale is harmless.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function all_cached(): \Illuminate\Support\Collection
    {
        $rows = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => static::query()
                ->ordered()
                ->get()
                ->map(fn (self $language) => $language->attributesToArray())
                ->all(),
        );

        return collect($rows)->map(
            // Rebuilds an existing-looking model from the cached attributes
            // without touching the database.
            fn (array $attributes) => (new self)->newFromBuilder($attributes),
        );
    }

    /**
     * Codes the application will accept, in display order.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return static::all_cached()
            ->where('is_active', true)
            ->pluck('code')
            ->values()
            ->all();
    }

    public static function defaultCode(): string
    {
        return static::all_cached()->firstWhere('is_default')?->code
            ?? config('app.locale', 'en');
    }

    public static function isSupported(?string $code): bool
    {
        return $code !== null && in_array($code, static::codes(), true);
    }

    /**
     * The shape the frontend switcher needs.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function options(): array
    {
        return static::all_cached()
            ->where('is_active', true)
            ->map(fn (self $language) => [
                'code' => $language->code,
                'name' => $language->name,
                'native_name' => $language->native_name,
                'is_default' => $language->is_default,
            ])
            ->values()
            ->all();
    }
}
