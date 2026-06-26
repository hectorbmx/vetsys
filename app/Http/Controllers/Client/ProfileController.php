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
        $pendingPayment = $tenant->payments
            ->where('status', 'pending')
            ->first();
        $canCheckout = $tenant->plan && $tenant->plan->stripe_price_id;
        $billingSummary = $this->billingSummary($tenant, $currentSubscription, $lastPayment, $pendingPayment);

        return view('client.profile.index', compact(
            'tenant',
            'currentSubscription',
            'lastPayment',
            'pendingCheckout',
            'pendingPayment',
            'canCheckout',
            'billingSummary',
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

    private function billingSummary($tenant, $currentSubscription, $lastPayment, $pendingPayment): array
    {
        $activeSubscription = $tenant->subscriptions
            ->where('status', 'active')
            ->filter(fn ($subscription) => ! $subscription->ends_at || $subscription->ends_at->isFuture())
            ->sortByDesc(fn ($subscription) => $subscription->starts_at ?? $subscription->created_at)
            ->first();
        $paidPayment = $tenant->payments
            ->where('status', 'paid')
            ->filter(fn ($payment) => ! $payment->period_ends_at || $payment->period_ends_at->isFuture())
            ->sortByDesc(fn ($payment) => $payment->period_ends_at ?? $payment->paid_at ?? $payment->created_at)
            ->first();

        if ($activeSubscription && $paidPayment) {
            $isTrial = $paidPayment->payment_method === 'trial' && (float) $paidPayment->amount === 0.0;

            return [
                'status' => $isTrial ? 'trial_active' : 'paid_active',
                'title' => $isTrial ? 'Trial activo' : 'Suscripcion activa',
                'description' => $isTrial
                    ? 'Tu acceso de prueba esta vigente hasta la fecha indicada.'
                    : 'Tu plan esta pagado y vigente.',
                'action_label' => $isTrial ? 'Pagar plan' : 'Renovar plan',
                'can_pay' => true,
                'ends_at' => $activeSubscription->ends_at ?? $paidPayment->period_ends_at,
                'badge' => $isTrial ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700',
            ];
        }

        if ($pendingPayment) {
            return [
                'status' => 'pending_payment',
                'title' => $pendingPayment->payment_method === 'stripe_checkout' ? 'Pago pendiente' : 'Pago por confirmar',
                'description' => $pendingPayment->payment_method === 'stripe_checkout'
                    ? 'Continua el pago en Stripe para habilitar el acceso operativo.'
                    : 'Tu pago esta pendiente de confirmacion administrativa.',
                'action_label' => $pendingPayment->payment_method === 'stripe_checkout' ? 'Continuar pago' : 'Ver estado',
                'can_pay' => $pendingPayment->payment_method === 'stripe_checkout',
                'ends_at' => $pendingPayment->period_ends_at,
                'badge' => 'bg-amber-50 text-amber-700',
            ];
        }

        if ($tenant->trial_ends_at && $tenant->trial_ends_at->isPast()) {
            return [
                'status' => 'trial_expired',
                'title' => 'Trial vencido',
                'description' => 'Tu periodo de prueba termino. Paga tu plan para recuperar acceso operativo.',
                'action_label' => 'Pagar plan',
                'can_pay' => true,
                'ends_at' => $tenant->trial_ends_at,
                'badge' => 'bg-rose-50 text-rose-700',
            ];
        }

        if ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isPast()) {
            return [
                'status' => 'subscription_expired',
                'title' => 'Suscripcion vencida',
                'description' => 'La vigencia de tu plan termino. Renueva para continuar.',
                'action_label' => 'Renovar plan',
                'can_pay' => true,
                'ends_at' => $tenant->subscription_ends_at,
                'badge' => 'bg-rose-50 text-rose-700',
            ];
        }

        return [
            'status' => 'needs_review',
            'title' => 'Plan pendiente de activacion',
            'description' => 'Tu cuenta tiene un plan asignado, pero falta registrar pago o trial.',
            'action_label' => 'Pagar plan',
            'can_pay' => true,
            'ends_at' => $tenant->subscription_ends_at ?? $currentSubscription?->ends_at ?? $lastPayment?->period_ends_at,
            'badge' => 'bg-amber-50 text-amber-700',
        ];
    }
}
