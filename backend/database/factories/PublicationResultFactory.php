<?php

namespace Database\Factories;

use App\Models\Publication;
use Illuminate\Database\Eloquent\Factories\Factory;

class PublicationResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'publication_id'  => Publication::factory(),
            'platform'        => $this->faker->randomElement(['facebook', 'instagram', 'tiktok', 'youtube', 'pinterest', 'snapchat']),
            'status'          => $this->faker->randomElement(['pending', 'success', 'failed']),
            'platform_post_id' => $this->faker->optional()->numerify('########'),
            'platform_url'    => $this->faker->optional()->url(),
            'error_message'   => null,
            'published_at'    => null,
        ];
    }
}
