<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сохранённые конфигурации виджетов.
 *
 * Ранее встраивание полностью настраивалось строкой запроса URL скрипта, чего
 * достаточно для одной стены. Таблица позволяет иметь несколько по-разному
 * настроенных виджетов пространства: каждая строка задаёт именованный макет со
 * своим публичным токеном.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widgets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('space_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('layout', 32)->default('masonry');

            // Public, unguessable identifier used in the embed URL. Never the
            // UUID: the token ends up in third-party page source.
            $table->string('token', 32)->unique();

            $table->json('config');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['space_id', 'layout']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};
