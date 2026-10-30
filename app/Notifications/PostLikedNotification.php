<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PostLikedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Post $post,
        public User $liker
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $categorySlug = $this->post->category ? $this->post->category->slug : 'story';

        return [
            'type' => 'like',
            'title' => 'New like on your story',
            'message' => "{$this->liker->name} liked your story \"{$this->post->title}\"",
            'post_id' => $this->post->id,
            'post_slug' => $this->post->slug,
            'category_slug' => $categorySlug,
            'liker_ids' => [$this->liker->id],
            'liker_names' => [$this->liker->name],
            'user_id' => $this->liker->id,
            'user_name' => $this->liker->name,
            'user_avatar' => $this->liker->avatar_url,
        ];
    }
}
