<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Software engineering, artificial intelligence, product architecture, and digital craft.',
                'color' => '#3B82F6',
            ],
            [
                'name' => 'Lifestyle',
                'slug' => 'lifestyle',
                'description' => 'Mindful living, daily habits, personal productivity, and intentional choices.',
                'color' => '#EC4899',
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Startups, independent creators, market economics, and sustainable growth.',
                'color' => '#10B981',
            ],
            [
                'name' => 'Health',
                'slug' => 'health',
                'description' => 'Physical wellness, mental balance, longevity, and science-backed living.',
                'color' => '#EF4444',
            ],
            [
                'name' => 'Travel',
                'slug' => 'travel',
                'description' => 'Journeys off the beaten path, cultural encounters, and wanderlust essays.',
                'color' => '#F59E0B',
            ],
            [
                'name' => 'Education',
                'slug' => 'education',
                'description' => 'Lifelong learning, pedagogy, self-taught paths, and academic explorations.',
                'color' => '#8B5CF6',
            ],
            [
                'name' => 'Culture',
                'slug' => 'culture',
                'description' => 'Literature, philosophy, film, artistic expression, and societal perspectives.',
                'color' => '#6366F1',
            ],
            [
                'name' => 'Opinion',
                'slug' => 'opinion',
                'description' => 'Thoughtful essays, cultural critiques, and distinct points of view.',
                'color' => '#14B8A6',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
