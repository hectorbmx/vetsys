<?php

namespace App\Services;

use App\Models\CatalogItem;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Note;
use App\Models\Tenant;
use App\Models\TenantNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    private const IN_TYPES = [
        'initial',
        'purchase',
        'return',
        'adjustment_in',
    ];

    private const OUT_TYPES = [
        'sale',
        'adjustment_out',
    ];

    public function recordMovement(
        Tenant $tenant,
        Inventory $inventory,
        string $type,
        float $quantity,
        ?Model $reference = null,
        ?int $userId = null,
        ?string $reason = null,
        ?string $notes = null,
        ?string $idempotencyKey = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad del movimiento debe ser mayor a cero.',
            ]);
        }

        $direction = $this->movementDirection($type);

        if ($idempotencyKey) {
            $existing = InventoryMovement::query()
                ->where('tenant_id', $tenant->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $inventory = Inventory::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($inventory->id)
            ->lockForUpdate()
            ->firstOrFail();

        $stockBefore = (float) $inventory->stock_actual;
        $stockAfter = $direction === 'in'
            ? $stockBefore + $quantity
            : $stockBefore - $quantity;

        if ($stockAfter < 0 && ! $inventory->allow_negative_stock) {
            $catalogItem = $inventory->catalogItem;

            throw ValidationException::withMessages([
                'items' => "No hay existencias suficientes de {$catalogItem->name}. Disponible: {$stockBefore}; requerido: {$quantity}.",
            ]);
        }

        $inventory->update(['stock_actual' => $stockAfter]);

        return InventoryMovement::create([
            'tenant_id' => $tenant->id,
            'inventory_id' => $inventory->id,
            'catalog_item_id' => $inventory->catalog_item_id,
            'user_id' => $userId,
            'type' => $type,
            'direction' => $direction,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reason' => $reason,
            'notes' => $notes,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Validate and consume the inventory required by a note.
     *
     * This method must be called inside the transaction that creates the note.
     */
    public function consumeForSale(Tenant $tenant, array $items, int $quantityMultiplier, Note $note, string $idempotencySuffix = ''): void
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

            if (! $catalogItem?->has_inventory) {
                continue;
            }

            $inventory = $inventories->get($catalogItemId);
            if (! $inventory) {
                throw ValidationException::withMessages([
                    'items' => "El producto {$catalogItem->name} controla inventario, pero no tiene existencias configuradas.",
                ]);
            }

            $currentStock = (float) $inventory->stock_actual;
            $resultingStock = $currentStock - $quantity;

            if ($resultingStock < 0 && ! $inventory->allow_negative_stock) {
                throw ValidationException::withMessages([
                    'items' => "No hay existencias suficientes de {$catalogItem->name}. Disponible: {$currentStock}; requerido: {$quantity}.",
                ]);
            }
        }

        foreach ($requestedByItem as $catalogItemId => $quantity) {
            $catalogItem = $catalogItems->get($catalogItemId);
            $inventory = $inventories->get($catalogItemId);

            if (! $catalogItem?->has_inventory || ! $inventory) {
                continue;
            }

            $previousStock = (float) $inventory->stock_actual;
            $resultingStock = $previousStock - $quantity;
            $previousLevel = $this->stockLevel($previousStock, (float) $inventory->stock_minimo);
            $resultingLevel = $this->stockLevel($resultingStock, (float) $inventory->stock_minimo);

            $movement = $this->recordMovement(
                $tenant,
                $inventory,
                'sale',
                (float) $quantity,
                $note,
                auth()->id(),
                'Venta',
                "Descuento automatico por nota {$note->folio}.",
                "sale:{$note->id}:catalog-item:{$catalogItemId}{$idempotencySuffix}"
            );

            if ($movement->wasRecentlyCreated && $this->levelRank($resultingLevel) > $this->levelRank($previousLevel)) {
                $inventory->refresh();
                $this->notifyStockLevel($tenant, $catalogItem, $inventory, $note, $resultingLevel);
            }
        }
    }

    public function reverseSaleConsumption(Tenant $tenant, Note $note, string $idempotencySuffix = ''): void
    {
        $quantities = $note->details()
            ->where('tenant_id', $tenant->id)
            ->with('catalogItem.inventory')
            ->get()
            ->groupBy('catalog_item_id')
            ->map(fn ($details) => $details->sum(fn ($detail) => (float) $detail->quantity));

        foreach ($quantities as $catalogItemId => $quantity) {
            $catalogItem = CatalogItem::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($catalogItemId)
                ->with('inventory')
                ->first();

            if (! $catalogItem?->has_inventory || ! $catalogItem->inventory) {
                continue;
            }

            $this->recordMovement(
                $tenant,
                $catalogItem->inventory,
                'return',
                (float) $quantity,
                $note,
                auth()->id(),
                'Reversion de venta',
                "Reversion automatica por ajuste de nota {$note->folio}.",
                "sale-reversal:{$note->id}:catalog-item:{$catalogItemId}{$idempotencySuffix}"
            );
        }
    }

    private function movementDirection(string $type): string
    {
        if (in_array($type, self::IN_TYPES, true)) {
            return 'in';
        }

        if (in_array($type, self::OUT_TYPES, true)) {
            return 'out';
        }

        throw ValidationException::withMessages([
            'type' => "Tipo de movimiento de inventario no soportado: {$type}.",
        ]);
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
            'type' => 'inventory.'.$level,
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
