<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\CustomerPaymentService;
use App\Services\StripeCustomerPaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'since' => ['nullable', 'date'],
            'customer_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $payments = Payment::with(['customer', 'paymentMethod', 'notes'])
            ->where('tenant_id', $tenantId)
            ->when(isset($data['since']), fn (Builder $query) => $query->where('updated_at', '>=', $data['since']))
            ->when(isset($data['customer_id']), fn (Builder $query) => $query->where('customer_id', $data['customer_id']))
            ->latest('id')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => $payments->getCollection()->map(fn (Payment $payment) => $this->serializePayment($payment)),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $data = $request->validate([
            'client_uuid' => ['nullable', 'uuid'],
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        if (!empty($data['client_uuid'])) {
            $existing = Payment::where('tenant_id', $tenantId)
                ->where('client_uuid', $data['client_uuid'])
                ->with(['customer', 'paymentMethod', 'notes'])
                ->first();

            if ($existing) {
                return response()->json([
                    'data' => $this->serializePayment($existing),
                    'idempotent' => true,
                ]);
            }
        }

        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($data['customer_id']);
        $payment = DB::transaction(fn () => app(CustomerPaymentService::class)->apply(
            $customer,
            (int) $data['payment_method_id'],
            (float) $data['amount'],
            [
                'client_uuid' => $data['client_uuid'] ?? null,
                'synced_from_mobile' => true,
                'reference' => $data['reference'] ?? null,
                'provider' => 'manual',
                'status' => 'paid',
            ],
        ));

        return response()->json([
            'data' => $this->serializePayment($payment->load(['customer', 'paymentMethod', 'notes'])),
            'idempotent' => false,
        ], 201);
    }

    public function show(Request $request, Payment $payment)
    {
        abort_if($payment->tenant_id !== $request->user()->tenant_id, 404);

        return response()->json([
            'data' => $this->serializePayment($payment->load(['customer', 'paymentMethod', 'notes'])),
        ]);
    }

    public function preview(Request $request, Customer $customer)
    {
        abort_if($customer->tenant_id !== $request->user()->tenant_id, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        return response()->json([
            'distribution' => $this->previewDistribution($customer, (float) $data['amount']),
        ]);
    }

    public function createPaymentLink(Request $request, Customer $customer)
    {
        abort_if($customer->tenant_id !== $request->user()->tenant_id, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('is_active', true)),
            ],
        ]);

        $method = PaymentMethod::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($data['payment_method_id']);

        abort_unless($this->isCardMethod($method), 422, 'Selecciona un metodo de pago con tarjeta.');

        try {
            $paymentLink = app(StripeCustomerPaymentService::class)->createLink(
                $customer,
                $method->id,
                (float) $data['amount'],
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'id' => $paymentLink->id,
                'public_url' => route('public.customer-payments.show', $paymentLink->token),
                'amount' => $paymentLink->amount,
                'expires_at' => $paymentLink->expires_at?->toISOString(),
            ],
        ], 201);
    }

    private function previewDistribution(Customer $customer, float $amount)
    {
        $remaining = $amount;
        $distribution = [];

        $pending = Note::where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'CANCELADA')
            ->orderBy('date_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Note $note) => $note->balance > 0);

        foreach ($pending as $note) {
            if ($remaining <= 0) {
                break;
            }

            $amountApplied = min($remaining, $note->balance);
            $distribution[] = [
                'note_id' => $note->id,
                'folio' => $note->folio,
                'balance' => $note->balance,
                'amount_applied' => $amountApplied,
                'new_balance' => round($note->balance - $amountApplied, 2),
            ];

            $remaining -= $amountApplied;
        }

        return [
            'items' => $distribution,
            'leftover' => round($remaining, 2),
        ];
    }

    private function serializePayment(Payment $payment): array
    {
        $payment->loadMissing(['customer', 'paymentMethod', 'notes']);

        return [
            'id' => $payment->id,
            'client_uuid' => $payment->client_uuid,
            'customer_id' => $payment->customer_id,
            'customer_name' => $payment->customer?->full_name,
            'payment_method_id' => $payment->payment_method_id,
            'payment_method_name' => $payment->paymentMethod?->name,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'amount_applied' => $payment->amount_applied,
            'unapplied_amount' => $payment->unapplied_amount,
            'reference' => $payment->reference,
            'synced_from_mobile' => $payment->synced_from_mobile,
            'applications' => $payment->notes->map(fn (Note $note) => [
                'note_id' => $note->id,
                'folio' => $note->folio,
                'amount_applied' => $note->pivot?->amount_applied,
            ])->values(),
            'created_at' => $payment->created_at?->toISOString(),
            'updated_at' => $payment->updated_at?->toISOString(),
        ];
    }

    private function isCardMethod(PaymentMethod $method): bool
    {
        $value = str($method->slug . ' ' . $method->name)->lower()->ascii()->toString();

        return str_contains($value, 'tarjeta')
            || str_contains($value, 'tarteja')
            || str_contains($value, 'card')
            || str_contains($value, 'stripe');
    }
}
