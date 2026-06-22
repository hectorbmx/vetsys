<?php

namespace Tests\Feature;

use App\Enums\AppointmentEventType;
use App\Enums\AppointmentStatus;
use App\Exceptions\AppointmentDomainException;
use App\Models\Animal;
use App\Models\AnimalType;
use App\Models\Appointment;
use App\Models\AppointmentSetting;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\DoctorSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VeterinarianProfile;
use App\Services\AppointmentService;
use App\Services\TenantAppointmentAccessService;
use App\Services\TenantAppointmentQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantAppointmentServicesTest extends TestCase
{
    use DatabaseTransactions;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['client-admin', 'asistente'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->context = $this->scenario();
    }

    public function test_only_admin_and_active_veterinarian_can_access_tenant_agenda(): void
    {
        $access = app(TenantAppointmentAccessService::class);

        $this->assertTrue($access->allows($this->context['tenant'], $this->context['admin']));
        $this->assertTrue($access->allows($this->context['tenant'], $this->context['doctor']));
        $this->assertFalse($access->allows($this->context['tenant'], $this->context['assistant']));

        $this->context['doctor']->veterinarianProfile->update(['is_active' => false]);
        $this->assertFalse($access->allows($this->context['tenant'], $this->context['doctor']));
        $this->assertFalse($access->allows(Tenant::factory()->create(), $this->context['admin']));
    }

    public function test_range_query_is_tenant_scoped_and_prioritizes_pending_requests(): void
    {
        $confirmed = $this->appointment([
            'status' => AppointmentStatus::Confirmed,
            'starts_at' => $this->context['startsAtLocal']->utc(),
        ]);
        $pending = $this->appointment([
            'status' => AppointmentStatus::PendingTenant,
            'starts_at' => $this->context['startsAtLocal']->addHour()->utc(),
        ]);
        Appointment::factory()->create([
            'tenant_id' => Tenant::factory(),
            'starts_at' => $this->context['startsAtLocal']->utc(),
        ]);
        $date = $this->context['startsAtLocal']->toDateString();
        $results = app(TenantAppointmentQueryService::class)
            ->queryForRange($this->context['tenant'], $this->context['admin'], $date, $date)
            ->get();

        $this->assertSame([$pending->id, $confirmed->id], $results->pluck('id')->all());
        $this->assertTrue($results->every(fn (Appointment $appointment) => $appointment->relationLoaded('pendingProposal')));
    }

    public function test_range_query_applies_status_customer_and_local_date_filters(): void
    {
        $matching = $this->appointment(['status' => AppointmentStatus::Confirmed]);
        $this->appointment([
            'status' => AppointmentStatus::PendingTenant,
            'starts_at' => $this->context['startsAtLocal']->addHour()->utc(),
        ]);
        $date = $this->context['startsAtLocal']->toDateString();
        $results = app(TenantAppointmentQueryService::class)
            ->queryForRange($this->context['tenant'], $this->context['admin'], $date, $date, [
                'statuses' => [AppointmentStatus::Confirmed],
                'customer_id' => $this->context['customer']->id,
                'animal_id' => $this->context['animal']->id,
            ])
            ->get();

        $this->assertSame([$matching->id], $results->pluck('id')->all());

        $this->expectException(AppointmentDomainException::class);
        app(TenantAppointmentQueryService::class)->queryForRange(
            $this->context['tenant'],
            $this->context['admin'],
            $date,
            CarbonImmutable::parse($date)->addDays(62)->toDateString(),
        );
    }

    public function test_detail_and_form_options_are_scoped_and_only_include_active_records(): void
    {
        $appointment = $this->appointment();
        $otherCustomer = Customer::create([
            'tenant_id' => $this->context['tenant']->id,
            'name' => 'Inactivo',
            'email' => 'inactive-'.str()->random(6).'@example.test',
            'status' => 'inactive',
        ]);
        $inactiveService = CatalogItem::create([
            'tenant_id' => $this->context['tenant']->id,
            'name' => 'No reservable',
            'type' => 'service',
            'is_active' => true,
            'is_bookable' => false,
        ]);
        $queries = app(TenantAppointmentQueryService::class);
        $detail = $queries->detail($this->context['tenant'], $this->context['doctor'], $appointment->id);
        $options = $queries->formOptions($this->context['tenant'], $this->context['admin']);

        $this->assertTrue($detail->relationLoaded('events'));
        $this->assertContains($this->context['customer']->id, $options['customers']->pluck('id'));
        $this->assertNotContains($otherCustomer->id, $options['customers']->pluck('id'));
        $this->assertContains($this->context['service']->id, $options['services']->pluck('id'));
        $this->assertNotContains($inactiveService->id, $options['services']->pluck('id'));
    }

    public function test_admin_creates_confirmed_manual_appointment_without_customer_portal_access(): void
    {
        $appointments = app(AppointmentService::class);
        $first = $appointments->createManual(
            $this->context['tenant'],
            $this->context['admin'],
            $this->context['customer'],
            $this->context['animal'],
            $this->context['service'],
            $this->context['startsAtLocal']->utc(),
            customerReason: 'Consulta telefonica',
            internalNotes: 'Paciente nervioso',
            idempotencyKey: 'manual-1',
        );
        $second = $appointments->createManual(
            $this->context['tenant'],
            $this->context['admin'],
            $this->context['customer'],
            $this->context['animal'],
            $this->context['service'],
            $this->context['startsAtLocal']->utc(),
            customerReason: 'Consulta telefonica',
            internalNotes: 'Paciente nervioso',
            idempotencyKey: 'manual-1',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(AppointmentStatus::Confirmed, $first->status);
        $this->assertSame($this->context['admin']->id, $first->created_by_user_id);
        $this->assertSame('Paciente nervioso', $first->internal_notes);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $first->id,
            'event_type' => AppointmentEventType::CreatedManually->value,
            'new_status' => AppointmentStatus::Confirmed->value,
        ]);
        $this->assertDatabaseMissing('customer_portal_accesses', [
            'customer_id' => $this->context['customer']->id,
        ]);
    }

    public function test_active_veterinarian_can_create_manual_appointment(): void
    {
        $appointment = app(AppointmentService::class)->createManual(
            $this->context['tenant'],
            $this->context['doctor'],
            $this->context['customer'],
            $this->context['animal'],
            $this->context['service'],
            $this->context['startsAtLocal']->utc(),
        );

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertSame($this->context['doctor']->id, $appointment->created_by_user_id);
    }

    public function test_manual_creation_rejects_assistant_conflicts_and_foreign_participants(): void
    {
        $appointments = app(AppointmentService::class);
        $this->captureDomainException(fn () => $appointments->createManual(
            $this->context['tenant'],
            $this->context['assistant'],
            $this->context['customer'],
            $this->context['animal'],
            $this->context['service'],
            $this->context['startsAtLocal']->utc(),
        ), 'APPOINTMENT_FORBIDDEN');

        $appointments->createManual(
            $this->context['tenant'],
            $this->context['admin'],
            $this->context['customer'],
            $this->context['animal'],
            $this->context['service'],
            $this->context['startsAtLocal']->utc(),
        );
        $this->captureDomainException(fn () => $appointments->createManual(
            $this->context['tenant'],
            $this->context['admin'],
            $this->context['customer'],
            $this->context['animal'],
            $this->context['service'],
            $this->context['startsAtLocal']->utc(),
        ), 'APPOINTMENT_SLOT_UNAVAILABLE');

        $foreignAnimal = Animal::where('id', '!=', $this->context['animal']->id)->first();
        if (! $foreignAnimal) {
            $foreignTenant = Tenant::factory()->create();
            $foreignCustomer = Customer::create([
                'tenant_id' => $foreignTenant->id,
                'name' => 'Ajeno',
                'email' => 'foreign-'.str()->random(6).'@example.test',
                'status' => 'active',
            ]);
            $foreignAnimal = Animal::create([
                'tenant_id' => $foreignTenant->id,
                'customer_id' => $foreignCustomer->id,
                'animal_type_id' => $this->context['animal']->animal_type_id,
                'name' => 'Ajeno',
                'sex' => 'unknown',
                'status' => 'active',
            ]);
        }
        $this->captureDomainException(fn () => $appointments->createManual(
            $this->context['tenant'],
            $this->context['admin'],
            $this->context['customer'],
            $foreignAnimal,
            $this->context['service'],
            $this->context['startsAtLocal']->addHour()->utc(),
        ), 'APPOINTMENT_PARTICIPANTS_INVALID');

        $this->assertSame(1, Appointment::where('tenant_id', $this->context['tenant']->id)->count());
    }

    private function appointment(array $overrides = []): Appointment
    {
        $startsAt = $overrides['starts_at'] ?? $this->context['startsAtLocal']->utc();

        return Appointment::factory()->create(array_merge([
            'tenant_id' => $this->context['tenant']->id,
            'customer_id' => $this->context['customer']->id,
            'animal_id' => $this->context['animal']->id,
            'doctor_user_id' => $this->context['doctor']->id,
            'catalog_item_id' => $this->context['service']->id,
            'starts_at' => $startsAt,
            'ends_at' => CarbonImmutable::instance($startsAt)->addMinutes(30),
        ], $overrides));
    }

    private function captureDomainException(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail("Se esperaba {$code}.");
        } catch (AppointmentDomainException $exception) {
            $this->assertSame($code, $exception->errorCode);
        }
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
            'professional_name' => 'Dra. Agenda',
            'professional_title' => 'Medica veterinaria',
            'license_number' => 'AGENDA-'.str()->random(8),
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Agenda',
            'email' => 'agenda-'.str()->random(8).'@example.test',
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
            'name' => 'Paciente Agenda',
            'sex' => 'unknown',
            'status' => 'active',
        ]);
        $service = CatalogItem::create([
            'tenant_id' => $tenant->id,
            'name' => 'Consulta agenda',
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
            'default_duration_minutes' => 30,
        ]);
        DoctorSchedule::create([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctor->id,
            'weekday' => $startsAtLocal->isoWeekday(),
            'starts_at' => '08:00',
            'ends_at' => '13:00',
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
