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
        // 1. Add is_featured flag to posts table
        Schema::table('posts', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('status');
            $table->index(['status', 'is_featured', 'published_at']);
            $table->index(['status', 'published_at', 'category_id']);
        });

        // 2. Create post_dismissals table for "Not interested" actions
        Schema::create('post_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->index(['user_id', 'category_id']);
        });

        // 3. Performance indexes on activity tables for sub-100ms feed scoring
        Schema::table('post_views', function (Blueprint $table) {
            $table->index(['user_id', 'post_id']);
            $table->index(['user_id', 'viewed_at']);
        });

        Schema::table('category_user', function (Blueprint $table) {
            $table->index(['user_id', 'category_id']);
        });

        Schema::table('tag_user', function (Blueprint $table) {
            $table->index(['user_id', 'tag_id']);
        });

        Schema::table('likes', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_dismissals');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_featured', 'published_at']);
            $table->dropIndex(['status', 'published_at', 'category_id']);
            $table->dropColumn('is_featured');
        });

        Schema::table('post_views', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'post_id']);
            $table->dropIndex(['user_id', 'viewed_at']);
        });

        Schema::table('category_user', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'category_id']);
        });

        Schema::table('tag_user', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'tag_id']);
        });

        Schema::table('likes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });
    }
};
