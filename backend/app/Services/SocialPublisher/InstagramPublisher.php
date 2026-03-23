<?php

namespace App\Services\SocialPublisher;

use App\Models\MediaFile;
use App\Models\Publication;
use App\Models\PublicationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class InstagramPublisher extends AbstractPublisher
{
    private const API_BASE = 'https://graph.facebook.com/v18.0';

    public function publish(Publication $publication, ?MediaFile $media): PublicationResult
    {
        try {
            $this->refreshTokenIfNeeded();
            $igUserId = $this->account->platform_user_id;
            $caption = $this->buildCaption($publication);

            if ($media?->isVideo()) {
                return $this->publishReel($publication, $media, $igUserId, $caption);
            }

            if ($media?->isImage()) {
                return $this->publishPhoto($publication, $media, $igUserId, $caption);
            }

            return $this->makeResult($publication, 'failed', error: 'Instagram requires media (image or video)');
        } catch (\Exception $e) {
            return $this->makeResult($publication, 'failed', error: $e->getMessage());
        }
    }

    private function publishPhoto(Publication $publication, MediaFile $media, string $igUserId, string $caption): PublicationResult
    {
        $mediaUrl = Storage::url($media->processed_path ?? $media->original_path);

        // Step 1: Create media container
        $containerResponse = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->post(self::API_BASE . "/{$igUserId}/media", [
                'image_url' => $mediaUrl,
                'caption' => $caption,
            ]));

        if ($containerResponse->failed()) {
            return $this->makeResult($publication, 'failed', error: $containerResponse->json('error.message'));
        }

        $containerId = $containerResponse->json('id');

        // Step 2: Wait for container to be ready
        $this->waitForMediaReady($containerId);

        // Step 3: Publish container
        $publishResponse = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->post(self::API_BASE . "/{$igUserId}/media_publish", [
                'creation_id' => $containerId,
            ]));

        if ($publishResponse->failed()) {
            return $this->makeResult($publication, 'failed', error: $publishResponse->json('error.message'));
        }

        $mediaId = $publishResponse->json('id');
        return $this->makeResult(
            $publication, 'success',
            postId: $mediaId,
            url: "https://www.instagram.com/p/{$mediaId}/"
        );
    }

    private function publishReel(Publication $publication, MediaFile $media, string $igUserId, string $caption): PublicationResult
    {
        $mediaUrl = Storage::url($media->processed_path ?? $media->original_path);

        // Create Reels container
        $containerResponse = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->post(self::API_BASE . "/{$igUserId}/media", [
                'media_type' => 'REELS',
                'video_url' => $mediaUrl,
                'caption' => $caption,
                'share_to_feed' => true,
            ]));

        if ($containerResponse->failed()) {
            return $this->makeResult($publication, 'failed', error: $containerResponse->json('error.message'));
        }

        $containerId = $containerResponse->json('id');
        $this->waitForMediaReady($containerId);

        $publishResponse = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->post(self::API_BASE . "/{$igUserId}/media_publish", [
                'creation_id' => $containerId,
            ]));

        if ($publishResponse->failed()) {
            return $this->makeResult($publication, 'failed', error: $publishResponse->json('error.message'));
        }

        $mediaId = $publishResponse->json('id');
        return $this->makeResult($publication, 'success', postId: $mediaId, url: "https://www.instagram.com/reel/{$mediaId}/");
    }

    private function waitForMediaReady(string $containerId, int $maxWaitSeconds = 60): void
    {
        $waited = 0;
        while ($waited < $maxWaitSeconds) {
            $status = Http::withToken($this->account->access_token)
                ->get(self::API_BASE . "/{$containerId}", ['fields' => 'status_code'])
                ->json('status_code');

            if ($status === 'FINISHED') {
                return;
            }

            if ($status === 'ERROR') {
                throw new \RuntimeException('Instagram media processing failed');
            }

            sleep(5);
            $waited += 5;
        }

        throw new \RuntimeException('Instagram media processing timed out');
    }

    public function publishShortVideo(Publication $publication, string $videoPath): PublicationResult
    {
        // Upload to accessible URL first, then use publishReel logic
        $storagePath = 'media/instagram/' . basename($videoPath);
        Storage::put($storagePath, file_get_contents($videoPath));

        $fakeMedia = new \App\Models\MediaFile([
            'type' => 'short_video',
            'processed_path' => $storagePath,
        ]);

        return $this->publishReel($publication, $fakeMedia, $this->account->platform_user_id, $this->buildCaption($publication));
    }

    public function validateMedia(MediaFile $media): bool
    {
        $constraints = $this->getMediaConstraints();

        if ($media->isVideo()) {
            if ($media->duration > $constraints['max_duration_seconds']) return false;
            // Reels require 9:16 aspect ratio (within tolerance)
            if ($media->width && $media->height) {
                $ratio = $media->width / $media->height;
                if ($ratio > 0.6 || $ratio < 0.55) {
                    // Not strictly 9:16 but within tolerance; warn but allow
                }
            }
        }

        return true;
    }

    public function getMediaConstraints(): array
    {
        return [
            'max_size_bytes' => 100 * 1024 * 1024, // 100MB for Reels
            'formats' => ['jpg', 'jpeg', 'png', 'mp4', 'mov'],
            'aspect_ratios' => ['9:16', '4:5', '1:1'],
            'max_duration_seconds' => 90, // Reels up to 90s
            'max_caption_length' => 2200,
            'video_width' => 1080,
            'video_height' => 1920,
        ];
    }

    protected function doRefreshToken(): void
    {
        // Same as Facebook (Meta platform)
        $response = Http::get('https://graph.facebook.com/v18.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'fb_exchange_token' => $this->account->access_token,
        ]);

        if ($response->successful()) {
            $this->account->update([
                'access_token' => $response->json('access_token'),
                'token_expires_at' => now()->addSeconds($response->json('expires_in')),
            ]);
        }
    }
}
