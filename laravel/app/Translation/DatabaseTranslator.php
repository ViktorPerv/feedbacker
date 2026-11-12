<?php

namespace App\Translation;

use App\Models\Translation;
use Illuminate\Translation\Translator;

/**
 * A translator that notices when the catalogue has changed.
 *
 * The base Translator memoises every group it loads, so it asks its loader for
 * a group exactly once per request. That makes the loader's own invalidation
 * useless: after a write, the translator would keep serving the lines it read
 * before it.
 *
 * This subclass checks the catalogue version first and drops its memo when it
 * moves, so a saved translation is visible immediately — which matters because
 * the catalogue is edited through the running application.
 */
class DatabaseTranslator extends Translator
{
    /**
     * The catalogue version the memoised groups were read at.
     */
    protected ?int $catalogueVersion = null;

    /**
     * @param  string  $namespace
     * @param  string  $group
     * @param  string  $locale
     */
    public function load($namespace, $group, $locale)
    {
        $current = Translation::version();

        if ($this->catalogueVersion !== $current) {
            $this->loaded = [];
            $this->catalogueVersion = $current;
        }

        parent::load($namespace, $group, $locale);
    }
}
