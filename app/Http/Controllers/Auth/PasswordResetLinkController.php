<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController extends Controller
{
    /**
     * Mostrar formulario para solicitar recuperación.
     */
    public function create()
    {
        return view('auth.forgot-password');
    }

    /**
     * Enviar enlace de recuperación.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
    }
}