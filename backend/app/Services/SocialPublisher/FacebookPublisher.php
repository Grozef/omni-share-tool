<?php

namespace App\Services\SocialPublisher;

use App\Models\MediaFile;
use App\Models\Publication;
use App\Models\PublicationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FacebookPublisher extends AbstractPublisher
{
    private const API_BASE = 'https://graph.facebook.com/v18.0';
    private const RATE_LIMIT_PER_HOUR = 200;

    public function publish(Publication $publication, ?MediaFile $media): PublicationResult
    {
        try {
            $this->refreshTokenIfNeeded();
            $pageId = $this->account->platform_user_id;
            $caption = $this->buildCaption($publication);

            if ($media?->isVideo()) {
                return $this->publishVideo($publication, $media, $pageId, $caption);
            }

            if ($media?->isImage()) {
                return $this->publishPhoto($publication, $media, $pageId, $caption);
            }

            // Text-only post
            $response = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
                ->post(self::API_BASE . "/{$pageId}/feed", [
                    'message' => $caption,
                ]));

            if ($response->failed()) {
                return $this->makeResult($publication, 'failed', error: $response->json('error.message'));
            }

            $postId = $response->json('id');
            return $this->makeResult(
                $publication, 'success',
                postId: $postId,
                url: "https://www.facebook.com/{$postId}"
            );
        } catch (\Exception $e) {
            return $this->makeResult($publication, 'failed', error: $e->getMessage());
        }
    }

    private function publishPhoto(Publication $publication, MediaFile $media, string $pageId, string $caption): PublicationResult
    {
        $filePath = Storage::path($media->processed_path ?? $media->original_path);

        $response = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->attach('source', fopen($filePath, 'r'), basename($filePath))
            ->post(self::API_BASE . "/{$pageId}/photos", [
                'caption' => $caption,
            ]));

        if ($response->failed()) {
            return $this->makeResult($publication, 'failed', error: $response->json('error.message'));
        }

        $postId = $response->json('post_id') ?? $response->json('id');
        return $this->makeResult($publication, 'success', postId: $postId, url: "https://www.facebook.com/{$postId}");
    }

    private function publishVideo(Publication $publication, MediaFile $media, string $pageId, string $caption): PublicationResult
    {
        $filePath = Storage::path($media->processed_path ?? $media->original_path);

        // Facebook requires chunked upload for videos > 1GB; simple upload for smaller
        $response = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->attach('source', fopen($filePath, 'r'), basename($filePath))
            ->post(self::API_BASE . "/{$pageId}/videos", [
                'description' => $caption,
                'title' => substr($caption, 0, 100),
            ]));

        if ($response->failed()) {
            return $this->makeResult($publication, 'failed', error: $response->json('error.message'));
        }

        $videoId = $response->json('id');
        return $this->makeResult($publication, 'success', postId: $videoId, url: "https://www.facebook.com/video/{$videoId}");
    }

    public function publishShortVideo(Publication $publication, string $videoPath): PublicationResult
    {
        // Facebook Reels API
        try {
            $this->refreshTokenIfNeeded();
            $pageId = $this->account->platform_user_id;

            // Step 1: Initialize upload session
            $initResponse = Http::withToken($this->account->access_token)
                ->post(self::API_BASE . "/{$pageId}/video_reels", [
                    'upload_phase' => 'start',
                ]);

            if ($initResponse->failed()) {
                return $this->makeResult($publication, 'failed', error: $initResponse->json('error.message'));
            }

            $videoId = $initResponse->json('video_id');
            $uploadUrl = $initResponse->json('upload_url');

            // Step 2: Upload video
            $uploadResponse = Http::withToken($this->account->access_token)
                ->attach('video_file_chunk', fopen($videoPath, 'r'), basename($videoPath))
                ->post($uploadUrl);

            if ($uploadResponse->failed()) {
                return $this->makeResult($publication, 'failed', error: 'Video upload failed');
            }

            // Step 3: Publish reel
            $publishResponse = Http::withToken($this->account->access_token)
                ->post(self::API_BASE . "/{$pageId}/video_reels", [
                    'video_id' => $videoId,
                    'upload_phase' => 'finish',
                    'video_state' => 'PUBLISHED',
                    'description' => $this->buildCaption($publication),
                ]);

            if ($publishResponse->failed()) {
                return $this->makeResult($publication, 'failed', error: $publishResponse->json('error.message'));
            }

            return $this->makeResult($publication, 'success', postId: $videoId);
        } catch (\Exception $e) {
            return $this->makeResult($publication, 'failed', error: $e->getMessage());
        }
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
            'max_size_bytes' => 4 * 1024 * 1024 * 1024, // 4GB for video
            'formats' => ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov'],
            'aspect_ratios' => ['1:1', '4:5', '16:9', '9:16'],
            'max_duration_seconds' => 14400, // 4 hours
            'max_caption_length' => 63206,
            'video_width' => 1280,
            'video_height' => 720,
        ];
    }

    protected function doRefreshToken(): void
    {
        // Meta long-lived tokens expire after 60 days; exchange for new long-lived token
        $response = Http::get(self::API_BASE . '/oauth/access_token', [
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
