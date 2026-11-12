<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full-text search over testimonials.
 *
 * A generated column rather than a trigger or an application-maintained
 * column: PostgreSQL recomputes it on every write, so it can never drift from
 * the row it describes.
 *
 * The `simple` configuration is deliberate. `english` would stem English
 * words but treat Russian ones as opaque tokens; `simple` only lowercases, so
 * every language behaves the same. Prefix matching (`term:*`) covers the
 * stemming people actually notice — searching "embed" finds "embeds".
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

        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('search_vector');
        });
    }
};
