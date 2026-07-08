<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\TenantHomeRouteResolver;
use App\Services\Auth\TenantSessionGuard;
use App\Services\Auth\UserAccessSessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, false)) {
            return back()->withErrors(['email' => 'Credenciales incorrectas.'])->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->is_active) {
            $this->logoutCurrentDevice($request);

            return back()->withErrors(['email' => 'Tu usuario todavia no esta activo.'])->onlyInput('email');
        }

        if ($user->hasRole('super-admin')) {
            $request->session()->regenerate();

            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole('customer')) {
            $this->logoutCurrentDevice($request);

            return back()->withErrors([
                'email' => 'Tu acceso es exclusivo para la app movil del cliente.',
            ])->onlyInput('email');
        }

        if (! $user->tenant_id) {
            $this->logoutCurrentDevice($request);

            return back()->withErrors(['email' => 'Tu usuario no tiene un perfil valido para acceder.'])->onlyInput('email');
        }

        $access = app(TenantSessionGuard::class)->canEnterBillingArea($user);

        if (! $access['allowed']) {
            $this->logoutCurrentDevice($request);

            return back()->withErrors(['email' => $access['message']])->onlyInput('email');
        }

        $accessManager = app(UserAccessSessionManager::class);

        if (! $accessManager->planAllows($user, 'web')) {
            $this->logoutCurrentDevice($request);

            return back()->withErrors(['email' => 'Tu plan no incluye acceso web.'])->onlyInput('email');
        }

        // Prevent an older "remember me" cookie from reviving a replaced session.
        $user->forceFill([
            'remember_token' => Str::random(60),
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $accessManager->registerWeb($user, $request);

        if ($access['billing_limited'] ?? false) {
            return redirect()->route('client.profile.index');
        }

        return redirect()->route(app(TenantHomeRouteResolver::class)->routeNameFor($user));
    }

    public function destroy(Request $request)
    {
        app(UserAccessSessionManager::class)->revokeCurrentWeb($request);
        $this->logoutCurrentDevice($request);

        return redirect()->route('login');
    }

    private function logoutCurrentDevice(Request $request): void
    {
        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
