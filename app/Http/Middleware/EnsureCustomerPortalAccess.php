<?php

namespace App\Http\Middleware;

use App\Models\CustomerPortalAccess;
use App\Models\TenantPortalSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if (!$user->hasRole('customer')) {
            return response()->json(['message' => 'Este acceso es solo para customers.'], 403);
        }

        if (!$user->tenant_id || !$user->is_active) {
            return response()->json(['message' => 'Tu usuario no tiene acceso activo.'], 403);
        }

        $setting = TenantPortalSetting::firstOrCreate(
            ['tenant_id' => $user->tenant_id],
            [
                'is_portal_enabled' => true,
                'is_mobile_access_enabled' => true,
                'access_mode' => 'free',
                'default_access_status' => 'active',
                'requires_manual_activation' => true,
                'currency' => 'MXN',
            ]
        );

        if (!$setting->is_portal_enabled || !$setting->is_mobile_access_enabled || $setting->access_mode === 'disabled') {
            return response()->json(['message' => 'El portal/app no esta habilitado para este tenant.'], 403);
        }

        $access = CustomerPortalAccess::with(['customer'])
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('access_ends_at')
                    ->orWhere('access_ends_at', '>=', now());
            })
            ->first();

        if (!$access) {
            return response()->json(['message' => 'Tu acceso al portal/app no esta activo.'], 403);
        }

        $request->attributes->set('customer_portal_access', $access);
        $request->attributes->set('customer_portal_setting', $setting);

        return $next($request);
    }
}
