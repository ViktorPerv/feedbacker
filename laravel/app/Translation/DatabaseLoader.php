<?php

namespace App\Translation;

use App\Models\Translation;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Translation\FileLoader;

/**
 * Loads translation strings from the database, falling back to the files.
 *
 * Laravel's translator asks its loader for a whole group at a time, so this
 * merges one query's worth of rows over the file array. Rows win, which is what
 * makes a translation editable without a deploy; anything without a row keeps
 * resolving from `lang/`, so a string can ship before it is translated.
 */
class DatabaseLoader extends FileLoader
{
    /**
     * Merged groups already built for this request.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $loaded = [];

    /**
     * The cache version the loaded groups were built against. When a write
     * bumps it, everything memoised here is stale and gets rebuilt.
     */
    protected ?int $version = null;

    public function __construct(Filesystem $files, string $path)
    {
        parent::__construct($files, $path);
    }

    /**
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array<string, mixed>
     */
    public function load($locale, $group, $namespace = null)
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
     * The rows for one locale and group, expanded into nested arrays so
     * `Arr::get($lines, 'nav.dashboard')` finds them.
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
     * Drop the per-request memo when the stored translations have changed.
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
