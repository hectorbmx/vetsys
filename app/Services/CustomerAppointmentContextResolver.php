<?php

namespace App\Services;

use App\Data\CustomerAppointmentContext;
use App\Exceptions\AppointmentDomainException;
use App\Models\Animal;
use App\Models\CustomerPortalAccess;
use App\Models\CustomerUserLink;
use App\Models\FinalUserPatientAssignment;
use Illuminate\Http\Request;

class CustomerAppointmentContextResolver
{
    public function resolve(Request $request): CustomerAppointmentContext
    {
        $user = $request->user();
        $access = $request->attributes->get('customer_portal_access');

        if (! $user || ! $access instanceof CustomerPortalAccess) {
            throw $this->forbidden('El acceso al portal no esta activo.');
        }

        $activeAccesses = CustomerPortalAccess::query()
            ->with(['customer', 'tenant'])
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
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
            ->get();

        if ($activeAccesses->count() !== 1) {
            throw $this->forbidden(
                $activeAccesses->isEmpty()
                    ? 'El acceso al portal no esta vigente.'
                    : 'El usuario tiene mas de un customer activo y requiere revision del tenant.',
            );
        }

        $access = $activeAccesses->first();
        $customer = $access->customer;
        $tenant = $access->tenant;
        $hasActiveLink = CustomerUserLink::query()
            ->where('tenant_id', $access->tenant_id)
            ->where('customer_id', $access->customer_id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->exists();

        if (! $tenant || ! $customer || $customer->status !== 'active' || ! $hasActiveLink) {
            throw $this->forbidden('El acceso del customer no esta activo.');
        }

        $visibleAnimalIds = FinalUserPatientAssignment::query()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->whereHas('animal', fn ($query) => $query
                ->where('tenant_id', $tenant->id)
                ->where('customer_id', $customer->id)
                ->where('status', 'active'))
            ->whereExists(function ($query) use ($tenant, $customer, $user) {
                $query->selectRaw('1')
                    ->from('animal_portal_visibility_settings')
                    ->whereColumn('animal_portal_visibility_settings.animal_id', 'final_user_patient_assignments.animal_id')
                    ->where('animal_portal_visibility_settings.tenant_id', $tenant->id)
                    ->where('animal_portal_visibility_settings.customer_id', $customer->id)
                    ->where('animal_portal_visibility_settings.user_id', $user->id)
                    ->where('animal_portal_visibility_settings.show_appointments', true);
            })
            ->pluck('animal_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return new CustomerAppointmentContext($user, $tenant, $customer, $access, $visibleAnimalIds);
    }

    public function authorizeAnimal(CustomerAppointmentContext $context, Animal $animal): void
    {
        if (! $context->visibleAnimalIds->contains((int) $animal->id)) {
            abort(404);
        }
    }

    private function forbidden(string $message): AppointmentDomainException
    {
        return new AppointmentDomainException('CUSTOMER_PORTAL_ACCESS_INACTIVE', $message, 403);
    }
}
