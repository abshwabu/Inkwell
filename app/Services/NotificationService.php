<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Notifications\PostLikedNotification;

class NotificationService
{
    /**
     * Send or batch a like notification to the post's author.
     */
    public function notifyPostLiked(Post $post, User $liker): void
    {
        $author = $post->user;

        // Don't notify the author if they liked their own post
        if (! $author || $author->id === $liker->id) {
            return;
        }

        // Find an existing unread like notification for this post
        $existing = $author->unreadNotifications()
            ->where('type', PostLikedNotification::class)
            ->get()
            ->first(function ($n) use ($post) {
                return ($n->data['post_id'] ?? null) === $post->id;
            });

        if ($existing) {
            $data = $existing->data;
            $likerIds = $data['liker_ids'] ?? [];
            $likerNames = $data['liker_names'] ?? [];

            if (! in_array($liker->id, $likerIds)) {
                $likerIds[] = $liker->id;
                $likerNames[] = $liker->name;

                $count = count($likerNames);
                if ($count === 1) {
                    $message = "{$likerNames[0]} liked your story \"{$post->title}\"";
                } elseif ($count === 2) {
                    $message = "{$likerNames[0]} and {$likerNames[1]} liked your story \"{$post->title}\"";
                } else {
                    $others = $count - 1;
                    $message = "{$likerNames[0]} and {$others} others liked your story \"{$post->title}\"";
                }

                $data['liker_ids'] = $likerIds;
                $data['liker_names'] = $likerNames;
                $data['message'] = $message;
                $existing->data = $data;
                $existing->save();
            }
        } else {
            $author->notify(new PostLikedNotification($post, $liker));
        }
    }
}
