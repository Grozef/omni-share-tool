<?php

namespace App\Jobs;

use App\Events\PublicationStatusUpdated;
use App\Models\MediaFile;
use App\Models\Publication;
use App\Services\MediaProcessingService;
use App\Services\SocialPublisher\PublisherFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishToSocialJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;
    public array $backoff = [60, 300, 900]; // exponential backoff in seconds

    public function __construct(
        private Publication $publication,
        private array $platformsOverride = []
    ) {}

    public function handle(MediaProcessingService $mediaService): void
    {
        $this->publication->update(['status' => 'processing']);

        $platforms = !empty($this->platformsOverride)
            ? $this->platformsOverride
            : $this->publication->platforms;

        $media = $this->publication->mediaFiles()->first();

        $results = [];

        foreach ($platforms as $platform) {
            $results[$platform] = $this->publishToPlatform($platform, $media, $mediaService);
        }

        // Update publication status
        $allStatuses = array_column($results, 'status');
        $finalStatus = match (true) {
            count(array_filter($allStatuses, fn($s) => $s === 'success')) === count($allStatuses) => 'published',
            count(array_filter($allStatuses, fn($s) => $s === 'success')) > 0 => 'partial',
            default => 'failed',
        };

        $this->publication->update(['status' => $finalStatus]);

        broadcast(new PublicationStatusUpdated(
            $this->publication->user_id,
            $this->publication->id,
            $finalStatus,
            $results
        ));
    }

    private function publishToPlatform(string $platform, ?MediaFile $media, MediaProcessingService $mediaService): array
    {
        try {
            $account = $this->publication->user
                ->platformAccounts()
                ->where('platform', $platform)
                ->where('is_active', true)
                ->firstOrFail();

            // Process media for the specific platform if needed
            $platformMedia = $media;
            if ($media && ($media->isVideo() || $media->isImage())) {
                try {
                    $processedPath = $mediaService->processForPlatform(
                        $media->processed_path ?? $media->original_path,
                        $platform
                    );
                    $platformMedia = $media->replicate();
                    $platformMedia->processed_path = $processedPath;
                } catch (\Exception $e) {
                    Log::warning("Media processing failed for {$platform}: {$e->getMessage()}");
                    // Use original media as fallback
                }
            }

            $publisher = PublisherFactory::make($account);
            $result = $publisher->publish($this->publication, $platformMedia);

            broadcast(new PublicationStatusUpdated(
                $this->publication->user_id,
                $this->publication->id,
                'platform_update',
                [$platform => ['status' => $result->status, 'url' => $result->platform_url]]
            ));

            return ['status' => $result->status, 'url' => $result->platform_url];
        } catch (\Exception $e) {
            Log::error("Failed to publish to {$platform}: {$e->getMessage()}");

            \App\Models\PublicationResult::updateOrCreate(
                ['publication_id' => $this->publication->id, 'platform' => $platform],
                ['status' => 'failed', 'error_message' => $e->getMessage()]
            );

            broadcast(new PublicationStatusUpdated(
                $this->publication->user_id,
                $this->publication->id,
                'platform_update',
                [$platform => ['status' => 'failed', 'error' => $e->getMessage()]]
            ));

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->publication->update(['status' => 'failed']);

        broadcast(new PublicationStatusUpdated(
            $this->publication->user_id,
            $this->publication->id,
            'failed',
            ['error' => $exception->getMessage()]
        ));

        Log::error("PublishToSocialJob failed: {$exception->getMessage()}");
    }
}
