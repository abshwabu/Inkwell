<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\Series;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewSeriesPartNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Post $post,
        public Series $series,
        public int $partNumber
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'series_part',
            'title' => 'New series installment',
            'message' => "New part: {$this->post->title}, Part {$this->partNumber} of {$this->series->title}",
            'post_id' => $this->post->id,
            'post_title' => $this->post->title,
            'post_slug' => $this->post->slug,
            'category_slug' => $this->post->category?->slug ?? 'story',
            'series_id' => $this->series->id,
            'series_title' => $this->series->title,
            'series_slug' => $this->series->slug,
            'part_number' => $this->partNumber,
        ];
    }
}
