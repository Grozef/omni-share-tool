<?php

namespace App\Services\SocialPublisher;

use App\Models\MediaFile;
use App\Models\PlatformAccount;
use App\Models\Publication;
use App\Models\PublicationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractPublisher implements PublisherInterface
{
    protected PlatformAccount $account;

    public function __construct(PlatformAccount $account)
    {
        $this->account = $account;
    }

    abstract public function publish(Publication $publication, ?MediaFile $media): PublicationResult;

    abstract public function publishShortVideo(Publication $publication, string $videoPath): PublicationResult;

    abstract public function validateMedia(MediaFile $media): bool;

    abstract public function getMediaConstraints(): array;

    public function refreshTokenIfNeeded(): void
    {
        if (!$this->account->isTokenExpired()) {
            return;
        }

        if (!$this->account->refresh_token) {
            throw new \RuntimeException("Token expired and no refresh token available for {$this->account->platform}");
        }

        $this->doRefreshToken();
    }

    protected function doRefreshToken(): void
    {
        // Override in subclass if platform supports token refresh
        throw new \RuntimeException("Token refresh not implemented for {$this->account->platform}");
    }

    protected function makeResult(
        Publication $publication,
        string $status,
        ?string $postId = null,
        ?string $url = null,
        ?string $error = null
    ): PublicationResult {
        return PublicationResult::updateOrCreate(
            [
                'publication_id' => $publication->id,
                'platform' => $this->account->platform,
            ],
            [
                'status' => $status,
                'platform_post_id' => $postId,
                'platform_url' => $url,
                'error_message' => $error,
                'published_at' => $status === 'success' ? now() : null,
            ]
        );
    }

    protected function buildCaption(Publication $publication): string
    {
        $caption = $publication->caption ?? '';
        $hashtags = $publication->hashtags ?? [];

        if (!empty($hashtags)) {
            $tagString = implode(' ', array_map(
                fn($tag) => str_starts_with($tag, '#') ? $tag : "#{$tag}",
                $hashtags
            ));
            $caption = trim($caption . "\n\n" . $tagString);
        }

        return $caption;
    }

    protected function retryRequest(callable $fn, int $maxRetries = 3): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $fn();
            } catch (\Exception $e) {
                $lastException = $e;
                $delay = pow(2, $attempt) * 1000; // exponential backoff in ms
                usleep($delay * 1000);
                $attempt++;
                Log::warning("Publisher retry {$attempt}/{$maxRetries}: " . $e->getMessage());
            }
        }

        throw $lastException;
    }
}
