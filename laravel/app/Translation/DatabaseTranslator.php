<?php

declare(strict_types=1);

namespace App\Translation;

use App\Models\Translation;
use Illuminate\Translation\Translator;

/**
 * Переводчик, отслеживающий изменения каталога.
 *
 * Базовый Translator кеширует загруженные группы и обращается к загрузчику
 * только один раз за запрос. Без дополнительной проверки после записи он
 * продолжал бы возвращать прежние строки.
 *
 * Этот подкласс проверяет версию каталога и очищает кеш при её изменении,
 * поэтому сохранённый через работающее приложение перевод виден сразу.
 */
class DatabaseTranslator extends Translator
{
    /**
     * Версия каталога, для которой были прочитаны кешированные группы.
     */
    protected ?int $catalogueVersion = null;

    public function load($namespace, $group, $locale): void
    {
        $current = Translation::version();

        if ($this->catalogueVersion !== $current) {
            $this->loaded = [];
            $this->catalogueVersion = $current;
        }

        parent::load($namespace, $group, $locale);
    }
}
