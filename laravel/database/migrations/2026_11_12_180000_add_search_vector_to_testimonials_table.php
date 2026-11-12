<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Полнотекстовый поиск по отзывам.
 *
 * Используется вычисляемый столбец вместо триггера или поддержки приложением:
 * PostgreSQL пересчитывает его при каждой записи, поэтому значение всегда
 * соответствует исходной строке.
 *
 * Конфигурация `simple` выбрана намеренно. `english` выделяет основы английских
 * слов, но не русских, а `simple` только меняет регистр и одинаково работает со
 * всеми языками. Префиксный поиск (`term:*`) покрывает заметные пользователю
 * варианты слов.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE testimonials
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                to_tsvector(
                    'simple',
                    coalesce(author_name, '') || ' ' ||
                    coalesce(author_title, '') || ' ' ||
                    coalesce(author_company, '') || ' ' ||
                    coalesce(content, '')
                )
            ) STORED
        SQL);

        DB::statement(
            'CREATE INDEX testimonials_search_vector_index ON testimonials USING GIN (search_vector)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS testimonials_search_vector_index');

        Schema::table('testimonials', function (Blueprint $table): void {
            $table->dropColumn('search_vector');
        });
    }
};
