<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantOnboardingStep extends Model
{
    public const CLINIC_CONFIGURED = 'clinic_configured';
    public const FIRST_SERVICE_CREATED = 'first_service_created';
    public const FIRST_CUSTOMER_CREATED = 'first_customer_created';
    public const FIRST_PET_CREATED = 'first_pet_created';
    public const FIRST_NOTE_CREATED = 'first_note_created';
    public const FIRST_NOTE_PAID = 'first_note_paid';

    public const STEPS = [
        self::CLINIC_CONFIGURED,
        self::FIRST_SERVICE_CREATED,
        self::FIRST_CUSTOMER_CREATED,
        self::FIRST_PET_CREATED,
        self::FIRST_NOTE_CREATED,
        self::FIRST_NOTE_PAID,
    ];

    protected $fillable = [
        'tenant_id',
        'step',
        'completed_at',
        'evidence_type',
        'evidence_id',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function evidence()
    {
        return $this->morphTo();
    }

    public static function isValidStep(string $step): bool
    {
        return in_array($step, self::STEPS, true);
    }
}
