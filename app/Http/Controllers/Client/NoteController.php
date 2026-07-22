<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\Note;
use App\Models\NoteDetail;
use App\Models\PaymentMethod;
use App\Services\StripeNotePaymentService;
use App\Services\CustomerPaymentService;
use App\Services\InventoryService;
use App\Services\PortalNotificationService;
use App\Services\TenantOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    /**
     * Listado historico de notas de venta de la clinica.
     */
    public function index(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $search = $request->get('q');
        $allowedPerPage = [15, 30, 50, 100];
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, $allowedPerPage, true)
            ? $requestedPerPage
            : 15;
        $usesMonthlyCutoffBilling = $tenant->usesMonthlyCutoffBilling();

        $notes = $tenant->notes()
            ->with('customer')
            ->withCount('payments')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('folio', 'LIKE', "%{$search}%")
                      ->orWhereHas('customer', function ($subQuery) use ($search) {
                          $subQuery->where('name', 'LIKE', "%{$search}%")
                                   ->orWhere('last_name', 'LIKE', "%{$search}%")
                                   ->orWhere('phone', 'LIKE', "%{$search}%");
                      });
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $serviceDetails = null;

        if ($usesMonthlyCutoffBilling) {
            $serviceDetails = NoteDetail::query()
                ->where('note_details.tenant_id', $tenant->id)
                ->join('notes', 'notes.id', '=', 'note_details.note_id')
                ->where('notes.status', '!=', 'CANCELADA')
                ->with(['note.customer', 'animal', 'catalogItem'])
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('notes.folio', 'LIKE', "%{$search}%")
                            ->orWhereHas('note.customer', function ($subQuery) use ($search) {
                                $subQuery->where('name', 'LIKE', "%{$search}%")
                                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                                    ->orWhere('phone', 'LIKE', "%{$search}%");
                            })
                            ->orWhereHas('animal', function ($subQuery) use ($search) {
                                $subQuery->where('name', 'LIKE', "%{$search}%");
                            })
                            ->orWhereHas('catalogItem', function ($subQuery) use ($search) {
                                $subQuery->where('name', 'LIKE', "%{$search}%")
                                    ->orWhere('sku', 'LIKE', "%{$search}%");
                            });
                    });
                })
                ->select('note_details.*')
                ->orderByDesc('notes.date_at')
                ->orderByDesc('note_details.id')
                ->paginate($perPage)
                ->withQueryString();
        }

        // KPIs del mes actual
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $monthNotes = $tenant->notes()
            ->whereBetween('date_at', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', 'CANCELADA')
            ->get();

        $totalSalesMonth = $monthNotes->sum('total');
        $totalNotesMonth = $monthNotes->count();
        $paidNotesMonth = $monthNotes->where('status', 'PAGADA')->count();
        $pendingNotesMonth = $monthNotes->where('status', 'PENDIENTE')->count();

        $monthNoteIds = $monthNotes->pluck('id');

        if ($usesMonthlyCutoffBilling) {
            $monthDetails = NoteDetail::query()
                ->where('note_details.tenant_id', $tenant->id)
                ->whereIn('note_details.note_id', $monthNoteIds)
                ->get();

            $totalSalesMonth = (float) $monthDetails->sum('subtotal');
            $totalNotesMonth = $monthDetails->count();
            $paidNotesMonth = 0;
            $pendingNotesMonth = $totalNotesMonth;
        }

        // Adeudo general: saldo real de todas las notas que siguen pendientes.
        $totalPending = $tenant->notes()
            ->where('status', 'PENDIENTE')
            ->get()
            ->sum(fn (Note $note) => max(0, $note->balance));

        // Pacientes atendidos (animales únicos en las notas del mes)
        $animalsAttendedMonth = \App\Models\NoteDetail::whereIn('note_id', $monthNoteIds)
            ->distinct('animal_id')
            ->count('animal_id');

        return view('client.ventas.index', compact(
            'notes',
            'serviceDetails',
            'usesMonthlyCutoffBilling',
            'totalSalesMonth',
            'totalPending',
            'totalNotesMonth',
            'paidNotesMonth',
            'pendingNotesMonth',
            'animalsAttendedMonth',
            'perPage'
        ));
    }

    /**
     * Muestra el formulario reactivo del punto de venta.
     */
    public function create(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $paymentMethods = $tenant->paymentMethods()->where('is_active', true)->get();
        $prefilledCustomer = null;
        $editingNote = null;
        $initialSaleState = $tenant->usesMonthlyCutoffBilling()
            ? session('monthly_sale_state')
            : null;
        $usesMonthlyCutoffBilling = $tenant->usesMonthlyCutoffBilling();

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

        return view('client.ventas.create', compact('paymentMethods', 'prefilledCustomer', 'editingNote', 'initialSaleState', 'usesMonthlyCutoffBilling'));
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
            ->with('inventory')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'price' => $item->current_price,
                    'has_inventory' => $item->has_inventory,
                    'stock_actual' => $item->inventory?->stock_actual,
                    'stock_minimo' => $item->inventory?->stock_minimo,
                    'allow_negative_stock' => $item->inventory?->allow_negative_stock ?? false,
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

            $ultimoFolio = Note::withTrashed()
                ->where('tenant_id', $tenant->id)
                ->lockForUpdate()
                ->max('id') ?? 0;
            $folioSequence = $ultimoFolio + 1;
            do {
                $nuevoFolio = 'VT-' . str_pad($folioSequence, 5, '0', STR_PAD_LEFT);
                $folioSequence++;
            } while (Note::withTrashed()
                ->where('tenant_id', $tenant->id)
                ->where('folio', $nuevoFolio)
                ->exists());

            $montoRecibido = $generateStripeLink ? 0 : (float) ($request->amount_received ?? 0);
            $status = $montoRecibido >= $totalNota ? 'PAGADA' : 'PENDIENTE';

            $note = $tenant->notes()->create([
                'customer_id' => $request->customer_id,
                'folio' => $nuevoFolio,
                'total' => $totalNota,
                'status' => $status,
                'date_at' => $request->date_at,
            ]);

            app(InventoryService::class)->consumeForSale(
                $tenant,
                $request->items,
                $animalIds->count(),
                $note
            );

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

        app(TenantOnboardingService::class)->reconcileSafely($tenant);
        app(PortalNotificationService::class)->notePublished($note->fresh(['customer', 'details']));

        if ($tenant->usesMonthlyCutoffBilling()) {
            return redirect()
                ->route('client.ventas.create', ['customer_id' => $note->customer_id])
                ->with('monthly_sale_state', $this->initialSaleStateFromNote($note->fresh(['details.catalogItem.inventory'])))
                ->with('monthly_capture_prompt', [
                    'return_url' => route('client.customers.show', ['customer' => $note->customer_id, 'tab' => 'notas']),
                ])
                ->with('success', "Nota {$note->folio} generada correctamente.");
        }

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

public function edit(Note $note)
{
    $tenant = auth()->user()->tenant;

    abort_if($note->tenant_id !== $tenant->id, 403);

    $note->load(['customer.animals', 'details.catalogItem.inventory', 'details.animal', 'payments']);
    $this->abortIfNoteHasPayments($note);

    $paymentMethods = $tenant->paymentMethods()->where('is_active', true)->get();
    $usesMonthlyCutoffBilling = $tenant->usesMonthlyCutoffBilling();
    $animalIds = $note->details->pluck('animal_id')->filter()->unique()->values();
    $customerAnimals = $note->customer->animals()
        ->where(function ($query) use ($animalIds) {
            $query->where('status', 'active')
                ->orWhereIn('id', $animalIds);
        })
        ->select('id', 'customer_id', 'name')
        ->get();

    $prefilledCustomer = [
        'id' => $note->customer->id,
        'full_name' => $note->customer->full_name,
        'phone' => $note->customer->phone,
        'animals' => $customerAnimals,
    ];

    $initialSaleState = [
        'selectedAnimalIds' => $animalIds->map(fn ($id) => (string) $id)->values(),
        'noteDate' => optional($note->date_at)->format('Y-m-d'),
        'basket' => $note->details
            ->groupBy('catalog_item_id')
            ->map(function ($details) {
                $first = $details->first();
                $item = $first->catalogItem;

                return [
                    'id' => $first->catalog_item_id,
                    'name' => $item?->name ?? 'Concepto eliminado',
                    'quantity' => (float) $first->quantity,
                    'price' => (float) $first->price_at_sale,
                    'has_inventory' => (bool) ($item?->has_inventory ?? false),
                    'stock_actual' => (float) ($item?->inventory?->stock_actual ?? 0),
                    'stock_minimo' => (float) ($item?->inventory?->stock_minimo ?? 0),
                    'allow_negative_stock' => (bool) ($item?->inventory?->allow_negative_stock ?? false),
                ];
            })
            ->values(),
    ];

    $editingNote = $note;

    return view('client.ventas.create', compact('paymentMethods', 'prefilledCustomer', 'editingNote', 'initialSaleState', 'usesMonthlyCutoffBilling'));
}

public function update(Request $request, Note $note)
{
    $tenant = auth()->user()->tenant;

    abort_if($note->tenant_id !== $tenant->id, 403);
    $note->load(['payments', 'details']);
    $this->abortIfNoteHasPayments($note);

    $this->validateSaleRequest($request, $tenant, false);

    DB::transaction(function () use ($request, $tenant, $note) {
        $note->refresh();
        $this->abortIfNoteHasPayments($note);

        $inventoryService = app(InventoryService::class);
        $inventorySuffix = ':update:'.now()->timestamp;
        $inventoryService->reverseSaleConsumption($tenant, $note, $inventorySuffix);

        $animalIds = collect($request->animal_ids)->map(fn ($id) => (int) $id)->unique()->values();
        $subtotalPorMascota = collect($request->items)->sum(fn ($itemData) => $itemData['quantity'] * $itemData['price']);
        $totalNota = $subtotalPorMascota * $animalIds->count();

        $note->details()->delete();
        $note->update([
            'customer_id' => $request->customer_id,
            'total' => $totalNota,
            'status' => 'PENDIENTE',
            'date_at' => $request->date_at,
        ]);

        $inventoryService->consumeForSale($tenant, $request->items, $animalIds->count(), $note, $inventorySuffix);

        foreach ($animalIds as $animalId) {
            foreach ($request->items as $itemData) {
                $note->details()->create([
                    'tenant_id' => $tenant->id,
                    'catalog_item_id' => $itemData['id'],
                    'animal_id' => $animalId,
                    'quantity' => $itemData['quantity'],
                    'price_at_sale' => $itemData['price'],
                    'subtotal' => $itemData['quantity'] * $itemData['price'],
                ]);
            }
        }
    });

    return redirect()
        ->route('client.ventas.show', $note)
        ->with('success', "Nota {$note->folio} actualizada correctamente.");
}

// public function destroy(Note $note)
// {
//     $tenant = auth()->user()->tenant;

//     abort_if($note->tenant_id !== $tenant->id, 403);
//     $note->load(['payments', 'details']);
//     $this->abortIfNoteHasPayments($note);

//     DB::transaction(function () use ($tenant, $note) {
//         app(InventoryService::class)->reverseSaleConsumption($tenant, $note, ':delete');
//         $note->details()->delete();
//         $note->delete();
//     });

//     return redirect()
//         ->route('client.ventas.index')
//         ->with('success', "Nota {$note->folio} eliminada correctamente.");
// }
public function destroy(Note $note)
{
    $tenant = auth()->user()->tenant;

    abort_if($note->tenant_id !== $tenant->id, 403);
    $note->load(['payments', 'details']);
    $this->abortIfNoteHasPayments($note);

    DB::transaction(function () use ($tenant, $note) {
        app(InventoryService::class)->reverseSaleConsumption($tenant, $note, ':delete');
        $note->details()->delete();
        $note->delete();
    });

    // Capturamos si viene el cliente en los parámetros de la URL
    $customerId = request('redirect_to_customer');

    if ($customerId) {
        return redirect()
            ->route('client.customers.show', $customerId)
            ->with('success', "Nota {$note->folio} eliminada correctamente.")
            // Opcional: Mandar al usuario directo a la pestaña de notas
            ->with('activeCustomerTab', 'notas'); 
    }

    // Comportamiento por defecto en caso de que se elimine desde otra sección
    return redirect()
        ->route('client.ventas.index')
        ->with('success', "Nota {$note->folio} eliminada correctamente.");
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

    $usesMonthlyCutoffBilling = $tenant->usesMonthlyCutoffBilling();
    $customerAccountBalance = 0.0;

    if ($usesMonthlyCutoffBilling) {
        $totalCharges = (float) \App\Models\NoteDetail::query()
            ->where('note_details.tenant_id', $tenant->id)
            ->whereHas('note', fn ($query) => $query
                ->where('tenant_id', $tenant->id)
                ->where('customer_id', $note->customer_id)
                ->where('status', '!=', 'CANCELADA'))
            ->sum('subtotal');

        $totalPayments = (float) $note->customer->payments()
            ->where('tenant_id', $tenant->id)
            ->sum('amount');

        $customerAccountBalance = max($totalCharges - $totalPayments, 0);
    }

    // Agrupar detalles por animal para mostrarlos ordenados
    $detailsByAnimal = $note->details->groupBy('animal_id');

    return view('client.ventas.show', compact(
        'note',
        'detailsByAnimal',
        'paymentMethods',
        'usesMonthlyCutoffBilling',
        'customerAccountBalance'
    ));
}

public function ticket(Note $note)
{
    $tenant = auth()->user()->tenant;

    abort_if($note->tenant_id !== $tenant->id, 403);

    $note->load([
        'customer',
        'details.catalogItem',
        'details.animal',
        'payments.paymentMethod',
    ]);

    // Agrupar detalles por animal
    $detailsByAnimal = $note->details->groupBy('animal_id');

    return view('client.ventas.ticket', compact('note', 'detailsByAnimal', 'tenant'));
}

public function publicTicket(string $token)
{
    $note = Note::where('public_token', $token)
        ->with([
            'tenant',
            'customer',
            'details.catalogItem',
            'details.animal',
            'payments.paymentMethod',
        ])
        ->firstOrFail();

    $tenant = $note->tenant;
    $detailsByAnimal = $note->details->groupBy('animal_id');

    return view('client.ventas.ticket', compact('note', 'detailsByAnimal', 'tenant'))->with('isPublic', true);
}

public function createStripePaymentLink(Note $note)
{
    $tenant = auth()->user()->tenant;

    abort_if($note->tenant_id !== $tenant->id, 403);

    if ($tenant->usesMonthlyCutoffBilling()) {
        return redirect()
            ->route('client.customers.show', ['customer' => $note->customer_id, 'tab' => 'notas'])
            ->with('activeCustomerTab', 'notas')
            ->with('error', 'En modo cuentas mensuales, registra abonos desde la cuenta del cliente.');
    }

    try {
        $paymentMethod = $this->cardPaymentMethodForTenant($tenant->id);
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

    if ($tenant->usesMonthlyCutoffBilling()) {
        return redirect()
            ->route('client.customers.show', ['customer' => $note->customer_id, 'tab' => 'notas'])
            ->with('activeCustomerTab', 'notas')
            ->with('error', 'En modo cuentas mensuales, registra abonos desde la cuenta del cliente.');
    }

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

        app(TenantOnboardingService::class)->reconcileSafely($tenant);

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

private function initialSaleStateFromNote(Note $note): array
{
    $animalIds = $note->details->pluck('animal_id')->filter()->unique()->values();

    return [
        'selectedAnimalIds' => $animalIds->map(fn ($id) => (string) $id)->values(),
        'noteDate' => '',
        'resetDate' => true,
        'basket' => $note->details
            ->groupBy('catalog_item_id')
            ->map(function ($details) {
                $first = $details->first();
                $item = $first->catalogItem;

                return [
                    'id' => $first->catalog_item_id,
                    'name' => $item?->name ?? 'Concepto eliminado',
                    'quantity' => (float) $first->quantity,
                    'price' => (float) $first->price_at_sale,
                    'has_inventory' => (bool) ($item?->has_inventory ?? false),
                    'stock_actual' => (float) ($item?->inventory?->stock_actual ?? 0),
                    'stock_minimo' => (float) ($item?->inventory?->stock_minimo ?? 0),
                    'allow_negative_stock' => (bool) ($item?->inventory?->allow_negative_stock ?? false),
                ];
            })
            ->values(),
    ];
}

private function validateSaleRequest(Request $request, $tenant, bool $requiresPaymentFields = true): void
{
    $rules = [
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
                    ->where('customer_id', $request->customer_id);
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
    ];

    if ($requiresPaymentFields) {
        $rules['amount_received'] = 'nullable|numeric|min:0';
        $rules['operation_type'] = ['required', Rule::in(['credito', 'contado'])];
        $rules['payment_method_id'] = [
            'required_if:operation_type,contado',
            'nullable',
            Rule::exists('payment_methods', 'id')->where(fn ($query) => $query
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)),
        ];
    }

    $request->validate($rules);
}

private function abortIfNoteHasPayments(Note $note): void
{
    if ($note->payments()->exists()) {
        abort(403, 'No se puede editar o eliminar una nota con pagos registrados.');
    }
}
}
