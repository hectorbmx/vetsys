<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Note;
use App\Models\PaymentMethod;
use App\Services\StripeNotePaymentService;
use App\Services\CustomerPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    /**
     * Listado historico de notas de venta de la clinica.
     */
    public function index()
    {
        $notes = auth()->user()->tenant->notes()
            ->with('customer')
            ->latest()
            ->get();

        return view('client.ventas.index', compact('notes'));
    }

    /**
     * Muestra el formulario reactivo del punto de venta.
     */
    public function create(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $paymentMethods = $tenant->paymentMethods()->where('is_active', true)->get();
        $prefilledCustomer = null;

        if ($request->filled('customer_id')) {
            $customer = $tenant->customers()
                ->where('status', 'active')
                ->whereKey($request->integer('customer_id'))
                ->with(['animals' => function ($q) {
                    $q->select('id', 'customer_id', 'name')
                        ->where('status', 'active');
                }])
                ->first();

            if ($customer) {
                $prefilledCustomer = [
                    'id' => $customer->id,
                    'full_name' => $customer->full_name,
                    'phone' => $customer->phone,
                    'animals' => $customer->animals,
                ];
            }
        }

        return view('client.ventas.create', compact('paymentMethods', 'prefilledCustomer'));
    }

    /**
     * Endpoint API para buscar clientes y retornar sus datos junto con sus mascotas.
     */
    public function searchCustomers(Request $request)
    {
        $search = $request->get('q');
        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $customers = auth()->user()->tenant->customers()
            ->where('status', 'active')
            ->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            })
            ->with(['animals' => function ($q) {
                $q->select('id', 'customer_id', 'name')
                    ->where('status', 'active');
            }])
            ->take(10)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'full_name' => $customer->full_name,
                    'phone' => $customer->phone,
                    'animals' => $customer->animals,
                ];
            });

        return response()->json($customers);
    }

    /**
     * Endpoint API para buscar articulos (Productos/Servicios) listos para la venta.
     */
    public function searchItems(Request $request)
    {
        $search = $request->get('q');
        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $items = auth()->user()->tenant->catalogItems()
            ->where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%");
            })
            ->take(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'price' => $item->current_price,
                ];
            });

        return response()->json($items);
    }

    /**
     * Guarda la venta, descuenta inventarios y procesa el cobro si aplica.
     */
    public function store(Request $request)
    {
        $tenant = auth()->user()->tenant;

        $request->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenant->id)),
            ],
            'date_at' => 'required|date',
            'animal_ids' => 'required|array|min:1',
            'animal_ids.*' => [
                'required',
                'integer',
                Rule::exists('animals', 'id')->where(function ($query) use ($tenant, $request) {
                    return $query
                        ->where('tenant_id', $tenant->id)
                        ->where('customer_id', $request->customer_id)
                        ->where('status', 'active');
                }),
            ],
            'items' => 'required|array|min:1',
            'items.*.id' => [
                'required',
                'integer',
                Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenant->id)
                    ->where('is_active', true)),
            ],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'amount_received' => 'nullable|numeric|min:0',
            'operation_type' => ['required', Rule::in(['credito', 'contado'])],
            'payment_method_id' => [
                'required_if:operation_type,contado',
                'nullable',
                Rule::exists('payment_methods', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenant->id)
                    ->where('is_active', true)),
            ],
        ]);

        $selectedPaymentMethod = $request->payment_method_id
            ? PaymentMethod::where('tenant_id', $tenant->id)->find($request->payment_method_id)
            : null;
        $generateStripeLink = $request->operation_type === 'contado'
            && $selectedPaymentMethod
            && $this->isCardPaymentMethod($selectedPaymentMethod);

        $note = DB::transaction(function () use ($request, $tenant, $generateStripeLink) {
            $animalIds = collect($request->animal_ids)->map(fn ($id) => (int) $id)->unique()->values();

            $subtotalPorMascota = 0;
            foreach ($request->items as $itemData) {
                $subtotalPorMascota += ($itemData['quantity'] * $itemData['price']);
            }

            $totalNota = $subtotalPorMascota * $animalIds->count();

            $ultimoFolio = $tenant->notes()->lockForUpdate()->max('id') ?? 0;
            $nuevoFolio = 'VT-' . str_pad($ultimoFolio + 1, 5, '0', STR_PAD_LEFT);

            $montoRecibido = $generateStripeLink ? 0 : (float) ($request->amount_received ?? 0);
            $status = $montoRecibido >= $totalNota ? 'PAGADA' : 'PENDIENTE';

            $note = $tenant->notes()->create([
                'customer_id' => $request->customer_id,
                'folio' => $nuevoFolio,
                'total' => $totalNota,
                'status' => $status,
                'date_at' => $request->date_at,
            ]);

            foreach ($animalIds as $animalId) {
                foreach ($request->items as $itemData) {
                    $subtotal = $itemData['quantity'] * $itemData['price'];

                    $note->details()->create([
                        'tenant_id' => $tenant->id,
                        'catalog_item_id' => $itemData['id'],
                        'animal_id' => $animalId,
                        'quantity' => $itemData['quantity'],
                        'price_at_sale' => $itemData['price'],
                        'subtotal' => $subtotal,
                    ]);

                    $catalogItem = $tenant->catalogItems()->find($itemData['id']);
                    if ($catalogItem->has_inventory && $catalogItem->inventory) {
                        $catalogItem->inventory->decrement('stock_actual', $itemData['quantity']);
                    }
                }
            }

            if ($montoRecibido > 0) {
                $montoAAplicar = min($montoRecibido, $totalNota);

                $payment = $tenant->clientPayments()->create([
                    'customer_id' => $request->customer_id,
                    'payment_method_id' => $request->payment_method_id,
                    'amount' => $montoAAplicar,
                    'reference' => 'Pago inicial en venta ' . $nuevoFolio,
                ]);

                $note->payments()->attach($payment->id, [
                    'amount_applied' => $montoAAplicar,
                ]);
            }

            app(CustomerPaymentService::class)->applyAvailableCredit(
                Customer::findOrFail($request->customer_id),
                $note
            );

            return $note;
        });

        $redirect = redirect()->route('client.ventas.show', $note);

        if ($generateStripeLink) {
            try {
                $paymentLink = app(StripeNotePaymentService::class)->createLink($note, $selectedPaymentMethod->id);
                $redirect->with('payment_link_url', route('public.payments.show', $paymentLink->token));
            } catch (\Throwable $exception) {
                report($exception);

                return $redirect->with('error', 'La nota se guardo, pero no se pudo generar el link Stripe: ' . $exception->getMessage());
            }
        }

        return $redirect->with('success', "Nota {$note->folio} generada correctamente.");
    }
public function show(Note $note)
{
    $tenant = auth()->user()->tenant;

    abort_if($note->tenant_id !== $tenant->id, 403);

    $note->load([
        'customer',
        'details.catalogItem',
        'details.animal',
        'payments.paymentMethod',
        'paymentLinks' => fn ($query) => $query->latest(),
    ]);

    $paymentMethods = $tenant->paymentMethods()
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

    // Agrupar detalles por animal para mostrarlos ordenados
    $detailsByAnimal = $note->details->groupBy('animal_id');

    return view('client.ventas.show', compact('note', 'detailsByAnimal', 'paymentMethods'));
}

public function createStripePaymentLink(Note $note)
{
    abort_if($note->tenant_id !== auth()->user()->tenant->id, 403);

    try {
        $paymentMethod = $this->cardPaymentMethodForTenant(auth()->user()->tenant->id);
        $paymentLink = app(StripeNotePaymentService::class)->createLink($note, $paymentMethod?->id);
    } catch (\Throwable $exception) {
        report($exception);

        return back()->with('error', 'No se pudo generar el link Stripe: ' . $exception->getMessage());
    }

    return back()
        ->with('success', 'Link Stripe generado correctamente.')
        ->with('payment_link_url', route('public.payments.show', $paymentLink->token));
}

public function storeManualPayment(Request $request, Note $note)
{
    $tenant = auth()->user()->tenant;

    abort_if($note->tenant_id !== $tenant->id, 403);

    $data = $request->validate([
        'amount' => ['required', 'numeric', 'min:0.01'],
        'payment_method_id' => [
            'required',
            Rule::exists('payment_methods', 'id')->where(fn ($query) => $query
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)),
        ],
        'reference' => ['nullable', 'string', 'max:255'],
    ]);

    if ($note->balance <= 0) {
        return back()->with('error', 'Esta nota ya no tiene saldo pendiente.');
    }

    $paymentMethod = PaymentMethod::findOrFail($data['payment_method_id']);

    if ($this->isCardPaymentMethod($paymentMethod)) {
        try {
            $paymentLink = app(StripeNotePaymentService::class)->createLink($note, $paymentMethod->id);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'No se pudo generar el link Stripe: ' . $exception->getMessage());
        }

        return back()
            ->with('success', 'Link Stripe generado correctamente.')
            ->with('payment_link_url', route('public.payments.show', $paymentLink->token));
    }

    DB::transaction(function () use ($request, $tenant, $note) {
        $note->refresh();
        $amountToApply = min((float) $request->amount, max((float) $note->balance, 0));

        if ($amountToApply <= 0) {
            return;
        }

        $payment = $tenant->clientPayments()->create([
            'customer_id' => $note->customer_id,
            'payment_method_id' => $request->payment_method_id,
            'amount' => $amountToApply,
            'reference' => $request->reference ?: 'Pago manual aplicado a nota ' . $note->folio,
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

    return back()->with('success', 'Pago manual aplicado correctamente.');
}

private function isCardPaymentMethod(PaymentMethod $paymentMethod): bool
{
    $value = str($paymentMethod->slug . ' ' . $paymentMethod->name)->lower()->ascii()->toString();

    return str_contains($value, 'tarjeta')
        || str_contains($value, 'tarteja')
        || str_contains($value, 'card')
        || str_contains($value, 'stripe');
}

private function cardPaymentMethodForTenant(int $tenantId): ?PaymentMethod
{
    return PaymentMethod::where('tenant_id', $tenantId)
        ->where('is_active', true)
        ->get()
        ->first(fn (PaymentMethod $method) => $this->isCardPaymentMethod($method));
}
}
PaymentMethod($method));
}
}
