<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ActivationController extends Controller
{
    public function show()
    {
        return view('auth.activate');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('invitation_token', User::activationCodeHash($validated['code']))
            ->whereNull('invitation_accepted_at')
            ->first();

        if (!$user) {
            return back()
                ->withErrors(['code' => 'El codigo o correo no coinciden con una cuenta pendiente.'])
                ->withInput($request->only('email'));
        }

        if ($user->invitation_expires_at && $user->invitation_expires_at->isPast()) {
            return back()
                ->withErrors(['code' => 'El codigo de activacion ha expirado. Solicita uno nuevo.'])
                ->withInput($request->only('email'));
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            'is_active' => true,
            'email_verified_at' => now(),
            'invitation_accepted_at' => now(),
            'invitation_token' => null,
            'invitation_expires_at' => null,
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'Cuenta activada correctamente. Ya puedes iniciar sesion.');
    }
}
