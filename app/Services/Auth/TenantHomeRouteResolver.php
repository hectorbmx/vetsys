<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\TenantHomeRoutes;
use App\Support\TenantMenuModules;
use Illuminate\Support\Facades\Route;

class TenantHomeRouteResolver
{
    public function routeNameFor(User $user): string
    {
        $tenant = $user->tenant;
        $routeName = TenantHomeRoutes::normalize($tenant?->default_home_route);

        $module = TenantMenuModules::moduleForRoute($routeName);

        if ($module && ! TenantMenuModules::isVisible($tenant?->visible_menu_modules, $module)) {
            $routeName = $this->firstVisibleHomeRoute($tenant?->visible_menu_modules);
        }

        return Route::has($routeName) ? $routeName : TenantHomeRoutes::DEFAULT;
    }

    private function firstVisibleHomeRoute(?array $visibleModules): string
    {
        $visibleModules = TenantMenuModules::normalize($visibleModules);

        foreach (TenantHomeRoutes::keys() as $routeName) {
            $module = TenantMenuModules::moduleForRoute($routeName);

            if (! $module || in_array($module, $visibleModules, true)) {
                return $routeName;
            }
        }

        return TenantHomeRoutes::DEFAULT;
    }
}
