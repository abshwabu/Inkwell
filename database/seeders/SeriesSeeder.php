<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Enums\SeriesStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Series;
use App\Models\SeriesProgress;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SeriesSeeder extends Seeder
{
    /**
     * Seed 5 story series with 3-6 parts each.
     */
    public function run(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            return;
        }

        $seriesTemplates = [
            [
                'title' => 'Mastering Modern SQLite in Production',
                'description' => 'A comprehensive deep dive into WAL mode, concurrent single-file databases, FTS5 full-text indexing, and zero-maintenance production pipelines.',
                'status' => SeriesStatus::COMPLETED,
                'cover' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1200&auto=format&fit=crop&q=80',
                'parts' => [
                    'Why SQLite with WAL Mode is the Secret Weapon for Modern Apps',
                    'FTS5 Search Synchronization and BM25 Ranking in Practice',
                    'Zero-Downtime SQLite Backups and Vacuum Strategies',
                    'Concurrency Controls, Pragma Tuning, and Memory Boundaries',
                    'When to Stay with SQLite and When to Scale to Distributed Engines',
                ],
            ],
            [
                'title' => 'The Architecture of Full-Stack Inertia & Vue',
                'description' => 'Bridging server-side Laravel architecture with reactive Vue 3 components without the overhead of client-side routing and separate API endpoints.',
                'status' => SeriesStatus::ONGOING,
                'cover' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=1200&auto=format&fit=crop&q=80',
                'parts' => [
                    'The Architecture of Inertia.js: Bridging Laravel and Vue 3 Seamlessly',
                    'State Hydration, Partial Reloads, and Preserving Scroll Positions',
                    'Crafting Distraction-Free Rich Text Editors with TipTap',
                    'Testing Full-Stack Inertia Applications from Unit to Playwright E2E',
                    'Building Resilient Optimistic UI Mutations with Axios and Inertia',
                ],
            ],
            [
                'title' => 'The Solopreneur’s Blueprint: Bootstrapping SaaS in 2026',
                'description' => 'From initial validation to compounding recurring revenue: building lean, hyper-efficient software businesses with minimal overhead.',
                'status' => SeriesStatus::ONGOING,
                'cover' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&auto=format&fit=crop&q=80',
                'parts' => [
                    'Bootstrapping SaaS in 2026: The New Unit Economics of Solopreneurship',
                    'Building Audience Before Product: The Inverted Startup Playbook',
                    'The Death of the Vanity Metric: Prioritizing Retention Over Signups',
                    'Pricing Power and Value Metrics: How to Avoid Commoditization',
                    'Negative Churn, Expansion Revenue, and Sustainable Cash Reserves',
                ],
            ],
            [
                'title' => 'The Mindful Technologist: Reclaiming Attention and Focus',
                'description' => 'Essays on navigating hyper-connectivity, intentional information diets, circadian biology, and the enduring craft of deep work.',
                'status' => SeriesStatus::COMPLETED,
                'cover' => 'https://images.unsplash.com/photo-1499750310107-5fef28a66643?w=1200&auto=format&fit=crop&q=80',
                'parts' => [
                    'Digital Minimalism: Curating an Intentional Information Diet',
                    'The Art of the Daily Walk: Why Movement Unlocks Creative Clarity',
                    'Circadian Optimization: The Science of Morning Light and Deep Sleep',
                    'Monastic Work Blocks: Protecting Flow State in an Era of Interruptions',
                    'The Analog Sanctuary: Designing Physical Spaces for Quiet Reflection',
                ],
            ],
            [
                'title' => 'Journeys off the Beaten Track: Slow Travel in Eurasia',
                'description' => 'Travel reflections spanning remote mountain refuges in the Pyrenees to single-track regional train journeys across rural Japan.',
                'status' => SeriesStatus::HIATUS,
                'cover' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1200&auto=format&fit=crop&q=80',
                'parts' => [
                    'In Search of Silence: Weeks in the High Pyrenees',
                    'Slow Travel Across Japan: Kyoto to Hokkaido on Local Trains',
                    'Mountain Architecture and Alpine Refuges: Lessons in Radical Economy',
                    'Crossing the Eurasian Steppes: The Solitude of Endless Horizons',
                    'The Ancient Caravanserais: Footsteps Along Forgotten Silk Road Corridors',
                ],
            ],
        ];

        $categories = Category::all();

        foreach ($seriesTemplates as $i => $tmpl) {
            $author = $users[$i % $users->count()];

            $series = Series::create([
                'user_id' => $author->id,
                'title' => $tmpl['title'],
                'slug' => Str::slug($tmpl['title']),
                'description' => $tmpl['description'],
                'cover_image_path' => $tmpl['cover'],
                'status' => $tmpl['status'],
            ]);

            $firstPost = null;

            foreach ($tmpl['parts'] as $orderIndex => $partTitle) {
                // Check if post already exists by title
                $post = Post::where('title', $partTitle)->first();

                $excerpt = 'Part ' . ($orderIndex + 1) . ' of the series "' . $series->title . '". Exploring core principles, concrete trade-offs, and actionable architecture.';
                $category = $categories->isNotEmpty() ? $categories->random() : null;
                $htmlContent = ContentHelper::generateArticleHtml(
                    title: $partTitle,
                    category: $category?->slug ?? 'technology',
                    excerpt: $excerpt,
                    seriesTitle: $series->title,
                    partNumber: $orderIndex + 1
                );
                $tiptapDoc = ContentHelper::generateArticleJson($htmlContent);

                if (! $post) {
                    $post = Post::create([
                        'user_id' => $author->id,
                        'category_id' => $category?->id,
                        'title' => $partTitle,
                        'slug' => Str::slug($partTitle),
                        'excerpt' => $excerpt,
                        'cover_image_path' => $tmpl['cover'],
                        'content_html' => $htmlContent,
                        'content_json' => $tiptapDoc,
                        'reading_time_minutes' => max(1, (int) ceil(str_word_count(strip_tags($htmlContent)) / 225)),
                        'status' => PostStatus::PUBLISHED,
                        'published_at' => now()->subDays(10 - $orderIndex),
                        'series_id' => $series->id,
                        'series_order' => $orderIndex + 1,
                    ]);
                } else {
                    $post->update([
                        'user_id' => $author->id, // Ensure ownership matches series owner
                        'series_id' => $series->id,
                        'series_order' => $orderIndex + 1,
                        'content_html' => $htmlContent,
                        'content_json' => $tiptapDoc,
                        'reading_time_minutes' => max(1, (int) ceil(str_word_count(strip_tags($htmlContent)) / 225)),
                    ]);
                }

                if ($orderIndex === 0) {
                    $firstPost = $post;
                }
            }

            // Add followers from other users
            $otherUsers = $users->where('id', '!=', $author->id);
            foreach ($otherUsers->take(3) as $follower) {
                $series->followers()->attach($follower->id);

                if ($firstPost) {
                    SeriesProgress::updateOrCreate(
                        ['user_id' => $follower->id, 'series_id' => $series->id],
                        ['last_post_id' => $firstPost->id]
                    );
                }
            }
        }
    }
}
