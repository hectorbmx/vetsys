<?php

namespace App\Services;

use App\Enums\AppointmentEventType;
use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Jobs\SendAppointmentEmail;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentNotificationDelivery;
use App\Models\CustomerPortalAccess;
use App\Models\PortalNotification;
use App\Models\TenantNotification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class AppointmentNotificationService
{
    public function process(AppointmentEvent $event): void
    {
        $event->loadMissing(['appointment.customer', 'appointment.animal', 'actor']);
        $appointment = $event->appointment;

        if (! $appointment || (int) $appointment->tenant_id !== (int) $event->tenant_id) {
            return;
        }

        $type = $this->notificationType($event);
        $copy = $this->copy($event, $appointment, $type);
        $customerAccesses = $this->eligibleCustomerAccesses($appointment);

        $this->deliverTenant($event, $appointment, $type, $copy['tenant']);

        foreach ($customerAccesses as $access) {
            $this->deliverCustomer($event, $appointment, $access, $type, $copy['customer']);
        }

        $this->queueEmailDeliveries($event, $appointment, $customerAccesses, $type);
    }

    public function customerCanReceive(Appointment $appointment, User $user): bool
    {
        return $this->eligibleCustomerAccesses($appointment)
            ->contains(fn (CustomerPortalAccess $access) => (int) $access->user_id === (int) $user->id);
    }

    public function emailData(AppointmentEvent $event, string $audience, User $recipient): array
    {
        $event->loadMissing(['appointment.tenant', 'appointment.pendingProposal']);
        $appointment = $event->appointment;
        $type = $this->notificationType($event);
        $copy = $this->copy($event, $appointment, $type)[$audience];
        $startsAt = $this->eventStartsAt($event, $appointment)->setTimezone($appointment->timezone);

        return [
            'subject' => $this->emailSubject($type, $audience, $appointment),
            'title' => $copy['title'],
            'intro' => $this->emailIntro($type, $audience),
            'recipient_name' => $recipient->name,
            'tenant_name' => $appointment->tenant->business_name ?: $appointment->tenant->name,
            'animal_name' => $appointment->animal_name_snapshot,
            'service_name' => $appointment->service_name_snapshot,
            'doctor_name' => $appointment->doctor_name_snapshot,
            'appointment_at' => $startsAt->format('d/m/Y H:i'),
            'timezone' => $appointment->timezone,
            'visible_message' => $this->visibleMessage($event, $appointment, $type),
            'url' => $audience === 'tenant'
                ? url('/client/agenda/'.$appointment->id)
                : url('/portal/citas/'.$appointment->id),
        ];
    }

    private function deliverTenant(
        AppointmentEvent $event,
        Appointment $appointment,
        string $type,
        array $copy,
    ): void {
        $recipientKey = 'tenant:'.$appointment->tenant_id;

        DB::transaction(function () use ($event, $appointment, $type, $copy, $recipientKey) {
            $delivery = $this->lockDelivery(
                $event,
                NotificationDeliveryChannel::TenantInApp,
                $recipientKey,
            );

            if ($delivery->status === NotificationDeliveryStatus::Delivered) {
                return;
            }

            $exists = TenantNotification::query()
                ->where('tenant_id', $appointment->tenant_id)
                ->where('type', $type)
                ->where('data->appointment_event_id', $event->id)
                ->exists();

            if (! $exists) {
                TenantNotification::create([
                    'tenant_id' => $appointment->tenant_id,
                    'user_id' => null,
                    'actor_tenant_id' => $appointment->tenant_id,
                    'actor_user_id' => $event->actor_user_id,
                    'type' => $type,
                    'title' => $copy['title'],
                    'body' => $copy['body'],
                    'url' => '/client/agenda/'.$appointment->id,
                    'data' => $this->safeData($event, $appointment, $type, '/tabs/agenda/'.$appointment->id),
                ]);
            }

            $this->markDelivered($delivery);
        }, 3);
    }

    private function deliverCustomer(
        AppointmentEvent $event,
        Appointment $appointment,
        CustomerPortalAccess $access,
        string $type,
        array $copy,
    ): void {
        $recipientKey = 'user:'.$access->user_id;

        DB::transaction(function () use ($event, $appointment, $access, $type, $copy, $recipientKey) {
            $delivery = $this->lockDelivery(
                $event,
                NotificationDeliveryChannel::CustomerInApp,
                $recipientKey,
                $access->user_id,
            );

            if ($delivery->status === NotificationDeliveryStatus::Delivered) {
                return;
            }

            $exists = PortalNotification::query()
                ->where('tenant_id', $appointment->tenant_id)
                ->where('user_id', $access->user_id)
                ->where('type', $type)
                ->where('data->appointment_event_id', $event->id)
                ->exists();

            if (! $exists) {
                PortalNotification::create([
                    'tenant_id' => $appointment->tenant_id,
                    'user_id' => $access->user_id,
                    'customer_id' => $appointment->customer_id,
                    'animal_id' => $appointment->animal_id,
                    'type' => $type,
                    'title' => $copy['title'],
                    'body' => $copy['body'],
                    'url' => '/portal/citas/'.$appointment->id,
                    'data' => $this->safeData($event, $appointment, $type, '/portal/citas/'.$appointment->id),
                ]);
            }

            $this->markDelivered($delivery);
        }, 3);
    }

    private function lockDelivery(
        AppointmentEvent $event,
        NotificationDeliveryChannel $channel,
        string $recipientKey,
        ?int $recipientUserId = null,
    ): AppointmentNotificationDelivery {
        $recipientHash = AppointmentNotificationDelivery::recipientHash($recipientKey);
        AppointmentNotificationDelivery::query()->insertOrIgnore([
            'tenant_id' => $event->tenant_id,
            'appointment_event_id' => $event->id,
            'recipient_user_id' => $recipientUserId,
            'channel' => $channel->value,
            'recipient_key' => $recipientKey,
            'recipient_hash' => $recipientHash,
            'status' => NotificationDeliveryStatus::Pending->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AppointmentNotificationDelivery::query()
            ->where('appointment_event_id', $event->id)
            ->where('channel', $channel->value)
            ->where('recipient_hash', $recipientHash)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function markDelivered(AppointmentNotificationDelivery $delivery): void
    {
        $delivery->update([
            'status' => NotificationDeliveryStatus::Delivered,
            'attempts' => $delivery->attempts + 1,
            'last_attempt_at' => now(),
            'delivered_at' => now(),
            'last_error' => null,
        ]);
    }

    private function eligibleCustomerAccesses(Appointment $appointment): Collection
    {
        if (
            ! $appointment->customer_id
            || ! $appointment->animal_id
            || $appointment->customer?->status !== 'active'
            || $appointment->animal?->status !== 'active'
            || $appointment->customer?->trashed()
            || $appointment->animal?->trashed()
        ) {
            return new Collection;
        }

        return CustomerPortalAccess::query()
            ->with('user')
            ->where('tenant_id', $appointment->tenant_id)
            ->where('customer_id', $appointment->customer_id)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query
                ->whereNull('access_starts_at')
                ->orWhere('access_starts_at', '<=', now()))
            ->where(fn ($query) => $query
                ->whereNull('access_ends_at')
                ->orWhere('access_ends_at', '>=', now()))
            ->where(fn ($query) => $query
                ->where('billing_mode', '!=', 'trial')
                ->orWhereNull('trial_ends_at')
                ->orWhere('trial_ends_at', '>=', now()))
            ->whereHas('user', fn ($query) => $query
                ->where('tenant_id', $appointment->tenant_id)
                ->where('is_active', true)
                ->whereHas('roles', fn ($roles) => $roles->where('name', 'customer')))
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('customer_user_links')
                    ->whereColumn('customer_user_links.user_id', 'customer_portal_accesses.user_id')
                    ->whereColumn('customer_user_links.tenant_id', 'customer_portal_accesses.tenant_id')
                    ->whereColumn('customer_user_links.customer_id', 'customer_portal_accesses.customer_id')
                    ->whereNull('customer_user_links.revoked_at');
            })
            ->whereExists(function ($query) use ($appointment) {
                $query->selectRaw('1')
                    ->from('final_user_patient_assignments')
                    ->whereColumn('final_user_patient_assignments.user_id', 'customer_portal_accesses.user_id')
                    ->whereColumn('final_user_patient_assignments.tenant_id', 'customer_portal_accesses.tenant_id')
                    ->whereColumn('final_user_patient_assignments.customer_id', 'customer_portal_accesses.customer_id')
                    ->where('final_user_patient_assignments.animal_id', $appointment->animal_id)
                    ->whereNull('final_user_patient_assignments.revoked_at');
            })
            ->whereExists(function ($query) use ($appointment) {
                $query->selectRaw('1')
                    ->from('animal_portal_visibility_settings')
                    ->whereColumn('animal_portal_visibility_settings.user_id', 'customer_portal_accesses.user_id')
                    ->whereColumn('animal_portal_visibility_settings.tenant_id', 'customer_portal_accesses.tenant_id')
                    ->whereColumn('animal_portal_visibility_settings.customer_id', 'customer_portal_accesses.customer_id')
                    ->where('animal_portal_visibility_settings.animal_id', $appointment->animal_id)
                    ->where('animal_portal_visibility_settings.show_appointments', true);
            })
            ->get()
            ->filter(fn (CustomerPortalAccess $access) => $this->activeAccessCount(
                (int) $access->tenant_id,
                (int) $access->user_id,
            ) === 1)
            ->unique('user_id')
            ->values();
    }

    private function activeAccessCount(int $tenantId, int $userId): int
    {
        return CustomerPortalAccess::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query
                ->whereNull('access_starts_at')
                ->orWhere('access_starts_at', '<=', now()))
            ->where(fn ($query) => $query
                ->whereNull('access_ends_at')
                ->orWhere('access_ends_at', '>=', now()))
            ->where(fn ($query) => $query
                ->where('billing_mode', '!=', 'trial')
                ->orWhereNull('trial_ends_at')
                ->orWhere('trial_ends_at', '>=', now()))
            ->count();
    }

    private function queueEmailDeliveries(
        AppointmentEvent $event,
        Appointment $appointment,
        Collection $customerAccesses,
        string $type,
    ): void {
        if ($this->tenantReceivesEmail($type)) {
            $tenantRecipients = User::query()
                ->where('tenant_id', $appointment->tenant_id)
                ->where('is_active', true)
                ->whereNotNull('email')
                ->where(function ($query) {
                    $query->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['client-admin', 'admin']))
                        ->orWhereHas('veterinarianProfile', fn ($profile) => $profile
                            ->where('is_active', true));
                })
                ->get()
                ->filter(fn (User $user) => filter_var($user->email, FILTER_VALIDATE_EMAIL))
                ->unique(fn (User $user) => strtolower($user->email));

            foreach ($tenantRecipients as $recipient) {
                $this->queueEmail($event, $recipient, 'tenant');
            }
        }

        if ($this->customerReceivesEmail($type)) {
            $customerRecipients = $customerAccesses
                ->pluck('user')
                ->filter(fn (?User $user) => $user && filter_var($user->email, FILTER_VALIDATE_EMAIL))
                ->unique(fn (User $user) => strtolower($user->email));

            foreach ($customerRecipients as $recipient) {
                $this->queueEmail($event, $recipient, 'customer');
            }
        }
    }

    private function queueEmail(AppointmentEvent $event, User $recipient, string $audience): void
    {
        $recipientKey = "email:{$audience}:{$recipient->id}";
        $recipientHash = AppointmentNotificationDelivery::recipientHash($recipientKey);

        $delivery = DB::transaction(function () use ($event, $recipient, $recipientKey, $recipientHash) {
            AppointmentNotificationDelivery::query()->insertOrIgnore([
                'tenant_id' => $event->tenant_id,
                'appointment_event_id' => $event->id,
                'recipient_user_id' => $recipient->id,
                'channel' => NotificationDeliveryChannel::Email->value,
                'recipient_key' => $recipientKey,
                'recipient_hash' => $recipientHash,
                'status' => NotificationDeliveryStatus::Pending->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return AppointmentNotificationDelivery::query()
                ->where('appointment_event_id', $event->id)
                ->where('channel', NotificationDeliveryChannel::Email->value)
                ->where('recipient_hash', $recipientHash)
                ->lockForUpdate()
                ->firstOrFail();
        }, 3);

        if (! in_array($delivery->status, [
            NotificationDeliveryStatus::Delivered,
            NotificationDeliveryStatus::Skipped,
        ], true)) {
            try {
                SendAppointmentEmail::dispatch($delivery->id);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function tenantReceivesEmail(string $type): bool
    {
        return in_array($type, [
            AppointmentEventType::Requested->value,
            AppointmentEventType::ProposalAccepted->value,
            AppointmentEventType::ProposalRejected->value,
            'appointment.cancelled_by_customer',
        ], true);
    }

    private function customerReceivesEmail(string $type): bool
    {
        return in_array($type, [
            AppointmentEventType::Requested->value,
            AppointmentEventType::CreatedManually->value,
            AppointmentEventType::Confirmed->value,
            AppointmentEventType::Rejected->value,
            AppointmentEventType::Proposed->value,
            AppointmentEventType::ProposalExpired->value,
            'appointment.cancelled_by_customer',
            'appointment.cancelled_by_tenant',
            AppointmentEventType::LateFeeCharged->value,
            AppointmentEventType::NoShow->value,
        ], true);
    }

    private function emailSubject(string $type, string $audience, Appointment $appointment): string
    {
        if ($audience === 'tenant') {
            return match ($type) {
                AppointmentEventType::Requested->value => 'Nueva solicitud de cita para '.$appointment->animal_name_snapshot,
                AppointmentEventType::ProposalAccepted->value,
                AppointmentEventType::ProposalRejected->value => 'Respuesta a propuesta de horario',
                'appointment.cancelled_by_customer' => 'Cita cancelada por customer',
                default => 'Actualizacion de cita',
            };
        }

        return match ($type) {
            AppointmentEventType::Requested->value => 'Recibimos tu solicitud de cita',
            AppointmentEventType::CreatedManually->value,
            AppointmentEventType::Confirmed->value => 'Tu cita fue confirmada',
            AppointmentEventType::Rejected->value => 'Tu solicitud no pudo ser confirmada',
            AppointmentEventType::Proposed->value => 'Nueva propuesta de horario para tu cita',
            AppointmentEventType::ProposalExpired->value => 'La propuesta de horario vencio',
            'appointment.cancelled_by_customer',
            'appointment.cancelled_by_tenant' => 'Cita cancelada',
            AppointmentEventType::LateFeeCharged->value => 'Cargo por cancelacion registrado',
            AppointmentEventType::NoShow->value => 'Inasistencia registrada',
            default => 'Actualizacion de tu cita',
        };
    }

    private function emailIntro(string $type, string $audience): string
    {
        if ($audience === 'tenant') {
            return match ($type) {
                AppointmentEventType::Requested->value => 'Se recibio una nueva solicitud que requiere revision.',
                AppointmentEventType::ProposalAccepted->value => 'El customer acepto el horario propuesto.',
                AppointmentEventType::ProposalRejected->value => 'El customer rechazo el horario propuesto.',
                'appointment.cancelled_by_customer' => 'El customer cancelo la cita.',
                default => 'La cita tuvo una actualizacion.',
            };
        }

        return match ($type) {
            AppointmentEventType::Requested->value => 'Recibimos tu solicitud. La veterinaria debe confirmarla.',
            AppointmentEventType::CreatedManually->value,
            AppointmentEventType::Confirmed->value => 'La veterinaria confirmo tu cita.',
            AppointmentEventType::Rejected->value => 'La veterinaria no pudo confirmar la solicitud.',
            AppointmentEventType::Proposed->value => 'La veterinaria sugirio un horario diferente.',
            AppointmentEventType::ProposalExpired->value => 'El tiempo para responder la propuesta termino.',
            'appointment.cancelled_by_customer' => 'Registramos la cancelacion de tu cita.',
            'appointment.cancelled_by_tenant' => 'La veterinaria cancelo la cita.',
            AppointmentEventType::LateFeeCharged->value => 'Se registro un cargo relacionado con la cancelacion.',
            AppointmentEventType::NoShow->value => 'La veterinaria registro que no hubo asistencia.',
            default => 'Tu cita tuvo una actualizacion.',
        };
    }

    private function visibleMessage(AppointmentEvent $event, Appointment $appointment, string $type): ?string
    {
        return match ($type) {
            AppointmentEventType::Rejected->value => $appointment->rejection_reason,
            'appointment.cancelled_by_customer',
            'appointment.cancelled_by_tenant' => $appointment->cancellation_reason,
            AppointmentEventType::Proposed->value => $appointment->proposals()
                ->whereKey(($event->metadata ?? [])['proposal_id'] ?? 0)
                ->value('message'),
            AppointmentEventType::ProposalRejected->value => ($event->metadata ?? [])['message'] ?? null,
            default => null,
        };
    }

    private function notificationType(AppointmentEvent $event): string
    {
        if ($event->event_type === AppointmentEventType::Cancelled) {
            $actorType = ($event->metadata ?? [])['actor_type']
                ?? ($event->actor?->hasRole('customer') ? 'customer' : 'tenant');

            return $actorType === 'customer'
                ? 'appointment.cancelled_by_customer'
                : 'appointment.cancelled_by_tenant';
        }

        return $event->event_type->value;
    }

    private function safeData(
        AppointmentEvent $event,
        Appointment $appointment,
        string $type,
        string $route,
    ): array {
        return [
            'appointment_event_id' => $event->id,
            'appointment_id' => $appointment->id,
            'animal_id' => $appointment->animal_id,
            'event_type' => $type,
            'starts_at' => $this->eventStartsAt($event, $appointment)->toIso8601String(),
            'timezone' => $appointment->timezone,
            'animal_name' => $appointment->animal_name_snapshot,
            'service_name' => $appointment->service_name_snapshot,
            'route' => $route,
        ];
    }

    private function copy(AppointmentEvent $event, Appointment $appointment, string $type): array
    {
        $when = $this->eventStartsAt($event, $appointment)
            ->setTimezone($appointment->timezone)
            ->format('d/m/Y H:i');
        $context = $appointment->animal_name_snapshot.' | '.$appointment->service_name_snapshot.' | '.$when;
        $copies = [
            AppointmentEventType::Requested->value => ['Nueva solicitud de cita', 'Solicitud de cita recibida'],
            AppointmentEventType::CreatedManually->value => ['Cita registrada', 'Tu cita fue confirmada'],
            AppointmentEventType::Confirmed->value => ['Cita confirmada', 'Tu cita fue confirmada'],
            AppointmentEventType::Rejected->value => ['Solicitud rechazada', 'Tu solicitud no pudo confirmarse'],
            AppointmentEventType::Proposed->value => ['Contrapropuesta enviada', 'Nueva propuesta de horario'],
            AppointmentEventType::ProposalAccepted->value => ['Horario aceptado', 'Horario confirmado'],
            AppointmentEventType::ProposalRejected->value => ['Horario alternativo rechazado', 'Respuesta de horario registrada'],
            AppointmentEventType::ProposalExpired->value => ['Contrapropuesta vencida', 'La propuesta de horario vencio'],
            'appointment.cancelled_by_customer' => ['Cita cancelada por customer', 'Cancelacion de cita registrada'],
            'appointment.cancelled_by_tenant' => ['Cita cancelada', 'La veterinaria cancelo la cita'],
            AppointmentEventType::Completed->value => ['Consulta completada', 'Consulta completada'],
            AppointmentEventType::NoShow->value => ['No asistencia registrada', 'No asistencia registrada'],
            AppointmentEventType::LateFeePending->value => ['Cargo pendiente de revision', 'Cancelacion en revision'],
            AppointmentEventType::LateFeeWaived->value => ['Cargo de cancelacion exentado', 'Cargo de cancelacion exentado'],
            AppointmentEventType::LateFeeCharged->value => ['Cargo de cancelacion registrado', 'Cargo de cancelacion registrado'],
        ];
        [$tenantTitle, $customerTitle] = $copies[$type] ?? ['Cita actualizada', 'Tu cita fue actualizada'];

        return [
            'tenant' => ['title' => $tenantTitle, 'body' => $context],
            'customer' => ['title' => $customerTitle, 'body' => $context],
        ];
    }

    private function eventStartsAt(AppointmentEvent $event, Appointment $appointment): CarbonImmutable
    {
        $value = ($event->metadata ?? [])['starts_at'] ?? $appointment->starts_at;

        return CarbonImmutable::parse($value)->utc();
    }
}
