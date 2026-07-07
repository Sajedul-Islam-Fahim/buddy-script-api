<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'body'       => fake()->paragraph(),
            'image'      => null,
            'visibility' => fake()->randomElement(['public', 'private']),
            'likes_count'    => 0,
            'comments_count' => 0,
        ];
    }

    public function public(): static
    {
        return $this->state(['visibility' => 'public']);
    }

    public function private(): static
    {
        return $this->state(['visibility' => 'private']);
    }
}
