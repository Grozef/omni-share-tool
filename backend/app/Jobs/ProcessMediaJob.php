<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Services\MediaProcessingService;
use App\Services\VideoGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 2;

    public function __construct(
        private MediaFile $media,
        private array $options = []
    ) {}

    public function handle(MediaProcessingService $mediaService, VideoGenerationService $videoService): void
    {
        if ($this->options['convert_to_video'] ?? false) {
            $this->convertImageToVideo($videoService);
            return;
        }

        if ($this->media->isVideo()) {
            $this->processVideo($mediaService);
        }
    }

    private function convertImageToVideo(VideoGenerationService $videoService): void
    {
        $result = $videoService->fromImage($this->media->original_path, [
            'duration'   => $this->options['duration'] ?? 15,
            'platform'   => $this->options['platform'] ?? 'instagram',
            'audio_path' => $this->options['audio_path'] ?? null,
        ]);

        $this->media->update([
            'type'           => 'short_video',
            'processed_path' => $result['video_path'],
            'thumbnail_path' => $result['thumbnail_path'],
            'duration'       => $result['duration'],
            'width'          => 1080,
            'height'         => 1920,
        ]);
    }

    private function processVideo(MediaProcessingService $mediaService): void
    {
        $info = $mediaService->getVideoInfo($this->media->original_path);

        $thumbnail = $mediaService->generateThumbnail($this->media->original_path, 1.0);

        $this->media->update([
            'thumbnail_path' => $thumbnail,
            'duration'       => $info['duration'],
            'width'          => $info['width'],
            'height'         => $info['height'],
        ]);
    }
}
