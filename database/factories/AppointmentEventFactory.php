<?php

namespace Database\Factories;

use App\Enums\AppointmentEventType;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppointmentEvent> */
class AppointmentEventFactory extends Factory
{
    protected $model = AppointmentEvent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'appointment_id' => Appointment::factory(),
            'event_type' => AppointmentEventType::Requested,
            'new_status' => AppointmentStatus::PendingTenant,
            'metadata' => [],
        ];
    }
}
