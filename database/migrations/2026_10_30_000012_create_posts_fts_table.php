<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("CREATE VIRTUAL TABLE IF NOT EXISTS posts_fts USING fts5(
            post_id UNINDEXED,
            title,
            excerpt,
            content_text,
            tokenize = 'unicode61 remove_diacritics 2'
        );");

        // Backfill existing published stories if posts table exists
        if (Schema::hasTable('posts')) {
            DB::statement("
                INSERT INTO posts_fts (post_id, title, excerpt, content_text)
                SELECT id, title, coalesce(excerpt, ''), coalesce(content_html, '')
                FROM posts
                WHERE status = 'published' AND published_at <= datetime('now') AND deleted_at IS NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS posts_fts;');
    }
};
