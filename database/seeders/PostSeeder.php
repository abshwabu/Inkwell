<?php

namespace Database\Seeders;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Bookmark;
use App\Models\Follow;
use App\Models\Post;
use App\Models\PostView;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $categories = Category::all();
        $tags = Tag::all();

        if ($users->isEmpty() || $categories->isEmpty()) {
            return;
        }

        $postTemplates = [
            // Technology
            [
                'title' => 'Why SQLite with WAL Mode is the Secret Weapon for Modern Apps',
                'category' => 'technology',
                'excerpt' => 'Write-Ahead Logging transforms SQLite from a modest embedded database into a blazing-fast, single-file concurrent engine.',
                'image' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'The Architecture of Inertia.js: Bridging Laravel and Vue 3 Seamlessly',
                'category' => 'technology',
                'excerpt' => 'How Inertia eliminates the need for complex client-side state management while retaining single-page app reactivity.',
                'image' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Tailwind CSS in Production: Designing Scalable Design Token Systems',
                'category' => 'technology',
                'excerpt' => 'Strategies for structuring typography, spacing scales, and dark mode transitions across complex component trees.',
                'image' => 'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Autonomous Agents and Context Engineering: Lessons from the Field',
                'category' => 'technology',
                'excerpt' => 'Context boundaries, compact toolchains, and state recovery patterns when building real-world AI coding assistants.',
                'image' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Demystifying PHP 8.4 and Laravel 12 Performance Optimizations',
                'category' => 'technology',
                'excerpt' => 'From JIT enhancements to zero-overhead class loading: how the latest PHP ecosystem achieves microsecond latencies.',
                'image' => 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=1200&auto=format&fit=crop&q=80',
            ],
            // Business
            [
                'title' => 'Bootstrapping SaaS in 2026: The New Unit Economics of Solopreneurship',
                'category' => 'business',
                'excerpt' => 'Why hyper-efficient infrastructure, AI tooling, and lean codebases allow solo founders to reach profitability sooner.',
                'image' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Building Audience Before Product: The Inverted Startup Playbook',
                'category' => 'business',
                'excerpt' => 'How validating ideas through editorial essays and organic discovery mitigates market risk before writing code.',
                'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'The Death of the Vanity Metric: Prioritizing Retention Over Signups',
                'category' => 'business',
                'excerpt' => 'Customer lifetime value, negative churn, and why deep product engagement outweighs high-velocity lead acquisition.',
                'image' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?w=1200&auto=format&fit=crop&q=80',
            ],
            // Lifestyle
            [
                'title' => 'Digital Minimalism: Curating an Intentional Information Diet',
                'category' => 'lifestyle',
                'excerpt' => 'Reclaiming cognitive bandwidth by stepping away from infinite feeds and rediscovering deep focus rituals.',
                'image' => 'https://images.unsplash.com/photo-1499750310107-5fef28a66643?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'The Art of the Daily Walk: Why Movement Unlocks Creative Clarity',
                'category' => 'lifestyle',
                'excerpt' => 'Historical habits of writers and scientists who used daily unhurried strides to solve intricate problems.',
                'image' => 'https://images.unsplash.com/photo-1476820865390-c52aeebb9891?w=1200&auto=format&fit=crop&q=80',
            ],
            // Health
            [
                'title' => 'Circadian Optimization: The Science of Morning Light and Deep Sleep',
                'category' => 'health',
                'excerpt' => 'How aligning our daily rhythms with solar cycles radically transforms mitochondrial energy and focus.',
                'image' => 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Stress Resilience in High-Paced Environments: A Neurobiological View',
                'category' => 'health',
                'excerpt' => 'Practical physiological interventions to regulate sympathetic arousal during high-stakes deadlines.',
                'image' => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=1200&auto=format&fit=crop&q=80',
            ],
            // Travel
            [
                'title' => 'In Search of Silence: Weeks in the High Pyrenees',
                'category' => 'travel',
                'excerpt' => 'An essay on solitude, stone refuges, and the acoustic clarity of uninhabited mountain passes.',
                'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Slow Travel Across Japan: Kyoto to Hokkaido on Local Trains',
                'category' => 'travel',
                'excerpt' => 'Trading the high-speed Shinkansen for single-track diesel rail cars winding through quiet farming valleys.',
                'image' => 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?w=1200&auto=format&fit=crop&q=80',
            ],
            // Education
            [
                'title' => 'The Self-Taught Engineer’s Compass: Learning Deep Foundations First',
                'category' => 'education',
                'excerpt' => 'Why mastering memory models, protocols, and relational calculus pays dividends longer than fleeting frameworks.',
                'image' => 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Rethinking Technical Documentation as a Pedagogical Craft',
                'category' => 'education',
                'excerpt' => 'Treating user guides not as technical reference manuals, but as empathetic learning journeys.',
                'image' => 'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=1200&auto=format&fit=crop&q=80',
            ],
            // Culture
            [
                'title' => 'The Architecture of Public Squares: How Spatial Design Shapes Society',
                'category' => 'culture',
                'excerpt' => 'Examining how civic plazas, promenades, and urban gathering places cultivate community resilience.',
                'image' => 'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Analog Reverberations: The Enduring Resurgence of Physical Print',
                'category' => 'culture',
                'excerpt' => 'Why independent quarterlies, risograph prints, and vinyl records thrive in an ephemeral streaming era.',
                'image' => 'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?w=1200&auto=format&fit=crop&q=80',
            ],
            // Opinion
            [
                'title' => 'The Indispensable Value of Friction in Software Design',
                'category' => 'opinion',
                'excerpt' => 'When making every action instantaneous degrades human deliberation, safety, and creative satisfaction.',
                'image' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title' => 'Why Every Engineer Should Cultivate a Public Writing Habit',
                'category' => 'opinion',
                'excerpt' => 'Writing clearly forces thinking clearly. The compounding benefits of publishing essays in the open.',
                'image' => 'https://images.unsplash.com/photo-1455390582262-044cdead277a?w=1200&auto=format&fit=crop&q=80',
            ],
        ];

        // Seed exactly 60 posts
        $createdPosts = [];

        for ($i = 0; $i < 60; $i++) {
            $template = $postTemplates[$i % count($postTemplates)];
            $user = $users[$i % $users->count()];

            // Map category slug to model or pick random
            $category = $categories->firstWhere('slug', $template['category']) ?? $categories->random();

            $title = $template['title'] . ($i >= count($postTemplates) ? ' — Part ' . (intdiv($i, count($postTemplates)) + 1) : '');
            $excerpt = $template['excerpt'];

            // Status distribution: ~45 published, ~10 draft, ~5 archived
            if ($i < 45) {
                $status = PostStatus::PUBLISHED;
                $publishedAt = now()->subHours(rand(1, 24 * 90));
                $viewsCount = rand(50, 6500);
                $likesCount = rand(5, 350);
            } elseif ($i < 55) {
                $status = PostStatus::DRAFT;
                $publishedAt = null;
                $viewsCount = 0;
                $likesCount = 0;
            } else {
                $status = PostStatus::ARCHIVED;
                $publishedAt = now()->subMonths(rand(6, 14));
                $viewsCount = rand(20, 1200);
                $likesCount = rand(2, 60);
            }

            $htmlContent = ContentHelper::generateArticleHtml($title, $category->slug, $excerpt);
            $tiptapDoc = ContentHelper::generateArticleJson($htmlContent);

            $post = Post::create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'title' => $title,
                'slug' => Str::slug($title),
                'excerpt' => $excerpt,
                'cover_image_path' => $template['image'],
                'content_json' => $tiptapDoc,
                'content_html' => $htmlContent,
                'reading_time_minutes' => max(1, (int) ceil(str_word_count(strip_tags($htmlContent)) / 225)),
                'status' => $status,
                'published_at' => $publishedAt,
                'views_count' => $viewsCount,
                'likes_count' => $likesCount,
                'comments_count' => 0,
            ]);

            // Attach 2 to 4 random tags
            if ($tags->isNotEmpty()) {
                $randomTags = $tags->random(rand(2, 4))->pluck('id')->toArray();
                $post->tags()->sync($randomTags);
            }

            $createdPosts[] = $post;
        }

        // Seed comments, likes, bookmarks, and views on published posts
        $publishedPosts = collect($createdPosts)->filter(fn (Post $p) => $p->status === PostStatus::PUBLISHED);

        foreach ($publishedPosts as $post) {
            // Seed 1 to 4 comments
            $commentCount = rand(1, 4);
            $parentComment = null;

            for ($c = 0; $c < $commentCount; $c++) {
                $commenter = $users->random();
                $isReply = ($c > 1 && $parentComment !== null);

                $comment = Comment::create([
                    'post_id' => $post->id,
                    'user_id' => $commenter->id,
                    'parent_id' => $isReply ? $parentComment->id : null,
                    'body' => $isReply
                        ? 'Great perspective! I especially appreciated the point regarding simplicity in architecture.'
                        : 'Wonderful article. The clarity of thought here really resonates with the challenges we face daily in software development.',
                    'status' => CommentStatus::VISIBLE,
                ]);

                if (!$isReply) {
                    $parentComment = $comment;
                }
            }

            $post->update(['comments_count' => $post->comments()->count()]);

            // Seed likes from 2-5 users
            $likers = $users->random(rand(2, min(6, $users->count())));
            foreach ($likers as $liker) {
                Like::firstOrCreate([
                    'user_id' => $liker->id,
                    'post_id' => $post->id,
                ]);
            }
            $post->update(['likes_count' => $post->likes()->count()]);

            // Seed bookmarks from 1-3 users
            $bookmarkers = $users->random(rand(1, min(3, $users->count())));
            foreach ($bookmarkers as $bookmarker) {
                Bookmark::firstOrCreate([
                    'user_id' => $bookmarker->id,
                    'post_id' => $post->id,
                ]);
            }

            // Seed 3-8 post views with session hashes
            for ($v = 0; $v < rand(3, 8); $v++) {
                PostView::create([
                    'post_id' => $post->id,
                    'user_id' => rand(0, 1) ? $users->random()->id : null,
                    'session_hash' => hash('sha256', 'session_' . rand(1000, 999999)),
                    'viewed_at' => now()->subHours(rand(1, 720)),
                ]);
            }
        }

        // Seed social follows between users
        foreach ($users as $user) {
            $otherUsers = $users->where('id', '!=', $user->id);
            $followTargets = $otherUsers->random(min(3, $otherUsers->count()));

            foreach ($followTargets as $target) {
                Follow::firstOrCreate([
                    'follower_id' => $user->id,
                    'followed_id' => $target->id,
                ]);
            }
        }
    }
}
