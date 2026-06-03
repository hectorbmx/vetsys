<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $user = User::where('invitation_token', hash('sha256', $token))
            ->whereNull('invitation_accepted_at')
            ->firstOrFail();

        if ($user->invitation_expires_at && $user->invitation_expires_at->isPast()) {
            abort(403, 'La invitacion ha expirado.');
        }

        return view('auth.invitation', compact('token', 'user'));
    }

    public function store(Request $request, string $token)
    {
        $user = User::where('invitation_token', hash('sha256', $token))
            ->whereNull('invitation_accepted_at')
            ->firstOrFail();

        if ($user->invitation_expires_at && $user->invitation_expires_at->isPast()) {
            return back()->with('error', 'La invitacion ha expirado.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

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
