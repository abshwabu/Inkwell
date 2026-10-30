<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();
        $username = Str::slug($name) . '-' . fake()->unique()->numberBetween(100, 9999);

        return [
            'name' => $name,
            'username' => $username,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'bio' => fake()->paragraph(2),
            'avatar_path' => 'https://images.unsplash.com/photo-' . fake()->randomElement([
                '1494790108377-be9c29b29330',
                '1507003211169-0a1dd7228f2d',
                '1534528741775-53994a69daeb',
                '1500648767791-00dcc994a43e',
                '1438761681033-6461ffad8d80',
                '1472099645785-5658abf4ff4e',
            ]) . '?w=150&auto=format&fit=crop&q=80',
            'role' => UserRole::USER,
            'is_banned' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Admin role state.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ADMIN,
        ]);
    }

    /**
     * Moderator role state.
     */
    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::MODERATOR,
        ]);
    }

    /**
     * Banned state.
     */
    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_banned' => true,
        ]);
    }
}
