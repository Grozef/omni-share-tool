<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicationResult extends Model
{
    protected $fillable = [
        'publication_id',
        'platform',
        'status',
        'platform_post_id',
        'platform_url',
        'error_message',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
