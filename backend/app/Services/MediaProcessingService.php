<?php

namespace App\Services;

use FFMpeg\Coordinate\AspectRatio;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Filters\Video\ResizeFilter;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaProcessingService
{
    private FFMpeg $ffmpeg;
    private FFProbe $ffprobe;

    private const PLATFORM_SPECS = [
        'instagram' => ['width' => 1080, 'height' => 1920, 'aspect' => '9:16', 'max_bitrate' => 3500],
        'tiktok'    => ['width' => 1080, 'height' => 1920, 'aspect' => '9:16', 'max_bitrate' => 2000],
        'youtube'   => ['width' => 1080, 'height' => 1920, 'aspect' => '9:16', 'max_bitrate' => 8000],
        'snapchat'  => ['width' => 1080, 'height' => 1920, 'aspect' => '9:16', 'max_bitrate' => 1500],
        'facebook'  => ['width' => 1280, 'height' => 720,  'aspect' => '16:9', 'max_bitrate' => 4000],
        'pinterest' => ['width' => 1080, 'height' => 1620, 'aspect' => '2:3',  'max_bitrate' => 2000],
    ];

    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries'  => config('media.ffmpeg_binaries', '/usr/bin/ffmpeg'),
            'ffprobe.binaries' => config('media.ffprobe_binaries', '/usr/bin/ffprobe'),
            'timeout'          => 3600,
            'ffmpeg.threads'   => 4,
        ]);

        $this->ffprobe = FFProbe::create([
            'ffprobe.binaries' => config('media.ffprobe_binaries', '/usr/bin/ffprobe'),
        ]);
    }

    /**
     * Resize/crop a video or image for a specific platform.
     */
    public function processForPlatform(string $sourcePath, string $platform): string
    {
        $spec = self::PLATFORM_SPECS[$platform] ?? self::PLATFORM_SPECS['instagram'];
        $outputPath = $this->makeOutputPath($sourcePath, "_{$platform}_processed");

        $absoluteSource = Storage::path($sourcePath);
        $absoluteOutput = Storage::path($outputPath);

        $video = $this->ffmpeg->open($absoluteSource);

        $video->filters()
            ->resize(new Dimension($spec['width'], $spec['height']), ResizeFilter::RESIZEMODE_SCALE_WIDTH)
            ->synchronize();

        $format = (new X264())
            ->setKiloBitrate($spec['max_bitrate'])
            ->setAudioCodec('aac')
            ->setAudioKiloBitrate(128);

        $video->save($format, $absoluteOutput);

        return $outputPath;
    }

    /**
     * Apply Ken Burns effect on an image to create a short video.
     */
    public function imageToShortVideo(string $imagePath, array $options = []): string
    {
        $duration = $options['duration'] ?? 15;
        $outputPath = $this->makeOutputPath($imagePath, '_short_video', 'mp4');
        $absoluteSource = Storage::path($imagePath);
        $absoluteOutput = Storage::path($outputPath);

        // Ken Burns: slow pan+zoom using FFmpeg zoompan filter
        $zoomSpeed = 0.0015;
        $fps = 25;
        $totalFrames = $duration * $fps;

        $this->runFFmpegCommand([
            '-loop', '1',
            '-i', $absoluteSource,
            '-vf', implode(',', [
                "scale=8000:-1",
                "zoompan=z='min(zoom+{$zoomSpeed},1.5)':d={$totalFrames}:x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':s=1080x1920:fps={$fps}",
                "scale=1080:1920:force_original_aspect_ratio=decrease",
                "pad=1080:1920:(ow-iw)/2:(oh-ih)/2",
            ]),
            '-c:v', 'libx264',
            '-t', (string) $duration,
            '-pix_fmt', 'yuv420p',
            '-r', (string) $fps,
            $absoluteOutput,
        ]);

        return $outputPath;
    }

    /**
     * Create a slideshow video from multiple images.
     */
    public function imagesToSlideshow(array $imagePaths, array $options = []): string
    {
        $duration = $options['duration'] ?? 30;
        $perImage = $duration / count($imagePaths);
        $fps = 25;
        $outputPath = 'media/processed/' . Str::uuid() . '_slideshow.mp4';
        $absoluteOutput = Storage::path($outputPath);

        // Build concat input
        $inputArgs = [];
        $filterParts = [];
        $absolutePaths = array_map(fn($p) => Storage::path($p), $imagePaths);

        foreach ($absolutePaths as $i => $path) {
            $inputArgs[] = '-loop';
            $inputArgs[] = '1';
            $inputArgs[] = '-t';
            $inputArgs[] = (string) $perImage;
            $inputArgs[] = '-i';
            $inputArgs[] = $path;

            $filterParts[] = "[{$i}:v]scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2,setsar=1,fade=t=in:st=0:d=0.5,fade=t=out:st=" . ($perImage - 0.5) . ":d=0.5[v{$i}]";
        }

        $concatInputs = implode('', array_map(fn($i) => "[v{$i}]", range(0, count($imagePaths) - 1)));
        $filterComplex = implode(';', $filterParts) . ";{$concatInputs}concat=n=" . count($imagePaths) . ":v=1:a=0[outv]";

        $this->runFFmpegCommand(array_merge(
            $inputArgs,
            [
                '-filter_complex', $filterComplex,
                '-map', '[outv]',
                '-c:v', 'libx264',
                '-pix_fmt', 'yuv420p',
                '-r', (string) $fps,
                $absoluteOutput,
            ]
        ));

        return $outputPath;
    }

    /**
     * Optimize an existing video for a specific platform.
     */
    public function optimizeVideoForPlatform(string $videoPath, string $platform): string
    {
        $spec = self::PLATFORM_SPECS[$platform] ?? self::PLATFORM_SPECS['instagram'];
        $outputPath = $this->makeOutputPath($videoPath, "_{$platform}_opt");
        $absoluteSource = Storage::path($videoPath);
        $absoluteOutput = Storage::path($outputPath);

        // Crop to target aspect ratio, then scale
        [$targetW, $targetH] = [$spec['width'], $spec['height']];

        $this->runFFmpegCommand([
            '-i', $absoluteSource,
            '-vf', implode(',', [
                "scale={$targetW}:{$targetH}:force_original_aspect_ratio=increase",
                "crop={$targetW}:{$targetH}",
            ]),
            '-c:v', 'libx264',
            '-b:v', $spec['max_bitrate'] . 'k',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            $absoluteOutput,
        ]);

        return $outputPath;
    }

    /**
     * Add a watermark/text overlay to a video.
     */
    public function addWatermark(string $videoPath, string $text): string
    {
        $outputPath = $this->makeOutputPath($videoPath, '_watermarked');
        $absoluteSource = Storage::path($videoPath);
        $absoluteOutput = Storage::path($outputPath);

        $this->runFFmpegCommand([
            '-i', $absoluteSource,
            '-vf', "drawtext=text='{$text}':fontcolor=white:fontsize=24:x=20:y=20:shadowcolor=black:shadowx=2:shadowy=2",
            '-codec:a', 'copy',
            $absoluteOutput,
        ]);

        return $outputPath;
    }

    /**
     * Generate a thumbnail from a video at a given time.
     */
    public function generateThumbnail(string $videoPath, float $timeSeconds = 1.0): string
    {
        $outputPath = $this->makeOutputPath($videoPath, '_thumb', 'jpg');
        $absoluteSource = Storage::path($videoPath);
        $absoluteOutput = Storage::path($outputPath);

        $this->runFFmpegCommand([
            '-i', $absoluteSource,
            '-ss', (string) $timeSeconds,
            '-vframes', '1',
            '-q:v', '2',
            $absoluteOutput,
        ]);

        return $outputPath;
    }

    /**
     * Get video metadata (duration, width, height).
     */
    public function getVideoInfo(string $videoPath): array
    {
        $absolutePath = Storage::path($videoPath);
        $streams = $this->ffprobe->streams($absolutePath)->videos()->first();

        return [
            'duration' => (int) $this->ffprobe->format($absolutePath)->get('duration'),
            'width'    => $streams?->get('width'),
            'height'   => $streams?->get('height'),
        ];
    }

    private function makeOutputPath(string $sourcePath, string $suffix, string $ext = null): string
    {
        $info = pathinfo($sourcePath);
        $extension = $ext ?? $info['extension'];
        return 'media/processed/' . $info['filename'] . $suffix . '.' . $extension;
    }

    private function runFFmpegCommand(array $args): void
    {
        $binary = config('media.ffmpeg_binaries', '/usr/bin/ffmpeg');
        $cmd = array_merge([$binary, '-y'], $args);

        $process = new \Symfony\Component\Process\Process($cmd);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('FFmpeg error: ' . $process->getErrorOutput());
        }
    }
}
