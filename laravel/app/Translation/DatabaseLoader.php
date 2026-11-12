<?php

declare(strict_types=1);

namespace App\Translation;

use App\Models\Translation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Translation\FileLoader;

/**
 * Загружает переводы из базы данных с резервными значениями из файлов.
 *
 * Переводчик Laravel запрашивает группу целиком, поэтому результат одного
 * запроса объединяется с массивом из файла. Строки БД имеют приоритет и могут
 * редактироваться без развёртывания, а отсутствующие берутся из `lang/`.
 */
class DatabaseLoader extends FileLoader
{
    /**
     * Объединённые группы, уже построенные для текущего запроса.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $loaded = [];

    /**
     * Версия кеша, для которой построены группы. При изменении версии
     * сохранённые здесь данные считаются устаревшими и перестраиваются.
     */
    protected ?int $version = null;

    public function __construct(Filesystem $files, string $path)
    {
        parent::__construct($files, $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        $this->syncVersion();

        $cacheKey = $namespace.'.'.$locale.'.'.$group;

        if (isset($this->loaded[$cacheKey])) {
            return $this->loaded[$cacheKey];
        }

        // Only the application's own namespaces are database-backed. Vendor
        // packages ship their own files and are not ours to override.
        if ($namespace !== null && $namespace !== '*') {
            return $this->loaded[$cacheKey] = parent::load($locale, $group, $namespace);
        }

        $file = parent::load($locale, $group, $namespace);

        $overrides = Cache::remember(
            'translations:'.Translation::version().':'.$locale.':'.$group,
            Translation::CACHE_TTL,
            fn () => $this->fetch($locale, $group),
        );

        return $this->loaded[$cacheKey] = array_replace_recursive($file, $overrides);
    }

    /**
     * Возвращает строки языка и группы как вложенный массив, доступный через
     * `Arr::get($lines, 'nav.dashboard')`.
     *
     * @return array<string, mixed>
     */
    protected function fetch(string $locale, string $group): array
    {
        $rows = Translation::query()
            ->forLocale($locale)
            ->inGroup($group)
            ->whereNotNull('value')
            ->pluck('value', 'key');

        $nested = [];

        foreach ($rows as $key => $value) {
            Arr::set($nested, $key, $value);
        }

        return $nested;
    }

    /**
     * Очищает кеш текущего запроса при изменении сохранённых переводов.
     */
    protected function syncVersion(): void
    {
        $current = Translation::version();

        if ($this->version !== $current) {
            $this->loaded = [];
            $this->version = $current;
        }
    }
}
