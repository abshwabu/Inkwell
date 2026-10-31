<?php

namespace Database\Factories;

use App\Enums\SeriesStatus;
use App\Models\Series;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Series>
 */
class SeriesFactory extends Factory
{
    protected $model = Series::class;

    public function definition(): array
    {
        $covers = [
            'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1200&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?w=1200&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1488190211105-8b0e65b80b4e?w=1200&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1499750310107-5fef28a66643?w=1200&auto=format&fit=crop&q=80',
        ];

        $title = fake()->unique()->sentence(4);

        return [
            'user_id' => User::factory(),
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(3),
            'cover_image_path' => fake()->randomElement($covers),
            'status' => fake()->randomElement([
                SeriesStatus::ONGOING,
                SeriesStatus::COMPLETED,
                SeriesStatus::HIATUS,
            ]),
        ];
    }

    public function ongoing(): static
    {
        return $this->state(fn () => ['status' => SeriesStatus::ONGOING]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => SeriesStatus::COMPLETED]);
    }
}
