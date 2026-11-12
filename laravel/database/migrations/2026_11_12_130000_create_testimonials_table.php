<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('space_id')->constrained()->cascadeOnDelete();

            // ── Author ──
            $table->string('author_name');
            $table->string('author_email')->nullable();
            $table->text('author_avatar_url')->nullable();
            $table->string('author_title')->nullable();
            $table->string('author_company')->nullable();

            // ── Content ──
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('content');
            $table->text('video_url')->nullable();

            // ── Moderation ──
            // json rather than a PostgreSQL array so the schema also works on
            // the SQLite database used by the test suite.
            $table->string('status', 20)->default('pending');
            $table->json('tags')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('approved_at')->nullable();

            // ── Provenance ──
            $table->string('source', 50)->default('direct');
            $table->text('imported_from_url')->nullable();

            $table->timestamps();

            $table->index(['space_id', 'status']);
            $table->index(['space_id', 'is_featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
