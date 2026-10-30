<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $usersData = [
            [
                'name' => 'Admin Inkwell',
                'username' => 'admin',
                'email' => 'admin@inkwell.test',
                'role' => UserRole::ADMIN,
                'bio' => 'Founder & lead engineer at Inkwell. Crafting minimalist platforms for open discourse and longform prose.',
                'avatar_path' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Mod Inkwell',
                'username' => 'moderator',
                'email' => 'mod@inkwell.test',
                'role' => UserRole::MODERATOR,
                'bio' => 'Inkwell editorial board member. Focused on community guidelines, respectful discussions, and curated highlights.',
                'avatar_path' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Sarah Chen',
                'username' => 'sarahchen',
                'email' => 'sarah@example.com',
                'role' => UserRole::USER,
                'bio' => 'Staff software engineer writing about distributed systems, SQLite internals, and modern web architectures.',
                'avatar_path' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Marcus Vance',
                'username' => 'marcusvance',
                'email' => 'marcus@example.com',
                'role' => UserRole::USER,
                'bio' => 'Product designer obsessed with calm interfaces, accessible typography, and fluid digital design systems.',
                'avatar_path' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Amina Diop',
                'username' => 'aminadiop',
                'email' => 'amina@example.com',
                'role' => UserRole::USER,
                'bio' => 'Tech columnist, cultural essayist, and advocate for ethical artificial intelligence and decentralized platforms.',
                'avatar_path' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Liam Thorne',
                'username' => 'liamthorne',
                'email' => 'liam@example.com',
                'role' => UserRole::USER,
                'bio' => 'Startup founder sharing unvarnished field notes on bootstrapping, unit economics, and remote work culture.',
                'avatar_path' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=150&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Elena Rostova',
                'username' => 'elenarostova',
                'email' => 'elena@example.com',
                'role' => UserRole::USER,
                'bio' => 'Documentary photographer and travel essayist exploring alpine solitude, architectural ruins, and slow travel.',
                'avatar_path' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'David Kim',
                'username' => 'davidkim',
                'email' => 'david@example.com',
                'role' => UserRole::USER,
                'bio' => 'Physician and neurobiology researcher writing about cognitive health, circadian rhythms, and longevity protocols.',
                'avatar_path' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=150&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Sofia Alvarez',
                'username' => 'sofiaalvarez',
                'email' => 'sofia@example.com',
                'role' => UserRole::USER,
                'bio' => 'Philosophy researcher and literary critic examining contemporary ethics, existentialism, and historical memory.',
                'avatar_path' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Julian Reed',
                'username' => 'julianreed',
                'email' => 'julian@example.com',
                'role' => UserRole::USER,
                'bio' => 'Educator and curriculum architect building open-source learning paths for non-traditional software developers.',
                'avatar_path' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=150&auto=format&fit=crop&q=80',
            ],
        ];

        $categories = Category::all();
        $tags = Tag::all();

        foreach ($usersData as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => Hash::make('password'),
                    'role' => $data['role'],
                    'bio' => $data['bio'],
                    'avatar_path' => $data['avatar_path'],
                    'is_banned' => false,
                    'email_verified_at' => now(),
                ]
            );

            // Assign random preferred categories & tags
            if ($categories->isNotEmpty()) {
                $user->preferredCategories()->syncWithoutDetaching(
                    $categories->random(min(3, $categories->count()))->pluck('id')->toArray()
                );
            }

            if ($tags->isNotEmpty()) {
                $user->preferredTags()->syncWithoutDetaching(
                    $tags->random(min(5, $tags->count()))->pluck('id')->toArray()
                );
            }
        }
    }
}
