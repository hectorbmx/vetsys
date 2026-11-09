<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\NoteDetail;
use App\Services\InventoryService;
use App\Services\TenantOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogItemController extends Controller
{
    //
    public function index(Request $request)
    {
        $search = trim((string) $request->get('q', ''));
        $allowedPerPage = [15, 30, 50, 100];
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, $allowedPerPage, true)
            ? $requestedPerPage
            : 15;

        $items = auth()->user()->tenant->catalogItems()
            ->with(['inventory'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        // KPIs
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // 1. Producto Estrella (más vendido del mes)
        $starProduct = \App\Models\NoteDetail::query()
            ->whereHas('note', fn ($q) => $q
                ->where('tenant_id', auth()->user()->tenant_id)
                ->whereBetween('date_at', [$startOfMonth, $endOfMonth])
                ->where('status', '!=', 'CANCELADA')
            )
            ->select('catalog_item_id', DB::raw('SUM(quantity) as total_quantity_sold'))
            ->groupBy('catalog_item_id')
            ->orderByDesc('total_quantity_sold')
            ->with('catalogItem')
            ->first();

        // 2. Productos que manejan inventario
        $inventoryProductsCount = auth()->user()->tenant->catalogItems()
            ->where('has_inventory', true)
            ->where('type', 'product')
            ->count();

        // 3. Último producto/servicio con movimiento o venta
        $lastCatalogItemMovement = null;

        $latestSaleDetail = \App\Models\NoteDetail::query()
            ->whereHas('note', fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id))
            ->orderByDesc('created_at')
            ->with('catalogItem')
            ->first();

        $latestInventoryMovement = \App\Models\InventoryMovement::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id') // Fallback for same occurred_at
            ->with('catalogItem')
            ->first();
        
        if ($latestSaleDetail && $latestInventoryMovement) {
            $lastCatalogItemMovement = ($latestSaleDetail->created_at->greaterThanOrEqualTo($latestInventoryMovement->occurred_at))
                ? $latestSaleDetail->catalogItem
                : $latestInventoryMovement->catalogItem;
        } elseif ($latestSaleDetail) {
            $lastCatalogItemMovement = $latestSaleDetail->catalogItem;
        } elseif ($latestInventoryMovement) {
            $lastCatalogItemMovement = $latestInventoryMovement->catalogItem;
        }


        return view('client.servicios.index', compact(
            'items',
            'search',
            'perPage',
            'starProduct',
            'inventoryProductsCount',
            'lastCatalogItemMovement'
        ));
    }

    public function inventoryIndex()
    {
        $items = auth()->user()->tenant->catalogItems()
            ->where('type', 'product')
            ->where('has_inventory', true)
            ->with(['inventory.movements' => fn ($query) => $query->latest('occurred_at')->latest('id')])
            ->orderBy('name')
            ->get();

        return view('client.servicios.inventory', compact('items'));
    }

    public function show(CatalogItem $catalogItem)
    {
        $this->authorizeCatalogItem($catalogItem);

        $historySearch = trim((string) request()->get('history_q', ''));
        $allowedHistoryPerPage = [15, 30, 50, 100];
        $requestedHistoryPerPage = request()->integer('history_per_page', 15);
        $historyPerPage = in_array($requestedHistoryPerPage, $allowedHistoryPerPage, true)
            ? $requestedHistoryPerPage
            : 15;

        $catalogItem->load([
            'inventory.movements' => fn ($query) => $query->latest('occurred_at')->latest('id')->limit(50),
            'priceHistories' => fn ($query) => $query->latest('start_date')->latest('id'),
        ]);

        $serviceHistory = null;

        if (! $catalogItem->has_inventory) {
            $serviceHistory = NoteDetail::query()
                ->where('note_details.tenant_id', $catalogItem->tenant_id)
                ->where('note_details.catalog_item_id', $catalogItem->id)
                ->whereHas('note', function ($query) use ($catalogItem) {
                    $query->where('tenant_id', $catalogItem->tenant_id)
                        ->where('status', '!=', 'CANCELADA');
                })
                ->with(['animal', 'note'])
                ->when($historySearch !== '', function ($query) use ($historySearch) {
                    $query->where(function ($subQuery) use ($historySearch) {
                        $subQuery->whereHas('animal', function ($animalQuery) use ($historySearch) {
                            $animalQuery->where('name', 'like', "%{$historySearch}%");
                        })->orWhereHas('note', function ($noteQuery) use ($historySearch) {
                            $noteQuery->where('folio', 'like', "%{$historySearch}%");
                        });
                    });
                })
                ->join('notes', 'notes.id', '=', 'note_details.note_id')
                ->select('note_details.*')
                ->orderByDesc('notes.date_at')
                ->orderByDesc('note_details.id')
                ->paginate($historyPerPage, ['*'], 'history_page')
                ->withQueryString();
        }

        return view('client.servicios.show', compact(
            'catalogItem',
            'serviceHistory',
            'historySearch',
            'historyPerPage'
        ));
    }

    /**
     * Registra un nuevo producto o servicio.
     */
    public function store(Request $request)
    {
        $tenant = auth()->user()->tenant;

        $request->validate([
            'name' => 'required|string|max:150',
            'sku' => 'nullable|string|max:50',
            'type' => 'required|in:product,service',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'has_inventory' => 'nullable|boolean',
            'stock_actual' => 'required_if:has_inventory,1|nullable|numeric|min:0',
            'stock_minimo' => 'required_if:has_inventory,1|nullable|numeric|min:0',
            'allow_negative_stock' => 'nullable|boolean',
        ]);

        // Si viene el SKU vacío, lo dejamos nulo para que el modelo lo genere automáticamente
        $sku = $request->filled('sku') ? $request->sku : null;

        // Validar SKU único por Tenant solo si se proporcionó uno manualmente
        if ($sku && $tenant->catalogItems()->where('sku', $sku)->exists()) {
            return back()->withErrors(['sku' => 'El SKU o código ya está en uso en esta clínica.'])->withInput();
        }

        // Ejecutamos en transacción para asegurar integridad total
        DB::transaction(function () use ($request, $tenant, $sku) {
            $hasInventory = $request->type === 'product' && $request->boolean('has_inventory');

            // 1. Crear el artículo base
            $item = $tenant->catalogItems()->create([
                'name' => $request->name,
                'sku' => $sku,
                'type' => $request->type,
                'description' => $request->description,
                'has_inventory' => $hasInventory,
                'tax_percentage' => 0.00, // Listo para el futuro
                'is_active' => true,
            ]);

            // 2. Crear el registro inicial en el historial de precios
            $item->priceHistories()->create([
                'tenant_id' => $tenant->id,
                'price' => $request->price,
                'start_date' => now(),
                'end_date' => null, // Precio activo actual
            ]);

            // 3. Crear registro de inventario si aplica
            if ($hasInventory) {
                $inventory = $item->inventory()->create([
                    'tenant_id' => $tenant->id,
                    'stock_actual' => 0,
                    'stock_minimo' => $request->stock_minimo ?? 0,
                    'allow_negative_stock' => $request->boolean('allow_negative_stock'),
                ]);

                $initialStock = (float) ($request->stock_actual ?? 0);

                if ($initialStock > 0) {
                    app(InventoryService::class)->recordMovement(
                        $tenant,
                        $inventory,
                        'initial',
                        $initialStock,
                        $item,
                        auth()->id(),
                        'Existencia inicial',
                        'Stock inicial capturado al crear el producto.',
                        "initial:catalog-item:{$item->id}"
                    );
                }
            }
        });

        app(TenantOnboardingService::class)->reconcileSafely($tenant);

        return redirect()->route('client.servicios.index')
            ->with('success', 'Artículo agregado al catálogo correctamente.');
    }

    /**
     * Crea rápidamente un servicio desde la captura de una nota de venta.
     *
     * Este endpoint devuelve JSON para que la captura pueda continuar sin
     * abandonar la pantalla de ventas.
     */
    public function storeFromSale(Request $request)
    {
        $tenant = auth()->user()->tenant;

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'price' => 'required|numeric|min:0',
        ]);

        $serviceName = trim($data['name']);

        $item = DB::transaction(function () use ($tenant, $data, $serviceName) {
            $existingItem = $tenant->catalogItems()
                ->where('type', 'service')
                ->where('is_active', true)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($serviceName)])
                ->first();

            if ($existingItem) {
                return $existingItem->load('inventory');
            }

            $item = $tenant->catalogItems()->create([
                'name' => $serviceName,
                'type' => 'service',
                'description' => null,
                'has_inventory' => false,
                'tax_percentage' => 0.00,
                'is_active' => true,
            ]);

            $item->priceHistories()->create([
                'tenant_id' => $tenant->id,
                'price' => $data['price'],
                'start_date' => now(),
                'end_date' => null,
            ]);

            return $item->load('inventory');
        });

        app(TenantOnboardingService::class)->reconcileSafely($tenant);

        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type,
            // Conserva el precio capturado para esta nota aunque el servicio ya exista.
            'price' => (float) $data['price'],
            'catalog_price' => $item->current_price,
            'has_inventory' => $item->has_inventory,
            'stock_actual' => null,
            'stock_minimo' => null,
            'allow_negative_stock' => false,
        ], 201);
    }

    /**
     * Actualiza el artículo y gestiona el histórico de precios si este cambió.
     */
    public function update(Request $request, CatalogItem $catalogItem)
    {
        // Seguridad SaaS preventiva
        if ($catalogItem->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:150',
            'sku' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'stock_actual' => 'nullable|numeric|min:0',
            'stock_minimo' => 'nullable|numeric|min:0',
        ]);

        // Validar duplicados de SKU excluyendo al actual
        if ($request->sku && auth()->user()->tenant->catalogItems()->where('sku', $request->sku)->where('id', '!=', $catalogItem->id)->exists()) {
            return back()->withErrors(['sku' => 'El SKU o código ya está asignado a otro artículo.'])->withInput();
        }

        DB::transaction(function () use ($request, $catalogItem) {
            // Evaluamos si el precio enviado es diferente al precio actual en el sistema
            $currentPrice = $catalogItem->current_price;
            $newPrice = (float) $request->price;

            // 1. Si el precio cambió, manejamos el histórico de forma limpia
            if ($currentPrice !== $newPrice) {
                // "Cerramos" el registro de precio vigente actual poniéndole end_date
                $catalogItem->priceHistories()
                    ->whereNull('end_date')
                    ->update(['end_date' => now()]);

                // Insertamos la nueva tarifa vigente
                $catalogItem->priceHistories()->create([
                    'tenant_id' => $catalogItem->tenant_id,
                    'price' => $newPrice,
                    'start_date' => now(),
                    'end_date' => null,
                ]);
            }

            // 2. Actualizamos los datos generales
            $catalogItem->update([
                'name' => $request->name,
                'sku' => $request->sku,
                'description' => $request->description,
            ]);

            // 3. Si tiene inventario activo, actualizamos las existencias
            if ($catalogItem->has_inventory && $catalogItem->inventory) {
                $catalogItem->inventory->update([
                    'stock_actual' => $request->stock_actual ?? 0,
                    'stock_minimo' => $request->stock_minimo ?? 0,
                ]);
            }
        });

        return redirect()->route('client.servicios.index')
            ->with('success', 'Catálogo actualizado correctamente.');
    }

    /**
     * Alterna de forma rápida el estado operativo (Activo/Inactivo) del artículo.
     */
    public function toggleStatus(CatalogItem $catalogItem)
    {
        if ($catalogItem->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $catalogItem->update([
            'is_active' => ! $catalogItem->is_active,
        ]);

        if ($catalogItem->is_active) {
            app(TenantOnboardingService::class)->reconcileSafely(auth()->user()->tenant);
        }

        return back()->with('success', 'El estado del artículo fue modificado.');
    }

    /**
     * Alterna si un producto inventariable puede venderse sin existencias.
     */
    public function toggleNegativeStock(CatalogItem $catalogItem)
    {
        if ($catalogItem->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        if (! $catalogItem->has_inventory || ! $catalogItem->inventory) {
            return back()->withErrors([
                'inventory' => 'Solo los productos que controlan inventario pueden cambiar esta política.',
            ]);
        }

        $catalogItem->inventory->update([
            'allow_negative_stock' => ! $catalogItem->inventory->allow_negative_stock,
        ]);

        return back()->with('success', 'Política de venta sin existencias actualizada.');
    }

    /**
     * Actualiza solo el precio vigente y conserva el historial de tarifas.
     */
    public function updatePrice(Request $request, CatalogItem $catalogItem)
    {
        $this->authorizeCatalogItem($catalogItem);

        $request->validate([
            'price' => 'required|numeric|min:0',
        ]);

        $newPrice = (float) $request->price;

        DB::transaction(function () use ($catalogItem, $newPrice) {
            if ($catalogItem->current_price === $newPrice) {
                return;
            }

            $catalogItem->priceHistories()
                ->whereNull('end_date')
                ->update(['end_date' => now()]);

            $catalogItem->priceHistories()->create([
                'tenant_id' => $catalogItem->tenant_id,
                'price' => $newPrice,
                'start_date' => now(),
                'end_date' => null,
            ]);
        });

        return back()
            ->with('success', 'Precio actualizado correctamente.');
    }

    public function storeMovement(Request $request, CatalogItem $catalogItem)
    {
        $this->authorizeCatalogItem($catalogItem);

        if (! $catalogItem->has_inventory || ! $catalogItem->inventory) {
            return back()->withErrors([
                'inventory' => 'Solo los productos que controlan inventario pueden registrar movimientos.',
            ]);
        }

        $data = $request->validate([
            'type' => 'required|in:purchase,adjustment_in,adjustment_out',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:500',
        ]);

        app(InventoryService::class)->recordMovement(
            auth()->user()->tenant,
            $catalogItem->inventory,
            $data['type'],
            (float) $data['quantity'],
            $catalogItem,
            auth()->id(),
            $data['reason'] ?? $this->movementLabel($data['type']),
            $data['notes'] ?? null,
            'manual:'.uniqid('', true)
        );

        return back()->with('success', 'Movimiento de inventario registrado correctamente.');
    }

    private function authorizeCatalogItem(CatalogItem $catalogItem): void
    {
        if ($catalogItem->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
    }

    private function movementLabel(string $type): string
    {
        return [
            'purchase' => 'Entrada / reposicion',
            'adjustment_in' => 'Ajuste positivo',
            'adjustment_out' => 'Ajuste negativo',
        ][$type] ?? 'Movimiento manual';
    }
}
