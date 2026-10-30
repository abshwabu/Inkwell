<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\FeedService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BenchmarkFeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feed:benchmark {--count=10000 : Number of posts to benchmark against} {--keep : Keep benchmark posts in database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Benchmark the personalized feed query against 10k posts and display EXPLAIN QUERY PLAN';

    /**
     * Execute the console command.
     */
    public function handle(FeedService $feedService): int
    {
        $targetCount = (int) $this->option('count');
        $this->info("=== Inkwell Personalized Feed Benchmark ({$targetCount} posts) ===");

        // Ensure we have test users, categories, tags
        $user = User::first() ?? User::factory()->create();
        $author = User::factory()->create();
        $category = Category::first() ?? Category::factory()->create();
        $tag = Tag::first() ?? Tag::factory()->create();

        // Setup user preferences
        $user->preferredCategories()->syncWithoutDetaching([$category->id]);
        $user->preferredTags()->syncWithoutDetaching([$tag->id]);
        $user->following()->syncWithoutDetaching([$author->id]);

        $currentCount = Post::count();
        $needed = max(0, $targetCount - $currentCount);

        if ($needed > 0) {
            $this->info("Bulk generating {$needed} posts to reach {$targetCount} posts...");
            $batchSize = 1000;
            $now = Carbon::now()->toDateTimeString();

            for ($b = 0; $b < $needed; $b += $batchSize) {
                $batchRecords = [];
                $limit = min($batchSize, $needed - $b);
                $uniqueBase = Str::random(8);

                for ($i = 0; $i < $limit; $i++) {
                    $postIndex = $b + $i;
                    $batchRecords[] = [
                        'user_id' => $author->id,
                        'category_id' => $category->id,
                        'title' => "Benchmark Story {$uniqueBase} #{$postIndex}",
                        'slug' => "benchmark-story-{$uniqueBase}-{$postIndex}",
                        'excerpt' => 'A benchmark story excerpt generated for high load query evaluation.',
                        'content_html' => '<p>Benchmark story content.</p>',
                        'status' => PostStatus::PUBLISHED->value,
                        'is_featured' => ($postIndex % 100 === 0),
                        'published_at' => Carbon::now()->subMinutes(rand(1, 100000))->toDateTimeString(),
                        'views_count' => rand(10, 5000),
                        'likes_count' => rand(0, 500),
                        'comments_count' => rand(0, 100),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('posts')->insert($batchRecords);
                $this->output->write('.');
            }
            $this->newLine();
        }

        $totalPosts = Post::count();
        $this->info("Database now contains {$totalPosts} posts.");

        // 1. Run EXPLAIN QUERY PLAN
        $signals = $feedService->getUserSignals($user);
        $scoreSql = $feedService->buildSqlScoreExpression($signals);

        $explainSql = "EXPLAIN QUERY PLAN SELECT posts.*, {$scoreSql} as feed_score FROM posts "
            ."WHERE posts.status = 'published' AND posts.published_at <= datetime('now') AND posts.deleted_at IS NULL AND posts.user_id != ? "
            ."ORDER BY feed_score DESC LIMIT 10";

        $explainResults = DB::select($explainSql, [$user->id]);

        $this->newLine();
        $this->info('--- SQLite EXPLAIN QUERY PLAN ---');
        foreach ($explainResults as $row) {
            $this->line("  [id: {$row->id}] {$row->detail}");
        }
        $this->newLine();

        // 2. Measure Query Execution Time (5 runs for accuracy)
        $this->info('--- Measuring Feed Query Execution Time (5 runs) ---');
        $times = [];

        // Clear feed cache before timing
        $feedService->invalidateUserFeedCache($user);

        for ($run = 1; $run <= 5; $run++) {
            $feedService->invalidateUserFeedCache($user);

            $start = microtime(true);
            $paginator = $feedService->getFeed($user, 'for-you', 1, 10);
            $durationMs = (microtime(true) - $start) * 1000.0;

            $times[] = $durationMs;
            $this->line(sprintf('  Run %d: %.2f ms (Fetched %d stories)', $run, $durationMs, $paginator->count()));
        }

        $avgMs = array_sum($times) / count($times);
        $this->newLine();
        $this->info(sprintf('Average Execution Time: %.2f ms', $avgMs));

        $passed = $avgMs < 100.0;
        if ($passed) {
            $this->info("✔ ACCEPTANCE PASSED: Feed query runs in {$avgMs} ms (< 100 ms threshold) on {$totalPosts} posts.");
        } else {
            $this->error("✘ ACCEPTANCE FAILED: Feed query exceeded 100 ms ({$avgMs} ms).");
        }

        // Clean up benchmark posts unless --keep was passed
        if (! $this->option('keep') && $needed > 0) {
            $this->info('Cleaning up generated benchmark posts...');
            DB::table('posts')->where('title', 'like', 'Benchmark Story %')->delete();
            $this->info('Clean up completed.');
        }

        return $passed ? Command::SUCCESS : Command::FAILURE;
    }
}
