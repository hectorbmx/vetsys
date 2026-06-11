<?php

namespace App\Services;

use App\Models\CatalogItem;
use App\Models\Inventory;
use App\Models\Note;
use App\Models\Tenant;
use App\Models\TenantNotification;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Validate and consume the inventory required by a note.
     *
     * This method must be called inside the transaction that creates the note.
     */
    public function consumeForSale(Tenant $tenant, array $items, int $quantityMultiplier, Note $note): void
    {
        $requestedByItem = collect($items)
            ->groupBy(fn (array $item) => (int) $item['id'])
            ->map(fn ($rows) => $rows->sum(fn (array $item) => (float) $item['quantity']) * $quantityMultiplier);

        $catalogItems = CatalogItem::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $requestedByItem->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $inventories = Inventory::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('catalog_item_id', $requestedByItem->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('catalog_item_id');

        foreach ($requestedByItem as $catalogItemId => $quantity) {
            $catalogItem = $catalogItems->get($catalogItemId);

            if (!$catalogItem?->has_inventory) {
                continue;
            }

            $inventory = $inventories->get($catalogItemId);
            if (!$inventory) {
                throw ValidationException::withMessages([
                    'items' => "El producto {$catalogItem->name} controla inventario, pero no tiene existencias configuradas.",
                ]);
            }

            $currentStock = (float) $inventory->stock_actual;
            $resultingStock = $currentStock - $quantity;

            if ($resultingStock < 0 && !$inventory->allow_negative_stock) {
                throw ValidationException::withMessages([
                    'items' => "No hay existencias suficientes de {$catalogItem->name}. Disponible: {$currentStock}; requerido: {$quantity}.",
                ]);
            }
        }

        foreach ($requestedByItem as $catalogItemId => $quantity) {
            $catalogItem = $catalogItems->get($catalogItemId);
            $inventory = $inventories->get($catalogItemId);

            if (!$catalogItem?->has_inventory || !$inventory) {
                continue;
            }

            $previousStock = (float) $inventory->stock_actual;
            $resultingStock = $previousStock - $quantity;
            $previousLevel = $this->stockLevel($previousStock, (float) $inventory->stock_minimo);
            $resultingLevel = $this->stockLevel($resultingStock, (float) $inventory->stock_minimo);

            $inventory->update(['stock_actual' => $resultingStock]);

            if ($this->levelRank($resultingLevel) > $this->levelRank($previousLevel)) {
                $this->notifyStockLevel($tenant, $catalogItem, $inventory, $note, $resultingLevel);
            }
        }
    }

    private function stockLevel(float $stock, float $minimum): string
    {
        if ($stock < 0) {
            return 'negative';
        }

        if ($stock == 0.0) {
            return 'out';
        }

        if ($stock <= $minimum) {
            return 'low';
        }

        return 'normal';
    }

    private function levelRank(string $level): int
    {
        return [
            'normal' => 0,
            'low' => 1,
            'out' => 2,
            'negative' => 3,
        ][$level];
    }

    private function notifyStockLevel(
        Tenant $tenant,
        CatalogItem $catalogItem,
        Inventory $inventory,
        Note $note,
        string $level
    ): void {
        $titles = [
            'low' => 'Inventario bajo',
            'out' => 'Producto agotado',
            'negative' => 'Inventario negativo',
        ];

        TenantNotification::create([
            'tenant_id' => $tenant->id,
            'actor_user_id' => auth()->id(),
            'type' => 'inventory.' . $level,
            'title' => $titles[$level],
            'body' => "{$catalogItem->name} quedó con {$inventory->stock_actual} unidades después de la nota {$note->folio}. Mínimo configurado: {$inventory->stock_minimo}.",
            'url' => route('client.servicios.index'),
            'data' => [
                'catalog_item_id' => $catalogItem->id,
                'note_id' => $note->id,
                'stock_actual' => $inventory->stock_actual,
                'stock_minimo' => $inventory->stock_minimo,
                'level' => $level,
            ],
        ]);
    }
}
