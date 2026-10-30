<?php

namespace App\Observers;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Support\Facades\DB;

class PostFtsObserver
{
    /**
     * Handle the Post "saved" event.
     */
    public function saved(Post $post): void
    {
        $this->syncPostFts($post);
    }

    /**
     * Handle the Post "deleted" event (soft or hard).
     */
    public function deleted(Post $post): void
    {
        $this->removeFromFts($post->id);
    }

    /**
     * Handle the Post "restored" event.
     */
    public function restored(Post $post): void
    {
        $this->syncPostFts($post);
    }

    /**
     * Handle the Post "forceDeleted" event.
     */
    public function forceDeleted(Post $post): void
    {
        $this->removeFromFts($post->id);
    }

    /**
     * Synchronize post data with SQLite FTS5 index.
     */
    public function syncPostFts(Post $post): void
    {
        $this->removeFromFts($post->id);

        // Only index publicly published and non-trashed stories
        $isPublished = $post->status === PostStatus::PUBLISHED
            && $post->published_at
            && $post->published_at <= now()
            && ! $post->trashed();

        if ($isPublished) {
            $plainContent = trim(strip_tags((string) $post->content_html));

            DB::insert('INSERT INTO posts_fts (post_id, title, excerpt, content_text) VALUES (?, ?, ?, ?)', [
                $post->id,
                $post->title,
                $post->excerpt ?? '',
                $plainContent,
            ]);
        }
    }

    /**
     * Remove post from FTS index.
     */
    public function removeFromFts(int $postId): void
    {
        DB::delete('DELETE FROM posts_fts WHERE post_id = ?', [$postId]);
    }
}
