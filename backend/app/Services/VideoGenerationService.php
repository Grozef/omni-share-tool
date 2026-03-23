<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoGenerationService
{
    public function __construct(private MediaProcessingService $media) {}

    /**
     * Full pipeline: single image → 9:16 short video with Ken Burns.
     */
    public function fromImage(string $imagePath, array $options = []): array
    {
        $duration = $options['duration'] ?? 15; // 15s | 30s | 60s
        $platform = $options['platform'] ?? 'instagram';
        $audioPath = $options['audio_path'] ?? null;

        // 1. Ken Burns video
        $rawVideo = $this->media->imageToShortVideo($imagePath, ['duration' => $duration]);

        // 2. Optimize for platform
        $optimized = $this->media->optimizeVideoForPlatform($rawVideo, $platform);
        Storage::delete($rawVideo); // cleanup intermediate

        // 3. Add audio if provided
        if ($audioPath) {
            $withAudio = $this->mixAudio($optimized, $audioPath, $duration);
            Storage::delete($optimized);
            $optimized = $withAudio;
        }

        // 4. Generate thumbnail
        $thumbnail = $this->media->generateThumbnail($optimized, 1.0);

        return [
            'video_path' => $optimized,
            'thumbnail_path' => $thumbnail,
            'duration' => $duration,
        ];
    }

    /**
     * Multiple images → slideshow short video.
     */
    public function fromImages(array $imagePaths, array $options = []): array
    {
        $duration = $options['duration'] ?? 30;
        $platform = $options['platform'] ?? 'instagram';
        $audioPath = $options['audio_path'] ?? null;

        $slideshow = $this->media->imagesToSlideshow($imagePaths, ['duration' => $duration]);
        $optimized = $this->media->optimizeVideoForPlatform($slideshow, $platform);
        Storage::delete($slideshow);

        if ($audioPath) {
            $withAudio = $this->mixAudio($optimized, $audioPath, $duration);
            Storage::delete($optimized);
            $optimized = $withAudio;
        }

        $thumbnail = $this->media->generateThumbnail($optimized, 1.0);

        return [
            'video_path' => $optimized,
            'thumbnail_path' => $thumbnail,
            'duration' => $duration,
        ];
    }

    /**
     * Mix background audio into a video, trimmed to video duration.
     */
    public function mixAudio(string $videoPath, string $audioPath, int $duration): string
    {
        $outputPath = 'media/processed/' . Str::uuid() . '_with_audio.mp4';
        $absoluteVideo = Storage::path($videoPath);
        $absoluteAudio = Storage::path($audioPath);
        $absoluteOutput = Storage::path($outputPath);

        $binary = config('media.ffmpeg_binaries', '/usr/bin/ffmpeg');

        $process = new \Symfony\Component\Process\Process([
            $binary, '-y',
            '-i', $absoluteVideo,
            '-i', $absoluteAudio,
            '-map', '0:v:0',
            '-map', '1:a:0',
            '-c:v', 'copy',
            '-c:a', 'aac',
            '-t', (string) $duration,
            '-shortest',
            $absoluteOutput,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            // Return original video without audio on failure
            return $videoPath;
        }

        return $outputPath;
    }

    /**
     * Prepare a video for all selected platforms (parallel processing).
     */
    public function prepareForPlatforms(string $videoPath, array $platforms): array
    {
        $results = [];
        foreach ($platforms as $platform) {
            try {
                $results[$platform] = $this->media->optimizeVideoForPlatform($videoPath, $platform);
            } catch (\Exception $e) {
                $results[$platform] = $videoPath; // fallback to original
            }
        }

        return $results;
    }
}
