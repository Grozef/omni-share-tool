<?php

namespace App\Services\SocialPublisher;

use App\Models\MediaFile;
use App\Models\Publication;
use App\Models\PublicationResult;

interface PublisherInterface
{
    public function publish(Publication $publication, ?MediaFile $media): PublicationResult;

    public function publishShortVideo(Publication $publication, string $videoPath): PublicationResult;

    public function validateMedia(MediaFile $media): bool;

    /**
     * Returns platform-specific constraints.
     * Keys: max_size_bytes, formats, aspect_ratios, max_duration_seconds,
     *       max_caption_length, video_width, video_height
     */
    public function getMediaConstraints(): array;

    public function refreshTokenIfNeeded(): void;
}
