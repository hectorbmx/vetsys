<?php

namespace Tests\Feature;

use App\Enums\AppointmentEventType;
use App\Enums\AppointmentStatus;
use App\Http\Middleware\CheckTenantSubscription;
use App\Http\Middleware\EnsureApiTenantAccess;
use App\Http\Middleware\EnsureTenantHasActivePlan;
use App\Http\Middleware\EnsureValidMobileAccessSession;
use App\Http\Middleware\EnsureValidWebAccessSession;
use App\Models\Animal;
use App\Models\AnimalType;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentSetting;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\DoctorSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VeterinarianProfile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantAppointmentHttpTest extends TestCase
{
    use DatabaseTransactions;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            EnsureValidMobileAccessSession::class,
            EnsureApiTenantAccess::class,
            EnsureValidWebAccessSession::class,
            EnsureTenantHasActivePlan::class,
            CheckTenantSubscription::class,
        ]);

        foreach (['client-admin', 'asistente', 'cajero'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->context = $this->scenario();
    }

    public function test_tenant_appointment_api_requires_authentication_and_operator_access(): void
    {
        $this->getJson('/api/v1/appointments')->assertUnauthorized();

        Sanctum::actingAs($this->context['assistant']);
        $this->getJson('/api/v1/appointments')->assertForbidden();

        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $customerUser = User::factory()->create([
            'tenant_id' => $this->context['tenant']->id,
            'is_active' => true,
        ]);
        $customerUser->assignRole('customer');
        Sanctum::actingAs($customerUser);
        $this->getJson('/api/v1/appointments')->assertForbidden();

        Sanctum::actingAs($this->context['doctor']);
        $this->getJson('/api/v1/appointments?from='.$this->date().'&to='.$this->date())
            ->assertOk();
    }

    public function test_bootstrap_and_tenant_availability_work_when_customer_booking_is_disabled(): void
    {
        $this->actingAsAdminApi();

        $this->getJson('/api/v1/appointments/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.customers.0.id', $this->context['customer']->id)
            ->assertJsonPath('data.customers.0.animals.0.id', $this->context['animal']->id)
            ->assertJsonPath('data.services.0.id', $this->context['service']->id);

        $this->getJson('/api/v1/appointments/availability?'.http_build_query([
            'service_id' => $this->context['service']->id,
            'from' => $this->date(),
        ]))
            ->assertOk()
            ->assertJsonPath('data.'.$this->date().'.0.duration_minutes', 30);
    }

    public function test_index_and_detail_are_tenant_scoped_and_include_internal_data(): void
    {
        $this->actingAsAdminApi();
        $own = $this->appointment(['internal_notes' => 'Nota solo tenant']);
        AppointmentEvent::create([
            'tenant_id' => $this->context['tenant']->id,
            'appointment_id' => $own->id,
            'actor_user_id' => $this->context['admin']->id,
            'event_type' => AppointmentEventType::Confirmed,
            'new_status' => AppointmentStatus::Confirmed,
            'metadata' => ['source' => 'tenant-test'],
        ]);
        $foreign = Appointment::factory()->create(['tenant_id' => Tenant::factory()]);

        $this->getJson('/api/v1/appointments?'.http_build_query([
            'from' => $this->date(),
            'to' => $this->date(),
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.internal_notes', 'Nota solo tenant');
        $this->getJson("/api/v1/appointments/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.events.0.metadata.source', 'tenant-test');
        $this->getJson("/api/v1/appointments/{$foreign->id}")->assertNotFound();
    }

    public function test_manual_api_creation_is_validated_scoped_and_idempotent(): void
    {
        $this->actingAsAdminApi();
        $payload = $this->manualPayload();

        $this->postJson('/api/v1/appointments/manual', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');
        $first = $this->withHeader('Idempotency-Key', 'tenant-manual-1')
            ->postJson('/api/v1/appointments/manual', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', AppointmentStatus::Confirmed->value)
            ->assertJsonPath('data.internal_notes', 'Nota interna');
        $appointmentId = $first->json('data.id');
        $this->withHeader('Idempotency-Key', 'tenant-manual-1')
            ->postJson('/api/v1/appointments/manual', $payload)
            ->assertCreated()
            ->assertJsonPath('data.id', $appointmentId);

        $foreignCustomer = Customer::create([
            'tenant_id' => Tenant::factory()->create()->id,
            'name' => 'Ajeno',
            'email' => 'foreign-'.str()->random(6).'@example.test',
            'status' => 'active',
        ]);
        $this->withHeader('Idempotency-Key', 'tenant-manual-foreign')
            ->postJson('/api/v1/appointments/manual', array_merge($payload, [
                'customer_id' => $foreignCustomer->id,
            ]))
            ->assertNotFound();
    }

    public function test_api_can_confirm_reject_and_propose_with_stable_domain_errors(): void
    {
        $this->actingAsAdminApi();
        $confirmed = $this->appointment(['status' => AppointmentStatus::PendingTenant]);
        $this->withHeader('Idempotency-Key', 'confirm-http-1')
            ->postJson("/api/v1/appointments/{$confirmed->id}/confirm", [
                'internal_notes' => 'Confirmada desde API',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Confirmed->value);

        $rejected = $this->appointment([
            'status' => AppointmentStatus::PendingTenant,
            'starts_at' => $this->context['startsAtLocal']->addHour()->utc(),
            'ends_at' => $this->context['startsAtLocal']->addMinutes(90)->utc(),
        ]);
        $this->withHeader('Idempotency-Key', 'reject-http-1')
            ->postJson("/api/v1/appointments/{$rejected->id}/reject", ['reason' => 'Sin espacio'])
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Rejected->value);

        $proposed = $this->appointment([
            'status' => AppointmentStatus::PendingTenant,
            'starts_at' => $this->context['startsAtLocal']->addHours(2)->utc(),
            'ends_at' => $this->context['startsAtLocal']->addMinutes(150)->utc(),
        ]);
        $this->withHeader('Idempotency-Key', 'propose-http-1')
            ->postJson("/api/v1/appointments/{$proposed->id}/proposals", [
                'starts_at' => $this->context['startsAtLocal']->addHours(3)->toIso8601String(),
                'message' => 'Horario alternativo',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', AppointmentStatus::PendingCustomer->value);

        $this->withHeader('Idempotency-Key', 'confirm-http-invalid')
            ->postJson("/api/v1/appointments/{$confirmed->id}/confirm")
            ->assertConflict()
            ->assertJsonPath('code', 'APPOINTMENT_INVALID_TRANSITION');
    }

    public function test_api_can_cancel_complete_and_mark_no_show(): void
    {
        $this->actingAsAdminApi();
        $cancelled = $this->appointment(['status' => AppointmentStatus::Confirmed]);
        $this->withHeader('Idempotency-Key', 'cancel-http-1')
            ->postJson("/api/v1/appointments/{$cancelled->id}/cancel", ['reason' => 'Cierre del consultorio'])
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Cancelled->value);

        $completed = $this->pastAppointment();
        $this->withHeader('Idempotency-Key', 'complete-http-1')
            ->postJson("/api/v1/appointments/{$completed->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Completed->value);

        $noShow = $this->pastAppointment();
        $this->withHeader('Idempotency-Key', 'no-show-http-1')
            ->postJson("/api/v1/appointments/{$noShow->id}/no-show")
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::NoShow->value);
    }

    public function test_web_actions_use_same_domain_and_block_assistants(): void
    {
        $appointment = $this->appointment(['status' => AppointmentStatus::PendingTenant]);

        $this->actingAs($this->context['assistant'])
            ->post(route('client.agenda.confirm', $appointment), [
                'idempotency_key' => 'web-forbidden',
            ])
            ->assertForbidden();

        $this->actingAs($this->context['admin'])
            ->from('/client/agenda')
            ->post(route('client.agenda.confirm', $appointment), [
                'idempotency_key' => 'web-confirm-1',
                'internal_notes' => 'Confirmada desde web',
            ])
            ->assertRedirect('/client/agenda')
            ->assertSessionHas('success');

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertSame('Confirmada desde web', $appointment->fresh()->internal_notes);
    }

    public function test_web_agenda_renders_day_week_filters_menu_and_manual_form(): void
    {
        $pending = $this->appointment([
            'status' => AppointmentStatus::PendingTenant,
            'customer_reason' => 'Revision desde agenda',
        ]);

        $this->actingAs($this->context['admin'])
            ->get(route('client.agenda.index', [
                'view' => 'week',
                'date' => $this->date(),
                'statuses' => [AppointmentStatus::PendingTenant->value],
            ]))
            ->assertOk()
            ->assertSee('Agenda')
            ->assertSee('Semana')
            ->assertSee('Cita manual')
            ->assertSee($pending->animal_name_snapshot)
            ->assertSee(route('client.agenda.show', $pending))
            ->assertSee('agenda\\/disponibilidad', false);

        $this->actingAs($this->context['doctor'])
            ->get(route('client.agenda.index', ['view' => 'day', 'date' => $this->date()]))
            ->assertOk()
            ->assertSee('Dia');
    }

    public function test_web_agenda_shows_configuration_warning_and_stays_hidden_from_assistant_menu(): void
    {
        $this->context['tenant']->appointmentSetting->update(['doctor_user_id' => null]);

        $this->actingAs($this->context['admin'])
            ->get(route('client.agenda.index', ['date' => $this->date()]))
            ->assertOk()
            ->assertSee('Configuracion pendiente')
            ->assertSee('Configurar agenda')
            ->assertDontSee('+ Cita manual');

        $this->actingAs($this->context['assistant'])
            ->get(route('client.agenda.index'))
            ->assertForbidden();

        $this->actingAs($this->context['assistant'])
            ->view('layouts.client')
            ->assertDontSee(route('client.agenda.index'));
    }

    public function test_web_detail_renders_internal_data_history_and_transition_forms(): void
    {
        $appointment = $this->appointment([
            'status' => AppointmentStatus::PendingTenant,
            'customer_reason' => 'Motivo visible de prueba',
            'internal_notes' => 'Nota clinica interna',
        ]);
        AppointmentEvent::create([
            'tenant_id' => $this->context['tenant']->id,
            'appointment_id' => $appointment->id,
            'actor_user_id' => $this->context['admin']->id,
            'event_type' => AppointmentEventType::Requested,
            'new_status' => AppointmentStatus::PendingTenant,
            'metadata' => ['origin' => 'web-test'],
        ]);

        $this->actingAs($this->context['admin'])
            ->get(route('client.agenda.show', $appointment))
            ->assertOk()
            ->assertSee('Motivo visible de prueba')
            ->assertSee('Nota clinica interna')
            ->assertSee('Confirmar cita')
            ->assertSee('Rechazar solicitud')
            ->assertSee('Proponer otro horario')
            ->assertSee('origin')
            ->assertSee(route('client.agenda.confirm', $appointment))
            ->assertSee(route('client.agenda.reject', $appointment));
    }

    public function test_web_availability_returns_real_tenant_slots(): void
    {
        $this->actingAs($this->context['admin'])
            ->getJson(route('client.agenda.availability', [
                'service_id' => $this->context['service']->id,
                'from' => $this->date(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.'.$this->date().'.0.duration_minutes', 30);
    }

    public function test_tenant_write_rate_limit_is_enforced(): void
    {
        $this->actingAsAdminApi();

        foreach (range(1, 20) as $attempt) {
            $this->withHeader('Idempotency-Key', "tenant-rate-{$attempt}")
                ->postJson('/api/v1/appointments/manual', [])
                ->assertUnprocessable();
        }

        $this->withHeader('Idempotency-Key', 'tenant-rate-21')
            ->postJson('/api/v1/appointments/manual', [])
            ->assertTooManyRequests();
    }

    private function actingAsAdminApi(): void
    {
        Sanctum::actingAs($this->context['admin']);
    }

    private function manualPayload(): array
    {
        return [
            'customer_id' => $this->context['customer']->id,
            'animal_id' => $this->context['animal']->id,
            'service_id' => $this->context['service']->id,
            'starts_at' => $this->context['startsAtLocal']->toIso8601String(),
            'customer_reason' => 'Consulta manual',
            'internal_notes' => 'Nota interna',
        ];
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

    private function pastAppointment(): Appointment
    {
        return $this->appointment([
            'status' => AppointmentStatus::Confirmed,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->subMinutes(30),
            'confirmed_at' => now()->subDay(),
        ]);
    }

    private function date(): string
    {
        return $this->context['startsAtLocal']->toDateString();
    }

    private function scenario(): array
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $admin->assignRole('client-admin');
        $assistant = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $assistant->assignRole('asistente');
        $doctor = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        VeterinarianProfile::create([
            'tenant_id' => $tenant->id,
            'user_id' => $doctor->id,
            'professional_name' => 'Dra. HTTP',
            'professional_title' => 'Medica veterinaria',
            'license_number' => 'HTTP-'.str()->random(8),
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente HTTP',
            'email' => 'http-'.str()->random(8).'@example.test',
            'status' => 'active',
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
            'name' => 'Paciente HTTP',
            'sex' => 'unknown',
            'status' => 'active',
        ]);
        $service = CatalogItem::create([
            'tenant_id' => $tenant->id,
            'name' => 'Consulta HTTP',
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
            'minimum_notice_minutes' => 10080,
            'is_customer_booking_enabled' => false,
        ]);
        DoctorSchedule::create([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctor->id,
            'weekday' => $startsAtLocal->isoWeekday(),
            'starts_at' => '08:00',
            'ends_at' => '14:00',
            'is_active' => true,
        ]);

        return compact(
            'tenant',
            'admin',
            'assistant',
            'doctor',
            'customer',
            'animal',
            'service',
            'startsAtLocal',
        );
    }
}
