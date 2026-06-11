<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CatalogItem;
use Illuminate\Support\Facades\DB;

class CatalogItemController extends Controller
{
    //
    public function index(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

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
            ->get();

        return view('client.servicios.index', compact('items', 'search'));
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
                $item->inventory()->create([
                    'tenant_id' => $tenant->id,
                    'stock_actual' => $request->stock_actual ?? 0,
                    'stock_minimo' => $request->stock_minimo ?? 0,
                    'allow_negative_stock' => $request->boolean('allow_negative_stock'),
                ]);
            }
        });

        return redirect()->route('client.servicios.index')
            ->with('success', 'Artículo agregado al catálogo correctamente.');
    }

    /**
     * Actualiza el artículo y gestiona el histórico de precios si este cambió.
     */
    public function update(Request $request, CatalogItem $catalogItem)
    {
        // Seguridad SaaS preventiva
        if ($catalogItem->tenant_id !== auth()->user()->tenant_id) { abort(403); }

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
        if ($catalogItem->tenant_id !== auth()->user()->tenant_id) { abort(403); }

        $catalogItem->update([
            'is_active' => !$catalogItem->is_active
        ]);

        return back()->with('success', 'El estado del artículo fue modificado.');
    }

    /**
     * Alterna si un producto inventariable puede venderse sin existencias.
     */
    public function toggleNegativeStock(CatalogItem $catalogItem)
    {
        if ($catalogItem->tenant_id !== auth()->user()->tenant_id) { abort(403); }

        if (!$catalogItem->has_inventory || !$catalogItem->inventory) {
            return back()->withErrors([
                'inventory' => 'Solo los productos que controlan inventario pueden cambiar esta política.',
            ]);
        }

        $catalogItem->inventory->update([
            'allow_negative_stock' => !$catalogItem->inventory->allow_negative_stock,
        ]);

        return back()->with('success', 'Política de venta sin existencias actualizada.');
    }

    /**
     * Actualiza solo el precio vigente y conserva el historial de tarifas.
     */
    public function updatePrice(Request $request, CatalogItem $catalogItem)
    {
        if ($catalogItem->tenant_id !== auth()->user()->tenant_id) { abort(403); }

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

        return redirect()->route('client.servicios.index')
            ->with('success', 'Precio actualizado correctamente.');
    }
}
