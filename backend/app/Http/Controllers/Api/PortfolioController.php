<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\PublicationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PortfolioController extends Controller
{
    public function show(): JsonResponse
    {
        $portfolio = auth()->user()->portfolio ?? new Portfolio(['slug' => auth()->user()->portfolio_slug]);

        return response()->json($portfolio);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'          => 'nullable|string|max:255',
            'bio'            => 'nullable|string|max:1000',
            'theme'          => 'nullable|in:light,dark,minimal',
            'custom_domain'  => 'nullable|url|max:255',
            'show_instagram' => 'nullable|boolean',
            'show_tiktok'    => 'nullable|boolean',
            'show_youtube'   => 'nullable|boolean',
            'is_public'      => 'nullable|boolean',
        ]);

        $portfolio = Portfolio::updateOrCreate(
            ['user_id' => auth()->id()],
            array_merge($data, [
                'slug' => auth()->user()->portfolio_slug ?? Str::slug(auth()->user()->name) . '-' . rand(100, 999),
            ])
        );

        return response()->json($portfolio);
    }

    public function publicShow(string $slug): JsonResponse
    {
        $portfolio = Portfolio::where('slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        return response()->json([
            'title'    => $portfolio->title,
            'bio'      => $portfolio->bio,
            'theme'    => $portfolio->theme,
            'slug'     => $portfolio->slug,
            'show_instagram' => $portfolio->show_instagram,
            'show_tiktok'    => $portfolio->show_tiktok,
            'show_youtube'   => $portfolio->show_youtube,
        ]);
    }

    public function publicFeed(string $slug): JsonResponse
    {
        $portfolio = Portfolio::where('slug', $slug)
            ->where('is_public', true)
            ->with('user.publications.mediaFiles')
            ->firstOrFail();

        $allowedPlatforms = array_filter([
            $portfolio->show_instagram ? 'instagram' : null,
            $portfolio->show_tiktok    ? 'tiktok'    : null,
            $portfolio->show_youtube   ? 'youtube'   : null,
        ]);

        $posts = PublicationResult::whereHas('publication', fn($q) => $q->where('user_id', $portfolio->user_id))
            ->whereIn('platform', $allowedPlatforms)
            ->where('status', 'success')
            ->with('publication.mediaFiles')
            ->latest()
            ->take(30)
            ->get()
            ->map(fn($result) => [
                'platform'     => $result->platform,
                'platform_url' => $result->platform_url,
                'published_at' => $result->published_at,
                'caption'      => $result->publication->caption,
                'thumbnail'    => $result->publication->mediaFiles->first()?->thumbnail_url,
            ]);

        return response()->json($posts);
    }

    public function widgetToken(): JsonResponse
    {
        $portfolio = auth()->user()->portfolio;

        if (!$portfolio) {
            return response()->json(['error' => 'Portfolio not configured'], 404);
        }

        $token = $portfolio->generateEmbedToken();
        $widgetCode = "<social-portfolio slug=\"{$portfolio->slug}\" theme=\"{$portfolio->theme}\" limit=\"9\"></social-portfolio>\n<script src=\"" . config('app.url') . "/widget/social-portfolio.js\"></script>";

        return response()->json([
            'token'       => $token,
            'widget_code' => $widgetCode,
        ]);
    }
}
