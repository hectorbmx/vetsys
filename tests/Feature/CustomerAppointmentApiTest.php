<?php

namespace Tests\Feature;

use App\Enums\AppointmentProposalStatus;
use App\Enums\AppointmentStatus;
use App\Http\Middleware\EnsureApiTenantAccess;
use App\Http\Middleware\EnsureValidMobileAccessSession;
use App\Models\Animal;
use App\Models\AnimalPortalVisibilitySetting;
use App\Models\AnimalType;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentProposal;
use App\Models\AppointmentSetting;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Models\CustomerUserLink;
use App\Models\DoctorSchedule;
use App\Models\FinalUserPatientAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VeterinarianProfile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerAppointmentApiTest extends TestCase
{
    use DatabaseTransactions;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            EnsureValidMobileAccessSession::class,
            EnsureApiTenantAccess::class,
        ]);
        $this->context = $this->scenario();
    }

    public function test_bootstrap_and_availability_only_expose_customer_safe_data(): void
    {
        $this->actingAsCustomer();

        $this->getJson('/api/v1/portal/appointments/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.patients.0.id', $this->context['animal']->id)
            ->assertJsonPath('data.services.0.id', $this->context['service']->id)
            ->assertJsonMissingPath('data.services.0.internal_notes');

        $date = $this->context['startsAtLocal']->toDateString();
        $this->getJson('/api/v1/portal/appointments/availability?'.http_build_query([
            'animal_id' => $this->context['animal']->id,
            'service_id' => $this->context['service']->id,
            'from' => $date,
            'to' => $date,
        ]))
            ->assertOk()
            ->assertJsonPath("data.{$date}.0.duration_minutes", 30)
            ->assertJsonStructure(['data' => [$date => [['starts_at', 'local_starts_at', 'timezone']]]]);
    }

    public function test_request_requires_idempotency_key_and_replays_same_result(): void
    {
        $this->actingAsCustomer();
        $payload = [
            'animal_id' => $this->context['animal']->id,
            'service_id' => $this->context['service']->id,
            'starts_at' => $this->context['startsAtLocal']->toIso8601String(),
            'customer_reason' => 'Revision anual',
        ];

        $this->postJson('/api/v1/portal/appointments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        $first = $this->withHeader('Idempotency-Key', 'customer-request-1')
            ->postJson('/api/v1/portal/appointments', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', AppointmentStatus::PendingTenant->value)
            ->assertJsonMissingPath('data.internal_notes');
        $appointmentId = $first->json('data.id');

        $this->withHeader('Idempotency-Key', 'customer-request-1')
            ->postJson('/api/v1/portal/appointments', $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', $appointmentId);

        $this->assertSame(1, Appointment::where('customer_id', $this->context['customer']->id)->count());
    }

    public function test_domain_errors_have_stable_json_contract(): void
    {
        $this->actingAsCustomer();

        $this->withHeader('Idempotency-Key', 'unavailable-slot')
            ->postJson('/api/v1/portal/appointments', [
                'animal_id' => $this->context['animal']->id,
                'service_id' => $this->context['service']->id,
                'starts_at' => $this->context['startsAtLocal']->startOfDay()->toIso8601String(),
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'APPOINTMENT_SLOT_UNAVAILABLE')
            ->assertJsonStructure(['message', 'code', 'errors']);
    }

    public function test_list_and_detail_are_isolated_and_hide_internal_notes(): void
    {
        $this->actingAsCustomer();
        $own = $this->appointment(['internal_notes' => 'Solo tenant']);
        $foreignCustomer = Customer::create([
            'tenant_id' => $this->context['tenant']->id,
            'name' => 'Otro',
            'email' => 'otro-'.str()->random(6).'@example.test',
            'status' => 'active',
        ]);
        $foreign = Appointment::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'customer_id' => $foreignCustomer->id,
            'animal_id' => $this->context['animal']->id,
            'doctor_user_id' => $this->context['doctor']->id,
            'catalog_item_id' => $this->context['service']->id,
        ]);
        AppointmentEvent::create([
            'tenant_id' => $this->context['tenant']->id,
            'appointment_id' => $own->id,
            'actor_user_id' => $this->context['doctor']->id,
            'event_type' => 'appointment.confirmed',
            'new_status' => AppointmentStatus::Confirmed,
            'metadata' => ['internal_notes' => 'No exponer'],
        ]);

        $this->getJson('/api/v1/portal/appointments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonMissing(['internal_notes' => 'Solo tenant']);
        $this->getJson("/api/v1/portal/appointments/{$own->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.internal_notes')
            ->assertJsonMissing(['internal_notes' => 'No exponer']);
        $this->getJson("/api/v1/portal/appointments/{$foreign->id}")->assertNotFound();
    }

    public function test_show_appointments_visibility_is_required_everywhere(): void
    {
        $this->actingAsCustomer();
        $appointment = $this->appointment();
        $this->context['visibility']->update(['show_appointments' => false]);
        $date = $this->context['startsAtLocal']->toDateString();

        $this->getJson('/api/v1/portal/appointments')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/portal/appointments/{$appointment->id}")->assertNotFound();
        $this->getJson('/api/v1/portal/appointments/availability?'.http_build_query([
            'animal_id' => $this->context['animal']->id,
            'service_id' => $this->context['service']->id,
            'from' => $date,
        ]))->assertNotFound();
    }

    public function test_ambiguous_or_not_yet_active_customer_access_is_rejected(): void
    {
        $this->actingAsCustomer();
        $this->context['access']->update(['access_starts_at' => now()->addDay()]);

        $this->getJson('/api/v1/portal/appointments/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('code', 'CUSTOMER_PORTAL_ACCESS_INACTIVE');

        $this->context['access']->update(['access_starts_at' => now()->subDay()]);
        $secondCustomer = Customer::create([
            'tenant_id' => $this->context['tenant']->id,
            'name' => 'Segundo',
            'email' => 'segundo-'.str()->random(6).'@example.test',
            'status' => 'active',
        ]);
        CustomerPortalAccess::create([
            'tenant_id' => $this->context['tenant']->id,
            'customer_id' => $secondCustomer->id,
            'user_id' => $this->context['user']->id,
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/portal/appointments/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('code', 'CUSTOMER_PORTAL_ACCESS_INACTIVE');
    }

    public function test_customer_can_reject_pending_proposal_and_cancel_appointment(): void
    {
        $this->actingAsCustomer();
        $appointment = $this->appointment(['status' => AppointmentStatus::PendingCustomer]);
        $proposal = AppointmentProposal::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'appointment_id' => $appointment->id,
            'proposed_by_user_id' => $this->context['doctor']->id,
            'starts_at' => $appointment->starts_at->addHour(),
            'ends_at' => $appointment->ends_at->addHour(),
            'status' => AppointmentProposalStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);

        $this->withHeader('Idempotency-Key', 'reject-proposal-1')
            ->postJson("/api/v1/portal/appointments/{$appointment->id}/proposals/{$proposal->id}/reject", [
                'response_message' => 'No puedo en ese horario',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::PendingTenant->value);

        $this->withHeader('Idempotency-Key', 'cancel-1')
            ->postJson("/api/v1/portal/appointments/{$appointment->id}/cancel", [
                'reason' => 'Ya no necesito la consulta',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Cancelled->value);
    }

    public function test_customer_can_accept_proposal_idempotently(): void
    {
        $this->actingAsCustomer();
        $appointment = $this->appointment(['status' => AppointmentStatus::PendingCustomer]);
        $proposal = AppointmentProposal::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'appointment_id' => $appointment->id,
            'proposed_by_user_id' => $this->context['doctor']->id,
            'starts_at' => $appointment->starts_at,
            'ends_at' => $appointment->ends_at,
            'status' => AppointmentProposalStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);
        $url = "/api/v1/portal/appointments/{$appointment->id}/proposals/{$proposal->id}/accept";

        $this->withHeader('Idempotency-Key', 'accept-proposal-1')
            ->postJson($url)
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Confirmed->value);
        $this->withHeader('Idempotency-Key', 'accept-proposal-1')
            ->postJson($url)
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Confirmed->value);

        $this->assertSame(AppointmentProposalStatus::Accepted, $proposal->fresh()->status);
    }

    public function test_manipulated_patient_and_service_ids_return_not_found(): void
    {
        $this->actingAsCustomer();
        $otherCustomer = Customer::create([
            'tenant_id' => $this->context['tenant']->id,
            'name' => 'Customer ajeno',
            'email' => 'ajeno-'.str()->random(6).'@example.test',
            'status' => 'active',
        ]);
        $otherAnimal = Animal::create([
            'tenant_id' => $this->context['tenant']->id,
            'customer_id' => $otherCustomer->id,
            'animal_type_id' => $this->context['animal']->animal_type_id,
            'name' => 'Mascota ajena',
            'sex' => 'unknown',
            'status' => 'active',
        ]);
        $otherTenant = Tenant::factory()->create();
        $otherService = CatalogItem::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Servicio ajeno',
            'type' => 'service',
            'is_active' => true,
            'is_bookable' => true,
        ]);
        $base = [
            'starts_at' => $this->context['startsAtLocal']->toIso8601String(),
            'service_id' => $this->context['service']->id,
            'animal_id' => $otherAnimal->id,
        ];

        $this->withHeader('Idempotency-Key', 'foreign-animal')
            ->postJson('/api/v1/portal/appointments', $base)
            ->assertNotFound();
        $this->withHeader('Idempotency-Key', 'foreign-service')
            ->postJson('/api/v1/portal/appointments', array_merge($base, [
                'animal_id' => $this->context['animal']->id,
                'service_id' => $otherService->id,
            ]))
            ->assertNotFound();
    }

    public function test_routes_require_authentication_and_writes_are_rate_limited(): void
    {
        $this->getJson('/api/v1/portal/appointments/bootstrap')->assertUnauthorized();
        $this->actingAsCustomer();
        $payload = [
            'animal_id' => $this->context['animal']->id,
            'service_id' => $this->context['service']->id,
            'starts_at' => $this->context['startsAtLocal']->startOfDay()->toIso8601String(),
        ];

        foreach (range(1, 10) as $attempt) {
            $this->withHeader('Idempotency-Key', "rate-limit-{$attempt}")
                ->postJson('/api/v1/portal/appointments', $payload)
                ->assertConflict();
        }

        $this->withHeader('Idempotency-Key', 'rate-limit-11')
            ->postJson('/api/v1/portal/appointments', $payload)
            ->assertTooManyRequests();
    }

    private function actingAsCustomer(): void
    {
        Sanctum::actingAs($this->context['user']);
    }

    private function appointment(array $overrides = []): Appointment
    {
        return Appointment::factory()->create(array_merge([
            'tenant_id' => $this->context['tenant']->id,
            'customer_id' => $this->context['customer']->id,
            'animal_id' => $this->context['animal']->id,
            'doctor_user_id' => $this->context['doctor']->id,
            'catalog_item_id' => $this->context['service']->id,
            'starts_at' => $this->context['startsAtLocal']->utc(),
            'ends_at' => $this->context['startsAtLocal']->addMinutes(30)->utc(),
        ], $overrides));
    }

    private function scenario(): array
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $user->assignRole('customer');
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente API',
            'email' => 'api-'.str()->random(8).'@example.test',
            'status' => 'active',
        ]);
        $doctor = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        VeterinarianProfile::create([
            'tenant_id' => $tenant->id,
            'user_id' => $doctor->id,
            'professional_name' => 'Dra. API',
            'professional_title' => 'Medica veterinaria',
            'license_number' => 'API-'.str()->random(8),
            'is_active' => true,
        ]);
        $animalType = AnimalType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Canino',
            'slug' => 'canino-'.str()->random(8),
            'is_active' => true,
        ]);
        $animal = Animal::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'animal_type_id' => $animalType->id,
            'name' => 'Paciente API',
            'sex' => 'unknown',
            'status' => 'active',
        ]);
        CustomerUserLink::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'relationship' => 'owner',
            'is_primary' => true,
        ]);
        $access = CustomerPortalAccess::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'active',
            'access_starts_at' => now()->subDay(),
        ]);
        FinalUserPatientAssignment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'animal_id' => $animal->id,
            'assigned_at' => now(),
        ]);
        $visibility = AnimalPortalVisibilitySetting::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'animal_id' => $animal->id,
            'show_appointments' => true,
        ]);
        $service = CatalogItem::create([
            'tenant_id' => $tenant->id,
            'name' => 'Consulta API',
            'type' => 'service',
            'is_active' => true,
            'is_bookable' => true,
            'appointment_duration_minutes' => 30,
            'appointment_buffer_minutes' => 0,
        ]);
        $startsAtLocal = CarbonImmutable::now('America/Mexico_City')
            ->next(CarbonImmutable::MONDAY)
            ->addWeek()
            ->setTime(9, 0);
        AppointmentSetting::create([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctor->id,
            'timezone' => 'America/Mexico_City',
            'minimum_notice_minutes' => 0,
            'booking_window_days' => 60,
            'is_customer_booking_enabled' => true,
        ]);
        DoctorSchedule::create([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctor->id,
            'weekday' => $startsAtLocal->isoWeekday(),
            'starts_at' => '09:00',
            'ends_at' => '12:00',
            'is_active' => true,
        ]);

        return compact(
            'tenant',
            'user',
            'customer',
            'doctor',
            'animal',
            'access',
            'visibility',
            'service',
            'startsAtLocal',
        );
    }
}
