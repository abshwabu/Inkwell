<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence(fake()->numberBetween(5, 10));
        $paragraphs = fake()->paragraphs(fake()->numberBetween(3, 6));

        $htmlContent = '';
        $tiptapContent = [];

        foreach ($paragraphs as $index => $paragraph) {
            if ($index === 1) {
                $subheading = fake()->sentence(4);
                $htmlContent .= "<h2>{$subheading}</h2>";
                $tiptapContent[] = [
                    'type' => 'heading',
                    'attrs' => ['level' => 2],
                    'content' => [['type' => 'text', 'text' => $subheading]],
                ];
            }

            $htmlContent .= "<p>{$paragraph}</p>";
            $tiptapContent[] = [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $paragraph]],
            ];

            if ($index === 2) {
                $quote = fake()->sentence(8);
                $htmlContent .= "<blockquote><p>{$quote}</p></blockquote>";
                $tiptapContent[] = [
                    'type' => 'blockquote',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => $quote]],
                        ],
                    ],
                ];
            }
        }

        $tiptapDoc = [
            'type' => 'doc',
            'content' => $tiptapContent,
        ];

        $wordCount = str_word_count(strip_tags($htmlContent));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        $status = fake()->randomElement([
            PostStatus::PUBLISHED,
            PostStatus::PUBLISHED,
            PostStatus::PUBLISHED,
            PostStatus::DRAFT,
            PostStatus::ARCHIVED,
        ]);

        $publishedAt = $status === PostStatus::PUBLISHED
            ? fake()->dateTimeBetween('-6 months', 'now')
            : ($status === PostStatus::ARCHIVED ? fake()->dateTimeBetween('-1 year', '-6 months') : null);

        $images = [
            'https://images.unsplash.com/photo-1499750310107-5fef28a66643?w=1200&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1488190211105-8b0e65b80b4e?w=1200&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?w=1200&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=1200&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1200&auto=format&fit=crop&q=80',
        ];

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title),
            'excerpt' => fake()->paragraph(2),
            'cover_image_path' => fake()->randomElement($images),
            'content_json' => $tiptapDoc,
            'content_html' => $htmlContent,
            'reading_time_minutes' => $readingTime,
            'status' => $status,
            'published_at' => $publishedAt,
            'views_count' => $status === PostStatus::PUBLISHED ? fake()->numberBetween(10, 5000) : 0,
            'likes_count' => $status === PostStatus::PUBLISHED ? fake()->numberBetween(0, 250) : 0,
            'comments_count' => 0,
        ];
    }

    /**
     * Published state.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::PUBLISHED,
            'published_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'views_count' => fake()->numberBetween(50, 4500),
            'likes_count' => fake()->numberBetween(5, 300),
        ]);
    }

    /**
     * Draft state.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::DRAFT,
            'published_at' => null,
            'views_count' => 0,
            'likes_count' => 0,
            'comments_count' => 0,
        ]);
    }

    /**
     * Archived state.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PostStatus::ARCHIVED,
            'published_at' => fake()->dateTimeBetween('-1 year', '-4 months'),
        ]);
    }
}
