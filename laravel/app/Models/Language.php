<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Язык, на котором может отображаться приложение.
 *
 * Список читается посредником SetLocale при каждом запросе, поэтому он
 * кешируется и сбрасывается при изменении вместо повторного запроса к БД.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $native_name
 * @property bool $is_active
 * @property bool $is_default
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    /**
     * Возвращает все языки, включая неактивные.
     *
     * Кешируются обычные массивы, а не модели. Хранилище кеша сериализует
     * переданное значение, а коллекция Eloquent связывает кеш с графом классов:
     * настройка сериализатора или устаревшая запись может превратить результат
     * в `__PHP_Incomplete_Class` и нарушить весь запрос. Массивы этого избегают.
     *
     * Используется короткий TTL: массовое обновление (`Language::where(...)
     * ->update(...)`) обходит события модели и сброс кеша, а устаревший на
     * несколько минут список языков не создаёт проблем.
     *
     * @return Collection<int, self>
     */
    public static function all_cached(): Collection
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
     * Возвращает допустимые коды языков в порядке отображения.
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
     * Возвращает данные в формате, необходимом переключателю языка.
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
