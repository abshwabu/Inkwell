<?php

namespace Database\Factories;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->paragraph(fake()->numberBetween(1, 3)),
            'status' => CommentStatus::VISIBLE,
        ];
    }

    /**
     * Hidden comment state.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CommentStatus::HIDDEN,
        ]);
    }
}
