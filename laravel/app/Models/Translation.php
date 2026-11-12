<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Одна строка перевода.
 *
 * Поле `key` использует точечную запись (`nav.dashboard`), соответствующую
 * разрешению ключей в Laravel, поэтому строка может переопределить вложенное
 * значение из языкового файла.
 *
 * @property int $id
 * @property string $locale
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Translation extends Model
{
    /**
     * Увеличивается при каждой записи и входит в ключ кеша загрузчика, поэтому
     * сохранённый перевод становится виден сразу без полного сброса кеша.
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
     * Ищет по ключу или значению, чтобы переводчик мог найти заменяемый текст.
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
     * Возвращает группы, содержащие хотя бы одну строку, для выпадающего фильтра.
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
     * Возвращает плоский список ключей, необходимых клиентскому приложению.
     *
     * Объединяет ключи из БД и основного файла `en`: ключ только из файла всё
     * равно должен попасть на клиент, как и заранее добавленный переводчиком ключ.
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
     * Возвращает плоскую карту «ключ => значение» для текущего языка с учётом
     * резервных значений из файлов переводов.
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
