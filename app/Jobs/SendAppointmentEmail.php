<?php

namespace App\Jobs;

use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Mail\AppointmentEventMail;
use App\Models\AppointmentNotificationDelivery;
use App\Services\AppointmentNotificationService;
use App\Services\TenantAppointmentAccessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAppointmentEmail implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public int $deliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(
        AppointmentNotificationService $notifications,
        TenantAppointmentAccessService $tenantAccess,
    ): void {
        $delivery = AppointmentNotificationDelivery::query()
            ->with(['appointmentEvent.appointment.tenant', 'recipientUser'])
            ->find($this->deliveryId);

        if (
            ! $delivery
            || $delivery->channel !== NotificationDeliveryChannel::Email
            || in_array($delivery->status, [
                NotificationDeliveryStatus::Delivered,
                NotificationDeliveryStatus::Skipped,
            ], true)
        ) {
            return;
        }

        $event = $delivery->appointmentEvent;
        $appointment = $event?->appointment;
        $recipient = $delivery->recipientUser;
        $audience = str_starts_with($delivery->recipient_key, 'email:tenant:') ? 'tenant' : 'customer';
        $eligible = $event && $appointment && $recipient && $recipient->is_active && filled($recipient->email)
            && (int) $recipient->tenant_id === (int) $delivery->tenant_id
            && ($audience === 'tenant'
                ? $tenantAccess->allows($appointment->tenant, $recipient)
                : $notifications->customerCanReceive($appointment, $recipient));

        if (! $eligible) {
            $delivery->update([
                'status' => NotificationDeliveryStatus::Skipped,
                'attempts' => $delivery->attempts + 1,
                'last_attempt_at' => now(),
                'last_error' => null,
            ]);

            return;
        }

        $delivery->update([
            'status' => NotificationDeliveryStatus::Processing,
            'attempts' => $delivery->attempts + 1,
            'last_attempt_at' => now(),
            'last_error' => null,
        ]);

        try {
            Mail::to($recipient->email)->send(new AppointmentEventMail(
                $notifications->emailData($event, $audience, $recipient),
            ));

            $delivery->update([
                'status' => NotificationDeliveryStatus::Delivered,
                'delivered_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => NotificationDeliveryStatus::Failed,
                'last_error' => class_basename($exception).': mail delivery failed',
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        AppointmentNotificationDelivery::query()
            ->whereKey($this->deliveryId)
            ->where('status', '!=', NotificationDeliveryStatus::Delivered->value)
            ->update([
                'status' => NotificationDeliveryStatus::Failed->value,
                'last_error' => class_basename($exception).': mail delivery failed',
                'updated_at' => now(),
            ]);
    }
}
