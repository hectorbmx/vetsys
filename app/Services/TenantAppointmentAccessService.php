<?php

namespace App\Services;

use App\Exceptions\AppointmentDomainException;
use App\Models\Tenant;
use App\Models\User;

class TenantAppointmentAccessService
{
    public function authorize(Tenant $tenant, User $actor): void
    {
        if (! $this->allows($tenant, $actor)) {
            throw new AppointmentDomainException(
                'APPOINTMENT_FORBIDDEN',
                'No tienes permiso para operar la agenda.',
                403,
            );
        }
    }

    public function allows(Tenant $tenant, User $actor): bool
    {
        if ((int) $actor->tenant_id !== (int) $tenant->id || ! $actor->is_active) {
            return false;
        }

        if ($actor->hasAnyRole(['client-admin', 'admin'])) {
            return true;
        }

        return $actor->veterinarianProfile()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->exists();
    }
}
