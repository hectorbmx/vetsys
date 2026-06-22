<?php

namespace App\Services;

use App\Data\AppointmentSlot;
use App\Enums\AppointmentProposalStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentProposal;
use App\Models\AppointmentSetting;
use App\Models\CatalogItem;
use App\Models\DoctorSchedule;
use App\Models\ScheduleBlock;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AppointmentAvailabilityService
{
    public const MAX_RANGE_DAYS = 31;

    public const MAX_BUFFER_MINUTES = 120;

    /**
     * @return Collection<int, AppointmentSlot>
     */
    public function slotsForDate(
        Tenant $tenant,
        CatalogItem $service,
        CarbonInterface|DateTimeInterface|string $date,
        CarbonInterface|DateTimeInterface|null $now = null,
        bool $applyCustomerRules = true,
    ): Collection {
        $setting = $tenant->appointmentSetting()->first();

        if (! $this->configurationAllowsBooking($tenant, $service, $setting, $applyCustomerRules)) {
            return collect();
        }

        $timezone = $setting->timezone;
        $localDate = $this->localDate($date, $timezone);
        $nowUtc = $now
            ? CarbonImmutable::instance($now)->utc()
            : CarbonImmutable::now('UTC');
        $nowLocal = $nowUtc->setTimezone($timezone);

        if ($applyCustomerRules && ! $this->dateIsInsideBookingWindow($localDate, $nowLocal, $setting)) {
            return collect();
        }

        $schedules = DoctorSchedule::query()
            ->where('tenant_id', $tenant->id)
            ->where('doctor_user_id', $setting->doctor_user_id)
            ->where('weekday', $localDate->isoWeekday())
            ->where('is_active', true)
            ->orderBy('starts_at')
            ->get();

        if ($schedules->isEmpty()) {
            return collect();
        }

        $durationMinutes = (int) ($service->appointment_duration_minutes ?: $setting->default_duration_minutes);
        $bufferMinutes = (int) ($service->appointment_buffer_minutes ?: 0);
        $slotIntervalMinutes = max(1, (int) $setting->slot_interval_minutes);
        $earliestStartUtc = $applyCustomerRules
            ? $nowLocal->addMinutes($setting->minimum_notice_minutes)->utc()
            : $nowUtc;
        $dayStartUtc = $localDate->startOfDay()->utc();
        $dayEndUtc = $localDate->addDay()->startOfDay()->utc();
        $occupiedIntervals = $this->occupiedIntervals(
            $tenant,
            (int) $setting->doctor_user_id,
            $dayStartUtc,
            $dayEndUtc,
            $nowUtc,
        );

        $slots = collect();
        $seenStarts = [];

        foreach ($schedules as $schedule) {
            $scheduleStart = $this->localDateTime($localDate, $schedule->starts_at, $timezone);
            $scheduleEnd = $this->localDateTime($localDate, $schedule->ends_at, $timezone);

            if (! $scheduleStart || ! $scheduleEnd || $scheduleEnd->lessThanOrEqualTo($scheduleStart)) {
                continue;
            }

            $scheduleStartUtc = $scheduleStart->utc();
            $scheduleEndUtc = $scheduleEnd->utc();

            for (
                $startsAtUtc = $scheduleStartUtc;
                $startsAtUtc->lessThan($scheduleEndUtc);
                $startsAtUtc = $startsAtUtc->addMinutes($slotIntervalMinutes)
            ) {
                $endsAtUtc = $startsAtUtc->addMinutes($durationMinutes);
                $occupiedUntilUtc = $endsAtUtc->addMinutes($bufferMinutes);

                if ($startsAtUtc->lessThan($earliestStartUtc) || $occupiedUntilUtc->greaterThan($scheduleEndUtc)) {
                    continue;
                }

                $uniqueStart = $startsAtUtc->format('Y-m-d H:i:sP');

                if (isset($seenStarts[$uniqueStart])) {
                    continue;
                }

                if ($this->overlapsAny($startsAtUtc, $occupiedUntilUtc, $occupiedIntervals)) {
                    continue;
                }

                $seenStarts[$uniqueStart] = true;
                $slots->push(new AppointmentSlot(
                    startsAtUtc: $startsAtUtc,
                    endsAtUtc: $endsAtUtc,
                    localStartsAt: $startsAtUtc->setTimezone($timezone),
                    localEndsAt: $endsAtUtc->setTimezone($timezone),
                    timezone: $timezone,
                    durationMinutes: $durationMinutes,
                    bufferMinutes: $bufferMinutes,
                ));
            }
        }

        return $slots->sortBy(fn (AppointmentSlot $slot) => $slot->startsAtUtc->getTimestamp())->values();
    }

    /**
     * @return Collection<string, Collection<int, AppointmentSlot>>
     */
    public function slotsForRange(
        Tenant $tenant,
        CatalogItem $service,
        CarbonInterface|DateTimeInterface|string $from,
        CarbonInterface|DateTimeInterface|string $to,
        CarbonInterface|DateTimeInterface|null $now = null,
        bool $applyCustomerRules = true,
    ): Collection {
        $setting = $tenant->appointmentSetting()->first();
        $timezone = $setting?->timezone ?: 'UTC';
        $fromDate = $this->localDate($from, $timezone);
        $toDate = $this->localDate($to, $timezone);

        if ($toDate->lessThan($fromDate)) {
            throw new InvalidArgumentException('La fecha final no puede ser anterior a la inicial.');
        }

        if ($fromDate->diffInDays($toDate) + 1 > self::MAX_RANGE_DAYS) {
            throw new InvalidArgumentException('El rango de disponibilidad no puede superar 31 dias.');
        }

        $result = collect();

        for ($date = $fromDate; $date->lessThanOrEqualTo($toDate); $date = $date->addDay()) {
            $result->put(
                $date->format('Y-m-d'),
                $this->slotsForDate($tenant, $service, $date, $now, $applyCustomerRules),
            );
        }

        return $result;
    }

    public function intervalIsAvailable(
        Tenant $tenant,
        int $doctorUserId,
        DateTimeInterface $startsAtUtc,
        DateTimeInterface $endsAtUtc,
        int $bufferMinutes = 0,
        ?int $excludeAppointmentId = null,
        ?int $excludeProposalId = null,
        ?DateTimeInterface $now = null,
    ): bool {
        $setting = $tenant->appointmentSetting()->first();
        $startsAtUtc = CarbonImmutable::instance($startsAtUtc)->utc();
        $endsAtUtc = CarbonImmutable::instance($endsAtUtc)->utc();
        $occupiedUntilUtc = $endsAtUtc->addMinutes($bufferMinutes);

        if (
            ! $setting
            || (int) $setting->doctor_user_id !== $doctorUserId
            || $endsAtUtc->lessThanOrEqualTo($startsAtUtc)
            || $bufferMinutes < 0
            || $bufferMinutes > self::MAX_BUFFER_MINUTES
        ) {
            return false;
        }

        $timezone = $setting->timezone;
        $localDate = $startsAtUtc->setTimezone($timezone)->startOfDay();
        $schedules = DoctorSchedule::query()
            ->where('tenant_id', $tenant->id)
            ->where('doctor_user_id', $doctorUserId)
            ->where('weekday', $localDate->isoWeekday())
            ->where('is_active', true)
            ->get();
        $fitsSchedule = $schedules->contains(function (DoctorSchedule $schedule) use (
            $localDate,
            $timezone,
            $startsAtUtc,
            $occupiedUntilUtc,
        ) {
            $scheduleStart = $this->localDateTime($localDate, $schedule->starts_at, $timezone)?->utc();
            $scheduleEnd = $this->localDateTime($localDate, $schedule->ends_at, $timezone)?->utc();

            return $scheduleStart
                && $scheduleEnd
                && $startsAtUtc->greaterThanOrEqualTo($scheduleStart)
                && $occupiedUntilUtc->lessThanOrEqualTo($scheduleEnd);
        });

        if (! $fitsSchedule) {
            return false;
        }

        $rangeStartUtc = $localDate->utc();
        $rangeEndUtc = $localDate->addDay()->startOfDay()->utc();
        $nowUtc = $now ? CarbonImmutable::instance($now)->utc() : CarbonImmutable::now('UTC');
        $occupiedIntervals = $this->occupiedIntervals(
            $tenant,
            $doctorUserId,
            $rangeStartUtc,
            $rangeEndUtc,
            $nowUtc,
            $excludeAppointmentId,
            $excludeProposalId,
        );

        return ! $this->overlapsAny($startsAtUtc, $occupiedUntilUtc, $occupiedIntervals);
    }

    private function configurationAllowsBooking(
        Tenant $tenant,
        CatalogItem $service,
        ?AppointmentSetting $setting,
        bool $applyCustomerRules,
    ): bool {
        if (
            ! $setting
            || ($applyCustomerRules && ! $setting->is_customer_booking_enabled)
            || ! $setting->doctor_user_id
            || (int) $service->tenant_id !== (int) $tenant->id
            || $service->type !== 'service'
            || ! $service->is_active
            || ! $service->is_bookable
            || $service->trashed()
        ) {
            return false;
        }

        return $tenant->users()
            ->whereKey($setting->doctor_user_id)
            ->where('is_active', true)
            ->whereHas('veterinarianProfile', fn ($query) => $query->where('is_active', true))
            ->exists();
    }

    private function dateIsInsideBookingWindow(
        CarbonImmutable $localDate,
        CarbonImmutable $nowLocal,
        AppointmentSetting $setting,
    ): bool {
        $today = $nowLocal->startOfDay();
        $lastDay = $today->addDays($setting->booking_window_days);

        return $localDate->betweenIncluded($today, $lastDay);
    }

    private function localDate(
        CarbonInterface|DateTimeInterface|string $value,
        string $timezone,
    ): CarbonImmutable {
        if (is_string($value)) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);

            if (! $parsed || $parsed->format('Y-m-d') !== $value) {
                throw new InvalidArgumentException('La fecha debe usar el formato Y-m-d.');
            }

            return $parsed;
        }

        return CarbonImmutable::instance($value)->setTimezone($timezone)->startOfDay();
    }

    private function localDateTime(
        CarbonImmutable $localDate,
        string $time,
        string $timezone,
    ): ?CarbonImmutable {
        $normalizedTime = substr($time, 0, 8);
        $requested = $localDate->format('Y-m-d').' '.$normalizedTime;
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $requested, $timezone);

        if (! $parsed || $parsed->format('Y-m-d H:i:s') !== $requested) {
            return null;
        }

        return $parsed;
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function occupiedIntervals(
        Tenant $tenant,
        int $doctorUserId,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
        CarbonImmutable $nowUtc,
        ?int $excludeAppointmentId = null,
        ?int $excludeProposalId = null,
    ): array {
        $intervals = [];

        ScheduleBlock::query()
            ->where('tenant_id', $tenant->id)
            ->where('doctor_user_id', $doctorUserId)
            ->where('starts_at', '<', $rangeEndUtc)
            ->where('ends_at', '>', $rangeStartUtc)
            ->get()
            ->each(function (ScheduleBlock $block) use (&$intervals) {
                $intervals[] = [
                    $block->starts_at->toImmutable()->utc(),
                    $block->ends_at->toImmutable()->utc(),
                ];
            });

        Appointment::query()
            ->where('tenant_id', $tenant->id)
            ->where('doctor_user_id', $doctorUserId)
            ->where('starts_at', '<', $rangeEndUtc)
            ->where('ends_at', '>', $rangeStartUtc->subMinutes(self::MAX_BUFFER_MINUTES))
            ->when($excludeAppointmentId, fn ($query) => $query->whereKeyNot($excludeAppointmentId))
            ->where(function ($query) {
                $query->where('status', AppointmentStatus::Confirmed->value)
                    ->orWhere(function ($query) {
                        $query->where('status', AppointmentStatus::PendingCustomer->value)
                            ->whereHas('proposals', fn ($proposalQuery) => $proposalQuery
                                ->where('status', AppointmentProposalStatus::Pending->value)
                                ->where('previous_appointment_status', AppointmentStatus::Confirmed->value));
                    });
            })
            ->get()
            ->each(function (Appointment $appointment) use (&$intervals) {
                $intervals[] = [
                    $appointment->starts_at->toImmutable()->utc(),
                    $appointment->ends_at->toImmutable()->addMinutes($appointment->buffer_minutes)->utc(),
                ];
            });

        AppointmentProposal::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('appointment', fn ($query) => $query->where('doctor_user_id', $doctorUserId))
            ->where('status', AppointmentProposalStatus::Pending->value)
            ->where('expires_at', '>', $nowUtc)
            ->where('starts_at', '<', $rangeEndUtc)
            ->where('ends_at', '>', $rangeStartUtc->subMinutes(self::MAX_BUFFER_MINUTES))
            ->when($excludeProposalId, fn ($query) => $query->whereKeyNot($excludeProposalId))
            ->with('appointment:id,buffer_minutes')
            ->get()
            ->each(function (AppointmentProposal $proposal) use (&$intervals) {
                $intervals[] = [
                    $proposal->starts_at->toImmutable()->utc(),
                    $proposal->ends_at->toImmutable()
                        ->addMinutes((int) ($proposal->appointment?->buffer_minutes ?? 0))
                        ->utc(),
                ];
            });

        return $intervals;
    }

    /**
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     */
    private function overlapsAny(
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        array $intervals,
    ): bool {
        foreach ($intervals as [$occupiedStart, $occupiedEnd]) {
            if ($startsAtUtc->lessThan($occupiedEnd) && $endsAtUtc->greaterThan($occupiedStart)) {
                return true;
            }
        }

        return false;
    }
}
