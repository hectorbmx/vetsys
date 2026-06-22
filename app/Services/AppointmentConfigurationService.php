<?php

namespace App\Services;

use App\Models\AppointmentSetting;
use App\Models\CatalogItem;
use App\Models\DoctorSchedule;
use App\Models\ScheduleBlock;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentConfigurationService
{
    public function viewData(Tenant $tenant, User $user): array
    {
        $setting = $tenant->appointmentSetting()->firstOrNew();
        $doctorId = $setting->doctor_user_id;

        $doctors = $tenant->users()
            ->where('is_active', true)
            ->whereHas('veterinarianProfile', fn ($query) => $query->where('is_active', true))
            ->with('veterinarianProfile')
            ->orderBy('name')
            ->get();

        $schedules = DoctorSchedule::query()
            ->where('tenant_id', $tenant->id)
            ->when($doctorId, fn ($query) => $query->where('doctor_user_id', $doctorId))
            ->when(! $doctorId, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get();

        $blocks = ScheduleBlock::query()
            ->where('tenant_id', $tenant->id)
            ->when($doctorId, fn ($query) => $query->where('doctor_user_id', $doctorId))
            ->when(! $doctorId, fn ($query) => $query->whereRaw('1 = 0'))
            ->where('ends_at', '>=', now()->utc())
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        $services = $tenant->catalogItems()
            ->where('type', 'service')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return [
            'appointmentSetting' => $setting,
            'appointmentDoctors' => $doctors,
            'appointmentSchedules' => $schedules,
            'appointmentBlocks' => $blocks,
            'appointmentServices' => $services,
            'appointmentReadiness' => $this->readiness($tenant, $setting),
            'appointmentWeekdays' => $this->weekdays(),
            'appointmentTimezones' => DateTimeZone::listIdentifiers(),
            'canManageAgenda' => $user->can('manage-appointment-configuration'),
        ];
    }

    public function updateSettings(Tenant $tenant, User $actor, array $data): AppointmentSetting
    {
        return DB::transaction(function () use ($tenant, $actor, $data) {
            $doctor = null;

            if (! empty($data['doctor_user_id'])) {
                $doctor = $tenant->users()
                    ->where('is_active', true)
                    ->whereHas('veterinarianProfile', fn ($query) => $query->where('is_active', true))
                    ->find($data['doctor_user_id']);

                if (! $doctor) {
                    throw ValidationException::withMessages([
                        'doctor_user_id' => 'Selecciona un veterinario activo con perfil profesional.',
                    ]);
                }
            }

            $setting = $tenant->appointmentSetting()->firstOrNew();
            $setting->fill($data);
            $setting->doctor_user_id = $doctor?->id;
            $setting->is_customer_booking_enabled = (bool) ($data['is_customer_booking_enabled'] ?? false);
            $setting->created_by ??= $actor->id;
            $setting->save();

            if ($setting->is_customer_booking_enabled) {
                $readiness = $this->readiness($tenant, $setting);

                if (! $readiness['ready']) {
                    throw ValidationException::withMessages([
                        'is_customer_booking_enabled' => 'Completa veterinario, horario y servicio agendable antes de habilitar reservas.',
                    ]);
                }
            }

            return $setting;
        });
    }

    public function storeSchedule(Tenant $tenant, array $data): DoctorSchedule
    {
        $setting = $tenant->appointmentSetting()->first();
        $doctorId = $setting?->doctor_user_id;

        if (! $doctorId) {
            throw ValidationException::withMessages([
                'doctor_user_id' => 'Selecciona y guarda primero al veterinario agendable.',
            ]);
        }

        $overlap = DoctorSchedule::query()
            ->where('tenant_id', $tenant->id)
            ->where('doctor_user_id', $doctorId)
            ->where('weekday', $data['weekday'])
            ->where('starts_at', '<', $data['ends_at'])
            ->where('ends_at', '>', $data['starts_at'])
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_at' => 'El horario se cruza con otro bloque del mismo dia.',
            ]);
        }

        return DoctorSchedule::create([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctorId,
            'weekday' => $data['weekday'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'is_active' => true,
        ]);
    }

    public function deleteSchedule(Tenant $tenant, DoctorSchedule $schedule): void
    {
        $this->ensureTenant($tenant, $schedule->tenant_id);
        $schedule->delete();
        $this->disableBookingIfIncomplete($tenant);
    }

    public function storeBlock(Tenant $tenant, User $actor, array $data): ScheduleBlock
    {
        $setting = $tenant->appointmentSetting()->first();
        $doctorId = $setting?->doctor_user_id;

        if (! $doctorId) {
            throw ValidationException::withMessages([
                'doctor_user_id' => 'Selecciona y guarda primero al veterinario agendable.',
            ]);
        }

        $timezone = $setting->timezone;
        $startsAt = Carbon::parse($data['starts_at'], $timezone)->utc();
        $endsAt = Carbon::parse($data['ends_at'], $timezone)->utc();

        $overlap = ScheduleBlock::query()
            ->where('tenant_id', $tenant->id)
            ->where('doctor_user_id', $doctorId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_at' => 'La ausencia se cruza con otro bloqueo existente.',
            ]);
        }

        return ScheduleBlock::create([
            'tenant_id' => $tenant->id,
            'doctor_user_id' => $doctorId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $data['reason'] ?? null,
            'created_by' => $actor->id,
        ]);
    }

    public function deleteBlock(Tenant $tenant, ScheduleBlock $block): void
    {
        $this->ensureTenant($tenant, $block->tenant_id);
        $block->delete();
    }

    public function updateBookableService(Tenant $tenant, CatalogItem $item, array $data): void
    {
        $this->ensureTenant($tenant, $item->tenant_id);

        if ($item->type !== 'service') {
            throw ValidationException::withMessages([
                'is_bookable' => 'Solo los servicios pueden habilitarse para agenda.',
            ]);
        }

        if (($data['is_bookable'] ?? false) && ! $item->is_active) {
            throw ValidationException::withMessages([
                'is_bookable' => 'Activa el servicio antes de habilitarlo para agenda.',
            ]);
        }

        $item->update([
            'is_bookable' => (bool) ($data['is_bookable'] ?? false),
            'appointment_duration_minutes' => $data['appointment_duration_minutes'] ?? $item->appointment_duration_minutes,
            'appointment_buffer_minutes' => $data['appointment_buffer_minutes'] ?? 0,
            'booking_description' => $data['booking_description'] ?? null,
        ]);

        $this->disableBookingIfIncomplete($tenant);
    }

    public function readiness(Tenant $tenant, ?AppointmentSetting $setting = null): array
    {
        $setting ??= $tenant->appointmentSetting()->first();
        $doctorReady = $setting?->doctor_user_id
            && $tenant->users()
                ->whereKey($setting->doctor_user_id)
                ->where('is_active', true)
                ->whereHas('veterinarianProfile', fn ($query) => $query->where('is_active', true))
                ->exists();
        $scheduleReady = $doctorReady && DoctorSchedule::query()
            ->where('tenant_id', $tenant->id)
            ->where('doctor_user_id', $setting->doctor_user_id)
            ->where('is_active', true)
            ->exists();
        $serviceReady = $tenant->catalogItems()
            ->where('type', 'service')
            ->where('is_active', true)
            ->where('is_bookable', true)
            ->whereNotNull('appointment_duration_minutes')
            ->exists();

        return [
            'doctor' => (bool) $doctorReady,
            'schedule' => (bool) $scheduleReady,
            'service' => $serviceReady,
            'ready' => (bool) ($doctorReady && $scheduleReady && $serviceReady),
        ];
    }

    private function disableBookingIfIncomplete(Tenant $tenant): void
    {
        $setting = $tenant->appointmentSetting()->first();

        if ($setting?->is_customer_booking_enabled && ! $this->readiness($tenant, $setting)['ready']) {
            $setting->update(['is_customer_booking_enabled' => false]);
        }
    }

    private function ensureTenant(Tenant $tenant, int $resourceTenantId): void
    {
        abort_unless((int) $tenant->id === $resourceTenantId, 404);
    }

    private function weekdays(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miercoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sabado',
            7 => 'Domingo',
        ];
    }
}
