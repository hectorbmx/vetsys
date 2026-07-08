<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\TenantHomeRoutes;
use Illuminate\Support\Facades\Route;

class TenantHomeRouteResolver
{
    public function routeNameFor(User $user): string
    {
        $routeName = TenantHomeRoutes::normalize($user->tenant?->default_home_route);

        return Route::has($routeName) ? $routeName : TenantHomeRoutes::DEFAULT;
    }
}
