<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewCommentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Post $post,
        public Comment $comment,
        public User $commenter
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $categorySlug = $this->post->category ? $this->post->category->slug : 'story';

        return [
            'type' => 'comment',
            'title' => 'New comment on your story',
            'message' => "{$this->commenter->name} commented on \"{$this->post->title}\"",
            'post_id' => $this->post->id,
            'post_slug' => $this->post->slug,
            'category_slug' => $categorySlug,
            'comment_id' => $this->comment->id,
            'user_id' => $this->commenter->id,
            'user_name' => $this->commenter->name,
            'user_avatar' => $this->commenter->avatar_url,
        ];
    }
}
