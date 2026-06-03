<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;

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

        return view('client.profile.index', compact('tenant', 'currentSubscription', 'lastPayment'));
    }
}
