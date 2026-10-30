<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostViewFactory extends Factory
{
    protected $model = PostView::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'session_hash' => hash('sha256', Str::random(40)),
            'viewed_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
