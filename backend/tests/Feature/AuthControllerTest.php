<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_registers_a_new_user(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    /** @test */
    public function it_logs_in_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com', 'password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    /** @test */
    public function it_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'password' => bcrypt('secret123')]);

        $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'wrongpassword',
        ])->assertStatus(422);
    }
}
