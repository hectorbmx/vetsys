<?php

namespace App\Jobs;

use App\Models\AppointmentEvent;
use App\Services\AppointmentNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAppointmentEventNotifications implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    public function __construct(public int $appointmentEventId) {}

    public function uniqueId(): string
    {
        return (string) $this->appointmentEventId;
    }

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(AppointmentNotificationService $notifications): void
    {
        $event = AppointmentEvent::query()->find($this->appointmentEventId);

        if ($event) {
            $notifications->process($event);
        }
    }
}
