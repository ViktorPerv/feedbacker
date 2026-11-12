<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->string('custom_domain')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('space_branding', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('space_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('logo_url')->nullable();
            $table->string('primary_color', 7)->default('#4F46E5');
            $table->string('background_color', 7)->default('#FFFFFF');
            $table->string('font_family', 100)->default('Inter');
            $table->string('button_text')->default('Leave a testimonial');
            $table->text('thank_you_message')->default('Thank you for your feedback!');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_branding');
        Schema::dropIfExists('spaces');
    }
};
