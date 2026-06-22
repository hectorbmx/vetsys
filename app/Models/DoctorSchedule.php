<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'tenant_id',
        'doctor_user_id',
        'weekday',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }
}
