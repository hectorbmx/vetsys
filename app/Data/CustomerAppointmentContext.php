<?php

namespace App\Data;

use App\Models\Customer;
use App\Models\CustomerPortalAccess;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class CustomerAppointmentContext
{
    /**
     * @param  Collection<int, int>  $visibleAnimalIds
     */
    public function __construct(
        public User $user,
        public Tenant $tenant,
        public Customer $customer,
        public CustomerPortalAccess $access,
        public Collection $visibleAnimalIds,
    ) {}
}
