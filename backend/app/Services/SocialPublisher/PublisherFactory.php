<?php

namespace App\Services\SocialPublisher;

use App\Models\PlatformAccount;

class PublisherFactory
{
    public static function make(PlatformAccount $account): PublisherInterface
    {
        return match ($account->platform) {
            'facebook'  => new FacebookPublisher($account),
            'instagram' => new InstagramPublisher($account),
            'tiktok'    => new TikTokPublisher($account),
            'youtube'   => new YoutubePublisher($account),
            'pinterest' => new PinterestPublisher($account),
            'snapchat'  => new SnapchatPublisher($account),
            default     => throw new \InvalidArgumentException("Unknown platform: {$account->platform}"),
        };
    }
}
