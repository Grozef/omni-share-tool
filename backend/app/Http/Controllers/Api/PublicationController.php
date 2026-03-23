<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PublishToSocialJob;
use App\Models\MediaFile;
use App\Models\Publication;
use App\Models\PublicationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'caption'      => 'nullable|string|max:65000',
            'hashtags'     => 'nullable|array',
            'hashtags.*'   => 'string|max:100',
            'platforms'    => 'required|array|min:1',
            'platforms.*'  => 'in:facebook,instagram,snapchat,tiktok,youtube,pinterest',
            'media_id'     => 'nullable|exists:media_files,id',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $publication = Publication::create([
            'user_id'      => auth()->id(),
            'caption'      => $data['caption'] ?? null,
            'hashtags'     => $data['hashtags'] ?? [],
            'platforms'    => $data['platforms'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status'       => 'pending',
        ]);

        // Attach media if provided
        if (!empty($data['media_id'])) {
            MediaFile::where('id', $data['media_id'])->update(['publication_id' => $publication->id]);
        }

        // Initialize result records
        foreach ($data['platforms'] as $platform) {
            PublicationResult::create([
                'publication_id' => $publication->id,
                'platform'       => $platform,
                'status'         => 'pending',
            ]);
        }

        // Dispatch immediately or at scheduled time
        $delay = $publication->scheduled_at
            ? now()->diffInSeconds($publication->scheduled_at)
            : 0;

        PublishToSocialJob::dispatch($publication)->delay($delay > 0 ? now()->addSeconds($delay) : null);

        return response()->json($publication->load('mediaFiles', 'results'), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $publications = auth()->user()
            ->publications()
            ->with(['mediaFiles', 'results'])
            ->latest()
            ->paginate(20);

        return response()->json($publications);
    }

    public function show(int $id): JsonResponse
    {
        $publication = auth()->user()
            ->publications()
            ->with(['mediaFiles', 'results'])
            ->findOrFail($id);

        return response()->json($publication);
    }

    public function retry(int $id): JsonResponse
    {
        $publication = auth()->user()
            ->publications()
            ->with('results')
            ->findOrFail($id);

        $failedPlatforms = $publication->results
            ->where('status', 'failed')
            ->pluck('platform')
            ->toArray();

        if (empty($failedPlatforms)) {
            return response()->json(['message' => 'No failed platforms to retry'], 422);
        }

        // Reset failed results
        PublicationResult::where('publication_id', $publication->id)
            ->where('status', 'failed')
            ->update(['status' => 'pending', 'error_message' => null]);

        $publication->update(['status' => 'pending']);

        PublishToSocialJob::dispatch($publication, $failedPlatforms);

        return response()->json(['message' => 'Retry queued', 'platforms' => $failedPlatforms]);
    }

    public function destroy(int $id): JsonResponse
    {
        $publication = auth()->user()
            ->publications()
            ->findOrFail($id);

        if ($publication->status !== 'draft') {
            return response()->json(['error' => 'Only drafts can be deleted'], 422);
        }

        $publication->delete();

        return response()->json(null, 204);
    }
}
