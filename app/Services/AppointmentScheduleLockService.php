<?php

namespace App\Services;

use App\Models\AppointmentScheduleLock;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class AppointmentScheduleLockService
{
    public function lock(Tenant $tenant, int $doctorUserId, DateTimeInterface $startsAtUtc, string $timezone): void
    {
        $scheduleDate = CarbonImmutable::instance($startsAtUtc)
            ->setTimezone($timezone)
            ->format('Y-m-d');

        AppointmentScheduleLock::query()->insertOrIgnore([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctorUserId,
            'schedule_date' => $scheduleDate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AppointmentScheduleLock::query()
            ->where('tenant_id', $tenant->id)
            ->where('doctor_user_id', $doctorUserId)
            ->whereDate('schedule_date', $scheduleDate)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
