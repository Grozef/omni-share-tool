<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PublicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'caption'   => $this->faker->sentence(10),
            'hashtags'  => ['test', 'sample'],
            'platforms' => $this->faker->randomElements(
                ['facebook', 'instagram', 'tiktok', 'youtube'],
                2
            ),
            'status'    => 'draft',
            'scheduled_at' => null,
        ];
    }
}
