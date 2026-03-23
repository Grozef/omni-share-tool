<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'bio',
        'theme',
        'custom_domain',
        'show_instagram',
        'show_tiktok',
        'show_youtube',
        'is_public',
    ];

    protected $casts = [
        'show_instagram' => 'boolean',
        'show_tiktok' => 'boolean',
        'show_youtube' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function generateEmbedToken(): string
    {
        return base64_encode(json_encode([
            'slug' => $this->slug,
            'ts' => now()->timestamp,
        ]));
    }
}
