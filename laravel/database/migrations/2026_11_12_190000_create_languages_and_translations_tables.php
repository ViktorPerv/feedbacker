<?php

declare(strict_types=1);

use Database\Seeders\LanguageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Языки и строки переводов.
 *
 * Переводы хранятся в БД, чтобы их можно было редактировать без развёртывания.
 * Файлы остаются резервным источником: ключ без строки в БД продолжает работать,
 * поэтому новую строку можно выпустить до её перевода.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table): void {
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

        Schema::create('translations', function (Blueprint $table): void {
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
        Schema::table('users', function (Blueprint $table): void {
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
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });

        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');
    }
};
