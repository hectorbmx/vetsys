<?php

namespace Tests\Feature;

use App\Enums\AppointmentEventType;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Jobs\ProcessAppointmentEventNotifications;
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
use App\Models\PortalNotification;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\User;
use App\Services\AppointmentNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentNotificationServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_requested_event_creates_safe_idempotent_notifications_for_both_audiences(): void
    {
        $context = $this->context();
        $event = $this->event($context, AppointmentEventType::Requested, [
            'internal_notes' => 'NO DEBE SALIR',
        ]);
        $service = app(AppointmentNotificationService::class);

        $service->process($event);
        $service->process($event);

        $this->assertSame(1, TenantNotification::where('tenant_id', $context['tenant']->id)->count());
        $this->assertSame(1, PortalNotification::where('user_id', $context['customerUser']->id)->count());
        $this->assertSame(2, AppointmentNotificationDelivery::where('appointment_event_id', $event->id)
            ->whereIn('channel', $this->inAppChannels())
            ->count());
        $this->assertSame(2, AppointmentNotificationDelivery::where('appointment_event_id', $event->id)
            ->whereIn('channel', $this->inAppChannels())
            ->where('status', NotificationDeliveryStatus::Delivered->value)
            ->count());

        $tenantNotification = TenantNotification::where('tenant_id', $context['tenant']->id)->firstOrFail();
        $customerNotification = PortalNotification::where('user_id', $context['customerUser']->id)->firstOrFail();
        $this->assertNull($tenantNotification->user_id);
        $this->assertSame('/client/agenda/'.$context['appointment']->id, $tenantNotification->url);
        $this->assertSame('/portal/citas/'.$context['appointment']->id, $customerNotification->url);
        $this->assertSame('/tabs/agenda/'.$context['appointment']->id, $tenantNotification->data['route']);
        $this->assertSame('/portal/citas/'.$context['appointment']->id, $customerNotification->data['route']);
        $this->assertSame($event->id, $tenantNotification->data['appointment_event_id']);
        $this->assertSame($context['appointment']->id, $customerNotification->data['appointment_id']);
        $serialized = json_encode([$tenantNotification->toArray(), $customerNotification->toArray()]);
        $this->assertStringNotContainsString('NOTA INTERNA SECRETA', $serialized);
        $this->assertStringNotContainsString('NO DEBE SALIR', $serialized);
    }

    public function test_customer_notification_requires_current_access_assignment_link_and_visibility(): void
    {
        $context = $this->context();
        $service = app(AppointmentNotificationService::class);

        $context['visibility']->update(['show_appointments' => false]);
        $visibilityEvent = $this->event($context, AppointmentEventType::Confirmed);
        $service->process($visibilityEvent);

        $context['visibility']->update(['show_appointments' => true]);
        $context['assignment']->update(['revoked_at' => now()]);
        $assignmentEvent = $this->event($context, AppointmentEventType::Rejected);
        $service->process($assignmentEvent);

        $context['assignment']->update(['revoked_at' => null]);
        $context['link']->update(['revoked_at' => now()]);
        $linkEvent = $this->event($context, AppointmentEventType::Proposed);
        $service->process($linkEvent);

        $context['link']->update(['revoked_at' => null]);
        $context['access']->update(['access_ends_at' => now()->subMinute()]);
        $accessEvent = $this->event($context, AppointmentEventType::ProposalExpired);
        $service->process($accessEvent);

        $context['access']->update(['access_ends_at' => null]);
        $eligibleEvent = $this->event($context, AppointmentEventType::Completed);
        $service->process($eligibleEvent);

        $this->assertSame(5, TenantNotification::where('tenant_id', $context['tenant']->id)->count());
        $this->assertSame(1, PortalNotification::where('user_id', $context['customerUser']->id)->count());
        $this->assertSame($eligibleEvent->id, PortalNotification::where('user_id', $context['customerUser']->id)
            ->firstOrFail()->data['appointment_event_id']);
        foreach ([$visibilityEvent, $assignmentEvent, $linkEvent, $accessEvent] as $blockedEvent) {
            $this->assertSame(1, AppointmentNotificationDelivery::where('appointment_event_id', $blockedEvent->id)
                ->whereIn('channel', $this->inAppChannels())
                ->count());
        }
    }

    public function test_all_domain_events_create_persistent_updates_and_cancel_type_is_differentiated(): void
    {
        $context = $this->context();
        $service = app(AppointmentNotificationService::class);
        $events = [
            AppointmentEventType::Requested,
            AppointmentEventType::CreatedManually,
            AppointmentEventType::Confirmed,
            AppointmentEventType::Rejected,
            AppointmentEventType::Proposed,
            AppointmentEventType::ProposalAccepted,
            AppointmentEventType::ProposalRejected,
            AppointmentEventType::ProposalExpired,
            AppointmentEventType::Completed,
            AppointmentEventType::NoShow,
            AppointmentEventType::LateFeePending,
            AppointmentEventType::LateFeeWaived,
            AppointmentEventType::LateFeeCharged,
        ];

        foreach ($events as $eventType) {
            $service->process($this->event($context, $eventType));
        }
        $service->process($this->event($context, AppointmentEventType::Cancelled, ['actor_type' => 'customer']));
        $service->process($this->event($context, AppointmentEventType::Cancelled, ['actor_type' => 'tenant']));

        $expected = count($events) + 2;
        $this->assertSame($expected, TenantNotification::where('tenant_id', $context['tenant']->id)->count());
        $this->assertSame($expected, PortalNotification::where('user_id', $context['customerUser']->id)->count());
        $this->assertSame($expected * 2, AppointmentNotificationDelivery::where('tenant_id', $context['tenant']->id)
            ->whereIn('channel', $this->inAppChannels())
            ->count());
        $this->assertDatabaseHas('tenant_notifications', ['type' => 'appointment.cancelled_by_customer']);
        $this->assertDatabaseHas('tenant_notifications', ['type' => 'appointment.cancelled_by_tenant']);
        $this->assertDatabaseHas('portal_notifications', ['type' => 'appointment.proposal_expired']);
        $this->assertDatabaseHas('portal_notifications', ['type' => 'appointment.no_show']);
    }

    public function test_ambiguous_customer_access_does_not_create_an_unopenable_notification(): void
    {
        $context = $this->context();
        $otherCustomer = Customer::create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Otro customer',
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ]);
        CustomerPortalAccess::create([
            'tenant_id' => $context['tenant']->id,
            'customer_id' => $otherCustomer->id,
            'user_id' => $context['customerUser']->id,
            'status' => 'active',
            'billing_mode' => 'free',
            'access_starts_at' => now()->subDay(),
        ]);
        $event = $this->event($context, AppointmentEventType::Confirmed);

        app(AppointmentNotificationService::class)->process($event);

        $this->assertSame(1, TenantNotification::where('tenant_id', $context['tenant']->id)->count());
        $this->assertSame(0, PortalNotification::where('user_id', $context['customerUser']->id)->count());
    }

    public function test_job_processes_existing_event_and_missing_event_is_harmless(): void
    {
        $context = $this->context();
        $event = $this->event($context, AppointmentEventType::Confirmed);
        $service = app(AppointmentNotificationService::class);

        (new ProcessAppointmentEventNotifications($event->id))->handle($service);
        (new ProcessAppointmentEventNotifications(PHP_INT_MAX))->handle($service);

        $this->assertDatabaseHas('tenant_notifications', [
            'tenant_id' => $context['tenant']->id,
            'type' => AppointmentEventType::Confirmed->value,
        ]);
        $this->assertDatabaseHas('portal_notifications', [
            'user_id' => $context['customerUser']->id,
            'type' => AppointmentEventType::Confirmed->value,
        ]);
    }

    public function test_rolled_back_event_does_not_dispatch_notification_job(): void
    {
        Queue::fake();
        $context = $this->context();

        try {
            DB::transaction(function () use ($context) {
                $this->event($context, AppointmentEventType::Confirmed);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // Expected rollback.
        }

        Queue::assertNotPushed(ProcessAppointmentEventNotifications::class);
        $this->assertDatabaseMissing('appointment_events', [
            'appointment_id' => $context['appointment']->id,
            'event_type' => AppointmentEventType::Confirmed->value,
        ]);
        $this->assertDatabaseMissing('tenant_notifications', ['tenant_id' => $context['tenant']->id]);
        $this->assertDatabaseMissing('portal_notifications', ['tenant_id' => $context['tenant']->id]);
    }

    private function context(): array
    {
        Role::findOrCreate('customer', 'web');
        $tenant = Tenant::factory()->create();
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente',
            'last_name' => 'Notificaciones',
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
        $customerUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $customerUser->assignRole('customer');
        $link = CustomerUserLink::create([
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
        $assignment = FinalUserPatientAssignment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $customerUser->id,
            'animal_id' => $animal->id,
            'assigned_at' => now(),
        ]);
        $visibility = AnimalPortalVisibilitySetting::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'user_id' => $customerUser->id,
            'animal_id' => $animal->id,
            'show_appointments' => true,
        ]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'animal_id' => $animal->id,
            'animal_name_snapshot' => $animal->name,
            'service_name_snapshot' => 'Consulta general',
            'internal_notes' => 'NOTA INTERNA SECRETA',
            'starts_at' => now()->addDay()->startOfHour(),
            'ends_at' => now()->addDay()->startOfHour()->addMinutes(30),
            'timezone' => 'America/Mexico_City',
        ]);

        return compact(
            'tenant',
            'customer',
            'animal',
            'customerUser',
            'link',
            'access',
            'assignment',
            'visibility',
            'appointment',
        );
    }

    private function inAppChannels(): array
    {
        return [
            NotificationDeliveryChannel::TenantInApp->value,
            NotificationDeliveryChannel::CustomerInApp->value,
        ];
    }

    private function event(
        array $context,
        AppointmentEventType $type,
        array $metadata = [],
    ): AppointmentEvent {
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
