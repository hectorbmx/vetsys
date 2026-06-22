<?php

namespace App\Providers;

use App\Models\User;
use App\Services\TenantAppointmentAccessService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('manage-appointment-configuration', function (User $user) {
            return $user->tenant_id && $user->hasAnyRole(['client-admin', 'admin']);
        });

        Gate::define('view-appointments', function (User $user) {
            $tenant = $user->tenant;

            return $tenant && app(TenantAppointmentAccessService::class)->allows($tenant, $user);
        });

        Gate::define('operate-appointments', function (User $user) {
            $tenant = $user->tenant;

            return $tenant && app(TenantAppointmentAccessService::class)->allows($tenant, $user);
        });
    }
}
