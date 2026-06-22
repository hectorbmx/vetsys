<?php

namespace Database\Factories;

use App\Enums\AppointmentProposalStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentProposal;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppointmentProposal> */
class AppointmentProposalFactory extends Factory
{
    protected $model = AppointmentProposal::class;

    public function definition(): array
    {
        $startsAt = now()->addWeek()->startOfHour();

        return [
            'tenant_id' => Tenant::factory(),
            'appointment_id' => Appointment::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'previous_appointment_status' => AppointmentStatus::PendingTenant,
            'status' => AppointmentProposalStatus::Pending,
            'expires_at' => now()->addDay(),
        ];
    }
}
