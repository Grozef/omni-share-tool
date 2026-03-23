<?php

namespace Tests\Feature;

use App\Models\MediaFile;
use App\Models\PlatformAccount;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /** @test */
    public function it_creates_a_publication_and_dispatches_job(): void
    {
        // Connect a platform
        PlatformAccount::factory()->create([
            'user_id'  => $this->user->id,
            'platform' => 'instagram',
        ]);

        $response = $this->postJson('/api/publications', [
            'caption'   => 'Test post caption',
            'hashtags'  => ['test', 'socialmedia'],
            'platforms' => ['instagram'],
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'caption', 'status', 'platforms', 'results'])
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('caption', 'Test post caption');

        $this->assertDatabaseHas('publications', [
            'user_id' => $this->user->id,
            'caption' => 'Test post caption',
            'status'  => 'pending',
        ]);

        $this->assertDatabaseHas('publication_results', [
            'publication_id' => $response->json('id'),
            'platform'       => 'instagram',
            'status'         => 'pending',
        ]);

        Queue::assertPushed(\App\Jobs\PublishToSocialJob::class);
    }

    /** @test */
    public function it_requires_at_least_one_platform(): void
    {
        $this->postJson('/api/publications', [
            'caption'   => 'Test',
            'platforms' => [],
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['platforms']);
    }

    /** @test */
    public function it_validates_platform_names(): void
    {
        $this->postJson('/api/publications', [
            'caption'   => 'Test',
            'platforms' => ['twitter'],
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['platforms.0']);
    }

    /** @test */
    public function it_schedules_a_publication_for_the_future(): void
    {
        $scheduledAt = now()->addHours(2)->toISOString();

        $response = $this->postJson('/api/publications', [
            'caption'      => 'Scheduled post',
            'platforms'    => ['facebook'],
            'scheduled_at' => $scheduledAt,
        ], $this->authHeaders());

        $response->assertStatus(201);

        $this->assertDatabaseHas('publications', [
            'id'     => $response->json('id'),
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_lists_only_the_authenticated_users_publications(): void
    {
        Publication::factory()->count(3)->create(['user_id' => $this->user->id]);
        Publication::factory()->count(2)->create(); // other user

        $response = $this->getJson('/api/publications', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('total', 3);
    }

    /** @test */
    public function it_shows_a_single_publication_with_results(): void
    {
        $pub = Publication::factory()
            ->has(\App\Models\PublicationResult::factory()->count(2), 'results')
            ->create(['user_id' => $this->user->id]);

        $this->getJson("/api/publications/{$pub->id}", $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonStructure(['id', 'caption', 'results' => [['platform', 'status']]]);
    }

    /** @test */
    public function it_cannot_view_another_users_publication(): void
    {
        $otherPub = Publication::factory()->create();

        $this->getJson("/api/publications/{$otherPub->id}", $this->authHeaders())
            ->assertStatus(404);
    }

    /** @test */
    public function it_retries_failed_platforms(): void
    {
        $pub = Publication::factory()->create([
            'user_id'   => $this->user->id,
            'status'    => 'partial',
            'platforms' => ['instagram', 'tiktok'],
        ]);

        \App\Models\PublicationResult::factory()->create([
            'publication_id' => $pub->id,
            'platform'       => 'instagram',
            'status'         => 'success',
        ]);

        \App\Models\PublicationResult::factory()->create([
            'publication_id' => $pub->id,
            'platform'       => 'tiktok',
            'status'         => 'failed',
            'error_message'  => 'Upload failed',
        ]);

        $response = $this->postJson("/api/publications/{$pub->id}/retry", [], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('platforms', ['tiktok']);

        $this->assertDatabaseHas('publication_results', [
            'publication_id' => $pub->id,
            'platform'       => 'tiktok',
            'status'         => 'pending',
        ]);

        Queue::assertPushed(\App\Jobs\PublishToSocialJob::class);
    }

    /** @test */
    public function it_only_deletes_drafts(): void
    {
        $draft = Publication::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'draft',
        ]);

        $published = Publication::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'published',
        ]);

        $this->deleteJson("/api/publications/{$draft->id}", [], $this->authHeaders())
            ->assertStatus(204);

        $this->deleteJson("/api/publications/{$published->id}", [], $this->authHeaders())
            ->assertStatus(422);

        $this->assertDatabaseMissing('publications', ['id' => $draft->id]);
        $this->assertDatabaseHas('publications', ['id' => $published->id]);
    }

    /** @test */
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/publications')->assertStatus(401);
        $this->postJson('/api/publications', [])->assertStatus(401);
    }
}
