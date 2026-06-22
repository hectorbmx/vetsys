<?php

namespace App\Jobs;

use App\Contracts\PushGateway;
use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Exceptions\InvalidPushTokenException;
use App\Exceptions\PermanentPushException;
use App\Models\AppointmentNotificationDelivery;
use App\Services\AppointmentNotificationService;
use App\Services\TenantAppointmentAccessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendAppointmentPush implements ShouldBeUnique, ShouldQueue
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
        PushGateway $gateway,
        AppointmentNotificationService $notifications,
        TenantAppointmentAccessService $tenantAccess,
    ): void {
        $delivery = AppointmentNotificationDelivery::query()
            ->with(['appointmentEvent.appointment.tenant', 'recipientUser', 'pushDevice'])
            ->find($this->deliveryId);

        if (! $delivery || $delivery->channel !== NotificationDeliveryChannel::Push
            || in_array($delivery->status, [NotificationDeliveryStatus::Delivered, NotificationDeliveryStatus::Skipped], true)) {
            return;
        }

        $event = $delivery->appointmentEvent;
        $appointment = $event?->appointment;
        $recipient = $delivery->recipientUser;
        $device = $delivery->pushDevice;
        $audience = str_starts_with($delivery->recipient_key, 'push:tenant:') ? 'tenant' : 'customer';
        $eligible = $event && $appointment && $recipient && $recipient->is_active && $device
            && ! $device->revoked_at
            && (int) $device->user_id === (int) $recipient->id
            && (int) $device->tenant_id === (int) $delivery->tenant_id
            && ($audience === 'tenant'
                ? $tenantAccess->allows($appointment->tenant, $recipient)
                : $notifications->customerCanReceive($appointment, $recipient));

        if (! $eligible || ! config('appointment_push.enabled')) {
            $this->skip($delivery, $eligible ? 'FCM disabled.' : null);

            return;
        }

        $delivery->update([
            'status' => NotificationDeliveryStatus::Processing,
            'attempts' => $delivery->attempts + 1,
            'last_attempt_at' => now(),
            'last_error' => null,
        ]);

        try {
            $payload = $notifications->pushData($event, $audience, $recipient);
            $gateway->send($device->token, $payload['title'], $payload['body'], $payload['data']);
            $delivery->update([
                'status' => NotificationDeliveryStatus::Delivered,
                'delivered_at' => now(),
                'last_error' => null,
            ]);
        } catch (InvalidPushTokenException $exception) {
            $device->update(['revoked_at' => now()]);
            $delivery->update([
                'status' => NotificationDeliveryStatus::Skipped,
                'last_error' => 'InvalidPushTokenException: device token revoked',
            ]);
        } catch (PermanentPushException $exception) {
            $delivery->update([
                'status' => NotificationDeliveryStatus::Skipped,
                'last_error' => 'PermanentPushException: push delivery rejected',
            ]);
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => NotificationDeliveryStatus::Failed,
                'last_error' => class_basename($exception).': push delivery failed',
            ]);
            throw $exception;
        }
    }

    private function skip(AppointmentNotificationDelivery $delivery, ?string $reason): void
    {
        $delivery->update([
            'status' => NotificationDeliveryStatus::Skipped,
            'attempts' => $delivery->attempts + 1,
            'last_attempt_at' => now(),
            'last_error' => $reason,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        AppointmentNotificationDelivery::query()
            ->whereKey($this->deliveryId)
            ->where('status', '!=', NotificationDeliveryStatus::Delivered->value)
            ->update([
                'status' => NotificationDeliveryStatus::Failed->value,
                'last_error' => class_basename($exception).': push delivery failed',
                'updated_at' => now(),
            ]);
    }
}
