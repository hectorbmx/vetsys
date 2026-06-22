<?php

namespace Tests\Feature;

use App\Enums\AppointmentProposalStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentProposal;
use App\Models\AppointmentSetting;
use App\Models\CatalogItem;
use App\Models\DoctorSchedule;
use App\Models\ScheduleBlock;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VeterinarianProfile;
use App\Services\AppointmentAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class AppointmentAvailabilityServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_generates_local_slots_and_returns_utc_timestamps(): void
    {
        $context = $this->context();
        $this->schedule($context, 1, '09:00', '12:00');

        $slots = $this->availability($context, '2026-07-06', '2026-07-06 07:00');

        $this->assertSame(['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'], $this->localStarts($slots));
        $this->assertSame('2026-07-06T15:00:00+00:00', $slots->first()->startsAtUtc->toIso8601String());
        $this->assertSame('2026-07-06T09:00:00-06:00', $slots->first()->localStartsAt->toIso8601String());
        $this->assertSame('America/Mexico_City', $slots->first()->toArray()['timezone']);
        $this->assertSame(30, $slots->first()->toArray()['duration_minutes']);
    }

    public function test_duration_and_buffer_must_fit_completely_inside_schedule(): void
    {
        $context = $this->context(serviceOverrides: [
            'appointment_duration_minutes' => 45,
            'appointment_buffer_minutes' => 15,
        ]);
        $this->schedule($context, 1, '09:00', '11:00');

        $slots = $this->availability($context, '2026-07-06', '2026-07-06 07:00');

        $this->assertSame(['09:00', '09:30', '10:00'], $this->localStarts($slots));
        $this->assertSame(45, $slots->first()->durationMinutes);
        $this->assertSame(15, $slots->first()->bufferMinutes);
    }

    public function test_services_with_different_durations_produce_different_slots(): void
    {
        $context = $this->context();
        $this->schedule($context, 1, '09:00', '11:00');
        $longService = CatalogItem::create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Consulta larga',
            'type' => 'service',
            'is_active' => true,
            'is_bookable' => true,
            'appointment_duration_minutes' => 60,
            'appointment_buffer_minutes' => 0,
        ]);
        $availability = app(AppointmentAvailabilityService::class);
        $now = $this->utc('2026-07-06 07:00', $context['timezone']);

        $shortSlots = $availability->slotsForDate(
            $context['tenant'],
            $context['service'],
            '2026-07-06',
            $now,
        );
        $longSlots = $availability->slotsForDate(
            $context['tenant'],
            $longService,
            '2026-07-06',
            $now,
        );

        $this->assertCount(4, $shortSlots);
        $this->assertCount(3, $longSlots);
        $this->assertSame(60, $longSlots->first()->durationMinutes);
    }

    public function test_minimum_notice_and_booking_window_are_enforced(): void
    {
        $context = $this->context(settingOverrides: [
            'minimum_notice_minutes' => 120,
            'booking_window_days' => 2,
        ]);
        $this->schedule($context, 1, '09:00', '13:00');

        $today = $this->availability($context, '2026-07-06', '2026-07-06 08:15');
        $outsideWindow = $this->availability($context, '2026-07-09', '2026-07-06 08:15');

        $this->assertSame(['10:30', '11:00', '11:30', '12:00', '12:30'], $this->localStarts($today));
        $this->assertTrue($outsideWindow->isEmpty());
    }

    public function test_day_without_schedule_and_disabled_booking_return_no_slots(): void
    {
        $context = $this->context();
        $this->schedule($context, 1, '09:00', '12:00');

        $this->assertTrue($this->availability($context, '2026-07-07', '2026-07-06 07:00')->isEmpty());

        $context['setting']->update(['is_customer_booking_enabled' => false]);
        $this->assertTrue($this->availability($context, '2026-07-06', '2026-07-06 07:00')->isEmpty());
    }

    public function test_schedule_blocks_remove_overlapping_slots(): void
    {
        $context = $this->context();
        $this->schedule($context, 1, '09:00', '12:00');
        ScheduleBlock::create([
            'tenant_id' => $context['tenant']->id,
            'doctor_user_id' => $context['doctor']->id,
            'starts_at' => $this->utc('2026-07-06 10:00', $context['timezone']),
            'ends_at' => $this->utc('2026-07-06 11:00', $context['timezone']),
        ]);

        $slots = $this->availability($context, '2026-07-06', '2026-07-06 07:00');

        $this->assertSame(['09:00', '09:30', '11:00', '11:30'], $this->localStarts($slots));
    }

    public function test_confirmed_appointments_block_but_pending_requests_do_not(): void
    {
        $context = $this->context();
        $this->schedule($context, 1, '09:00', '12:00');
        $this->appointment($context, '2026-07-06 10:00', AppointmentStatus::Confirmed);
        $this->appointment($context, '2026-07-06 11:00', AppointmentStatus::PendingTenant);

        $slots = $this->availability($context, '2026-07-06', '2026-07-06 07:00');

        $this->assertNotContains('10:00', $this->localStarts($slots));
        $this->assertContains('11:00', $this->localStarts($slots));
    }

    public function test_existing_appointment_buffer_blocks_the_following_slot(): void
    {
        $context = $this->context();
        $this->schedule($context, 1, '09:00', '12:00');
        $this->appointment(
            $context,
            '2026-07-06 10:00',
            AppointmentStatus::Confirmed,
            bufferMinutes: 15,
        );

        $slots = $this->availability($context, '2026-07-06', '2026-07-06 07:00');

        $this->assertNotContains('10:00', $this->localStarts($slots));
        $this->assertNotContains('10:30', $this->localStarts($slots));
        $this->assertContains('11:00', $this->localStarts($slots));
    }

    public function test_previous_day_buffer_can_block_first_slot_of_next_day(): void
    {
        $context = $this->context();
        $this->schedule($context, 2, '00:00', '01:00');
        $this->appointment(
            $context,
            '2026-07-06 23:30',
            AppointmentStatus::Confirmed,
            bufferMinutes: 30,
        );

        $slots = $this->availability($context, '2026-07-07', '2026-07-06 07:00');

        $this->assertNotContains('00:00', $this->localStarts($slots));
        $this->assertContains('00:30', $this->localStarts($slots));
    }

    public function test_active_proposal_blocks_while_expired_proposal_does_not(): void
    {
        $context = $this->context();
        $this->schedule($context, 1, '09:00', '12:00');
        $appointment = $this->appointment($context, '2026-07-06 09:00', AppointmentStatus::PendingCustomer);
        $this->proposal($context, $appointment, '2026-07-06 10:00', '2026-07-06 08:00');
        $this->proposal($context, $appointment, '2026-07-06 11:00', '2026-07-06 06:00');

        $slots = $this->availability($context, '2026-07-06', '2026-07-06 07:00');

        $this->assertNotContains('10:00', $this->localStarts($slots));
        $this->assertContains('11:00', $this->localStarts($slots));
    }

    public function test_proposal_for_another_doctor_in_same_tenant_does_not_block(): void
    {
        $context = $this->context();
        $this->schedule($context, 1, '09:00', '11:00');
        $otherDoctor = User::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'is_active' => true,
        ]);
        $otherContext = array_merge($context, ['doctor' => $otherDoctor]);
        $appointment = $this->appointment(
            $otherContext,
            '2026-07-06 09:00',
            AppointmentStatus::PendingCustomer,
        );
        $this->proposal($otherContext, $appointment, '2026-07-06 10:00', '2026-07-06 08:00');

        $slots = $this->availability($context, '2026-07-06', '2026-07-06 07:00');

        $this->assertContains('10:00', $this->localStarts($slots));
    }

    public function test_reprogramming_holds_original_and_proposed_intervals(): void
    {
        $context = $this->context();
        $this->schedule($context, 1, '09:00', '12:00');
        $appointment = $this->appointment($context, '2026-07-06 10:00', AppointmentStatus::PendingCustomer);
        $this->proposal(
            $context,
            $appointment,
            '2026-07-06 11:00',
            '2026-07-06 08:00',
            AppointmentStatus::Confirmed,
        );

        $slots = $this->availability($context, '2026-07-06', '2026-07-06 07:00');

        $this->assertNotContains('10:00', $this->localStarts($slots));
        $this->assertNotContains('11:00', $this->localStarts($slots));
    }

    public function test_occupations_from_another_tenant_are_ignored(): void
    {
        $context = $this->context();
        $other = $this->context('other');
        $this->schedule($context, 1, '09:00', '11:00');
        $this->appointment($other, '2026-07-06 09:00', AppointmentStatus::Confirmed);

        $slots = $this->availability($context, '2026-07-06', '2026-07-06 07:00');

        $this->assertContains('09:00', $this->localStarts($slots));
    }

    public function test_dst_spring_transition_skips_nonexistent_local_hour(): void
    {
        $context = $this->context(
            settingOverrides: [
                'timezone' => 'America/New_York',
                'slot_interval_minutes' => 60,
            ],
            serviceOverrides: ['appointment_duration_minutes' => 60],
        );
        $this->schedule($context, 7, '01:00', '05:00');

        $slots = $this->availability($context, '2026-03-08', '2026-03-08 00:00');

        $this->assertSame(['01:00', '03:00', '04:00'], $this->localStarts($slots));
        $this->assertSame(['-05:00', '-04:00', '-04:00'], $slots->map(
            fn ($slot) => $slot->localStartsAt->format('P')
        )->all());
    }

    public function test_dst_fall_transition_keeps_both_repeated_local_hours_with_offsets(): void
    {
        $context = $this->context(
            settingOverrides: [
                'timezone' => 'America/New_York',
                'slot_interval_minutes' => 60,
            ],
            serviceOverrides: ['appointment_duration_minutes' => 60],
        );
        $this->schedule($context, 7, '00:00', '04:00');

        $slots = $this->availability($context, '2026-11-01', '2026-11-01 00:00');

        $this->assertSame(['00:00', '01:00', '01:00', '02:00', '03:00'], $this->localStarts($slots));
        $this->assertSame(['-04:00', '-04:00', '-05:00', '-05:00', '-05:00'], $slots->map(
            fn ($slot) => $slot->localStartsAt->format('P')
        )->all());
    }

    public function test_range_is_keyed_by_date_and_limited_to_thirty_one_days(): void
    {
        $context = $this->context();
        $this->schedule($context, 1, '09:00', '10:00');
        $this->schedule($context, 2, '09:00', '10:00');
        $service = app(AppointmentAvailabilityService::class);
        $now = $this->utc('2026-07-06 07:00', $context['timezone']);

        $range = $service->slotsForRange(
            $context['tenant'],
            $context['service'],
            '2026-07-06',
            '2026-07-07',
            $now,
        );

        $this->assertSame(['2026-07-06', '2026-07-07'], $range->keys()->all());
        $this->assertCount(2, $range->get('2026-07-06'));

        $this->expectException(InvalidArgumentException::class);
        $service->slotsForRange(
            $context['tenant'],
            $context['service'],
            '2026-07-01',
            '2026-08-01',
            $now,
        );
    }

    private function context(
        string $suffix = 'main',
        array $settingOverrides = [],
        array $serviceOverrides = [],
    ): array {
        $timezone = $settingOverrides['timezone'] ?? 'America/Mexico_City';
        $tenant = Tenant::factory()->create([
            'name' => 'Availability '.$suffix,
            'slug' => 'availability-'.$suffix.'-'.str()->random(6),
        ]);
        $doctor = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        VeterinarianProfile::create([
            'tenant_id' => $tenant->id,
            'user_id' => $doctor->id,
            'professional_name' => 'Dra. Disponibilidad',
            'professional_title' => 'MVZ',
            'license_number' => 'AV-'.str()->upper(str()->random(8)),
            'is_active' => true,
        ]);
        $setting = AppointmentSetting::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctor->id,
            'timezone' => $timezone,
            'slot_interval_minutes' => 30,
            'default_duration_minutes' => 30,
            'minimum_notice_minutes' => 0,
            'booking_window_days' => 60,
            'is_customer_booking_enabled' => true,
        ], $settingOverrides));
        $service = CatalogItem::create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Consulta '.$suffix,
            'type' => 'service',
            'is_active' => true,
            'is_bookable' => true,
            'appointment_duration_minutes' => 30,
            'appointment_buffer_minutes' => 0,
        ], $serviceOverrides));

        return compact('tenant', 'doctor', 'setting', 'service', 'timezone');
    }

    private function schedule(array $context, int $weekday, string $startsAt, string $endsAt): DoctorSchedule
    {
        return DoctorSchedule::create([
            'tenant_id' => $context['tenant']->id,
            'doctor_user_id' => $context['doctor']->id,
            'weekday' => $weekday,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => true,
        ]);
    }

    private function appointment(
        array $context,
        string $localStartsAt,
        AppointmentStatus $status,
        int $durationMinutes = 30,
        int $bufferMinutes = 0,
    ): Appointment {
        $startsAt = $this->utc($localStartsAt, $context['timezone']);

        return Appointment::create([
            'tenant_id' => $context['tenant']->id,
            'doctor_user_id' => $context['doctor']->id,
            'catalog_item_id' => $context['service']->id,
            'service_name_snapshot' => $context['service']->name,
            'animal_name_snapshot' => 'Paciente',
            'doctor_name_snapshot' => 'Dra. Disponibilidad',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($durationMinutes),
            'timezone' => $context['timezone'],
            'duration_minutes' => $durationMinutes,
            'buffer_minutes' => $bufferMinutes,
            'status' => $status,
            'requested_at' => $startsAt->subDay(),
        ]);
    }

    private function proposal(
        array $context,
        Appointment $appointment,
        string $localStartsAt,
        string $localExpiresAt,
        AppointmentStatus $previousStatus = AppointmentStatus::PendingTenant,
    ): AppointmentProposal {
        $startsAt = $this->utc($localStartsAt, $context['timezone']);

        return AppointmentProposal::create([
            'tenant_id' => $context['tenant']->id,
            'appointment_id' => $appointment->id,
            'proposed_by_user_id' => $context['doctor']->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
            'duration_minutes' => 30,
            'previous_appointment_status' => $previousStatus,
            'status' => AppointmentProposalStatus::Pending,
            'expires_at' => $this->utc($localExpiresAt, $context['timezone']),
        ]);
    }

    private function availability(array $context, string $date, string $localNow)
    {
        return app(AppointmentAvailabilityService::class)->slotsForDate(
            $context['tenant'],
            $context['service'],
            $date,
            $this->utc($localNow, $context['timezone']),
        );
    }

    private function utc(string $localDateTime, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($localDateTime, $timezone)->utc();
    }

    private function localStarts($slots): array
    {
        return $slots->map(fn ($slot) => $slot->localStartsAt->format('H:i'))->all();
    }
}
