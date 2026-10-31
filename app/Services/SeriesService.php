<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Series;
use App\Models\SeriesProgress;
use App\Models\User;
use App\Notifications\NewSeriesPartNotification;
use Illuminate\Support\Facades\DB;

class SeriesService
{
    /**
     * Build series reading context for a post.
     */
    public function getSeriesContextForPost(Post $post, ?User $viewer): ?array
    {
        if (! $post->series_id) {
            return null;
        }

        $series = $post->series;
        if (! $series) {
            return null;
        }

        $isAuthor = $viewer && $viewer->id === $series->user_id;

        // Fetch parts: author sees drafts too, readers only published
        $query = $series->posts()->orderBy('series_order', 'asc');
        if (! $isAuthor) {
            $query->published();
        }

        $posts = $query->get(['id', 'user_id', 'title', 'slug', 'excerpt', 'status', 'published_at', 'reading_time_minutes', 'series_order']);

        $publishedCount = 0;
        $formattedParts = [];
        $currentPartNumber = null;

        foreach ($posts as $part) {
            $isPublished = $part->status === PostStatus::PUBLISHED && $part->published_at && $part->published_at->lte(now());

            if ($isPublished) {
                $publishedCount++;
                $displayNumber = $publishedCount;
            } else {
                $displayNumber = null;
            }

            if ($part->id === $post->id) {
                $currentPartNumber = $displayNumber;
            }

            $formattedParts[] = [
                'id' => $part->id,
                'title' => $part->title,
                'slug' => $part->slug,
                'status' => $part->status->value,
                'is_published' => $isPublished,
                'display_number' => $displayNumber,
                'is_current' => $part->id === $post->id,
                'reading_time_minutes' => $part->reading_time_minutes,
            ];
        }

        // Previous and next published parts
        $publishedPartsOnly = array_values(array_filter($formattedParts, fn ($p) => $p['is_published']));
        $currentIndexInPublished = null;
        foreach ($publishedPartsOnly as $idx => $p) {
            if ($p['id'] === $post->id) {
                $currentIndexInPublished = $idx;
                break;
            }
        }

        $prevPart = null;
        $nextPart = null;
        if ($currentIndexInPublished !== null) {
            if ($currentIndexInPublished > 0) {
                $prevPart = $publishedPartsOnly[$currentIndexInPublished - 1];
            }
            if ($currentIndexInPublished < count($publishedPartsOnly) - 1) {
                $nextPart = $publishedPartsOnly[$currentIndexInPublished + 1];
            }
        }

        $isFollowing = $series->isFollowedBy($viewer);

        return [
            'id' => $series->id,
            'title' => $series->title,
            'slug' => $series->slug,
            'description' => $series->description,
            'cover_image_path' => $series->cover_image_path,
            'status' => $series->status->value,
            'status_label' => $series->status->label(),
            'total_published_parts' => $publishedCount,
            'current_part_number' => $currentPartNumber,
            'is_author' => $isAuthor,
            'is_following' => $isFollowing,
            'followers_count' => $series->followers()->count(),
            'parts' => $formattedParts,
            'prev_part' => $prevPart,
            'next_part' => $nextPart,
        ];
    }

    /**
     * Record reading progress for an authenticated user on a series.
     */
    public function recordProgress(?User $user, Post $post): void
    {
        if (! $user || ! $post->series_id) {
            return;
        }

        SeriesProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'series_id' => $post->series_id,
            ],
            [
                'last_post_id' => $post->id,
            ]
        );
    }

    /**
     * Notify series followers when a new part goes live.
     */
    public function notifyFollowers(Post $post): void
    {
        if (! $post->series_id || $post->status !== PostStatus::PUBLISHED) {
            return;
        }

        $series = $post->series;
        if (! $series) {
            return;
        }

        // Avoid duplicate notification if already sent for this post
        $alreadySent = DB::table('notifications')
            ->where('type', NewSeriesPartNotification::class)
            ->where('data', 'like', '%"post_id":' . $post->id . '%')
            ->exists();

        if ($alreadySent) {
            return;
        }

        // Calculate 1-based part number among published parts
        $partNumber = $series->publishedPosts()
            ->where('series_order', '<=', $post->series_order)
            ->count();

        if ($partNumber === 0) {
            $partNumber = 1;
        }

        foreach ($series->followers as $follower) {
            if ($follower->id !== $post->user_id) {
                $follower->notify(new NewSeriesPartNotification($post, $series, $partNumber));
            }
        }
    }

    /**
     * Get continue reading items for authenticated user.
     */
    public function getContinueReadingList(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $progressItems = SeriesProgress::where('user_id', $user->id)
            ->with(['series', 'lastPost'])
            ->latest('updated_at')
            ->take(5)
            ->get();

        $results = [];

        foreach ($progressItems as $progress) {
            $series = $progress->series;
            $lastPost = $progress->lastPost;

            if (! $series || ! $lastPost) {
                continue;
            }

            // Get published parts in series
            $publishedParts = $series->publishedPosts()->get(['id', 'title', 'slug', 'series_order']);
            $totalParts = $publishedParts->count();
            if ($totalParts === 0) {
                continue;
            }

            $currentIndex = $publishedParts->search(fn ($p) => $p->id === $lastPost->id);
            if ($currentIndex === false) {
                $nextPart = $publishedParts->first();
                $readCount = 1;
                $displayNumber = 1;
            } elseif ($currentIndex + 1 < $totalParts) {
                $nextPart = $publishedParts[$currentIndex + 1];
                $readCount = $currentIndex + 1;
                $displayNumber = $currentIndex + 2;
            } else {
                $nextPart = $publishedParts[$currentIndex];
                $readCount = $totalParts;
                $displayNumber = $totalParts;
            }

            $results[] = [
                'series_id' => $series->id,
                'series_title' => $series->title,
                'series_slug' => $series->slug,
                'series_status' => $series->status->value,
                'cover_image_path' => $series->cover_image_path,
                'last_post' => [
                    'id' => $lastPost->id,
                    'title' => $lastPost->title,
                    'slug' => $lastPost->slug,
                ],
                'next_part' => [
                    'id' => $nextPart->id,
                    'title' => $nextPart->title,
                    'slug' => $nextPart->slug,
                    'display_number' => $displayNumber,
                ],
                'total_parts' => $totalParts,
                'read_parts' => $readCount,
                'progress_percent' => (int) round(($readCount / $totalParts) * 100),
                'has_unread_next' => $currentIndex !== false && $currentIndex + 1 < $totalParts,
            ];
        }

        return $results;
    }
}
