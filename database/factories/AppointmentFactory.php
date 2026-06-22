<?php

namespace Database\Factories;

use App\Enums\AppointmentCancellationFeeStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $startsAt = now()->addWeek()->startOfHour();

        return [
            'tenant_id' => Tenant::factory(),
            'service_name_snapshot' => 'Consulta general',
            'animal_name_snapshot' => fake()->firstName(),
            'doctor_name_snapshot' => fake()->name(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'timezone' => 'America/Mexico_City',
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
            'status' => AppointmentStatus::PendingTenant,
            'requested_at' => now(),
            'is_late_cancellation' => false,
            'cancellation_fee_status' => AppointmentCancellationFeeStatus::NotApplicable,
        ];
    }
}
