<?php

use Database\Seeders\LanguageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Locales and their translation strings.
 *
 * Translations live in the database rather than only in files so they can be
 * edited by a translator without a deploy. The files remain the fallback: a
 * key that has no row still resolves, which is what lets a new string ship
 * before anyone has translated it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            // English name, for the admin list.
            $table->string('name', 60);
            // Name in its own language, for the switcher.
            $table->string('native_name', 60);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 10);
            // Laravel's own grouping: `app`, `auth`, `validation`, …
            $table->string('group', 60)->default('app');
            // Dot notation for nesting: `nav.dashboard`.
            $table->string('key', 191);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['locale', 'group', 'key']);
            $table->index(['group', 'locale']);
        });

        // The signed-in user's preferred locale, so the choice follows them
        // between browsers.
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->after('email');
        });

        // Reference data, not user data: seeded here so the application boots
        // with a working locale list even before any seeder has run. The same
        // seeder is called by the test fixtures, because the test database is
        // cloned schema-only and would otherwise have no languages at all.
        (new LanguageSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');
    }
};
