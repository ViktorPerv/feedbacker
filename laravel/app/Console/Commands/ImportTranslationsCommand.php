<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * Loads the language files into the database.
 *
 * The files stay the source of truth for *which* keys exist; the database is
 * where their wording is edited. Importing is therefore additive by default —
 * an existing row is left alone so a translator's work is never clobbered by
 * a deploy.
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
     * Drop rows whose key has disappeared from the language files.
     *
     * Scoped to the locale and group combinations that were actually scanned:
     * pruning everything on a `--group=auth` run would delete the rest of the
     * catalogue.
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
