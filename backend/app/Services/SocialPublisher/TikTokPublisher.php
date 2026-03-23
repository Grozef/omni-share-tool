<?php

namespace App\Services\SocialPublisher;

use App\Models\MediaFile;
use App\Models\Publication;
use App\Models\PublicationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TikTokPublisher extends AbstractPublisher
{
    private const API_BASE = 'https://open.tiktokapis.com/v2';

    public function publish(Publication $publication, ?MediaFile $media): PublicationResult
    {
        try {
            $this->refreshTokenIfNeeded();

            if (!$media || !$media->isVideo()) {
                return $this->makeResult($publication, 'failed', error: 'TikTok requires a video file');
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
        $caption = substr($this->buildCaption($publication), 0, 2200);

        // Step 1: Initialize upload
        $initResponse = $this->retryRequest(fn() => Http::withToken($this->account->access_token)
            ->post(self::API_BASE . '/post/publish/video/init/', [
                'post_info' => [
                    'title' => $caption,
                    'privacy_level' => 'PUBLIC_TO_EVERYONE',
                    'disable_duet' => false,
                    'disable_comment' => false,
                    'disable_stitch' => false,
                ],
                'source_info' => [
                    'source' => 'FILE_UPLOAD',
                    'video_size' => $fileSize,
                    'chunk_size' => min($fileSize, 64 * 1024 * 1024), // 64MB chunks
                    'total_chunk_count' => (int) ceil($fileSize / (64 * 1024 * 1024)),
                ],
            ]));

        if ($initResponse->failed()) {
            return $this->makeResult($publication, 'failed', error: $initResponse->json('error.message'));
        }

        $publishId = $initResponse->json('data.publish_id');
        $uploadUrl = $initResponse->json('data.upload_url');

        // Step 2: Upload video chunks
        $this->uploadChunks($filePath, $uploadUrl, $fileSize);

        // Step 3: Poll for completion
        return $this->pollPublishStatus($publication, $publishId);
    }

    private function uploadChunks(string $filePath, string $uploadUrl, int $fileSize): void
    {
        $chunkSize = 64 * 1024 * 1024; // 64MB
        $handle = fopen($filePath, 'rb');
        $offset = 0;

        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            $chunkLength = strlen($chunk);
            $end = $offset + $chunkLength - 1;

            Http::withHeaders([
                'Content-Range' => "bytes {$offset}-{$end}/{$fileSize}",
                'Content-Type' => 'video/mp4',
            ])->withBody($chunk, 'video/mp4')->put($uploadUrl);

            $offset += $chunkLength;
        }

        fclose($handle);
    }

    private function pollPublishStatus(Publication $publication, string $publishId): PublicationResult
    {
        $maxWait = 120;
        $waited = 0;

        while ($waited < $maxWait) {
            $statusResponse = Http::withToken($this->account->access_token)
                ->post(self::API_BASE . '/post/publish/status/fetch/', [
                    'publish_id' => $publishId,
                ]);

            $status = $statusResponse->json('data.status');

            if ($status === 'PUBLISH_COMPLETE') {
                return $this->makeResult($publication, 'success', postId: $publishId);
            }

            if ($status === 'FAILED') {
                return $this->makeResult($publication, 'failed', error: $statusResponse->json('data.fail_reason'));
            }

            sleep(5);
            $waited += 5;
        }

        return $this->makeResult($publication, 'failed', error: 'TikTok publish timed out');
    }

    public function publishShortVideo(Publication $publication, string $videoPath): PublicationResult
    {
        $tempMedia = new MediaFile([
            'type' => 'short_video',
            'original_path' => $videoPath,
            'processed_path' => $videoPath,
        ]);

        return $this->publish($publication, $tempMedia);
    }

    public function validateMedia(MediaFile $media): bool
    {
        $constraints = $this->getMediaConstraints();

        if (!$media->isVideo()) return false;
        if ($media->duration > $constraints['max_duration_seconds']) return false;

        return true;
    }

    public function getMediaConstraints(): array
    {
        return [
            'max_size_bytes' => 4 * 1024 * 1024 * 1024, // 4GB
            'formats' => ['mp4', 'webm', 'mov'],
            'aspect_ratios' => ['9:16'],
            'max_duration_seconds' => 600, // 10 minutes
            'max_caption_length' => 2200,
            'video_width' => 1080,
            'video_height' => 1920,
        ];
    }

    protected function doRefreshToken(): void
    {
        $response = Http::post(self::API_BASE . '/oauth/token/', [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
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
