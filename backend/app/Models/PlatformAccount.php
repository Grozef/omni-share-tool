<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAccount extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'platform_user_id',
        'platform_username',
        'is_active',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }
}
