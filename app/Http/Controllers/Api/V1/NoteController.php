<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\NotePaymentLink;
use App\Models\PaymentMethod;
use App\Services\StripeNotePaymentService;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'since' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
            'customer_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['PENDIENTE', 'PAGADA', 'CANCELADA'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $notes = Note::withTrashed()
            ->with(['customer', 'details.catalogItem'])
            ->where('tenant_id', $tenantId)
            ->when(isset($data['since']), function (Builder $query) use ($data) {
                $query->where(function (Builder $query) use ($data) {
                    $query->where('updated_at', '>=', $data['since'])
                        ->orWhere('deleted_at', '>=', $data['since']);
                });
            })
            ->when(isset($data['customer_id']), fn (Builder $query) => $query->where('customer_id', $data['customer_id']))
            ->when(isset($data['status']), fn (Builder $query) => $query->where('status', $data['status']))
            ->when(isset($data['q']), function (Builder $query) use ($data) {
                $search = $data['q'];

                $query->where(function (Builder $query) use ($search) {
                    $query->where('folio', 'like', "%{$search}%")
                        ->orWhereHas('details.catalogItem', function (Builder $query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('id')
            ->paginate($data['per_page'] ?? 50);

        return response()->json([
            'data' => $notes->getCollection()->map(fn (Note $note) => $this->serializeNote($note)),
            'meta' => [
                'current_page' => $notes->currentPage(),
                'last_page' => $notes->lastPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        $data = $this->validatedData($request, $tenant->id);

        if (!empty($data['client_uuid'])) {
            $existing = Note::withTrashed()
                ->where('tenant_id', $tenant->id)
                ->where('client_uuid', $data['client_uuid'])
                ->with(['customer', 'details.catalogItem', 'details.animal', 'payments.paymentMethod'])
                ->first();

            if ($existing) {
                return response()->json([
                    'data' => $this->serializeNote($existing),
                    'idempotent' => true,
                ]);
            }
        }

        $note = DB::transaction(function () use ($tenant, $data) {
            $animalIds = collect($data['animal_ids'])->map(fn ($id) => (int) $id)->unique()->values();
            $subtotalPerAnimal = collect($data['items'])->sum(fn ($item) => (float) $item['quantity'] * (float) $item['price']);
            $total = $subtotalPerAnimal * $animalIds->count();

            $lastNoteId = $tenant->notes()->lockForUpdate()->max('id') ?? 0;
            $folio = 'VT-' . str_pad($lastNoteId + 1, 5, '0', STR_PAD_LEFT);
            $amountReceived = (float) ($data['amount_received'] ?? 0);
            $status = $amountReceived >= $total ? 'PAGADA' : 'PENDIENTE';

            $note = $tenant->notes()->create([
                'client_uuid' => $data['client_uuid'] ?? null,
                'synced_from_mobile' => true,
                'customer_id' => $data['customer_id'],
                'folio' => $folio,
                'total' => $total,
                'status' => $status,
                'date_at' => $data['date_at'],
            ]);

            app(InventoryService::class)->consumeForSale(
                $tenant,
                $data['items'],
                $animalIds->count(),
                $note
            );

            foreach ($animalIds as $animalId) {
                foreach ($data['items'] as $itemData) {
                    $quantity = (float) $itemData['quantity'];
                    $price = (float) $itemData['price'];
                    $subtotal = $quantity * $price;

                    $note->details()->create([
                        'tenant_id' => $tenant->id,
                        'catalog_item_id' => $itemData['id'],
                        'animal_id' => $animalId,
                        'quantity' => $quantity,
                        'price_at_sale' => $price,
                        'tax_at_sale' => $itemData['tax_percentage'] ?? 0,
                        'subtotal' => $subtotal,
                    ]);

                }
            }

            if ($amountReceived > 0) {
                $payment = $tenant->clientPayments()->create([
                    'client_uuid' => $data['payment_client_uuid'] ?? null,
                    'synced_from_mobile' => true,
                    'customer_id' => $data['customer_id'],
                    'payment_method_id' => $data['payment_method_id'],
                    'amount' => min($amountReceived, $total),
                    'reference' => $data['payment_reference'] ?? 'Pago inicial en venta ' . $folio,
                ]);

                $note->payments()->attach($payment->id, [
                    'amount_applied' => min($amountReceived, $total),
                ]);
            }

            return $note;
        });

        return response()->json([
            'data' => $this->serializeNote($note->load(['customer', 'details.catalogItem', 'details.animal', 'payments.paymentMethod'])),
            'idempotent' => false,
        ], 201);
    }

    public function show(Request $request, Note $note)
    {
        abort_if($note->tenant_id !== $request->user()->tenant_id, 404);

        return response()->json([
            'data' => $this->serializeNote($note->load(['customer', 'details.catalogItem', 'details.animal', 'payments.paymentMethod'])),
        ]);
    }

    public function createPaymentLink(Request $request, Note $note)
    {
        abort_if($note->tenant_id !== $request->user()->tenant_id, 404);

        $data = $request->validate([
            'payment_method_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('is_active', true)),
            ],
            'expires_in_hours' => ['nullable', 'integer', 'between:1,168'],
        ]);

        $paymentMethodId = $data['payment_method_id'] ?? $this->cardPaymentMethodForTenant($request->user()->tenant_id)?->id;

        try {
            $paymentLink = app(StripeNotePaymentService::class)
                ->createLink($note, $paymentMethodId, $data['expires_in_hours'] ?? 24);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $this->serializePaymentLink($paymentLink),
        ], 201);
    }

    public function storeManualPayment(Request $request, Note $note)
    {
        abort_if($note->tenant_id !== $request->user()->tenant_id, 404);

        if ($note->balance <= 0) {
            return response()->json([
                'message' => 'Esta nota ya no tiene saldo pendiente.',
            ], 422);
        }

        $data = $request->validate([
            'client_uuid' => ['nullable', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('is_active', true)),
            ],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $note, $data) {
            $note->refresh();
            $amountToApply = min((float) $data['amount'], max((float) $note->balance, 0));

            if ($amountToApply <= 0) {
                return;
            }

            $payment = $request->user()->tenant->clientPayments()->create([
                'client_uuid' => $data['client_uuid'] ?? null,
                'synced_from_mobile' => true,
                'customer_id' => $note->customer_id,
                'payment_method_id' => $data['payment_method_id'],
                'amount' => $amountToApply,
                'reference' => $data['reference'] ?? 'Pago manual aplicado a nota ' . $note->folio,
                'provider' => 'manual',
                'status' => 'paid',
            ]);

            $note->payments()->attach($payment->id, [
                'amount_applied' => $amountToApply,
            ]);

            $note->refresh();

            if ($note->balance <= 0) {
                $note->update(['status' => 'PAGADA']);
            }
        });

        return response()->json([
            'data' => $this->serializeNote($note->fresh()->load(['customer', 'details.catalogItem', 'details.animal', 'payments.paymentMethod'])),
        ]);
    }

    private function validatedData(Request $request, int $tenantId): array
    {
        return $request->validate([
            'client_uuid' => ['nullable', 'uuid'],
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'date_at' => ['required', 'date'],
            'animal_ids' => ['required', 'array', 'min:1'],
            'animal_ids.*' => [
                'required',
                'integer',
                Rule::exists('animals', 'id')->where(function ($query) use ($tenantId, $request) {
                    return $query
                        ->where('tenant_id', $tenantId)
                        ->where('customer_id', $request->input('customer_id'))
                        ->where('status', 'active');
                }),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'required',
                'integer',
                Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'operation_type' => ['required', Rule::in(['credito', 'contado'])],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'payment_client_uuid' => ['nullable', 'uuid'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_method_id' => [
                Rule::requiredIf(fn () => $request->input('operation_type') === 'contado' || (float) $request->input('amount_received', 0) > 0),
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)),
            ],
        ]);
    }

    private function serializeNote(Note $note): array
    {
        $note->loadMissing(['customer', 'details.catalogItem', 'details.animal', 'payments.paymentMethod']);

        return [
            'id' => $note->id,
            'client_uuid' => $note->client_uuid,
            'customer_id' => $note->customer_id,
            'customer_name' => $note->customer?->full_name,
            'folio' => $note->folio,
            'total' => $note->total,
            'amount_paid' => $note->amount_paid,
            'balance' => $note->balance,
            'status' => $note->status,
            'date_at' => $note->date_at?->toDateString(),
            'synced_from_mobile' => $note->synced_from_mobile,
            'details' => $note->details->map(fn ($detail) => [
                'id' => $detail->id,
                'catalog_item_id' => $detail->catalog_item_id,
                'catalog_item_name' => $detail->catalogItem?->name,
                'animal_id' => $detail->animal_id,
                'animal_name' => $detail->animal?->name,
                'quantity' => $detail->quantity,
                'price_at_sale' => $detail->price_at_sale,
                'tax_at_sale' => $detail->tax_at_sale,
                'subtotal' => $detail->subtotal,
            ])->values(),
            'payments' => $note->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'client_uuid' => $payment->client_uuid,
                'payment_method_id' => $payment->payment_method_id,
                'payment_method_name' => $payment->paymentMethod?->name,
                'amount' => $payment->amount,
                'amount_applied' => $payment->pivot?->amount_applied,
                'reference' => $payment->reference,
                'status' => $payment->status,
                'created_at' => $payment->created_at?->toISOString(),
            ])->values(),
            'created_at' => $note->created_at?->toISOString(),
            'updated_at' => $note->updated_at?->toISOString(),
            'deleted_at' => $note->deleted_at?->toISOString(),
        ];
    }

    private function serializePaymentLink(NotePaymentLink $paymentLink): array
    {
        return [
            'id' => $paymentLink->id,
            'note_id' => $paymentLink->note_id,
            'customer_id' => $paymentLink->customer_id,
            'payment_method_id' => $paymentLink->payment_method_id,
            'token' => $paymentLink->token,
            'amount' => $paymentLink->amount,
            'currency' => $paymentLink->currency,
            'status' => $paymentLink->status,
            'is_payable' => $paymentLink->is_payable,
            'public_url' => route('public.payments.show', $paymentLink->token),
            'expires_at' => $paymentLink->expires_at?->toISOString(),
            'paid_at' => $paymentLink->paid_at?->toISOString(),
            'created_at' => $paymentLink->created_at?->toISOString(),
            'updated_at' => $paymentLink->updated_at?->toISOString(),
        ];
    }

    private function cardPaymentMethodForTenant(int $tenantId): ?PaymentMethod
    {
        return PaymentMethod::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->first(function (PaymentMethod $method) {
                $value = str($method->slug . ' ' . $method->name)->lower()->ascii()->toString();

                return str_contains($value, 'tarjeta')
                    || str_contains($value, 'card')
                    || str_contains($value, 'stripe');
            });
    }
}
