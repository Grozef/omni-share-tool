<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publication extends Model
{
    protected $fillable = [
        'user_id',
        'caption',
        'hashtags',
        'scheduled_at',
        'status',
        'platforms',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'platforms' => 'array',
        'scheduled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mediaFiles()
    {
        return $this->hasMany(MediaFile::class);
    }

    public function results()
    {
        return $this->hasMany(PublicationResult::class);
    }

    public function getSuccessfulPlatformsAttribute(): array
    {
        return $this->results()
            ->where('status', 'success')
            ->pluck('platform')
            ->toArray();
    }

    public function getFailedPlatformsAttribute(): array
    {
        return $this->results()
            ->where('status', 'failed')
            ->pluck('platform')
            ->toArray();
    }
}
