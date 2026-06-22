<?php

namespace Database\Factories;

use App\Enums\AppointmentCancellationPolicy;
use App\Models\AppointmentSetting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppointmentSetting> */
class AppointmentSettingFactory extends Factory
{
    protected $model = AppointmentSetting::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'timezone' => 'America/Mexico_City',
            'slot_interval_minutes' => 15,
            'default_duration_minutes' => 30,
            'minimum_notice_minutes' => 120,
            'booking_window_days' => 60,
            'customer_cancellation_notice_minutes' => 1440,
            'proposal_hold_hours' => 24,
            'reminder_hours_before' => 24,
            'cancellation_policy' => AppointmentCancellationPolicy::NoPenalty,
            'is_customer_booking_enabled' => false,
        ];
    }
}
