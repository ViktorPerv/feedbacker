<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics written by the Go widget service.
 *
 * Both tables are append-only and carry a denormalised space_id: every
 * dashboard query is scoped to a set of spaces, so keeping the column here
 * avoids a join on the hot path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_views', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('space_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('testimonial_id')->nullable()->constrained()->nullOnDelete();
            $table->text('referrer')->nullable();
            $table->text('user_agent')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('viewed_at')->useCurrent();

            $table->index(['space_id', 'viewed_at']);
        });

        Schema::create('testimonial_clicks', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('space_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('testimonial_id')->constrained()->cascadeOnDelete();
            $table->text('referrer')->nullable();
            $table->timestamp('clicked_at')->useCurrent();

            $table->index(['space_id', 'clicked_at']);
            $table->index('testimonial_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonial_clicks');
        Schema::dropIfExists('widget_views');
    }
};
