<?php

namespace Tests\Feature;

use App\Enums\AppointmentEventType;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Jobs\SendAppointmentEmail;
use App\Mail\AppointmentEventMail;
use App\Models\Animal;
use App\Models\AnimalPortalVisibilitySetting;
use App\Models\AnimalType;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentNotificationDelivery;
use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Models\CustomerUserLink;
use App\Models\FinalUserPatientAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VeterinarianProfile;
use App\Services\AppointmentNotificationService;
use App\Services\TenantAppointmentAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_requested_event_queues_and_sends_email_to_operators_and_customer_only(): void
    {
        Queue::fake();
        Mail::fake();
        $context = $this->context();
        $event = $this->event($context, AppointmentEventType::Requested);
        $notifications = app(AppointmentNotificationService::class);

        $notifications->process($event);

        Queue::assertPushed(SendAppointmentEmail::class, 3);
        $this->assertSame(3, $this->emailDeliveries($event)->count());
        $this->assertDatabaseMissing('appointment_notification_deliveries', [
            'appointment_event_id' => $event->id,
            'recipient_user_id' => $context['assistant']->id,
            'channel' => NotificationDeliveryChannel::Email->value,
        ]);

        foreach ($this->emailDeliveries($event) as $delivery) {
            $this->send($delivery);
        }

        Mail::assertSent(AppointmentEventMail::class, 3);
        $sent = Mail::sent(AppointmentEventMail::class);
        $this->assertEqualsCanonicalizing(
            [$context['admin']->email, $context['vet']->email, $context['customerUser']->email],
            $sent->flatMap(fn (AppointmentEventMail $mail) => collect($mail->to)->pluck('address'))->all(),
        );
        $this->assertTrue($sent->contains(fn (AppointmentEventMail $mail) => $mail->mailData['subject'] === 'Nueva solicitud de cita para Luna'));
        $this->assertTrue($sent->contains(fn (AppointmentEventMail $mail) => $mail->mailData['subject'] === 'Recibimos tu solicitud de cita'));
        $this->assertSame(3, $this->emailDeliveries($event)
            ->where('status', NotificationDeliveryStatus::Delivered)
            ->count());
    }

    public function test_email_matrix_avoids_redundant_channels(): void
    {
        Queue::fake();
        $context = $this->context();
        $notifications = app(AppointmentNotificationService::class);
        $confirmed = $this->event($context, AppointmentEventType::Confirmed);
        $accepted = $this->event($context, AppointmentEventType::ProposalAccepted);
        $completed = $this->event($context, AppointmentEventType::Completed);

        $notifications->process($confirmed);
        $notifications->process($accepted);
        $notifications->process($completed);

        $this->assertSame(1, $this->emailDeliveries($confirmed)->count());
        $this->assertSame($context['customerUser']->id, $this->emailDeliveries($confirmed)->first()->recipient_user_id);
        $this->assertSame(2, $this->emailDeliveries($accepted)->count());
        $this->assertEqualsCanonicalizing(
            [$context['admin']->id, $context['vet']->id],
            $this->emailDeliveries($accepted)->pluck('recipient_user_id')->all(),
        );
        $this->assertSame(0, $this->emailDeliveries($completed)->count());
    }

    public function test_every_event_matches_the_email_recipient_matrix(): void
    {
        Queue::fake();
        $context = $this->context();
        $notifications = app(AppointmentNotificationService::class);
        $cases = [
            [AppointmentEventType::Requested, [], 3],
            [AppointmentEventType::CreatedManually, [], 1],
            [AppointmentEventType::Confirmed, [], 1],
            [AppointmentEventType::Rejected, [], 1],
            [AppointmentEventType::Proposed, [], 1],
            [AppointmentEventType::ProposalAccepted, [], 2],
            [AppointmentEventType::ProposalRejected, [], 2],
            [AppointmentEventType::ProposalExpired, [], 1],
            [AppointmentEventType::Cancelled, ['actor_type' => 'customer'], 3],
            [AppointmentEventType::Cancelled, ['actor_type' => 'tenant'], 1],
            [AppointmentEventType::Completed, [], 0],
            [AppointmentEventType::NoShow, [], 1],
            [AppointmentEventType::LateFeePending, [], 0],
            [AppointmentEventType::LateFeeWaived, [], 0],
            [AppointmentEventType::LateFeeCharged, [], 1],
        ];

        foreach ($cases as [$type, $metadata, $expected]) {
            $event = $this->event($context, $type, $metadata);
            $notifications->process($event);
            $this->assertSame($expected, $this->emailDeliveries($event)->count(), $type->value);
        }
    }

    public function test_email_contains_visible_reason_and_excludes_internal_information(): void
    {
        Queue::fake();
        Mail::fake();
        $context = $this->context();
        $context['appointment']->update([
            'rejection_reason' => 'No hay personal disponible',
            'customer_reason' => 'Detalle privado del customer',
            'internal_notes' => 'NOTA INTERNA SECRETA',
        ]);
        $event = $this->event($context, AppointmentEventType::Rejected);
        app(AppointmentNotificationService::class)->process($event);

        $delivery = $this->emailDeliveries($event)->firstOrFail();
        $this->send($delivery);

        Mail::assertSent(AppointmentEventMail::class, function (AppointmentEventMail $mail) {
            $html = $mail->render();

            return $mail->hasSubject('Tu solicitud no pudo ser confirmada')
                && str_contains($html, 'No hay personal disponible')
                && ! str_contains($html, 'NOTA INTERNA SECRETA')
                && ! str_contains($html, 'Detalle privado del customer');
        });
    }

    public function test_revoked_customer_is_skipped_when_job_runs(): void
    {
        Queue::fake();
        Mail::fake();
        $context = $this->context();
        $event = $this->event($context, AppointmentEventType::Confirmed);
        app(AppointmentNotificationService::class)->process($event);
        $delivery = $this->emailDeliveries($event)->firstOrFail();
        $context['access']->update(['revoked_at' => now()]);

        $this->send($delivery);

        Mail::assertNothingSent();
        $this->assertSame(NotificationDeliveryStatus::Skipped, $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
    }

    public function test_smtp_failure_is_sanitized_and_can_be_retried_without_new_delivery(): void
    {
        Queue::fake();
        $context = $this->context();
        $event = $this->event($context, AppointmentEventType::Confirmed);
        $notifications = app(AppointmentNotificationService::class);
        $notifications->process($event);
        $delivery = $this->emailDeliveries($event)->firstOrFail();
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP password=super-secret'));

        try {
            $this->send($delivery);
            $this->fail('Se esperaba una falla SMTP.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('SMTP', $exception->getMessage());
        }

        $failed = $delivery->fresh();
        $this->assertSame(NotificationDeliveryStatus::Failed, $failed->status);
        $this->assertSame('RuntimeException: mail delivery failed', $failed->last_error);
        $this->assertStringNotContainsString('super-secret', $failed->last_error);
        $this->assertSame(1, $failed->attempts);

        $notifications->process($event);
        $this->assertSame(1, $this->emailDeliveries($event)->count());

        Mail::fake();
        $this->send($failed);
        Mail::assertSent(AppointmentEventMail::class, 1);
        $this->assertSame(NotificationDeliveryStatus::Delivered, $failed->fresh()->status);
        $this->assertSame(2, $failed->fresh()->attempts);
    }

    private function send(AppointmentNotificationDelivery $delivery): void
    {
        (new SendAppointmentEmail($delivery->id))->handle(
            app(AppointmentNotificationService::class),
            app(TenantAppointmentAccessService::class),
        );
    }

    private function emailDeliveries(AppointmentEvent $event)
    {
        return AppointmentNotificationDelivery::query()
            ->where('appointment_event_id', $event->id)
            ->where('channel', NotificationDeliveryChannel::Email->value)
            ->get();
    }

    private function context(): array
    {
        foreach (['client-admin', 'asistente', 'customer'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $tenant = Tenant::factory()->create(['business_name' => 'Veterinaria Central']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $admin->assignRole('client-admin');
        $vet = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        VeterinarianProfile::create([
            'tenant_id' => $tenant->id,
            'user_id' => $vet->id,
            'professional_name' => 'Dra. Correo',
            'professional_title' => 'MVZ',
            'license_number' => 'MAIL-'.str()->random(8),
            'is_active' => true,
        ]);
        $assistant = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $assistant->assignRole('asistente');
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente',
            'last_name' => 'Correo',
            'email' => fake()->unique()->safeEmail(),
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
            'name' => 'Luna',
            'status' => 'active',
        ]);
        $customerUser = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $customerUser->assignRole('customer');
        CustomerUserLink::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $customerUser->id,
            'relationship' => 'owner',
            'is_primary' => true,
        ]);
        $access = CustomerPortalAccess::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $customerUser->id,
            'status' => 'active',
            'billing_mode' => 'free',
            'access_starts_at' => now()->subDay(),
        ]);
        FinalUserPatientAssignment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $customerUser->id,
            'animal_id' => $animal->id,
            'assigned_at' => now(),
        ]);
        AnimalPortalVisibilitySetting::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $customerUser->id,
            'animal_id' => $animal->id,
            'show_appointments' => true,
        ]);
        $startsAt = now()->addDay()->startOfHour();
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'animal_id' => $animal->id,
            'doctor_user_id' => $vet->id,
            'animal_name_snapshot' => $animal->name,
            'service_name_snapshot' => 'Consulta general',
            'doctor_name_snapshot' => 'Dra. Correo',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'timezone' => 'America/Mexico_City',
        ]);

        return compact(
            'tenant', 'admin', 'vet', 'assistant', 'customer', 'animal',
            'customerUser', 'access', 'appointment',
        );
    }

    private function event(array $context, AppointmentEventType $type, array $metadata = []): AppointmentEvent
    {
        return AppointmentEvent::create([
            'tenant_id' => $context['tenant']->id,
            'appointment_id' => $context['appointment']->id,
            'actor_user_id' => $context['customerUser']->id,
            'event_type' => $type,
            'previous_status' => AppointmentStatus::PendingTenant,
            'new_status' => AppointmentStatus::Confirmed,
            'metadata' => $metadata ?: null,
        ]);
    }
}
