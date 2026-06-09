<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class StripeConnectController extends Controller
{
    private $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Inicia el proceso de conexión con Stripe Connect.
     */
public function connect()
{
    $tenant = auth()->user()->tenant;

    if (!$tenant->stripe_connect_id) {
        $account = $this->stripe->accounts->create([
            'type'          => 'standard',
            'email'         => $tenant->email,
            'business_type' => 'individual',
            'metadata'      => [
                'tenant_id'   => $tenant->id,
                'tenant_name' => $tenant->name,
            ],
        ]);

        $tenant->update(['stripe_connect_id' => $account->id]);
        $tenant->stripe_connect_id = $account->id; // ✅ actualiza en memoria
    }

    $accountLink = $this->stripe->accountLinks->create([
        'account'     => $tenant->stripe_connect_id, // ya no llega null
        'refresh_url' => route('client.stripe-connect.connect'),
        'return_url'  => route('client.stripe-connect.return'),
        'type'        => 'account_onboarding',
    ]);

    return redirect($accountLink->url);
}

    /**
     * Retorno después del onboarding de Stripe.
     */
    public function return()
    {
        $tenant = auth()->user()->tenant;

        // Verificamos el estado de la cuenta en Stripe
        $account = $this->stripe->accounts->retrieve($tenant->stripe_connect_id);

        if ($account->details_submitted) {
            $tenant->update([
                'stripe_onboarding_completed' => true,
            ]);

            return redirect()->route('client.mi-configuracion.index')
                ->with('success', '¡Cuenta de Stripe conectada correctamente! Ya puedes recibir pagos.');
        }

        return redirect()->route('client.mi-configuracion.index')
            ->with('error', 'El proceso de conexión no se completó. Por favor, intenta de nuevo.');
    }
}
