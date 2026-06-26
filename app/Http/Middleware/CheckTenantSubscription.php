<?php

namespace App\Http\Middleware;

use App\Services\Auth\TenantSessionGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->tenant_id) {
            return $next($request);
        }

        $access = app(TenantSessionGuard::class)->canLogin($user);

        if (! $access['allowed']) {
            $billingAccess = app(TenantSessionGuard::class)->canEnterBillingArea($user);

            if (($billingAccess['billing_limited'] ?? false) === true) {
                return response()->view('client.billing.restricted', [
                    'message' => $access['message'],
                    'billingStatus' => $access['billing_status'],
                ], 402);
            }

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => $access['message'],
                ]);
        }

        return $next($request);
    }
}
