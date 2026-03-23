<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PlatformController extends Controller
{
    private const OAUTH_CONFIGS = [
        'facebook' => [
            'auth_url'     => 'https://www.facebook.com/v18.0/dialog/oauth',
            'token_url'    => 'https://graph.facebook.com/v18.0/oauth/access_token',
            'scope'        => 'pages_manage_posts,pages_read_engagement,instagram_basic,instagram_content_publish',
        ],
        'instagram' => [
            'auth_url'  => 'https://www.facebook.com/v18.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v18.0/oauth/access_token',
            'scope'     => 'instagram_basic,instagram_content_publish',
        ],
        'tiktok' => [
            'auth_url'  => 'https://www.tiktok.com/v2/auth/authorize/',
            'token_url' => 'https://open.tiktokapis.com/v2/oauth/token/',
            'scope'     => 'user.info.basic,video.publish',
        ],
        'youtube' => [
            'auth_url'  => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'scope'     => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube',
        ],
        'snapchat' => [
            'auth_url'  => 'https://accounts.snapchat.com/login/oauth2/authorize',
            'token_url' => 'https://accounts.snapchat.com/login/oauth2/access_token',
            'scope'     => 'snapchat-marketing-api',
        ],
        'pinterest' => [
            'auth_url'  => 'https://www.pinterest.com/oauth/',
            'token_url' => 'https://api.pinterest.com/v5/oauth/token',
            'scope'     => 'boards:read,pins:read,pins:write',
        ],
    ];

    public function connect(string $platform): JsonResponse
    {
        $this->validatePlatform($platform);

        $state = Str::random(40);
        $userId = auth()->id();

        Cache::put("oauth_state_{$state}", $userId, now()->addMinutes(10));

        $config = self::OAUTH_CONFIGS[$platform];
        $clientId = config("services.{$platform}.client_id") ?? config("services.{$platform}.client_key") ?? config("services.{$platform}.app_id");
        $redirectUri = config("services.{$platform}.redirect_uri");

        $params = [
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => $config['scope'],
            'state'         => $state,
        ];

        // TikTok uses different param names
        if ($platform === 'tiktok') {
            $params['client_key'] = $clientId;
            unset($params['client_id']);
        }

        $authUrl = $config['auth_url'] . '?' . http_build_query($params);

        return response()->json(['auth_url' => $authUrl]);
    }

    public function callback(Request $request, string $platform): JsonResponse
    {
        $this->validatePlatform($platform);

        $code = $request->query('code');
        $state = $request->query('state');

        if (!$code) {
            return response()->json(['error' => 'Authorization denied'], 400);
        }

        $userId = Cache::pull("oauth_state_{$state}");
        if (!$userId) {
            return response()->json(['error' => 'Invalid or expired state'], 400);
        }

        $tokenData = $this->exchangeCodeForToken($platform, $code);
        if (!$tokenData) {
            return response()->json(['error' => 'Failed to obtain access token'], 500);
        }

        $accountInfo = $this->fetchAccountInfo($platform, $tokenData['access_token']);

        PlatformAccount::updateOrCreate(
            ['user_id' => $userId, 'platform' => $platform],
            [
                'access_token'      => $tokenData['access_token'],
                'refresh_token'     => $tokenData['refresh_token'] ?? null,
                'token_expires_at'  => isset($tokenData['expires_in'])
                    ? now()->addSeconds($tokenData['expires_in'])
                    : null,
                'platform_user_id'  => $accountInfo['id'] ?? null,
                'platform_username' => $accountInfo['username'] ?? $accountInfo['name'] ?? null,
                'is_active'         => true,
            ]
        );

        return response()->json(['message' => "Connected to {$platform} successfully"]);
    }

    public function index(): JsonResponse
    {
        $accounts = auth()->user()
            ->platformAccounts()
            ->select(['id', 'platform', 'platform_username', 'token_expires_at', 'is_active', 'created_at'])
            ->get();

        return response()->json($accounts);
    }

    public function destroy(string $platform): JsonResponse
    {
        $this->validatePlatform($platform);

        auth()->user()
            ->platformAccounts()
            ->where('platform', $platform)
            ->delete();

        return response()->json(['message' => "Disconnected from {$platform}"]);
    }

    private function exchangeCodeForToken(string $platform, string $code): ?array
    {
        $config = self::OAUTH_CONFIGS[$platform];
        $clientId = config("services.{$platform}.client_id") ?? config("services.{$platform}.client_key") ?? config("services.{$platform}.app_id");
        $clientSecret = config("services.{$platform}.client_secret") ?? config("services.{$platform}.app_secret");
        $redirectUri = config("services.{$platform}.redirect_uri");

        $payload = [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ];

        if ($platform === 'tiktok') {
            $payload['client_key'] = $clientId;
            unset($payload['client_id']);
        }

        $response = \Illuminate\Support\Facades\Http::post($config['token_url'], $payload);

        return $response->successful() ? $response->json() : null;
    }

    private function fetchAccountInfo(string $platform, string $accessToken): array
    {
        try {
            return match ($platform) {
                'facebook', 'instagram' => \Illuminate\Support\Facades\Http::withToken($accessToken)
                    ->get('https://graph.facebook.com/v18.0/me', ['fields' => 'id,name'])
                    ->json(),
                'tiktok' => \Illuminate\Support\Facades\Http::withToken($accessToken)
                    ->post('https://open.tiktokapis.com/v2/user/info/', ['fields' => ['open_id', 'display_name']])
                    ->json('data.user'),
                'youtube' => \Illuminate\Support\Facades\Http::withToken($accessToken)
                    ->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet', 'mine' => 'true'])
                    ->json('items.0.snippet') ?? [],
                'snapchat' => \Illuminate\Support\Facades\Http::withToken($accessToken)
                    ->get('https://kit.snapchat.com/v1/me', ['query' => '{me{externalId displayName}}'])
                    ->json('data.me') ?? [],
                'pinterest' => \Illuminate\Support\Facades\Http::withToken($accessToken)
                    ->get('https://api.pinterest.com/v5/user_account')
                    ->json(),
                default => [],
            };
        } catch (\Exception) {
            return [];
        }
    }

    private function validatePlatform(string $platform): void
    {
        $valid = ['facebook', 'instagram', 'snapchat', 'tiktok', 'youtube', 'pinterest'];
        if (!in_array($platform, $valid)) {
            abort(404, "Unknown platform: {$platform}");
        }
    }
}
