<?php

namespace App\Services\SocialPublisher;

use App\Models\MediaFile;
use App\Models\Publication;
use App\Models\PublicationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class YoutubePublisher extends AbstractPublisher
{
    private const API_BASE = 'https://www.googleapis.com/youtube/v3';
    private const UPLOAD_BASE = 'https://www.googleapis.com/upload/youtube/v3';

    public function publish(Publication $publication, ?MediaFile $media): PublicationResult
    {
        try {
            $this->refreshTokenIfNeeded();

            if (!$media || !$media->isVideo()) {
                return $this->makeResult($publication, 'failed', error: 'YouTube requires a video file');
            }

            return $this->uploadVideo($publication, $media);
        } catch (\Exception $e) {
            return $this->makeResult($publication, 'failed', error: $e->getMessage());
        }
    }

    private function uploadVideo(Publication $publication, MediaFile $media): PublicationResult
    {
        $filePath = Storage::path($media->processed_path ?? $media->original_path);
        $fileSize = filesize($filePath);
        $caption = $this->buildCaption($publication);

        $isShort = ($media->duration ?? 999) <= 60 &&
                   ($media->width ?? 0) < ($media->height ?? 0); // 9:16 ratio

        $metadata = [
            'snippet' => [
                'title' => substr($caption, 0, 100) ?: 'New Video',
                'description' => $caption,
                'tags' => $publication->hashtags ?? [],
                'categoryId' => '22', // People & Blogs
            ],
            'status' => [
                'privacyStatus' => 'public',
                'selfDeclaredMadeForKids' => false,
            ],
        ];

        // Step 1: Initiate resumable upload
        $initResponse = Http::withToken($this->account->access_token)
            ->withHeaders([
                'X-Upload-Content-Type' => 'video/mp4',
                'X-Upload-Content-Length' => $fileSize,
            ])
            ->post(self::UPLOAD_BASE . '/videos?uploadType=resumable&part=snippet,status', $metadata);

        if ($initResponse->failed()) {
            return $this->makeResult($publication, 'failed', error: $initResponse->json('error.message'));
        }

        $uploadUrl = $initResponse->header('Location');

        // Step 2: Upload video in chunks
        $videoId = $this->uploadChunked($filePath, $uploadUrl, $fileSize);

        if (!$videoId) {
            return $this->makeResult($publication, 'failed', error: 'YouTube upload failed');
        }

        $url = $isShort
            ? "https://www.youtube.com/shorts/{$videoId}"
            : "https://www.youtube.com/watch?v={$videoId}";

        return $this->makeResult($publication, 'success', postId: $videoId, url: $url);
    }

    private function uploadChunked(string $filePath, string $uploadUrl, int $fileSize): ?string
    {
        $chunkSize = 8 * 1024 * 1024; // 8MB chunks
        $handle = fopen($filePath, 'rb');
        $offset = 0;
        $videoId = null;

        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            $chunkLength = strlen($chunk);
            $end = $offset + $chunkLength - 1;

            $response = Http::withToken($this->account->access_token)
                ->withHeaders([
                    'Content-Range' => "bytes {$offset}-{$end}/{$fileSize}",
                    'Content-Type' => 'video/mp4',
                ])
                ->withBody($chunk, 'video/mp4')
                ->put($uploadUrl);

            if ($response->status() === 200 || $response->status() === 201) {
                $videoId = $response->json('id');
            }

            $offset += $chunkLength;
        }

        fclose($handle);
        return $videoId;
    }

    public function publishShortVideo(Publication $publication, string $videoPath): PublicationResult
    {
        $tempMedia = new MediaFile([
            'type' => 'short_video',
            'original_path' => $videoPath,
            'processed_path' => $videoPath,
            'duration' => 30,
            'width' => 1080,
            'height' => 1920,
        ]);

        return $this->publish($publication, $tempMedia);
    }

    public function validateMedia(MediaFile $media): bool
    {
        $constraints = $this->getMediaConstraints();
        return $media->isVideo() && ($media->duration ?? 0) <= $constraints['max_duration_seconds'];
    }

    public function getMediaConstraints(): array
    {
        return [
            'max_size_bytes' => 256 * 1024 * 1024 * 1024, // 256GB
            'formats' => ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm'],
            'aspect_ratios' => ['16:9', '9:16', '1:1'],
            'max_duration_seconds' => 43200, // 12 hours
            'shorts_max_duration_seconds' => 60,
            'max_caption_length' => 5000,
            'video_width' => 1080,
            'video_height' => 1920,
        ];
    }

    protected function doRefreshToken(): void
    {
        $response = Http::post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $this->account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->successful()) {
            $this->account->update([
                'access_token' => $response->json('access_token'),
                'token_expires_at' => now()->addSeconds($response->json('expires_in')),
            ]);
        }
    }
}
