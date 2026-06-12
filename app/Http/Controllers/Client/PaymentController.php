<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Note;
use App\Models\PaymentMethod;
use App\Services\CustomerPaymentService;
use App\Services\StripeCustomerPaymentService;
use App\Services\TenantOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function preview(Request $request, Customer $customer)
    {
        abort_if($customer->tenant_id !== auth()->user()->tenant_id, 403);
        $amount = (float) $request->input('amount', 0);

        if ($amount <= 0) {
            return response()->json(['distribution' => [], 'leftover' => 0]);
        }

        $pending = Note::where('customer_id', $customer->id)
            ->where('tenant_id', $customer->tenant_id)
            ->orderBy('date_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Note $note) => $note->balance > 0);

        $remaining = $amount;
        $distribution = [];

        foreach ($pending as $note) {
            if ($remaining <= 0) {
                break;
            }

            $apply = min($remaining, $note->balance);
            $distribution[] = [
                'folio' => $note->folio,
                'balance' => $note->balance,
                'amount_applied' => $apply,
                'new_balance' => round($note->balance - $apply, 2),
            ];
            $remaining -= $apply;
        }

        return response()->json([
            'distribution' => $distribution,
            'leftover' => round($remaining, 2),
        ]);
    }

    public function store(Request $request, Customer $customer)
    {
        $tenantId = auth()->user()->tenant_id;
        abort_if($customer->tenant_id !== $tenantId, 403);
        $data = $this->validatePayment($request, $tenantId);
        $method = PaymentMethod::findOrFail($data['payment_method_id']);

        if ($this->isCardMethod($method)) {
            return $this->generateStripeLink($customer, $method, (float) $data['amount']);
        }

        DB::transaction(fn () => app(CustomerPaymentService::class)->apply(
            $customer,
            (int) $data['payment_method_id'],
            (float) $data['amount'],
            [
                'reference' => $data['reference'] ?? null,
                'provider' => 'manual',
                'status' => 'paid',
            ]
        ));

        app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);

        return redirect()
            ->route('client.customers.show', $customer)
            ->with('success', 'Pago registrado correctamente.');
    }

    public function createStripePaymentLink(Request $request, Customer $customer)
    {
        $tenantId = auth()->user()->tenant_id;
        abort_if($customer->tenant_id !== $tenantId, 403);
        $data = $this->validatePayment($request, $tenantId);
        $method = PaymentMethod::findOrFail($data['payment_method_id']);
        abort_unless($this->isCardMethod($method), 422, 'Selecciona un metodo de pago con tarjeta.');

        return $this->generateStripeLink($customer, $method, (float) $data['amount']);
    }

    private function validatePayment(Request $request, int $tenantId): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)),
            ],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function isCardMethod(PaymentMethod $method): bool
    {
        $value = str($method->slug . ' ' . $method->name)->lower()->ascii()->toString();

        return str_contains($value, 'tarjeta')
            || str_contains($value, 'tarteja')
            || str_contains($value, 'card')
            || str_contains($value, 'stripe');
    }

    private function generateStripeLink(Customer $customer, PaymentMethod $method, float $amount)
    {
        try {
            $paymentLink = app(StripeCustomerPaymentService::class)->createLink(
                $customer,
                $method->id,
                $amount
            );
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'No se pudo generar el link Stripe: ' . $exception->getMessage());
        }

        return back()
            ->with('success', 'Link de pago general generado correctamente.')
            ->with('customer_payment_link_url', route('public.customer-payments.show', $paymentLink->token));
    }
}
