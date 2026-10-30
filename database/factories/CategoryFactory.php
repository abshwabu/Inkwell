<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(fake()->numberBetween(1, 2), true);
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(10),
            'color' => fake()->randomElement([
                '#3B82F6', // Blue
                '#10B981', // Emerald
                '#8B5CF6', // Purple
                '#F59E0B', // Amber
                '#EF4444', // Red
                '#EC4899', // Pink
                '#06B6D4', // Cyan
                '#14B8A6', // Teal
                '#6366F1', // Indigo
            ]),
        ];
    }
}
