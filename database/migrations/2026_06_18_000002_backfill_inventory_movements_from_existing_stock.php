<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventories')
            ->orderBy('id')
            ->chunkById(100, function ($inventories) {
                foreach ($inventories as $inventory) {
                    $stock = (float) $inventory->stock_actual;

                    if ($stock == 0.0) {
                        continue;
                    }

                    $idempotencyKey = "backfill:inventory:{$inventory->id}";
                    $exists = DB::table('inventory_movements')
                        ->where('tenant_id', $inventory->tenant_id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $isPositive = $stock > 0;

                    DB::table('inventory_movements')->insert([
                        'tenant_id' => $inventory->tenant_id,
                        'inventory_id' => $inventory->id,
                        'catalog_item_id' => $inventory->catalog_item_id,
                        'user_id' => null,
                        'type' => $isPositive ? 'initial' : 'adjustment_out',
                        'direction' => $isPositive ? 'in' : 'out',
                        'quantity' => abs($stock),
                        'stock_before' => 0,
                        'stock_after' => $stock,
                        'reason' => 'Backfill de inventario existente',
                        'notes' => 'Movimiento creado para respaldar el saldo previo a Kardex.',
                        'reference_type' => null,
                        'reference_id' => null,
                        'idempotency_key' => $idempotencyKey,
                        'occurred_at' => $inventory->created_at ?? now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('inventory_movements')
            ->where('idempotency_key', 'like', 'backfill:inventory:%')
            ->delete();
    }
};
