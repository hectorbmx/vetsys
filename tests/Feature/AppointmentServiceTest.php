<?php

namespace Tests\Feature;

use App\Enums\AppointmentCancellationFeeStatus;
use App\Enums\AppointmentCancellationPolicy;
use App\Enums\AppointmentEventType;
use App\Enums\AppointmentProposalStatus;
use App\Enums\AppointmentStatus;
use App\Exceptions\AppointmentDomainException;
use App\Models\Animal;
use App\Models\AnimalType;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentScheduleLock;
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
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_request_is_audited_and_idempotent(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $startsAt = $this->utc('2026-07-06 09:00');
        $now = $this->utc('2026-07-06 07:00');

        $first = $appointments->request(
            $context['tenant'],
            $context['customerUser'],
            $context['customer'],
            $context['animal'],
            $context['service'],
            $startsAt,
            'Revision general',
            'request-1',
            $now,
        );
        $second = $appointments->request(
            $context['tenant'],
            $context['customerUser'],
            $context['customer'],
            $context['animal'],
            $context['service'],
            $startsAt,
            'Revision general',
            'request-1',
            $now,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(AppointmentStatus::PendingTenant, $first->status);
        $this->assertSame(1, Appointment::where('tenant_id', $context['tenant']->id)->count());
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $first->id,
            'event_type' => AppointmentEventType::Requested->value,
        ]);
        $this->assertDatabaseHas('appointment_idempotency_keys', [
            'operation' => 'request',
            'idempotency_key' => 'request-1',
            'status' => 'completed',
        ]);
    }

    public function test_reusing_idempotency_key_with_different_payload_is_rejected(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $now = $this->utc('2026-07-06 07:00');
        $appointments->request(
            $context['tenant'],
            $context['customerUser'],
            $context['customer'],
            $context['animal'],
            $context['service'],
            $this->utc('2026-07-06 09:00'),
            null,
            'same-key',
            $now,
        );

        $exception = $this->captureDomainException(fn () => $appointments->request(
            $context['tenant'],
            $context['customerUser'],
            $context['customer'],
            $context['animal'],
            $context['service'],
            $this->utc('2026-07-06 09:30'),
            null,
            'same-key',
            $now,
        ));

        $this->assertSame('APPOINTMENT_IDEMPOTENCY_PAYLOAD_MISMATCH', $exception->errorCode);
        $this->assertSame(1, Appointment::where('tenant_id', $context['tenant']->id)->count());
    }

    public function test_two_pending_requests_can_share_slot_but_only_one_can_be_confirmed(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $now = $this->utc('2026-07-06 07:00');
        $first = $this->request($appointments, $context, '2026-07-06 09:00', $now);
        $second = $this->request($appointments, $context, '2026-07-06 09:00', $now);

        $confirmed = $appointments->confirm($first, $context['admin'], now: $now);
        $exception = $this->captureDomainException(
            fn () => $appointments->confirm($second, $context['admin'], now: $now)
        );

        $this->assertSame(AppointmentStatus::Confirmed, $confirmed->status);
        $this->assertSame('APPOINTMENT_SLOT_UNAVAILABLE', $exception->errorCode);
        $this->assertSame(AppointmentStatus::PendingTenant, $second->fresh()->status);
        $this->assertSame(1, AppointmentScheduleLock::where('tenant_id', $context['tenant']->id)->count());
        $this->assertSame(1, AppointmentEvent::where('tenant_id', $context['tenant']->id)
            ->where('event_type', AppointmentEventType::Confirmed->value)
            ->count());
    }

    public function test_confirm_transition_is_idempotent_with_same_key(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $now = $this->utc('2026-07-06 07:00');
        $appointment = $this->request($appointments, $context, '2026-07-06 09:00', $now);

        $first = $appointments->confirm(
            $appointment,
            $context['admin'],
            idempotencyKey: 'confirm-1',
            now: $now,
        );
        $second = $appointments->confirm(
            $appointment,
            $context['admin'],
            idempotencyKey: 'confirm-1',
            now: $now,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(AppointmentStatus::Confirmed, $second->status);
        $this->assertSame(1, AppointmentEvent::where([
            'appointment_id' => $appointment->id,
            'event_type' => AppointmentEventType::Confirmed->value,
        ])->count());
    }

    public function test_tenant_can_reject_with_visible_reason(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $now = $this->utc('2026-07-06 07:00');
        $appointment = $this->request($appointments, $context, '2026-07-06 09:00', $now);

        $rejected = $appointments->reject($appointment, $context['admin'], 'No hay personal disponible', now: $now);

        $this->assertSame(AppointmentStatus::Rejected, $rejected->status);
        $this->assertSame('No hay personal disponible', $rejected->rejection_reason);
        $this->assertNotNull($rejected->rejected_at);
    }

    public function test_active_veterinarian_can_confirm_appointment(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $now = $this->utc('2026-07-06 07:00');
        $appointment = $this->request($appointments, $context, '2026-07-06 09:00', $now);

        $confirmed = $appointments->confirm($appointment, $context['doctor'], now: $now);

        $this->assertSame(AppointmentStatus::Confirmed, $confirmed->status);
    }

    public function test_customer_with_revoked_patient_assignment_cannot_request(): void
    {
        $context = $this->context();
        FinalUserPatientAssignment::where([
            'tenant_id' => $context['tenant']->id,
            'user_id' => $context['customerUser']->id,
            'animal_id' => $context['animal']->id,
        ])->update(['revoked_at' => now()]);

        $exception = $this->captureDomainException(fn () => $this->request(
            app(AppointmentService::class),
            $context,
            '2026-07-06 09:00',
            $this->utc('2026-07-06 07:00'),
        ));

        $this->assertSame('CUSTOMER_PORTAL_ACCESS_INACTIVE', $exception->errorCode);
        $this->assertDatabaseMissing('appointments', ['tenant_id' => $context['tenant']->id]);
    }

    public function test_customer_accepts_proposal_and_it_becomes_confirmed(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $now = $this->utc('2026-07-06 07:00');
        $appointment = $this->request($appointments, $context, '2026-07-06 09:00', $now);
        $proposal = $appointments->propose(
            $appointment,
            $context['admin'],
            $this->utc('2026-07-06 10:00'),
            message: 'Podemos a las diez',
            now: $now,
        );

        $confirmed = $appointments->acceptProposal(
            $appointment,
            $proposal,
            $context['customerUser'],
            now: $now,
        );

        $this->assertSame(AppointmentProposalStatus::Accepted, $proposal->fresh()->status);
        $this->assertSame(AppointmentStatus::Confirmed, $confirmed->status);
        $this->assertTrue($confirmed->starts_at->equalTo($this->utc('2026-07-06 10:00')));
    }

    public function test_replacing_and_rejecting_proposal_restores_original_state(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $now = $this->utc('2026-07-06 07:00');
        $appointment = $this->request($appointments, $context, '2026-07-06 09:00', $now);
        $first = $appointments->propose(
            $appointment,
            $context['admin'],
            $this->utc('2026-07-06 10:00'),
            now: $now,
        );
        $second = $appointments->propose(
            $appointment,
            $context['admin'],
            $this->utc('2026-07-06 11:00'),
            now: $now,
        );

        $restored = $appointments->rejectProposal(
            $appointment,
            $second,
            $context['customerUser'],
            'No puedo asistir',
            now: $now,
        );

        $this->assertSame(AppointmentProposalStatus::Superseded, $first->fresh()->status);
        $this->assertSame(AppointmentProposalStatus::Rejected, $second->fresh()->status);
        $this->assertSame(AppointmentStatus::PendingTenant, $restored->status);
    }

    public function test_expired_proposal_cannot_be_accepted_before_expiration_job_runs(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $requestNow = $this->utc('2026-07-05 07:00');
        $appointment = $this->request($appointments, $context, '2026-07-06 09:00', $requestNow);
        $proposal = $appointments->propose(
            $appointment,
            $context['admin'],
            $this->utc('2026-07-06 10:00'),
            now: $requestNow,
        );

        $exception = $this->captureDomainException(fn () => $appointments->acceptProposal(
            $appointment,
            $proposal,
            $context['customerUser'],
            now: $this->utc('2026-07-06 08:00'),
        ));

        $this->assertSame('APPOINTMENT_PROPOSAL_EXPIRED', $exception->errorCode);
        $this->assertSame(AppointmentProposalStatus::Pending, $proposal->fresh()->status);
        $this->assertSame(AppointmentStatus::PendingCustomer, $appointment->fresh()->status);
    }

    public function test_rejected_reprogramming_restores_confirmed_original_time(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $now = $this->utc('2026-07-06 07:00');
        $appointment = $this->request($appointments, $context, '2026-07-06 09:00', $now);
        $appointment = $appointments->confirm($appointment, $context['admin'], now: $now);
        $originalStart = $appointment->starts_at->copy();
        $proposal = $appointments->propose(
            $appointment,
            $context['admin'],
            $this->utc('2026-07-06 10:00'),
            now: $now,
        );

        $restored = $appointments->rejectProposal(
            $appointment,
            $proposal,
            $context['customerUser'],
            now: $now,
        );

        $this->assertSame(AppointmentStatus::Confirmed, $restored->status);
        $this->assertTrue($restored->starts_at->equalTo($originalStart));
    }

    public function test_late_customer_cancellation_is_marked_for_fee_review(): void
    {
        $context = $this->context();
        $context['setting']->update([
            'cancellation_policy' => AppointmentCancellationPolicy::LateFeeReview,
            'late_fee_type' => 'fixed',
            'late_fee_value' => 50,
            'customer_cancellation_notice_minutes' => 1440,
        ]);
        $appointments = app(AppointmentService::class);
        $requestNow = $this->utc('2026-07-06 07:00');
        $appointment = $this->request($appointments, $context, '2026-07-06 10:00', $requestNow);
        $appointment = $appointments->confirm($appointment, $context['admin'], now: $requestNow);

        $cancelled = $appointments->cancel(
            $appointment,
            $context['customerUser'],
            'No podre asistir',
            now: $this->utc('2026-07-06 09:00'),
        );

        $this->assertSame(AppointmentStatus::Cancelled, $cancelled->status);
        $this->assertTrue($cancelled->is_late_cancellation);
        $this->assertSame(AppointmentCancellationFeeStatus::PendingReview, $cancelled->cancellation_fee_status);
        $this->assertSame('50.00', $cancelled->cancellation_fee_amount);
    }

    public function test_complete_and_no_show_require_started_confirmed_appointment(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $now = $this->utc('2026-07-06 07:00');
        $first = $this->request($appointments, $context, '2026-07-06 09:00', $now);
        $first = $appointments->confirm($first, $context['admin'], now: $now);
        $exception = $this->captureDomainException(
            fn () => $appointments->complete($first, $context['admin'], now: $this->utc('2026-07-06 08:00'))
        );
        $completed = $appointments->complete(
            $first,
            $context['admin'],
            now: $this->utc('2026-07-06 09:30'),
        );

        $second = $this->request($appointments, $context, '2026-07-06 10:00', $now);
        $second = $appointments->confirm($second, $context['admin'], now: $now);
        $noShow = $appointments->markNoShow(
            $second,
            $context['admin'],
            now: $this->utc('2026-07-06 10:30'),
        );

        $this->assertSame('APPOINTMENT_NOT_STARTED', $exception->errorCode);
        $this->assertSame(AppointmentStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame(AppointmentStatus::NoShow, $noShow->status);
        $this->assertNotNull($noShow->no_show_at);
    }

    public function test_expiration_command_restores_previous_state_and_is_idempotent(): void
    {
        $context = $this->context();
        $appointments = app(AppointmentService::class);
        $requestNow = $this->utc('2026-07-05 07:00');
        $appointment = $this->request($appointments, $context, '2026-07-06 09:00', $requestNow);
        $proposal = $appointments->propose(
            $appointment,
            $context['admin'],
            $this->utc('2026-07-06 10:00'),
            now: $requestNow,
        );

        $this->travelTo($this->utc('2026-07-06 08:00'));
        $this->artisan('appointments:expire-proposals')
            ->expectsOutput('Propuestas expiradas: 1.')
            ->assertSuccessful();
        $this->artisan('appointments:expire-proposals')
            ->expectsOutput('Propuestas expiradas: 0.')
            ->assertSuccessful();
        $this->travelBack();

        $this->assertSame(AppointmentProposalStatus::Expired, $proposal->fresh()->status);
        $this->assertSame(AppointmentStatus::PendingTenant, $appointment->fresh()->status);
    }

    public function test_unauthorized_actor_and_conflicting_proposal_roll_back(): void
    {
        $context = $this->context();
        $assistant = User::factory()->create(['tenant_id' => $context['tenant']->id, 'is_active' => true]);
        Role::findOrCreate('asistente', 'web');
        $assistant->assignRole('asistente');
        $appointments = app(AppointmentService::class);
        $now = $this->utc('2026-07-06 07:00');
        $appointment = $this->request($appointments, $context, '2026-07-06 09:00', $now);
        $forbidden = $this->captureDomainException(
            fn () => $appointments->confirm($appointment, $assistant, now: $now)
        );
        $other = $this->request($appointments, $context, '2026-07-06 10:00', $now);
        $appointments->confirm($other, $context['admin'], now: $now);
        $eventsBefore = AppointmentEvent::where('appointment_id', $appointment->id)->count();
        $conflict = $this->captureDomainException(fn () => $appointments->propose(
            $appointment,
            $context['admin'],
            $this->utc('2026-07-06 10:00'),
            now: $now,
        ));

        $this->assertSame('APPOINTMENT_FORBIDDEN', $forbidden->errorCode);
        $this->assertSame('APPOINTMENT_SLOT_UNAVAILABLE', $conflict->errorCode);
        $this->assertSame(AppointmentStatus::PendingTenant, $appointment->fresh()->status);
        $this->assertSame($eventsBefore, AppointmentEvent::where('appointment_id', $appointment->id)->count());
        $this->assertDatabaseMissing('appointment_proposals', ['appointment_id' => $appointment->id]);
    }

    public function test_appointment_events_cannot_be_updated_or_deleted(): void
    {
        $context = $this->context();
        $appointment = $this->request(
            app(AppointmentService::class),
            $context,
            '2026-07-06 09:00',
            $this->utc('2026-07-06 07:00'),
        );
        $event = $appointment->events()->firstOrFail();

        $this->expectException(LogicException::class);
        $event->update(['metadata' => ['tampered' => true]]);
    }

    private function context(): array
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        Role::findOrCreate('client-admin', 'web');
        $admin->assignRole('client-admin');
        $doctor = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        VeterinarianProfile::create([
            'tenant_id' => $tenant->id,
            'user_id' => $doctor->id,
            'professional_name' => 'Dra. Dominio',
            'professional_title' => 'MVZ',
            'license_number' => 'DOM-'.str()->upper(str()->random(8)),
            'is_active' => true,
        ]);
        $setting = AppointmentSetting::factory()->create([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctor->id,
            'timezone' => 'America/Mexico_City',
            'slot_interval_minutes' => 30,
            'minimum_notice_minutes' => 0,
            'booking_window_days' => 60,
            'proposal_hold_hours' => 24,
            'is_customer_booking_enabled' => true,
        ]);
        DoctorSchedule::create([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctor->id,
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'is_active' => true,
        ]);
        $service = CatalogItem::create([
            'tenant_id' => $tenant->id,
            'name' => 'Consulta general',
            'type' => 'service',
            'is_active' => true,
            'is_bookable' => true,
            'appointment_duration_minutes' => 30,
            'appointment_buffer_minutes' => 0,
        ]);
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Customer',
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
            'name' => 'Paciente',
            'status' => 'active',
        ]);
        $customerUser = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        Role::findOrCreate('customer', 'web');
        $customerUser->assignRole('customer');
        CustomerUserLink::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $customerUser->id,
            'relationship' => 'owner',
            'is_primary' => true,
        ]);
        CustomerPortalAccess::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $customerUser->id,
            'status' => 'active',
            'billing_mode' => 'free',
            'activated_at' => now(),
        ]);
        FinalUserPatientAssignment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $customerUser->id,
            'animal_id' => $animal->id,
            'assigned_at' => now(),
        ]);

        return compact(
            'tenant',
            'admin',
            'doctor',
            'setting',
            'service',
            'customer',
            'animal',
            'customerUser',
        );
    }

    private function request(
        AppointmentService $appointments,
        array $context,
        string $localStartsAt,
        CarbonImmutable $now,
    ): Appointment {
        return $appointments->request(
            $context['tenant'],
            $context['customerUser'],
            $context['customer'],
            $context['animal'],
            $context['service'],
            $this->utc($localStartsAt),
            now: $now,
        );
    }

    private function utc(string $localDateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($localDateTime, 'America/Mexico_City')->utc();
    }

    private function captureDomainException(callable $callback): AppointmentDomainException
    {
        try {
            $callback();
        } catch (AppointmentDomainException $exception) {
            return $exception;
        }

        $this->fail('Se esperaba AppointmentDomainException.');
    }
}
