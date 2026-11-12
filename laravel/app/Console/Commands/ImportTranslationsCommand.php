<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * Загружает языковые файлы в базу данных.
 *
 * Файлы остаются источником истины о наборе ключей, а текст редактируется в БД.
 * Поэтому импорт по умолчанию только добавляет строки и не перезаписывает
 * работу переводчика при развёртывании.
 */
class ImportTranslationsCommand extends Command
{
    protected $signature = 'translations:import
                            {--force : Overwrite values that already exist}
                            {--prune : Delete rows whose key is no longer in the language files}
                            {--locale=* : Import only these locales}
                            {--group=* : Import only these groups}';

    protected $description = 'Import translation files into the database';

    public function handle(): int
    {
        $locales = $this->option('locale') ?: Language::codes();
        $only = $this->option('group');

        $created = 0;
        $updated = 0;
        $skipped = 0;

        /** @var array<string, array<int, string>> $seen */
        $seen = [];

        foreach ($locales as $locale) {
            $directory = lang_path($locale);

            if (! is_dir($directory)) {
                $this->components->warn("No language directory for [{$locale}].");

                continue;
            }

            foreach (glob($directory.'/*.php') ?: [] as $file) {
                $group = basename($file, '.php');

                if ($only !== [] && ! in_array($group, $only, true)) {
                    continue;
                }

                foreach (Arr::dot(require $file) as $key => $value) {
                    // Nested arrays that are not strings (a rule list, say)
                    // have nothing to store.
                    if (! is_string($value)) {
                        continue;
                    }

                    $seen[$locale.'|'.$group][] = $key;

                    $translation = Translation::query()
                        ->where('locale', $locale)
                        ->where('group', $group)
                        ->where('key', $key)
                        ->first();

                    if ($translation === null) {
                        Translation::create([
                            'locale' => $locale,
                            'group' => $group,
                            'key' => $key,
                            'value' => $value,
                        ]);

                        $created++;

                        continue;
                    }

                    if ($this->option('force') && $translation->value !== $value) {
                        $translation->update(['value' => $value]);
                        $updated++;

                        continue;
                    }

                    $skipped++;
                }
            }
        }

        $this->components->info(
            "Translations imported: {$created} created, {$updated} updated, {$skipped} left alone."
        );

        if ($this->option('prune')) {
            $this->prune($seen);
        }

        return self::SUCCESS;
    }

    /**
     * Удаляет строки, ключи которых исчезли из языковых файлов.
     *
     * Удаление ограничено проверенными комбинациями языка и группы, иначе запуск
     * с `--group=auth` удалил бы остальную часть каталога.
     *
     * @param  array<string, array<int, string>>  $seen
     */
    private function prune(array $seen): void
    {
        $removed = 0;

        foreach ($seen as $combination => $keys) {
            [$locale, $group] = explode('|', $combination, 2);

            $removed += Translation::query()
                ->where('locale', $locale)
                ->where('group', $group)
                ->whereNotIn('key', array_unique($keys))
                ->delete();
        }

        $this->components->info(
            $removed === 0
                ? 'Nothing to prune.'
                : "Pruned {$removed} row(s) whose key no longer exists."
        );
    }
}
