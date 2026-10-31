<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('status')->default('ongoing'); // ongoing, completed, hiatus
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('status');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->unsignedInteger('series_order')->nullable();

            $table->index('series_id');
            $table->unique(['series_id', 'series_order']);
        });

        Schema::create('series_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'series_id']);
            $table->index('series_id');
        });

        Schema::create('series_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('last_post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'series_id']);
            $table->index(['user_id', 'series_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('series_progress');
        Schema::dropIfExists('series_follows');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique(['series_id', 'series_order']);
            $table->dropForeign(['series_id']);
            $table->dropColumn(['series_id', 'series_order']);
        });

        Schema::dropIfExists('series');
    }
};
