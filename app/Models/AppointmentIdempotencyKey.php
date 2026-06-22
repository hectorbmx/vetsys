<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentIdempotencyKey extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'operation',
        'idempotency_key',
        'request_hash',
        'status',
        'result_type',
        'result_id',
        'response_data',
    ];

    protected $casts = [
        'response_data' => 'array',
    ];
}
