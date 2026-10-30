<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\PostView;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IncrementPostViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $postId,
        public ?int $userId,
        public string $sessionHash
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Double check deduplication in job execution
        $alreadyLogged = PostView::where('post_id', $this->postId)
            ->where(function ($q) {
                $q->where('session_hash', $this->sessionHash);
                if ($this->userId) {
                    $q->orWhere('user_id', $this->userId);
                }
            })
            ->where('viewed_at', '>=', now()->subHours(24))
            ->exists();

        if (! $alreadyLogged) {
            PostView::create([
                'post_id' => $this->postId,
                'user_id' => $this->userId,
                'session_hash' => $this->sessionHash,
                'viewed_at' => now(),
            ]);

            Post::where('id', $this->postId)->increment('views_count');
        }
    }
}
