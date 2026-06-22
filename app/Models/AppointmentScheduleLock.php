<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentScheduleLock extends Model
{
    protected $fillable = [
        'tenant_id',
        'doctor_user_id',
        'schedule_date',
    ];

    protected $casts = [
        'schedule_date' => 'date',
    ];
}
