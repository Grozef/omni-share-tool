<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMediaJob;
use App\Models\MediaFile;
use App\Models\Publication;
use App\Services\MediaProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function __construct(private MediaProcessingService $mediaService) {}

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'           => 'required|file|max:2097152', // 2GB max
            'publication_id' => 'nullable|exists:publications,id',
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType();
        $type = str_starts_with($mime, 'video/') ? 'video' : 'image';

        // Chunked upload support
        $chunkIndex = $request->input('chunk_index');
        $totalChunks = $request->input('total_chunks');
        $uploadId = $request->input('upload_id', Str::uuid());

        if ($totalChunks && $chunkIndex !== null) {
            return $this->handleChunkedUpload($request, $uploadId, (int) $chunkIndex, (int) $totalChunks, $type);
        }

        $path = $file->store('media/originals', 'public');

        $media = MediaFile::create([
            'publication_id' => $request->input('publication_id'),
            'type'           => $type,
            'original_path'  => $path,
        ]);

        if ($type === 'video') {
            ProcessMediaJob::dispatch($media);
        }

        return response()->json($media->load([]), 201);
    }

    private function handleChunkedUpload(
        Request $request,
        string $uploadId,
        int $chunkIndex,
        int $totalChunks,
        string $type
    ): JsonResponse {
        $chunkPath = "media/chunks/{$uploadId}/{$chunkIndex}";
        Storage::put($chunkPath, $request->file('file')->get());

        if ($chunkIndex + 1 < $totalChunks) {
            return response()->json(['upload_id' => $uploadId, 'chunk' => $chunkIndex, 'status' => 'partial']);
        }

        // All chunks received — assemble
        $ext = $request->file('file')->getClientOriginalExtension();
        $finalPath = "media/originals/{$uploadId}.{$ext}";
        $finalAbsolute = Storage::path($finalPath);

        $out = fopen($finalAbsolute, 'wb');
        for ($i = 0; $i < $totalChunks; $i++) {
            fwrite($out, Storage::get("media/chunks/{$uploadId}/{$i}"));
        }
        fclose($out);

        // Cleanup chunks
        Storage::deleteDirectory("media/chunks/{$uploadId}");

        $media = MediaFile::create([
            'publication_id' => $request->input('publication_id'),
            'type'           => $type,
            'original_path'  => $finalPath,
        ]);

        if ($type === 'video') {
            ProcessMediaJob::dispatch($media);
        }

        return response()->json($media, 201);
    }

    public function convertToVideo(Request $request, int $id): JsonResponse
    {
        $media = MediaFile::findOrFail($id);

        $request->validate([
            'duration'    => 'nullable|integer|in:15,30,60',
            'platform'    => 'nullable|string',
            'audio_path'  => 'nullable|string',
        ]);

        if (!$media->isImage()) {
            return response()->json(['error' => 'Media is not an image'], 422);
        }

        ProcessMediaJob::dispatch($media, [
            'convert_to_video' => true,
            'duration'         => $request->input('duration', 15),
            'platform'         => $request->input('platform', 'instagram'),
            'audio_path'       => $request->input('audio_path'),
        ]);

        return response()->json(['message' => 'Video conversion queued', 'media_id' => $media->id]);
    }

    public function preview(int $id): JsonResponse
    {
        $media = MediaFile::findOrFail($id);

        return response()->json([
            'id'            => $media->id,
            'type'          => $media->type,
            'original_url'  => $media->original_url,
            'thumbnail_url' => $media->thumbnail_url,
            'processed_url' => $media->processed_path ? Storage::url($media->processed_path) : null,
            'width'         => $media->width,
            'height'        => $media->height,
            'duration'      => $media->duration,
        ]);
    }
}
