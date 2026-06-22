<?php

namespace App\Models;

use App\Enums\AppointmentCancellationPolicy;
use App\Enums\AppointmentLateFeeCollectionMethod;
use App\Enums\AppointmentLateFeeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentSetting extends Model
{
    use HasFactory;

    protected $attributes = [
        'timezone' => 'America/Mexico_City',
        'slot_interval_minutes' => 15,
        'default_duration_minutes' => 30,
        'minimum_notice_minutes' => 120,
        'booking_window_days' => 60,
        'customer_cancellation_notice_minutes' => 1440,
        'proposal_hold_hours' => 24,
        'reminder_hours_before' => 24,
        'cancellation_policy' => 'no_penalty',
        'is_customer_booking_enabled' => false,
    ];

    protected $fillable = [
        'tenant_id',
        'doctor_user_id',
        'timezone',
        'slot_interval_minutes',
        'default_duration_minutes',
        'minimum_notice_minutes',
        'booking_window_days',
        'customer_cancellation_notice_minutes',
        'proposal_hold_hours',
        'reminder_hours_before',
        'cancellation_policy',
        'late_fee_type',
        'late_fee_value',
        'late_fee_collection_method',
        'late_fee_catalog_item_id',
        'is_customer_booking_enabled',
        'created_by',
    ];

    protected $casts = [
        'slot_interval_minutes' => 'integer',
        'default_duration_minutes' => 'integer',
        'minimum_notice_minutes' => 'integer',
        'booking_window_days' => 'integer',
        'customer_cancellation_notice_minutes' => 'integer',
        'proposal_hold_hours' => 'integer',
        'reminder_hours_before' => 'integer',
        'cancellation_policy' => AppointmentCancellationPolicy::class,
        'late_fee_type' => AppointmentLateFeeType::class,
        'late_fee_value' => 'decimal:2',
        'late_fee_collection_method' => AppointmentLateFeeCollectionMethod::class,
        'is_customer_booking_enabled' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function lateFeeCatalogItem()
    {
        return $this->belongsTo(CatalogItem::class, 'late_fee_catalog_item_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
