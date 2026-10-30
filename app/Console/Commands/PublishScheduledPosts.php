<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'posts:publish-scheduled';

    /**
     * The console command description.
     */
    protected $description = 'Publish scheduled posts whose scheduled publication date has arrived';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $posts = Post::where('status', PostStatus::SCHEDULED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($posts as $post) {
            $post->update([
                'status' => PostStatus::PUBLISHED,
            ]);
            $count++;
        }

        $this->info("Published {$count} scheduled post(s).");

        return Command::SUCCESS;
    }
}
