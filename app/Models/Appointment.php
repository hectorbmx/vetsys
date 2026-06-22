<?php

namespace App\Models;

use App\Enums\AppointmentCancellationFeeStatus;
use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $attributes = [
        'buffer_minutes' => 0,
        'status' => 'pending_tenant',
        'is_late_cancellation' => false,
        'cancellation_fee_status' => 'not_applicable',
    ];

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'animal_id',
        'doctor_user_id',
        'catalog_item_id',
        'service_name_snapshot',
        'animal_name_snapshot',
        'doctor_name_snapshot',
        'starts_at',
        'ends_at',
        'timezone',
        'duration_minutes',
        'buffer_minutes',
        'status',
        'customer_reason',
        'internal_notes',
        'requested_at',
        'confirmed_at',
        'rejected_at',
        'rejection_reason',
        'completed_at',
        'no_show_at',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'is_late_cancellation',
        'cancellation_fee_status',
        'cancellation_fee_amount',
        'created_by_user_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'duration_minutes' => 'integer',
        'buffer_minutes' => 'integer',
        'status' => AppointmentStatus::class,
        'requested_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
        'no_show_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_late_cancellation' => 'boolean',
        'cancellation_fee_status' => AppointmentCancellationFeeStatus::class,
        'cancellation_fee_amount' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class)->withTrashed();
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function catalogItem()
    {
        return $this->belongsTo(CatalogItem::class)->withTrashed();
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function proposals()
    {
        return $this->hasMany(AppointmentProposal::class);
    }

    public function pendingProposal()
    {
        return $this->hasOne(AppointmentProposal::class)
            ->where('status', 'pending')
            ->latestOfMany();
    }

    public function events()
    {
        return $this->hasMany(AppointmentEvent::class);
    }
}
