<?php

namespace Tests\Feature;

use App\Enums\AppointmentCancellationFeeStatus;
use App\Enums\AppointmentCancellationPolicy;
use App\Enums\AppointmentEventType;
use App\Enums\AppointmentProposalStatus;
use App\Enums\AppointmentStatus;
use App\Models\Animal;
use App\Models\AnimalType;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentProposal;
use App\Models\AppointmentSetting;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\DoctorSchedule;
use App\Models\ScheduleBlock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentDataModelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_appointment_schema_contains_the_contract_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('catalog_items', [
            'is_bookable',
            'appointment_duration_minutes',
            'appointment_buffer_minutes',
            'booking_description',
        ]));
        $this->assertTrue(Schema::hasColumns('appointment_settings', [
            'tenant_id',
            'doctor_user_id',
            'minimum_notice_minutes',
            'booking_window_days',
            'cancellation_policy',
        ]));
        $this->assertTrue(Schema::hasColumns('appointments', [
            'tenant_id',
            'customer_id',
            'animal_id',
            'doctor_user_id',
            'catalog_item_id',
            'service_name_snapshot',
            'status',
            'rejected_at',
            'rejection_reason',
            'no_show_at',
            'cancellation_fee_status',
        ]));
        $this->assertTrue(Schema::hasColumns('appointment_proposals', [
            'appointment_id',
            'previous_appointment_status',
            'status',
            'expires_at',
        ]));
        $this->assertTrue(Schema::hasColumns('appointment_events', [
            'appointment_id',
            'event_type',
            'previous_status',
            'new_status',
            'metadata',
        ]));
        $this->assertTrue(Schema::hasColumns('appointment_schedule_locks', [
            'tenant_id',
            'doctor_user_id',
            'schedule_date',
        ]));
        $this->assertTrue(Schema::hasColumns('appointment_idempotency_keys', [
            'tenant_id',
            'user_id',
            'operation',
            'idempotency_key',
            'request_hash',
            'result_type',
            'result_id',
        ]));
    }

    public function test_models_cast_enums_and_expose_the_expected_relations(): void
    {
        $context = $this->createContext();

        $setting = AppointmentSetting::create([
            'tenant_id' => $context['tenant']->id,
            'doctor_user_id' => $context['doctor']->id,
            'cancellation_policy' => AppointmentCancellationPolicy::LateFeeReview,
            'is_customer_booking_enabled' => true,
        ]);
        $schedule = DoctorSchedule::create([
            'tenant_id' => $context['tenant']->id,
            'doctor_user_id' => $context['doctor']->id,
            'weekday' => 1,
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
        ]);
        $block = ScheduleBlock::create([
            'tenant_id' => $context['tenant']->id,
            'doctor_user_id' => $context['doctor']->id,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHour(),
            'created_by' => $context['doctor']->id,
        ]);
        $appointment = $this->createAppointment($context);
        $proposal = AppointmentProposal::create([
            'tenant_id' => $context['tenant']->id,
            'appointment_id' => $appointment->id,
            'proposed_by_user_id' => $context['doctor']->id,
            'starts_at' => now()->addDays(8),
            'ends_at' => now()->addDays(8)->addMinutes(30),
            'duration_minutes' => 30,
            'previous_appointment_status' => AppointmentStatus::PendingTenant,
            'status' => AppointmentProposalStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);
        $event = AppointmentEvent::create([
            'tenant_id' => $context['tenant']->id,
            'appointment_id' => $appointment->id,
            'actor_user_id' => $context['doctor']->id,
            'event_type' => AppointmentEventType::Requested,
            'new_status' => AppointmentStatus::PendingTenant,
            'metadata' => ['source' => 'test'],
        ]);

        $this->assertSame(AppointmentCancellationPolicy::LateFeeReview, $setting->cancellation_policy);
        $this->assertSame(AppointmentStatus::PendingTenant, $appointment->status);
        $this->assertSame(AppointmentCancellationFeeStatus::NotApplicable, $appointment->cancellation_fee_status);
        $this->assertSame(AppointmentProposalStatus::Pending, $proposal->status);
        $this->assertSame(AppointmentStatus::PendingTenant, $proposal->previous_appointment_status);
        $this->assertSame(AppointmentEventType::Requested, $event->event_type);
        $this->assertSame(['source' => 'test'], $event->metadata);
        $this->assertTrue($context['tenant']->appointmentSetting->is($setting));
        $this->assertTrue($context['doctor']->doctorSchedules->contains($schedule));
        $this->assertTrue($context['doctor']->scheduleBlocks->contains($block));
        $this->assertTrue($appointment->proposals->contains($proposal));
        $this->assertTrue($appointment->events->contains($event));
    }

    public function test_soft_deleted_business_entities_keep_appointment_history_readable(): void
    {
        $context = $this->createContext();
        $appointment = $this->createAppointment($context);

        $context['customer']->delete();
        $context['animal']->delete();
        $context['service']->delete();
        $appointment->refresh();

        $this->assertSame('Consulta general', $appointment->service_name_snapshot);
        $this->assertSame('Paciente agenda', $appointment->animal_name_snapshot);
        $this->assertSame('Dra. Agenda', $appointment->doctor_name_snapshot);
        $this->assertNotNull($appointment->customer);
        $this->assertNotNull($appointment->animal);
        $this->assertNotNull($appointment->catalogItem);
    }

    private function createContext(): array
    {
        $tenant = Tenant::factory()->create();
        $doctor = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Dra. Agenda',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente',
            'last_name' => 'Agenda',
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ]);
        $animalType = AnimalType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Canino',
            'slug' => 'canino-'.str()->random(6),
            'is_active' => true,
        ]);
        $animal = Animal::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'animal_type_id' => $animalType->id,
            'name' => 'Paciente agenda',
            'status' => 'active',
        ]);
        $service = CatalogItem::create([
            'tenant_id' => $tenant->id,
            'name' => 'Consulta general',
            'type' => 'service',
            'is_active' => true,
            'is_bookable' => true,
            'appointment_duration_minutes' => 30,
        ]);

        return compact('tenant', 'doctor', 'customer', 'animal', 'service');
    }

    private function createAppointment(array $context): Appointment
    {
        return Appointment::create([
            'tenant_id' => $context['tenant']->id,
            'customer_id' => $context['customer']->id,
            'animal_id' => $context['animal']->id,
            'doctor_user_id' => $context['doctor']->id,
            'catalog_item_id' => $context['service']->id,
            'service_name_snapshot' => $context['service']->name,
            'animal_name_snapshot' => $context['animal']->name,
            'doctor_name_snapshot' => $context['doctor']->name,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addMinutes(30),
            'timezone' => 'America/Mexico_City',
            'duration_minutes' => 30,
            'status' => AppointmentStatus::PendingTenant,
            'requested_at' => now(),
        ]);
    }
}
