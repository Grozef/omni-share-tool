<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'           => User::factory(),
            'platform'          => $this->faker->randomElement(['facebook', 'instagram', 'tiktok', 'youtube', 'pinterest', 'snapchat']),
            'access_token'      => $this->faker->sha256(),
            'refresh_token'     => $this->faker->optional()->sha256(),
            'token_expires_at'  => now()->addDays(60),
            'platform_user_id'  => $this->faker->numerify('#########'),
            'platform_username' => $this->faker->userName(),
            'is_active'         => true,
        ];
    }
}
