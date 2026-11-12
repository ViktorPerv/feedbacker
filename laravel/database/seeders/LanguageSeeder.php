<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

/**
 * Языки, поставляемые с приложением.
 *
 * Вызывается из миграции и наполнителя тестовых данных, поскольку тестовая БД
 * клонируется из дампа одной схемы. Без повторного заполнения посредник не
 * найдёт ни одного поддерживаемого языка.
 */
class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'ru',
                'name' => 'Russian',
                'native_name' => 'Русский',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2,
            ],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(['code' => $language['code']], $language);
        }
    }
}
