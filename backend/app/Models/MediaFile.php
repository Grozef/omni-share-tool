<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    protected $fillable = [
        'publication_id',
        'type',
        'original_path',
        'processed_path',
        'thumbnail_path',
        'duration',
        'width',
        'height',
    ];

    protected $casts = [
        'duration' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected $appends = ['original_url', 'thumbnail_url'];

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }

    public function getOriginalUrlAttribute(): ?string
    {
        return $this->original_path ? Storage::url($this->original_path) : null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path ? Storage::url($this->thumbnail_path) : null;
    }

    public function isVideo(): bool
    {
        return in_array($this->type, ['video', 'short_video']);
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }
}
