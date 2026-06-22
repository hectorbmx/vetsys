<?php

namespace Database\Factories;

use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentNotificationDelivery;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppointmentNotificationDelivery> */
class AppointmentNotificationDeliveryFactory extends Factory
{
    protected $model = AppointmentNotificationDelivery::class;

    public function definition(): array
    {
        $recipientKey = 'tenant:'.fake()->unique()->numberBetween(1, 1000000);

        return [
            'tenant_id' => Tenant::factory(),
            'appointment_event_id' => fn (array $attributes) => AppointmentEvent::factory()->create([
                'tenant_id' => $attributes['tenant_id'],
                'appointment_id' => Appointment::factory()->create([
                    'tenant_id' => $attributes['tenant_id'],
                ])->id,
            ])->id,
            'channel' => NotificationDeliveryChannel::TenantInApp,
            'recipient_key' => $recipientKey,
            'recipient_hash' => AppointmentNotificationDelivery::recipientHash($recipientKey),
            'status' => NotificationDeliveryStatus::Pending,
        ];
    }
}
