<?php

namespace App\Services\SocialPublisher;

use App\Models\MediaFile;
use App\Models\Publication;
use App\Models\PublicationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PinterestPublisher extends AbstractPublisher
{
    private const API_BASE = 'https://api.pinterest.com/v5';

    public function publish(Publication $publication, ?MediaFile $media): PublicationResult
    {
        try {
            $this->refreshTokenIfNeeded();
            $caption = $this->buildCaption($publication);

            if ($media?->isVideo()) {
                return $this->publishVideoPin($publication, $media, $caption);
            }

            return $this->publishImagePin($publication, $media, $caption);
        } catch (\Exception $e) {
            return $this->makeResult($publication, 'failed', error: $e->getMessage());
        }
    }

    private function publishImagePin(Publication $publication, ?MediaFile $media, string $caption): PublicationResult
    {
        $payload = [
            'title' => substr($caption, 0, 100),
            'description' => $caption,
            'board_id' => $this->account->platform_user_id, // stored as default board ID
        ];

        if ($media) {
            $mediaUrl = Storage::url($media->processed_path ?? $media->original_path);
            $payload['media_source'] = [
                'source_type' => 'image_url',
                'url' => $mediaUrl,
            ];
        }

        $response = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->post(self::API_BASE . '/pins', $payload));

        if ($response->failed()) {
            return $this->makeResult($publication, 'failed', error: $response->json('message'));
        }

        $pinId = $response->json('id');
        return $this->makeResult(
            $publication, 'success',
            postId: $pinId,
            url: "https://www.pinterest.com/pin/{$pinId}/"
        );
    }

    private function publishVideoPin(Publication $publication, MediaFile $media, string $caption): PublicationResult
    {
        $filePath = Storage::path($media->processed_path ?? $media->original_path);
        $fileSize = filesize($filePath);

        // Step 1: Register video upload
        $registerResponse = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->post(self::API_BASE . '/media', [
                'media_type' => 'video',
            ]));

        if ($registerResponse->failed()) {
            return $this->makeResult($publication, 'failed', error: $registerResponse->json('message'));
        }

        $mediaId = $registerResponse->json('media_id');
        $uploadUrl = $registerResponse->json('upload_url');
        $uploadParameters = $registerResponse->json('upload_parameters', []);

        // Step 2: Upload to S3 pre-signed URL
        $multipart = [];
        foreach ($uploadParameters as $key => $value) {
            $multipart[] = ['name' => $key, 'contents' => $value];
        }
        $multipart[] = ['name' => 'file', 'contents' => fopen($filePath, 'r'), 'filename' => basename($filePath)];

        Http::asMultipart()->post($uploadUrl, $multipart);

        // Step 3: Create pin with video
        $response = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->post(self::API_BASE . '/pins', [
                'title' => substr($caption, 0, 100),
                'description' => $caption,
                'board_id' => $this->account->platform_user_id,
                'media_source' => [
                    'source_type' => 'video_id',
                    'cover_image_url' => Storage::url($media->thumbnail_path ?? $media->original_path),
                    'media_id' => $mediaId,
                ],
            ]));

        if ($response->failed()) {
            return $this->makeResult($publication, 'failed', error: $response->json('message'));
        }

        $pinId = $response->json('id');
        return $this->makeResult($publication, 'success', postId: $pinId, url: "https://www.pinterest.com/pin/{$pinId}/");
    }

    public function publishShortVideo(Publication $publication, string $videoPath): PublicationResult
    {
        $tempMedia = new MediaFile([
            'type' => 'short_video',
            'original_path' => $videoPath,
            'processed_path' => $videoPath,
        ]);

        return $this->publishVideoPin($publication, $tempMedia, $this->buildCaption($publication));
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
            'max_size_bytes' => 2 * 1024 * 1024 * 1024, // 2GB for video
            'formats' => ['jpg', 'jpeg', 'png', 'gif', 'mp4'],
            'aspect_ratios' => ['2:3', '1:1', '9:16'],
            'max_duration_seconds' => 900, // 15 minutes
            'max_caption_length' => 500,
            'recommended_image_ratio' => '2:3',
            'video_width' => 1080,
            'video_height' => 1920,
        ];
    }

    protected function doRefreshToken(): void
    {
        $response = Http::withBasicAuth(
            config('services.pinterest.app_id'),
            config('services.pinterest.app_secret')
        )->post(self::API_BASE . '/oauth/token', [
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
