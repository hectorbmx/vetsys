<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Exceptions\AppointmentDomainException;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

class TenantAppointmentQueryService
{
    public const MAX_RANGE_DAYS = 62;

    public function __construct(private TenantAppointmentAccessService $access) {}

    /**
     * @param  array{statuses?: array<int, string|AppointmentStatus>, customer_id?: int|null, animal_id?: int|null}  $filters
     */
    public function queryForRange(
        Tenant $tenant,
        User $actor,
        CarbonInterface|DateTimeInterface|string $from,
        CarbonInterface|DateTimeInterface|string $to,
        array $filters = [],
    ): Builder {
        $this->access->authorize($tenant, $actor);
        $timezone = $tenant->appointmentSetting()->value('timezone') ?: 'UTC';
        $fromLocal = $this->localDate($from, $timezone)->startOfDay();
        $toLocal = $this->localDate($to, $timezone)->startOfDay();

        if ($toLocal->lessThan($fromLocal)) {
            throw $this->invalidRange('La fecha final no puede ser anterior a la inicial.');
        }

        if ($fromLocal->diffInDays($toLocal) + 1 > self::MAX_RANGE_DAYS) {
            throw $this->invalidRange('El rango de agenda no puede superar 62 dias.');
        }

        $statuses = collect($filters['statuses'] ?? [])
            ->map(fn ($status) => $status instanceof AppointmentStatus ? $status->value : $status)
            ->filter()
            ->values()
            ->all();

        return Appointment::query()
            ->where('tenant_id', $tenant->id)
            ->where('starts_at', '>=', $fromLocal->utc())
            ->where('starts_at', '<', $toLocal->addDay()->utc())
            ->when($statuses, fn (Builder $query) => $query->whereIn('status', $statuses))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $customerId) => $query
                ->where('customer_id', $customerId))
            ->when($filters['animal_id'] ?? null, fn (Builder $query, $animalId) => $query
                ->where('animal_id', $animalId))
            ->with([
                'customer',
                'animal',
                'doctor.veterinarianProfile',
                'catalogItem',
                'pendingProposal.proposer',
            ])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [AppointmentStatus::PendingTenant->value])
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    public function detail(Tenant $tenant, User $actor, int $appointmentId): Appointment
    {
        $this->access->authorize($tenant, $actor);

        return Appointment::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($appointmentId)
            ->with([
                'customer',
                'animal.animalType',
                'doctor.veterinarianProfile',
                'catalogItem',
                'pendingProposal.proposer',
                'proposals' => fn ($query) => $query->latest('id'),
                'proposals.proposer',
                'events' => fn ($query) => $query->with('actor')->oldest('id'),
            ])
            ->firstOrFail();
    }

    public function formOptions(Tenant $tenant, User $actor): array
    {
        $this->access->authorize($tenant, $actor);
        $setting = $tenant->appointmentSetting()->with('doctor.veterinarianProfile')->first();

        return [
            'setting' => $setting,
            'customers' => $tenant->customers()
                ->where('status', 'active')
                ->whereHas('animals', fn ($query) => $query->where('status', 'active'))
                ->with(['animals' => fn ($query) => $query
                    ->where('status', 'active')
                    ->orderBy('name')])
                ->orderBy('name')
                ->orderBy('last_name')
                ->get(),
            'services' => $tenant->catalogItems()
                ->where('type', 'service')
                ->where('is_active', true)
                ->where('is_bookable', true)
                ->orderBy('name')
                ->get(),
        ];
    }

    private function localDate(
        CarbonInterface|DateTimeInterface|string $value,
        string $timezone,
    ): CarbonImmutable {
        if (is_string($value)) {
            return CarbonImmutable::parse($value, $timezone);
        }

        return CarbonImmutable::instance($value)->setTimezone($timezone);
    }

    private function invalidRange(string $message): AppointmentDomainException
    {
        return new AppointmentDomainException('APPOINTMENT_RANGE_INVALID', $message, 422);
    }
}
