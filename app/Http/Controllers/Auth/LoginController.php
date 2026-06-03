<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Auth\TenantSessionGuard;


class LoginController extends Controller
{
   public function store(Request $request)
{
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (Auth::attempt($credentials, $request->boolean('remember'))) {

        $request->session()->regenerate();

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();

            return back()
                ->withErrors([
                    'email' => 'Tu usuario todavía no está activo.',
                ])
                ->onlyInput('email');
        }

        if ($user->hasRole('super-admin')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->tenant_id) {
            $access = app(TenantSessionGuard::class)->canLogin($user);

            if (!$access['allowed']) {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withErrors([
                        'email' => $access['message'],
                    ])
                    ->onlyInput('email');
            }

            return redirect()->route('client.dashboard');
        }

        Auth::logout();

        return back()
            ->withErrors([
                'email' => 'Tu usuario no tiene un perfil válido para acceder.',
            ])
            ->onlyInput('email');
    }

    return back()
        ->withErrors([
            'email' => 'Credenciales incorrectas.',
        ])
        ->onlyInput('email');
}
public function destroy(Request $request)
{
    Auth::logout();

    $request->session()->invalidate();

    $request->session()->regenerateToken();

    return redirect()->route('login');
}
}