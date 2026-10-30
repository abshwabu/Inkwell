<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommentReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Post $post,
        public Comment $parentComment,
        public Comment $reply,
        public User $replier
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $categorySlug = $this->post->category ? $this->post->category->slug : 'story';

        return [
            'type' => 'comment_reply',
            'title' => 'New reply to your comment',
            'message' => "{$this->replier->name} replied to your comment on \"{$this->post->title}\"",
            'post_id' => $this->post->id,
            'post_slug' => $this->post->slug,
            'category_slug' => $categorySlug,
            'comment_id' => $this->reply->id,
            'parent_comment_id' => $this->parentComment->id,
            'user_id' => $this->replier->id,
            'user_name' => $this->replier->name,
            'user_avatar' => $this->replier->avatar_url,
        ];
    }
}
