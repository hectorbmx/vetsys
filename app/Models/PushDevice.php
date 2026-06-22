<?php

namespace App\Models;

use App\Enums\PushPlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PushDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'platform',
        'token',
        'token_hash',
        'device_uuid',
        'device_name',
        'app_version',
        'last_seen_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token',
        'token_hash',
    ];

    protected $casts = [
        'platform' => PushPlatform::class,
        'token' => 'encrypted',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries()
    {
        return $this->hasMany(AppointmentNotificationDelivery::class);
    }
}
