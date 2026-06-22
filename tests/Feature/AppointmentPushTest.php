<?php

namespace Tests\Feature;

use App\Contracts\PushGateway;
use App\Enums\AppointmentEventType;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\PushPlatform;
use App\Exceptions\InvalidPushTokenException;
use App\Exceptions\TransientPushException;
use App\Jobs\SendAppointmentPush;
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
use App\Models\PushDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VeterinarianProfile;
use App\Services\AppointmentNotificationService;
use App\Services\DisabledPushGateway;
use App\Services\TenantAppointmentAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentPushTest extends TestCase
{
    use DatabaseTransactions;

    public function test_disabled_environment_resolves_without_firebase_credentials(): void
    {
        config(['appointment_push.enabled' => false]);

        $this->assertInstanceOf(DisabledPushGateway::class, app(PushGateway::class));
    }

    public function test_requested_event_queues_active_operator_and_customer_devices_only(): void
    {
        Queue::fake();
        $context = $this->context();
        $this->device($context['admin']);
        $this->device($context['vet']);
        $this->device($context['customerUser']);
        $this->device($context['assistant']);
        $revoked = $this->device($context['customerUser']);
        $revoked->update(['revoked_at' => now()]);
        $event = $this->event($context, AppointmentEventType::Requested);

        app(AppointmentNotificationService::class)->process($event);

        Queue::assertPushed(SendAppointmentPush::class, 3);
        $this->assertEqualsCanonicalizing(
            [$context['admin']->id, $context['vet']->id, $context['customerUser']->id],
            $this->pushDeliveries($event)->pluck('recipient_user_id')->all(),
        );
        $this->assertFalse($this->pushDeliveries($event)->contains('push_device_id', $revoked->id));
    }

    public function test_every_event_matches_the_push_recipient_matrix(): void
    {
        Queue::fake();
        $context = $this->context();
        $this->device($context['admin']);
        $this->device($context['vet']);
        $this->device($context['customerUser']);
        $cases = [
            [AppointmentEventType::Requested, [], 3],
            [AppointmentEventType::CreatedManually, [], 1],
            [AppointmentEventType::Confirmed, [], 1],
            [AppointmentEventType::Rejected, [], 1],
            [AppointmentEventType::Proposed, [], 1],
            [AppointmentEventType::ProposalAccepted, [], 2],
            [AppointmentEventType::ProposalRejected, [], 2],
            [AppointmentEventType::ProposalExpired, [], 1],
            [AppointmentEventType::Cancelled, ['actor_type' => 'customer'], 2],
            [AppointmentEventType::Cancelled, ['actor_type' => 'tenant'], 1],
            [AppointmentEventType::Completed, [], 0],
            [AppointmentEventType::NoShow, [], 0],
            [AppointmentEventType::LateFeePending, [], 0],
            [AppointmentEventType::LateFeeWaived, [], 0],
            [AppointmentEventType::LateFeeCharged, [], 1],
        ];

        foreach ($cases as [$type, $metadata, $expected]) {
            $event = $this->event($context, $type, $metadata);
            app(AppointmentNotificationService::class)->process($event);
            $this->assertSame($expected, $this->pushDeliveries($event)->count(), $type->value);
        }
    }

    public function test_job_sends_safe_string_payload_and_marks_delivery_delivered(): void
    {
        Queue::fake();
        config(['appointment_push.enabled' => true]);
        $context = $this->context();
        $device = $this->device($context['customerUser']);
        $event = $this->event($context, AppointmentEventType::Confirmed);
        app(AppointmentNotificationService::class)->process($event);
        $delivery = $this->pushDeliveries($event)->firstOrFail();
        $gateway = Mockery::mock(PushGateway::class);
        $gateway->shouldReceive('send')->once()->withArgs(function ($token, $title, $body, $data) use ($device) {
            return $token === $device->token
                && filled($title) && filled($body)
                && collect($data)->every(fn ($value) => is_string($value))
                && $data['appointment_id'] !== ''
                && $data['route'] === '/portal/citas/'.$data['appointment_id']
                && ! str_contains(json_encode($data), 'NOTA INTERNA SECRETA');
        });

        $this->send($delivery, $gateway);

        $this->assertSame(NotificationDeliveryStatus::Delivered, $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
    }

    public function test_invalid_token_is_revoked_without_retry(): void
    {
        Queue::fake();
        config(['appointment_push.enabled' => true]);
        $context = $this->context();
        $device = $this->device($context['customerUser']);
        $event = $this->event($context, AppointmentEventType::Confirmed);
        app(AppointmentNotificationService::class)->process($event);
        $delivery = $this->pushDeliveries($event)->firstOrFail();
        $gateway = Mockery::mock(PushGateway::class);
        $gateway->shouldReceive('send')->once()->andThrow(new InvalidPushTokenException('secret token data'));

        $this->send($delivery, $gateway);

        $this->assertNotNull($device->fresh()->revoked_at);
        $this->assertSame(NotificationDeliveryStatus::Skipped, $delivery->fresh()->status);
        $this->assertSame('InvalidPushTokenException: device token revoked', $delivery->fresh()->last_error);
    }

    public function test_transient_failure_is_sanitized_and_reuses_delivery_on_retry(): void
    {
        Queue::fake();
        config(['appointment_push.enabled' => true]);
        $context = $this->context();
        $this->device($context['customerUser']);
        $event = $this->event($context, AppointmentEventType::Confirmed);
        $service = app(AppointmentNotificationService::class);
        $service->process($event);
        $delivery = $this->pushDeliveries($event)->firstOrFail();
        $failedGateway = Mockery::mock(PushGateway::class);
        $failedGateway->shouldReceive('send')->once()->andThrow(new TransientPushException('credential=secret'));

        try {
            $this->send($delivery, $failedGateway);
            $this->fail('Se esperaba una falla temporal.');
        } catch (TransientPushException) {
        }

        $this->assertSame(NotificationDeliveryStatus::Failed, $delivery->fresh()->status);
        $this->assertSame('TransientPushException: push delivery failed', $delivery->fresh()->last_error);
        $service->process($event);
        $this->assertSame(1, $this->pushDeliveries($event)->count());
        $gateway = Mockery::mock(PushGateway::class);
        $gateway->shouldReceive('send')->once();
        $this->send($delivery->fresh(), $gateway);
        $this->assertSame(2, $delivery->fresh()->attempts);
        $this->assertSame(NotificationDeliveryStatus::Delivered, $delivery->fresh()->status);
    }

    private function send(AppointmentNotificationDelivery $delivery, PushGateway $gateway): void
    {
        (new SendAppointmentPush($delivery->id))->handle(
            $gateway,
            app(AppointmentNotificationService::class),
            app(TenantAppointmentAccessService::class),
        );
    }

    private function pushDeliveries(AppointmentEvent $event)
    {
        return AppointmentNotificationDelivery::query()
            ->where('appointment_event_id', $event->id)
            ->where('channel', NotificationDeliveryChannel::Push->value)
            ->get();
    }

    private function device(User $user): PushDevice
    {
        $token = 'token-'.str()->uuid();

        return PushDevice::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'platform' => PushPlatform::Android,
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'device_uuid' => (string) str()->uuid(),
            'last_seen_at' => now(),
        ]);
    }

    private function context(): array
    {
        foreach (['client-admin', 'asistente', 'customer'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $admin->assignRole('client-admin');
        $vet = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        VeterinarianProfile::create(['tenant_id' => $tenant->id, 'user_id' => $vet->id, 'professional_name' => 'Dra. Push', 'professional_title' => 'MVZ', 'license_number' => 'PUSH-'.str()->random(8), 'is_active' => true]);
        $assistant = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $assistant->assignRole('asistente');
        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Cliente', 'last_name' => 'Push', 'email' => fake()->unique()->safeEmail(), 'status' => 'active']);
        $animalType = AnimalType::create(['tenant_id' => $tenant->id, 'name' => 'Canino', 'slug' => 'canino-'.str()->random(8), 'is_active' => true]);
        $animal = Animal::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'animal_type_id' => $animalType->id, 'name' => 'Luna', 'status' => 'active']);
        $customerUser = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $customerUser->assignRole('customer');
        CustomerUserLink::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'user_id' => $customerUser->id, 'relationship' => 'owner', 'is_primary' => true]);
        CustomerPortalAccess::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'user_id' => $customerUser->id, 'status' => 'active', 'billing_mode' => 'free', 'access_starts_at' => now()->subDay()]);
        FinalUserPatientAssignment::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'user_id' => $customerUser->id, 'animal_id' => $animal->id, 'assigned_at' => now()]);
        AnimalPortalVisibilitySetting::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'user_id' => $customerUser->id, 'animal_id' => $animal->id, 'show_appointments' => true]);
        $startsAt = now()->addDay()->startOfHour();
        $appointment = Appointment::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'animal_id' => $animal->id, 'doctor_user_id' => $vet->id, 'animal_name_snapshot' => 'Luna', 'service_name_snapshot' => 'Consulta general', 'internal_notes' => 'NOTA INTERNA SECRETA', 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addMinutes(30), 'timezone' => 'America/Mexico_City']);

        return compact('tenant', 'admin', 'vet', 'assistant', 'customerUser', 'appointment');
    }

    private function event(array $context, AppointmentEventType $type, array $metadata = []): AppointmentEvent
    {
        return AppointmentEvent::create(['tenant_id' => $context['tenant']->id, 'appointment_id' => $context['appointment']->id, 'actor_user_id' => $context['customerUser']->id, 'event_type' => $type, 'previous_status' => AppointmentStatus::PendingTenant, 'new_status' => AppointmentStatus::Confirmed, 'metadata' => $metadata ?: null]);
    }
}
