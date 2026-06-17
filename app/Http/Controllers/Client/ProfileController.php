<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant()
            ->with([
                'plan',
                'subscriptions' => fn ($query) => $query->latest(),
                'payments' => fn ($query) => $query->latest(),
            ])
            ->first();

        $currentSubscription = $tenant->subscriptions
            ->firstWhere('status', 'active')
            ?? $tenant->subscriptions->first();

        $lastPayment = $tenant->payments
            ->where('status', 'paid')
            ->first()
            ?? $tenant->payments->first();

        $pendingCheckout = $tenant->payments
            ->where('status', 'pending')
            ->where('payment_method', 'stripe_checkout')
            ->first();
        $canCheckout = $tenant->plan && $tenant->plan->stripe_price_id;

        return view('client.profile.index', compact(
            'tenant',
            'currentSubscription',
            'lastPayment',
            'pendingCheckout',
            'canCheckout',
        ));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $oldEmail = $user->email;
        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // Si el usuario es el que creó el tenant o tiene el mismo email que el tenant,
        // actualizamos también el email del tenant para mantener consistencia.
        $tenant = $user->tenant;
        if ($tenant) {
            $tenant->phone = $request->phone;
            
            if ($tenant->email === $oldEmail) {
                $tenant->email = $user->email;
                $tenant->name = $user->name; // Opcionalmente actualizar el nombre si coinciden
            }
            
            $tenant->save();
        }

        return back()->with('success', 'Perfil actualizado correctamente.');
    }
}
