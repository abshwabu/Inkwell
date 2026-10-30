<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            'laravel',
            'vuejs',
            'php',
            'javascript',
            'tailwind',
            'sqlite',
            'web-development',
            'ui-ux',
            'ai',
            'productivity',
            'remote-work',
            'writing',
            'startups',
            'architecture',
            'design-systems',
            'open-source',
            'devops',
            'career',
            'books',
            'mental-health',
            'fitness',
            'travel-guides',
            'minimalism',
            'finance',
            'leadership',
            'creativity',
            'future-of-work',
            'security',
            'clean-code',
            'algorithms',
        ];

        foreach ($tags as $tagName) {
            Tag::updateOrCreate(
                ['slug' => Str::slug($tagName)],
                [
                    'name' => str_replace('-', ' ', $tagName),
                    'slug' => Str::slug($tagName),
                ]
            );
        }
    }
}
