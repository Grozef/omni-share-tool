<?php

namespace App\Services\SocialPublisher;

use App\Models\MediaFile;
use App\Models\Publication;
use App\Models\PublicationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SnapchatPublisher extends AbstractPublisher
{
    private const API_BASE = 'https://adsapi.snapchat.com/v1';
    private const STORY_API = 'https://kit.snapchat.com/v1';

    public function publish(Publication $publication, ?MediaFile $media): PublicationResult
    {
        try {
            $this->refreshTokenIfNeeded();

            if (!$media) {
                return $this->makeResult($publication, 'failed', error: 'Snapchat requires media');
            }

            if ($media->isVideo()) {
                return $this->publishSpotlight($publication, $media);
            }

            return $this->publishStory($publication, $media);
        } catch (\Exception $e) {
            return $this->makeResult($publication, 'failed', error: $e->getMessage());
        }
    }

    private function publishSpotlight(Publication $publication, MediaFile $media): PublicationResult
    {
        $filePath = Storage::path($media->processed_path ?? $media->original_path);
        $caption = substr($this->buildCaption($publication), 0, 250);

        // Upload media to Snap Business API
        $uploadResponse = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post(self::API_BASE . '/media/upload', [
                'media_type' => 'VIDEO',
            ]));

        if ($uploadResponse->failed()) {
            return $this->makeResult($publication, 'failed', error: $uploadResponse->json('request_status'));
        }

        $mediaId = $uploadResponse->json('media.id');

        // Post to Spotlight
        $postResponse = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->post(self::STORY_API . '/me/stories/spotlight', [
                'caption_text' => $caption,
                'video_media_id' => $mediaId,
            ]));

        if ($postResponse->failed()) {
            return $this->makeResult($publication, 'failed', error: 'Spotlight post failed');
        }

        return $this->makeResult($publication, 'success', postId: $postResponse->json('story.id'));
    }

    private function publishStory(Publication $publication, MediaFile $media): PublicationResult
    {
        $filePath = Storage::path($media->processed_path ?? $media->original_path);

        $uploadResponse = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post(self::API_BASE . '/media/upload', [
                'media_type' => 'IMAGE',
            ]));

        if ($uploadResponse->failed()) {
            return $this->makeResult($publication, 'failed', error: 'Snap media upload failed');
        }

        $mediaId = $uploadResponse->json('media.id');

        $postResponse = Http::withToken($this->account->access_token)
            ->post(self::STORY_API . '/me/stories', [
                'image_media_id' => $mediaId,
                'caption_text' => substr($this->buildCaption($publication), 0, 250),
            ]);

        if ($postResponse->failed()) {
            return $this->makeResult($publication, 'failed', error: 'Story post failed');
        }

        return $this->makeResult($publication, 'success', postId: $postResponse->json('story.id'));
    }

    public function publishShortVideo(Publication $publication, string $videoPath): PublicationResult
    {
        $tempMedia = new MediaFile([
            'type' => 'short_video',
            'original_path' => $videoPath,
            'processed_path' => $videoPath,
        ]);

        return $this->publishSpotlight($publication, $tempMedia);
    }

    public function validateMedia(MediaFile $media): bool
    {
        $constraints = $this->getMediaConstraints();

        if ($media->isVideo() && $media->duration > $constraints['max_duration_seconds']) {
            return false;
        }

        return true;
    }

    public function getMediaConstraints(): array
    {
        return [
            'max_size_bytes' => 32 * 1024 * 1024, // 32MB
            'formats' => ['jpg', 'jpeg', 'png', 'mp4'],
            'aspect_ratios' => ['9:16'],
            'max_duration_seconds' => 60,
            'max_caption_length' => 250,
            'video_width' => 1080,
            'video_height' => 1920,
        ];
    }

    protected function doRefreshToken(): void
    {
        $response = Http::withBasicAuth(
            config('services.snapchat.client_id'),
            config('services.snapchat.client_secret')
        )->post('https://accounts.snapchat.com/login/oauth2/access_token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->account->refresh_token,
        ]);

        if ($response->successful()) {
            $this->account->update([
                'access_token' => $response->json('access_token'),
                'refresh_token' => $response->json('refresh_token'),
                'token_expires_at' => now()->addSeconds($response->json('expires_in')),
            ]);
        }
    }
}
