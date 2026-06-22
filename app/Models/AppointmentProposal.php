<?php

namespace App\Models;

use App\Enums\AppointmentProposalStatus;
use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentProposal extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'proposed_by_user_id',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'previous_appointment_status',
        'message',
        'status',
        'expires_at',
        'responded_at',
        'responded_by_user_id',
        'response_message',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'duration_minutes' => 'integer',
        'previous_appointment_status' => AppointmentStatus::class,
        'status' => AppointmentProposalStatus::class,
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by_user_id');
    }
}
