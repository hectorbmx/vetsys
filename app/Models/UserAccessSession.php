<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAccessSession extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'platform',
        'session_id',
        'token_id',
        'device_name',
        'ip_address',
        'user_agent',
        'last_activity_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
