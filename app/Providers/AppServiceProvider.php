<?php

namespace App\Providers;

use App\Models\TenantNotification;
use App\Models\AdminNotification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.client', function ($view) {
            $user = auth()->user();

            if (!$user || !$user->tenant_id) {
                $view->with('layoutNotifications', collect());
                $view->with('layoutUnreadNotificationsCount', 0);
                return;
            }

            $baseQuery = TenantNotification::query()
                ->where('tenant_id', $user->tenant_id);

            $view->with('layoutNotifications', (clone $baseQuery)
                ->latest()
                ->limit(6)
                ->get());

            $view->with('layoutUnreadNotificationsCount', (clone $baseQuery)
                ->whereNull('read_at')
                ->count());
        });

        View::composer('layouts.admin', function ($view) {
            $user = auth()->user();

            if (!$user || !$user->hasRole('super-admin')) {
                $view->with('layoutAdminNotifications', collect());
                $view->with('layoutAdminUnreadNotificationsCount', 0);
                return;
            }

            $baseQuery = AdminNotification::query();

            $view->with('layoutAdminNotifications', (clone $baseQuery)
                ->latest()
                ->limit(6)
                ->get());

            $view->with('layoutAdminUnreadNotificationsCount', (clone $baseQuery)
                ->whereNull('read_at')
                ->count());
        });
    }
}
